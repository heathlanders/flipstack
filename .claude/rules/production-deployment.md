# Production Deployment (Coolify)

## Container Architecture

Production runs on Coolify at `flipstack.app` with these containers (defined in `docker-compose.coolify.yaml`):
- `flipstack_app_1` - PHP-FPM app server
- `flipstack_web` - Nginx reverse proxy (Traefik routes traffic to it)
- `flipstack_horizon` - Queue worker (runs `php artisan horizon`)
- `flipstack_scheduler` - Cron (runs `php artisan schedule:work`)
- `flipstack_db` - PostgreSQL 16
- `flipstack_redis` - Redis 7

**All PHP containers volume-mount `/data/flipstack/current:/var/www/html`** (symlink to active release). Code deploys via atomic symlink swap + container recreation.

## Deployment Pipeline

Deployments are fully automated via GitHub Actions (`push to main`):
1. Tests + Pint + frontend build run in CI
2. Code rsynced to `/data/flipstack/releases/TIMESTAMP/`
3. `deploy.sh` runs on server: symlink swap, container recreation, migrations, health checks
4. External health check from GitHub Actions verifies site is reachable
5. Auto-rollback triggers on failure (swaps symlink back to previous release)

**No manual post-deploy commands should be needed.** If the pipeline fails, check GitHub Actions logs first.

## Manual Rollback

If auto-rollback did not work:

1. SSH to the server
2. List available releases: `ls -lt /data/flipstack/releases/`
3. Swap to a known-good release:
   ```bash
   cd /data/flipstack
   ln -sfn /data/flipstack/releases/GOOD_TIMESTAMP /data/flipstack/current
   docker compose -f docker-compose.coolify.yaml up -d --no-deps --force-recreate --no-build app-1
   # Wait ~60s for app-1 health check to pass
   docker compose -f docker-compose.coolify.yaml up -d --no-deps --force-recreate --no-build web
   # Wait ~30s for web health check to pass
   docker compose -f docker-compose.coolify.yaml up -d --no-deps --force-recreate --no-build horizon scheduler
   docker exec flipstack_horizon php artisan horizon:terminate
   ```
4. Verify: `curl -k --resolve "flipstack.app:443:127.0.0.1" https://flipstack.app/api/health`

## Common Production Issues

**Jobs failing instantly (FAIL in horizon logs)**:
1. Check the actual error: `docker exec flipstack_app_1 php artisan tinker --execute="echo DB::table('failed_jobs')->orderByDesc('failed_at')->first()?->exception;"`
2. Check Laravel log: `docker exec flipstack_app_1 tail -100 storage/logs/laravel.log`
3. Most common cause: Horizon running stale code. Fix with `horizon:terminate`.

**Items stuck in "processing" forever**:
- Horizon timeout kills workers before job completes. Job's `failed()` method never runs.
- Fix: `docker exec flipstack_app_1 php artisan queue:reset-stuck`
- Prevention: Horizon `supervisor-1` timeout (config/horizon.php) must exceed the job's `$timeout`.

**503 Service Unavailable / "no server available"**:
1. Check container health: `docker ps` (look for unhealthy/restarting containers)
2. Check nginx logs: `docker logs flipstack_web --tail 50`
3. Check app logs: `docker exec flipstack_app_1 tail -50 storage/logs/laravel.log`
4. Check PHP-FPM is running: `docker exec flipstack_app_1 ps aux | grep php-fpm`
5. Check Traefik routing: `curl -k --resolve "flipstack.app:443:127.0.0.1" https://flipstack.app/api/health`

## Background Removal Service

The background removal server runs on the **local dev machine** (which has a GPU), NOT as a production container. Production reaches it via Cloudflare Tunnel at `https://bg.flipstack.app`. The tunnel config is at `background-removal-server/cloudflared-config.yml`. The production `.env` must have `BACKGROUND_REMOVAL_URL=https://bg.flipstack.app` (not a Docker internal URL). Do NOT add this service to `docker-compose.coolify.yaml`.
