#!/usr/bin/env python3
"""
Background Removal API Server
Flask-based REST API for processing product images.

Quality goals:
  - Retain 100% of the product (no erased/blurred interior regions)
  - White background
  - Product trimmed and centered prominently on a square canvas

Tuning is per-request via query params (see process_image / strength profiles).
"""

import os
import sys
import base64
import logging
from datetime import datetime
from pathlib import Path

from functools import wraps
from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
from werkzeug.utils import secure_filename
import tempfile

try:
    from rembg import remove, new_session
    from PIL import Image
    import numpy as np
    from scipy import ndimage
    import io
except ImportError:
    print("Required packages not installed. Install with:")
    print("pip install flask flask-cors rembg pillow numpy scipy")
    sys.exit(1)


# Configuration
class Config:
    """Server configuration"""
    UPLOAD_FOLDER = Path(tempfile.gettempdir()) / 'bg_removal_uploads'
    OUTPUT_FOLDER = Path(tempfile.gettempdir()) / 'bg_removal_outputs'
    MAX_CONTENT_LENGTH = 50 * 1024 * 1024  # 50MB max file size
    ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'webp', 'bmp'}
    # Default model for intake. isnet-general-use retains full objects far better
    # than u2net on general product photos.
    MODEL = 'isnet-general-use'
    BACKGROUND_COLOR = (255, 255, 255)  # White

    def __init__(self):
        self.UPLOAD_FOLDER.mkdir(exist_ok=True, parents=True)
        self.OUTPUT_FOLDER.mkdir(exist_ok=True, parents=True)


# Strength profiles. Each resolves to a full set of removal params; individual
# params can still be overridden per-request via query string.
#   balanced     -> intake default. Clean mask, no edge erosion, no hole-fill.
#   conservative -> manual "keep more of product" re-run. Alpha matting with
#                   zero erode + interior hole-fill so the product interior can
#                   never be erased.
#   aggressive   -> halo/fringe cleanup. u2net with a small erode.
STRENGTH_PROFILES = {
    'balanced': {
        'model': 'isnet-general-use',
        'alpha_matting': False,
        'fg_threshold': 240,
        'bg_threshold': 10,
        'erode_size': 0,
        'post_process_mask': True,
        'fill_holes': False,
        'keep_largest': True,
    },
    'conservative': {
        'model': 'isnet-general-use',
        'alpha_matting': True,
        'fg_threshold': 240,
        'bg_threshold': 10,
        'erode_size': 0,
        'post_process_mask': True,
        'fill_holes': True,
        'keep_largest': True,
    },
    'aggressive': {
        'model': 'u2net',
        'alpha_matting': False,
        'fg_threshold': 240,
        'bg_threshold': 10,
        'erode_size': 10,
        'post_process_mask': True,
        'fill_holes': False,
        'keep_largest': True,
    },
}
DEFAULT_STRENGTH = 'balanced'

# If segmentation removes essentially nothing, treat it as a failure and keep
# the original (just trimmed/centered onto white) rather than emitting garbage.
MIN_REMOVED_FRACTION = 0.03

# Initialize Flask app
app = Flask(__name__)
app.config.from_object(Config())
CORS(app)  # Enable CORS for cross-origin requests

# API Key from environment
API_KEY = os.environ.get('BACKGROUND_REMOVAL_API_KEY')


def require_api_key(f):
    """Decorator to require API key on routes when configured"""
    @wraps(f)
    def decorated(*args, **kwargs):
        if API_KEY:
            provided_key = request.headers.get('X-API-Key')
            if provided_key != API_KEY:
                return jsonify({'error': 'Unauthorized'}), 401
        return f(*args, **kwargs)
    return decorated


# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Lazy per-model session cache (replaces the single global session). Loading a
# model is expensive, so each model is created once on first use and reused.
_sessions = {}


def get_session(model: str):
    """Return a cached rembg session for the given model, creating it lazily."""
    if model not in _sessions:
        logger.info(f"Loading AI model: {model}")
        _sessions[model] = new_session(model)
        logger.info(f"Model loaded: {model}")
    return _sessions[model]


def allowed_file(filename: str) -> bool:
    """Check if file extension is allowed"""
    return '.' in filename and \
           filename.rsplit('.', 1)[1].lower() in Config.ALLOWED_EXTENSIONS


def _to_bool(value, default: bool) -> bool:
    if value is None:
        return default
    return str(value).lower() not in ('false', '0', 'no', 'off')


def resolve_options(args) -> dict:
    """
    Build the effective removal options from a strength profile plus any
    per-request query-param overrides.
    """
    strength = (args.get('strength') or DEFAULT_STRENGTH).lower()
    profile = STRENGTH_PROFILES.get(strength, STRENGTH_PROFILES[DEFAULT_STRENGTH])
    opts = dict(profile)
    opts['strength'] = strength if strength in STRENGTH_PROFILES else DEFAULT_STRENGTH

    if args.get('model'):
        opts['model'] = args.get('model')

    opts['alpha_matting'] = _to_bool(args.get('alpha_matting'), opts['alpha_matting'])
    opts['post_process_mask'] = _to_bool(args.get('post_process_mask'), opts['post_process_mask'])
    opts['fill_holes'] = _to_bool(args.get('fill_holes'), opts['fill_holes'])
    opts['keep_largest'] = _to_bool(args.get('keep_largest'), opts['keep_largest'])
    opts['center'] = _to_bool(args.get('center'), True)
    opts['remove_bg'] = _to_bool(args.get('remove_bg'), True)

    for key, qp in (('fg_threshold', 'fg_threshold'),
                    ('bg_threshold', 'bg_threshold'),
                    ('erode_size', 'erode_size')):
        if args.get(qp) is not None:
            try:
                opts[key] = int(args.get(qp))
            except (TypeError, ValueError):
                pass

    opts['padding_pct'] = 5.0
    if args.get('padding_pct') is not None:
        try:
            opts['padding_pct'] = float(args.get('padding_pct'))
        except (TypeError, ValueError):
            pass

    bg_color = Config.BACKGROUND_COLOR
    if args.get('bg_color'):
        try:
            bg_color = tuple(int(x) for x in args.get('bg_color').split(','))
        except (TypeError, ValueError):
            pass
    opts['bg_color'] = bg_color

    return opts


def center_and_trim(image: Image.Image, padding_pct: float = 5.0) -> Image.Image:
    """
    Trim transparent whitespace around the subject and center it on a square
    canvas so the product is prominent.
    """
    bbox = image.getbbox()
    if not bbox:
        return image

    cropped = image.crop(bbox)
    w, h = cropped.size
    side = int(max(w, h) * (1 + 2 * padding_pct / 100))
    canvas = Image.new('RGBA', (side, side), (0, 0, 0, 0))
    offset_x = (side - w) // 2
    offset_y = (side - h) // 2
    canvas.paste(cropped, (offset_x, offset_y), cropped)
    return canvas


def repair_mask(rgba: Image.Image, original_rgb: Image.Image, opts: dict) -> tuple:
    """
    Clean and repair the segmentation mask so no part of the product is lost.

    - keep_largest: drop disconnected specks (noise), keeping the largest blob.
    - fill_holes: fill interior holes of the mask and re-apply the ORIGINAL RGB
      under the repaired region, so an erased product interior is restored.

    Returns (repaired_rgba, diagnostics).
    """
    alpha = np.array(rgba.split()[-1])
    total = alpha.size
    binary = alpha > 128

    diag = {'holes_filled': 0, 'specks_removed': 0}

    if opts.get('keep_largest') and binary.any():
        labels, num = ndimage.label(binary)
        if num > 1:
            sizes = ndimage.sum(np.ones_like(labels), labels, range(1, num + 1))
            largest = int(np.argmax(sizes)) + 1
            largest_only = labels == largest
            diag['specks_removed'] = int(binary.sum() - largest_only.sum())
            binary = largest_only

    repaired = binary
    if opts.get('fill_holes') and binary.any():
        filled = ndimage.binary_fill_holes(binary)
        diag['holes_filled'] = int(filled.sum() - binary.sum())
        repaired = filled

    diag['coverage'] = round(float(repaired.sum()) / total, 4)

    # Rebuild RGBA: original colours everywhere, alpha = repaired mask. Using the
    # original RGB (not the masked output) guarantees filled holes show real
    # product pixels, not black.
    new_alpha = np.where(repaired, 255, 0).astype('uint8')
    base = np.array(original_rgb.convert('RGB'))
    rgba_arr = np.dstack([base, new_alpha])
    return Image.fromarray(rgba_arr, mode='RGBA'), diag


def process_image(input_data: bytes, opts: dict) -> tuple:
    """
    Process image to remove background per the resolved options.

    Returns (png_bytes, diagnostics).
    """
    input_image = Image.open(io.BytesIO(input_data)).convert('RGB')
    diagnostics = {
        'model': opts.get('model'),
        'strength': opts.get('strength'),
        'remove_bg': opts.get('remove_bg', True),
        'coverage': None,
        'holes_filled': 0,
        'specks_removed': 0,
        'fallback_original': False,
    }

    if not opts.get('remove_bg', True):
        # Master off-switch: no segmentation, just trim + center + white.
        output = input_image.convert('RGBA')
        diagnostics['coverage'] = 1.0
    else:
        session = get_session(opts['model'])
        output = remove(
            input_image,
            session=session,
            alpha_matting=opts['alpha_matting'],
            alpha_matting_foreground_threshold=opts['fg_threshold'],
            alpha_matting_background_threshold=opts['bg_threshold'],
            alpha_matting_erode_size=opts['erode_size'],
            post_process_mask=opts['post_process_mask'],
        )
        if output.mode != 'RGBA':
            output = output.convert('RGBA')

        alpha = np.array(output.split()[-1])
        removed_fraction = float((alpha <= 128).sum()) / alpha.size

        if removed_fraction < MIN_REMOVED_FRACTION:
            # Segmentation removed essentially nothing -> keep original on white.
            output = input_image.convert('RGBA')
            diagnostics['fallback_original'] = True
            diagnostics['coverage'] = 1.0
        else:
            output, mask_diag = repair_mask(output, input_image, opts)
            diagnostics.update(mask_diag)

    # Trim + center the subject on a square canvas
    if opts.get('center', True):
        output = center_and_trim(output, opts.get('padding_pct', 5.0))

    # Composite onto the background colour
    bg_color = opts.get('bg_color', Config.BACKGROUND_COLOR)
    if output.mode == 'RGBA':
        canvas = Image.new('RGBA', output.size, bg_color + (255,))
        canvas.paste(output, (0, 0), output)
        final_image = canvas.convert('RGB')
    else:
        final_image = output.convert('RGB')

    output_buffer = io.BytesIO()
    final_image.save(output_buffer, format='PNG', quality=95)
    output_buffer.seek(0)
    return output_buffer.getvalue(), diagnostics


# API Routes

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'model': Config.MODEL,
        'strengths': list(STRENGTH_PROFILES.keys()),
        'timestamp': datetime.now().isoformat()
    })


@app.route('/api/v1/process', methods=['POST'])
@require_api_key
def process_single():
    """
    Process a single image.

    Body: multipart 'file' OR JSON {image: base64, filename?}
    Query: format (file|base64), strength, model, alpha_matting, fg_threshold,
           bg_threshold, erode_size, post_process_mask, fill_holes, keep_largest,
           padding_pct, center, bg_color, remove_bg
    """
    start_time = datetime.now()

    try:
        if 'file' in request.files:
            file = request.files['file']
            if file.filename == '':
                return jsonify({'error': 'No file selected'}), 400
            if not allowed_file(file.filename):
                return jsonify({'error': 'Invalid file type'}), 400
            input_data = file.read()
            original_filename = secure_filename(file.filename)
        elif request.is_json:
            data = request.get_json()
            if 'image' not in data:
                return jsonify({'error': 'No image data provided'}), 400
            try:
                input_data = base64.b64decode(data['image'])
            except Exception as e:
                return jsonify({'error': f'Invalid base64 data: {str(e)}'}), 400
            original_filename = data.get('filename', 'image.png')
        else:
            return jsonify({'error': 'No file or data provided'}), 400

        opts = resolve_options(request.args)
        logger.info(f"Processing {original_filename} strength={opts['strength']} model={opts['model']}")
        processed_data, diagnostics = process_image(input_data, opts)

        processing_time = (datetime.now() - start_time).total_seconds()
        diagnostics['processing_time'] = round(processing_time, 2)
        logger.info(f"Processed {original_filename} in {processing_time:.2f}s {diagnostics}")

        return_format = request.args.get('format', 'file')
        if return_format == 'base64':
            encoded = base64.b64encode(processed_data).decode('utf-8')
            return jsonify({
                'success': True,
                'image': encoded,
                'filename': original_filename.replace('.jpg', '.png').replace('.jpeg', '.png'),
                'processing_time': processing_time,
                'diagnostics': diagnostics,
            })
        return send_file(
            io.BytesIO(processed_data),
            mimetype='image/png',
            as_attachment=True,
            download_name=f"processed_{original_filename.rsplit('.', 1)[0]}.png"
        )

    except Exception as e:
        logger.error(f"Error in process_single: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/v1/batch', methods=['POST'])
@require_api_key
def process_batch():
    """
    Process multiple images. Same query params as /process apply to all images.

    Body: multipart 'files' OR JSON {images: [{image: base64, filename?}, ...]}
    Returns base64 JSON array (default) or a ZIP file.
    """
    start_time = datetime.now()

    try:
        opts = resolve_options(request.args)
        processed_images = []

        if request.files:
            files = request.files.getlist('files')
            if not files:
                return jsonify({'error': 'No files provided'}), 400
            for file in files:
                if file and allowed_file(file.filename):
                    data_bytes, diag = process_image(file.read(), opts)
                    processed_images.append({'filename': file.filename, 'data': data_bytes, 'diagnostics': diag})
        elif request.is_json:
            data = request.get_json()
            if 'images' not in data:
                return jsonify({'error': 'No images provided'}), 400
            for idx, image_data in enumerate(data['images']):
                input_data = base64.b64decode(image_data['image'])
                data_bytes, diag = process_image(input_data, opts)
                processed_images.append({
                    'filename': image_data.get('filename', f'image_{idx}.png'),
                    'data': data_bytes,
                    'diagnostics': diag,
                })
        else:
            return jsonify({'error': 'No files or data provided'}), 400

        processing_time = (datetime.now() - start_time).total_seconds()
        logger.info(f"Processed {len(processed_images)} images in {processing_time:.2f}s")

        return_format = request.args.get('format', 'base64')
        if return_format == 'base64':
            result = [{
                'filename': img['filename'],
                'image': base64.b64encode(img['data']).decode('utf-8'),
                'diagnostics': img['diagnostics'],
            } for img in processed_images]
            return jsonify({
                'success': True,
                'count': len(result),
                'images': result,
                'processing_time': processing_time,
            })

        import zipfile
        zip_buffer = io.BytesIO()
        with zipfile.ZipFile(zip_buffer, 'w', zipfile.ZIP_DEFLATED) as zip_file:
            for img in processed_images:
                filename = f"processed_{img['filename'].rsplit('.', 1)[0]}.png"
                zip_file.writestr(filename, img['data'])
        zip_buffer.seek(0)
        return send_file(
            zip_buffer,
            mimetype='application/zip',
            as_attachment=True,
            download_name=f'processed_batch_{datetime.now().strftime("%Y%m%d_%H%M%S")}.zip'
        )

    except Exception as e:
        logger.error(f"Error in process_batch: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/v1/info', methods=['GET'])
@require_api_key
def server_info():
    """Get server information"""
    return jsonify({
        'version': '2.0.0',
        'model': Config.MODEL,
        'strengths': STRENGTH_PROFILES,
        'max_file_size_mb': Config.MAX_CONTENT_LENGTH / (1024 * 1024),
        'allowed_extensions': list(Config.ALLOWED_EXTENSIONS),
        'endpoints': {
            'health': '/health',
            'process_single': '/api/v1/process',
            'process_batch': '/api/v1/batch',
            'info': '/api/v1/info'
        }
    })


@app.errorhandler(413)
def request_entity_too_large(error):
    """Handle file too large error"""
    return jsonify({'error': 'File too large'}), 413


@app.errorhandler(500)
def internal_error(error):
    """Handle internal server errors"""
    logger.error(f"Internal error: {error}")
    return jsonify({'error': 'Internal server error'}), 500


def main():
    """Main entry point"""
    import argparse

    parser = argparse.ArgumentParser(description='Background Removal API Server')
    parser.add_argument('--host', default='0.0.0.0', help='Host to bind to')
    parser.add_argument('--port', type=int, default=5000, help='Port to bind to')
    parser.add_argument('--model', default=Config.MODEL,
                        choices=['isnet-general-use', 'u2net', 'u2netp', 'silueta'],
                        help='Default AI model')
    parser.add_argument('--debug', action='store_true', help='Enable debug mode')

    args = parser.parse_args()
    Config.MODEL = args.model
    STRENGTH_PROFILES['balanced']['model'] = args.model

    logger.info("Starting Background Removal API Server")
    logger.info(f"Host: {args.host}:{args.port}")
    logger.info(f"Default model: {args.model}")
    logger.info(f"Max file size: {Config.MAX_CONTENT_LENGTH / (1024 * 1024)}MB")

    # Warm the default model so the first request is fast.
    try:
        get_session(args.model)
    except Exception as e:
        logger.warning(f"Could not preload model {args.model}: {e}")

    app.run(host=args.host, port=args.port, debug=args.debug, threaded=True)


if __name__ == '__main__':
    main()
