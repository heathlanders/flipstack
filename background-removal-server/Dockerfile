FROM python:3.11-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 \
    libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Pre-download models so they're cached in the image (no runtime download).
# isnet-general-use is the default (balanced + conservative profiles); u2net
# backs the aggressive profile.
RUN python -c "from rembg import new_session; new_session('isnet-general-use'); new_session('u2net')"

COPY bg_removal_server.py .

EXPOSE 5000

CMD ["gunicorn", "--bind", "0.0.0.0:5000", "--timeout", "120", "--workers", "2", "bg_removal_server:app"]
