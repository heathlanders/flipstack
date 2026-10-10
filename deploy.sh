#!/bin/bash
{

# FlipStack Deployment Script - Symlink-Based Atomic Releases
# Uses the same pattern as Laravel Envoyer / Capistrano / Deployer
#
# Directory structure:
#   /data/flipstack/releases/TIMESTAMP/  -- each deploy gets its own directory
#   /data/flipstack/shared/.env          -- persistent env file
#   /data/flipstack/shared/storage/      -- persistent storage (logs, uploads, cache)
#   /data/flipstack/current -> releases/TIMESTAMP  -- atomic symlink swap

# Configuration (override via env vars for staging/other environments)
PROJECT_DIR="${FLIPSTACK_DIR:-/data/flipstack}"
COMPOSE_FILE="${FLIPSTACK_COMPOSE:-docker-compose.coolify.yaml}"
CONTAINER_PREFIX="${FLIPSTACK_PREFIX:-flipstack}"
DEPLOY_DOMAIN="${FLIPSTACK_DOMAIN:-flipstack.app}"
KEEP_RELEASES=5
SEED_PRODUCTION=false
RELEASE=""
PREVIOUS_RELEASE=""
ROLLBACK_ATTEMPTED=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --seed)
            SEED_PRODUCTION=true
            shift
            ;;
        --release=*)
            RELEASE="${arg#*=}"
            shift
            ;;
    esac
done

cd "$PROJECT_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

write_deploy_result() {
    local status="$1"
    cat > "$PROJECT_DIR/deploy-result.txt" <<EOF
DEPLOY_STATUS=$status
DEPLOY_RELEASE=$RELEASE
DEPLOY_PREVIOUS=$PREVIOUS_RELEASE
EOF
}

check_health() {
    local container=$1
    local max_attempts=$2
    local attempt=1

    log "Waiting for $container to become healthy..."

    while [ $attempt -le $max_attempts ]; do
        if docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null | grep -q "healthy"; then
            log "$container is healthy after ${attempt} attempts"
            return 0
        fi

        log "Attempt $attempt/$max_attempts: $container not ready yet..."
        sleep 3
        attempt=$((attempt + 1))
    done

    log "ERROR: $container failed health check after $max_attempts attempts"
    return 1
}

wait_for_container() {
    local container=$1
    local max_attempts=${2:-10}
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        local status
        status=$(docker inspect --format='{{.State.Status}}' "$container" 2>/dev/null || echo "not_found")
        if [ "$status" = "running" ]; then
            return 0
        fi
        log "Waiting for $container to be running (status: $status, attempt $attempt/$max_attempts)..."
        sleep 3
        attempt=$((attempt + 1))
    done

    log "WARNING: $container did not reach running state after $max_attempts attempts"
    return 1
}

check_traefik_routing() {
    log "Verifying Traefik is routing traffic to $DEPLOY_DOMAIN..."
    local max_attempts=20
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        local http_code
        http_code=$(curl -s -o /dev/null -w '%{http_code}' \
            --resolve "$DEPLOY_DOMAIN:443:127.0.0.1" \
            --max-time 5 \
            -k \
            "https://$DEPLOY_DOMAIN/api/health" 2>/dev/null || echo "000")

        if [ "$http_code" = "200" ]; then
            log "Traefik routing verified (HTTP $http_code) after $attempt attempts"
            return 0
        fi

        log "Traefik routing check attempt $attempt/$max_attempts (HTTP $http_code)..."
        sleep 5
        attempt=$((attempt + 1))
    done

    log "ERROR: Traefik routing check failed after $max_attempts attempts"
    return 1
}

rollback() {
    local reason="$1"

    if [ "$ROLLBACK_ATTEMPTED" = true ]; then
        log "!!! Rollback already attempted, skipping"
        return 1
    fi
    ROLLBACK_ATTEMPTED=true

    log "!!! DEPLOYMENT FAILED: $reason"
    log "!!! Rolling back to previous release: $PREVIOUS_RELEASE"

    if [ -z "$PREVIOUS_RELEASE" ] || [ ! -d "$PREVIOUS_RELEASE" ]; then
        log "!!! Cannot rollback: no previous release found at $PREVIOUS_RELEASE"
        write_deploy_result "failed_no_rollback"
        return 1
    fi

    # Swap symlink back to previous release
    ln -sfn "$PREVIOUS_RELEASE" "$PROJECT_DIR/current"

    # Recreate app containers with previous code
    docker compose -f $COMPOSE_FILE up -d --no-deps --force-recreate --no-build app-1 || {
        log "!!! CRITICAL: Failed to recreate app-1 during rollback"
        write_deploy_result "failed_rollback_broken"
        return 1
    }

    check_health "${CONTAINER_PREFIX}_app_1" 30 || {
        log "!!! CRITICAL: Rollback app-1 health check failed"
        write_deploy_result "failed_rollback_broken"
        return 1
    }

    docker compose -f $COMPOSE_FILE up -d --no-deps --force-recreate --no-build web || {
        log "!!! CRITICAL: Failed to recreate web during rollback"
        write_deploy_result "failed_rollback_broken"
        return 1
    }

    check_health "${CONTAINER_PREFIX}_web" 30 || {
        log "!!! CRITICAL: Rollback web health check failed"
        write_deploy_result "failed_rollback_broken"
        return 1
    }

    docker compose -f $COMPOSE_FILE up -d --no-deps --force-recreate --no-build horizon scheduler

    check_traefik_routing || {
        log "!!! CRITICAL: Rollback Traefik routing check failed"
        write_deploy_result "failed_rollback_broken"
        return 1
    }

    # Terminate Horizon so it loads the previous code
    docker exec ${CONTAINER_PREFIX}_horizon php artisan horizon:terminate || true

    log "!!! Rollback complete. Site is serving previous release."
    write_deploy_result "rolled_back"
    return 0
}

cleanup_old_releases() {
    log "Cleaning up old releases (keeping last $KEEP_RELEASES)..."
    local current_target
    current_target=$(readlink -f "$PROJECT_DIR/current" 2>/dev/null || echo "")

    ls -1dt "$PROJECT_DIR/releases"/*/ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | while read -r old_release; do
        old_release="${old_release%/}"
        if [ "$old_release" != "$current_target" ]; then
            log "Removing old release: $old_release"
            rm -rf "$old_release"
        fi
    done
}

# Determine release directory
if [ -z "$RELEASE" ]; then
    RELEASE=$(date +%Y%m%d_%H%M%S)
    log "No release specified, using: $RELEASE"
fi

RELEASE_DIR="$PROJECT_DIR/releases/$RELEASE"

if [ ! -d "$RELEASE_DIR" ]; then
    log "ERROR: Release directory does not exist: $RELEASE_DIR"
    exit 1
fi

log "=== FlipStack Atomic Deployment ==="
log "Release: $RELEASE"
log "Release dir: $RELEASE_DIR"

# Propagate deployment config from release to project root
log "Updating deployment configuration..."
cp "$RELEASE_DIR/$COMPOSE_FILE" "$PROJECT_DIR/$COMPOSE_FILE"

# Fix file ownership (before build so Docker context has correct perms)
log "Fixing file ownership..."
chown -R 1000:1000 "$RELEASE_DIR"

# Swap symlink to new release BEFORE building images
# Build context (./current) needs to resolve to the new release
PREVIOUS_RELEASE=$(readlink -f "$PROJECT_DIR/current" 2>/dev/null || echo "")
log "Pointing current to new release..."
# Force-remove current if it's a directory (Docker auto-creates volume mount
# paths as directories, which prevents ln -sfn from creating a symlink)
if [ -d "$PROJECT_DIR/current" ] && [ ! -L "$PROJECT_DIR/current" ]; then
    rm -rf "$PROJECT_DIR/current"
fi
ln -sfn "$RELEASE_DIR" "$PROJECT_DIR/current"

# Symlink .env to project root so Docker Compose can interpolate variables
# (e.g. DB_PASSWORD for the db service's POSTGRES_PASSWORD)
ln -sf "$PROJECT_DIR/shared/.env" "$PROJECT_DIR/.env"

# Build Docker images BEFORE replacing storage with symlink
# (the build context needs a real storage/ dir, not a symlink to shared)
# Pass the release dir as build context since ./current symlink may not
# resolve correctly in Docker BuildKit
log "Building Docker images (uses cache if unchanged)..."
FLIPSTACK_BUILD_CONTEXT="$RELEASE_DIR" docker compose -f $COMPOSE_FILE build || {
    rollback "Docker image build failed"
    exit 1
}

# Ensure shared directories exist
mkdir -p "$PROJECT_DIR/shared/storage/app/public"
mkdir -p "$PROJECT_DIR/shared/storage/framework/cache"
mkdir -p "$PROJECT_DIR/shared/storage/framework/sessions"
mkdir -p "$PROJECT_DIR/shared/storage/framework/views"
mkdir -p "$PROJECT_DIR/shared/storage/logs"

# Symlink shared resources into the new release
log "Linking shared resources..."
rm -rf "$RELEASE_DIR/storage"
ln -s "$PROJECT_DIR/shared/storage" "$RELEASE_DIR/storage"

# Copy .env into release (not symlink -- symlink targets absolute paths
# that don't exist inside Docker containers)
rm -f "$RELEASE_DIR/.env"
cp "$PROJECT_DIR/shared/.env" "$RELEASE_DIR/.env"

# Fix shared directory ownership
chown -R 1000:1000 "$PROJECT_DIR/shared"

# === CRITICAL SECTION: failures below trigger rollback ===

# Ensure db and redis are running
log "Ensuring infrastructure containers are running..."
docker compose -f $COMPOSE_FILE up -d --no-build db redis || {
    rollback "Failed to start infrastructure containers"
    exit 1
}

# Remove orphaned app-2 container from previous config
docker rm -f ${CONTAINER_PREFIX}_app_2 2>/dev/null || true

# Force-recreate app-1 so its bind mount resolves the updated symlink
log "Recreating app-1 with new release..."
docker compose -f $COMPOSE_FILE up -d --no-deps --force-recreate --no-build app-1 || {
    rollback "Failed to recreate app-1"
    exit 1
}

# Wait for app-1 to be running before exec'ing into it
wait_for_container "${CONTAINER_PREFIX}_app_1" 10 || {
    rollback "app-1 did not reach running state"
    exit 1
}

log "Running composer install..."
docker exec ${CONTAINER_PREFIX}_app_1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader || {
    rollback "Composer install failed"
    exit 1
}

# Ensure storage symlink (public/storage -> ../storage/app/public)
log "Ensuring storage symlink..."
docker exec ${CONTAINER_PREFIX}_app_1 php artisan storage:link || true

# Run migrations
log "Running database migrations..."
docker exec ${CONTAINER_PREFIX}_app_1 php artisan migrate --force || {
    log "WARNING: Migrations may have already been applied"
}

# Run production seeder if --seed flag was provided
if [ "$SEED_PRODUCTION" = true ]; then
    log "Running production database seeder..."
    docker exec ${CONTAINER_PREFIX}_app_1 php artisan db:seed:production --force || {
        log "WARNING: Production seeding may have already been run"
    }
fi

# Optimize application (caches config/routes/views inside the release dir)
log "Optimizing application..."
docker exec ${CONTAINER_PREFIX}_app_1 php artisan config:cache || {
    rollback "Config cache failed"
    exit 1
}
docker exec ${CONTAINER_PREFIX}_app_1 php artisan route:cache
docker exec ${CONTAINER_PREFIX}_app_1 php artisan view:cache

# Wait for app-1 to be fully healthy BEFORE touching anything else
check_health "${CONTAINER_PREFIX}_app_1" 30 || {
    rollback "app-1 health check failed after optimization"
    exit 1
}

# Recreate web container FIRST and verify it's healthy before touching background services
# IMPORTANT: web MUST be recreated because Docker resolves volume mount symlinks
# at container creation time, not dynamically. The /data/flipstack/current/public
# mount will still point to the old release directory otherwise.
log "Recreating web container..."
docker compose -f $COMPOSE_FILE up -d --no-deps --force-recreate --no-build web || {
    rollback "Failed to recreate web container"
    exit 1
}

# Wait for web container Docker health check to pass
check_health "${CONTAINER_PREFIX}_web" 30 || {
    rollback "Web container health check failed"
    exit 1
}

# Verify Traefik is actually routing public traffic
check_traefik_routing || {
    rollback "Traefik routing verification failed - site not reachable externally"
    exit 1
}

log "Web container healthy and Traefik routing verified."

# Now recreate background services (these don't affect public traffic)
log "Recreating horizon and scheduler..."
docker compose -f $COMPOSE_FILE up -d --no-deps --force-recreate --no-build horizon scheduler

# Wait for horizon to be running, then terminate workers so they reload code
wait_for_container "${CONTAINER_PREFIX}_horizon" 10
log "Terminating Horizon workers..."
docker exec ${CONTAINER_PREFIX}_horizon php artisan horizon:terminate || true

# Reset any queue items stuck in "processing" from the previous deploy
log "Resetting stuck queue items..."
docker exec ${CONTAINER_PREFIX}_app_1 php artisan queue:reset-stuck || true

# Clean up old releases
cleanup_old_releases

# Clean up unused Docker images
log "Cleaning up unused images..."
docker image prune -f || true

log "=== Deployment Complete ==="
log "Active release: $RELEASE"
log "Previous release: $PREVIOUS_RELEASE"
log "Rollback command: ln -sfn $PREVIOUS_RELEASE $PROJECT_DIR/current && docker compose -f $COMPOSE_FILE up -d --no-deps --force-recreate --no-build app-1 web horizon scheduler"
docker compose -f $COMPOSE_FILE ps

# Show resource usage
log "Container resource usage:"
docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}" | grep ${CONTAINER_PREFIX} || true

# Write success result for CI consumption
write_deploy_result "success"

exit
}
