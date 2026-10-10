# Develop vs Main Tracker

Living tracker of what is on `develop` (staging — `develop.flipstack.app`) but not yet released to `main` (production — `flipstack.app`).

Refresh this doc by running the `develop-vs-main` skill. See `.claude/skills/develop-vs-main/SKILL.md` for the procedure.

**Last refreshed:** 2026-06-07 (post develop→main merge — mobile gap build; develop fast-forwarded to main)

## Counts

| Metric | Count | Notes |
|--------|-------|-------|
| Commits ahead of main | 0 | Branches in sync — nothing pending release |
| Commits behind main | 0 | develop fast-forwarded to `810e7b2` after the merge |
| Migrations pending | 0 | — |
| New env vars | 0 | — |

**Branches are in sync.** `origin/develop` == `origin/main` == `810e7b2`. There is nothing staged for release. Next feature work branches from here.

## Last release (2026-06-07)

Merge `810e7b2` shipped to production and verified healthy (`/api/health` → DB/Redis/rate-limiter up; new routes return 401 not 404; deploy + both APK builds green, no rollback). Contents:

- **Mobile gap build** (`9c7d633`) — 5 mobile-relevant features end-to-end: PO receiving, labeling, buyer messaging, warehouse bins scan/lookup, bundles + eBay listing mgmt. New `Mobile{Receiving,Message,Bin,Bundle}Controller` + 4 API route groups + Flutter slices; 18 backend tests; fixed `InventoryMovement.created_by` null under Sanctum in `ReceivingService`; removed a 64-error dead-code island. Memory: `project_mobile_gap_build.md`.
- **Prior pending develop release** (folded in by the merge) — image-processing overhaul (isnet + hole-fill, per-image reprocess/revert/replace/sync, marketplace push), eBay sync/retry/bulk repair + `ebay:resync` command + structured item specifics + aspect parity, intake eBay 65-char aspect cap + editable package dimensions. Memory: `project_image_processing_overhaul.md`, `project_ebay_aspect_intake_dims_fix.md`.

No pending migrations or new env keys remain — all were applied by this deploy (prod is healthy).

## Pending features

None. Branches are in sync.

## Refresh checklist

When you re-run this audit:

1. `git fetch origin main develop`
2. Recount ahead/behind, files, migrations, env keys (skill has the exact commands).
3. Group new commits by feature and link to a memory file if one exists.
4. Flag any pending migrations or env vars at the top — those gate the deploy.
5. After a develop → main merge, fast-forward develop and reset this doc to baseline.
