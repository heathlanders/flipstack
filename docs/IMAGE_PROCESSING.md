# Image Processing & Editing

How product photos get a white background, stay 100% intact, and how users fix
and re-push them. Added/overhauled 2026-06-06.

## Pipeline overview

```
upload (queue-photos/) -> background removal (GPU server) -> queue-photos-processed/
   -> ProductImage rows (url = processed, metadata.original_url = pristine)
   -> product hub: reprocess / revert / replace / sync-to-marketplaces
```

## Background-removal server

Python Flask + rembg, runs on the **local GPU machine** (NOT a prod container),
exposed to production via Cloudflare Tunnel at `https://bg.flipstack.app`
(`cloudflared-bg.service`). Code: `background-removal-server/bg_removal_server.py`.

- **Default model:** `isnet-general-use` (retains full objects far better than
  u2net) + `post_process_mask=True`. Per-model session cache.
- **Strength profiles** (`?strength=`):
  - `balanced` (intake default) — isnet, post-process, no alpha matting, no hole-fill.
  - `conservative` (manual "Keep more of product") — alpha matting, `erode_size=0`,
    keep-largest-component + **interior hole-fill** (re-applies original RGB so a
    product interior can never be erased). Hole-fill is size-gated and
    conservative-only (can over-fill a legitimate opening like a mug handle).
  - `aggressive` — u2net + small erode for halo/fringe cases.
- **Per-request params** on `/api/v1/process` and `/api/v1/batch`: `model`,
  `strength`, `alpha_matting`, `fg_threshold`, `bg_threshold`, `erode_size`,
  `post_process_mask`, `fill_holes`, `keep_largest`, `padding_pct`, `center`,
  `bg_color`, `remove_bg` (master off-switch = trim+center+white only).
- **Safeguard:** if segmentation removes <3% it falls back to the original on white.
- Response includes `diagnostics` (coverage, holes_filled, model, strength).
- Trim + center the subject on a square canvas for prominence.

**Deploy:** edit the .py, then on the GPU box rebuild the container:
`docker compose up -d --build background-removal`. Models (`isnet-general-use`,
`u2net`) are baked into the image (Dockerfile) so there's no runtime download.
Verify: `curl https://bg.flipstack.app/health`.

## Laravel side

- `app/Services/BackgroundRemovalService.php`
  - `removeBackgroundWithOptions($path, $options): {path, diagnostics, success}` —
    builds the query string, saves a **versioned** output (`<base>__v<ts>.png`) so the
    URL changes and marketplaces actually re-fetch (same URL = cached old image).
  - `processMultipleWithCropping($paths, $preset)` — intake batch (tight/default/padded -> padding).
- `app/Models/Catalog/ProductImage.php` — image edits tracked in `metadata` (no
  schema change): `original_url`, `bg_removed`, `processed_url`, `processing`,
  append-only `history`. Helpers: `hasOriginal()`, `sourcePathForProcessing()`
  (prefers tracked original; for legacy rows derives `queue-photos/<basename>` from
  the processed basename), `recordProcessing()`, `revertToOriginal()`.

## Per-image editing UI (product hub `Show.vue`)

Gallery toolbar (perm `inventory.update`, not archived):
- **Reprocess** — strength modal (Keep more of product / Balanced / Trim tighter),
  advanced model/padding/center, master "remove background" toggle. Multi-select via
  thumbnail checkboxes + "select all" -> bulk endpoint; none checked = current photo.
- **Revert** — restore the pristine original (disabled when no `original_url`).
- **Replace** — upload a new file (becomes the new original).
- **Sync to marketplaces** — shown when the product has listings; pushes current
  images on demand (for images edited before auto-push existed).

Routes/controller: `routes/inventory.php` -> `App\Http\Controllers\Product\ProductImageController`
(`reprocess`, `reprocessBulk`, `revert`, `replace`, `syncMarketplaces`). Each is
permission- + ownership-gated and audited (`product_image.reprocessed|bulk_reprocessed|reverted|replaced|marketplace_sync_requested`).

## Marketplace propagation

Image edits auto-push. After any edit, the controller mirrors `product_images`
into `Product.gallery_images` + `featured_image` (`saveQuietly`) and dispatches
`PushListingToMarketplaces` -> `pushListingToAll` -> eBay/Shopify/Amazon (respecting
`auto_sync_enabled` + sync rules; per-listing hash-gated). eBay reads images from
`gallery_images`; Shopify/Amazon from the `images` relationship — both updated.

Manual / unstick: `php artisan ebay:resync <product UUID|SKU>`.

## Gotchas

- eBay's inventory PUT is a **full replace** — a thin update payload strips
  images/specifics. The update path (`UpdateEbayListing`) and `Product::toEbayInventoryData()`
  send images + specifics for this reason.
- Pre-existing images created before original-tracking have no `metadata.original_url`;
  Revert is disabled for them, but Reprocess derives the original from the processed
  basename (`queue-photos/<basename>`).
- Read `Product.attributes` inside the model via `getAttribute('attributes')` (Eloquent
  `$attributes` property collision).
