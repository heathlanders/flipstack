# Cloud Deployment Plan: Single Server with Staging & Production

## Executive Summary

Migrate the ecommerce SaaS platform from local hosting to a single cloud server running both staging and production environments. This allows testing SSL, subdomain routing, and cloud-specific behavior while minimizing costs.

**Stack:** Hetzner Cloud VPS + Coolify + Cloudflare + GitHub Actions
**Estimated Cost:** ~$16-20/month
**Architecture:** Single server, two isolated environments (staging + production)

---

## Architecture Overview

```
                         ┌─────────────────────┐
                         │     Cloudflare      │
                         │   (DNS + Security)  │
                         │        FREE         │
                         └──────────┬──────────┘
                                    │
          ┌─────────────────────────┴─────────────────────────┐
          │                                                   │
          ▼                                                   ▼
   yourbrand.com                                    staging.yourbrand.com
   *.yourbrand.com                                  *.staging.yourbrand.com
          │                                                   │
          └─────────────────────┬─────────────────────────────┘
                                │
                                ▼
              ┌─────────────────────────────────────┐
              │      Hetzner CPX31 VPS              │
              │   (4 vCPU, 8GB RAM, 160GB SSD)      │
              │           ~$16/month                │
              │                                     │
              │  ┌─────────────┐ ┌─────────────┐    │
              │  │   STAGING   │ │ PRODUCTION  │    │
              │  │             │ │             │    │
              │  │ - PHP-FPM   │ │ - PHP-FPM   │    │
              │  │ - Nginx     │ │ - Nginx     │    │
              │  │ - Postgres  │ │ - Postgres  │    │
              │  │ - Redis     │ │ - Redis     │    │
              │  │ - Horizon   │ │ - Horizon   │    │
              │  │             │ │             │    │
              │  │ Port: 9001  │ │ Port: 9000  │    │
              │  └─────────────┘ └─────────────┘    │
              │                                     │
              │         ┌───────────┐               │
              │         │  Coolify  │               │
              │         │  (PaaS)   │               │
              │         │ Port 8000 │               │
              │         └───────────┘               │
              └─────────────────────────────────────┘
                                │
                                ▼
              ┌─────────────────────────────────────┐
              │      Hetzner Object Storage         │
              │         (S3-compatible)             │
              │           ~$5/month                 │
              │                                     │
              │  ┌─────────────┐ ┌─────────────┐    │
              │  │staging-bucket│ │ prod-bucket │    │
              │  └─────────────┘ └─────────────┘    │
              └─────────────────────────────────────┘

         ┌───────────────────────────────────────────────┐
         │              GitHub Repository                │
         │                                               │
         │   develop branch ──────► Staging deployment   │
         │   main branch ─────────► Production deployment│
         └───────────────────────────────────────────────┘
```

### Key Benefits

- **Real cloud testing** - Test SSL wildcards and subdomain routing before production
- **Cost effective** - Single server instead of two (~$16 vs ~$22)
- **Isolated environments** - Separate databases, Redis instances, and containers
- **Same deployment process** - Both environments use identical Docker setup

---

## Phase 1: Infrastructure Provisioning

### 1.1 Hetzner Cloud Server

**Server Specification (CPX31)**
- 4 vCPU (AMD EPYC)
- 8GB RAM
- 160GB NVMe SSD
- 20TB traffic included
- Location: Ashburn, VA (us-east) or Hillsboro, OR (us-west)
- OS: Ubuntu 24.04 LTS
- **Cost: ~$16/month**

*Why CPX31?* Running two full Laravel stacks (each with PHP-FPM, Postgres, Redis, Horizon) needs adequate RAM. 8GB allows ~3GB per environment plus system overhead.

### 1.2 Hetzner Object Storage

- S3-compatible storage
- Two buckets: `yourbrand-staging`, `yourbrand-prod`
- 100GB initial allocation
- **Cost: ~$5/month**

### 1.3 Server Creation Steps

1. Create Hetzner Cloud account at https://console.hetzner.cloud
2. Create new project
3. Add your SSH public key to project
4. Create server:
   - Location: Ashburn or Hillsboro
   - Image: Ubuntu 24.04
   - Type: CPX31
   - SSH Key: Select your key
   - Name: `yourbrand-cloud`

### 1.4 Initial Server Security

```bash
# SSH into server as root
ssh root@<SERVER_IP>

# 1. Update system
apt update && apt upgrade -y

# 2. Create deploy user
adduser deploy
usermod -aG sudo deploy

# 3. Copy SSH key to deploy user
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# 4. Secure SSH
cat >> /etc/ssh/sshd_config.d/hardening.conf << 'EOF'
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
EOF
systemctl restart sshd

# 5. Configure firewall
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 8000/tcp comment 'Coolify Dashboard'
ufw --force enable

# 6. Install and configure Fail2ban
apt install fail2ban -y
cat > /etc/fail2ban/jail.local << 'EOF'
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
findtime = 600
EOF
systemctl enable fail2ban
systemctl restart fail2ban

# 7. Enable automatic security updates
apt install unattended-upgrades -y
cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}";
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
Unattended-Upgrade::AutoFixInterruptedDpkg "true";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
EOF
systemctl enable unattended-upgrades

# 8. Set timezone
timedatectl set-timezone UTC

# 9. Increase file limits for Docker
cat >> /etc/sysctl.conf << 'EOF'
fs.file-max = 65535
net.core.somaxconn = 65535
vm.overcommit_memory = 1
EOF
sysctl -p

echo "Server hardening complete. Log out and reconnect as deploy user."
```

---

## Phase 2: Domain & DNS Configuration

### 2.1 Domain Purchase (Cloudflare Registrar)

1. Go to https://dash.cloudflare.com
2. Navigate to **Domain Registration** > **Register Domain**
3. Search for your desired domain (e.g., `yourbrand.com`)
4. Purchase (~$10-15/year for .com, at-cost pricing)

### 2.2 DNS Records Configuration

Add these records in Cloudflare DNS:

```
Type    Name              Content              Proxy    TTL
─────────────────────────────────────────────────────────────────
A       @                 <SERVER_IP>          DNS only Auto
A       www               <SERVER_IP>          DNS only Auto
A       *                 <SERVER_IP>          DNS only Auto
A       staging           <SERVER_IP>          DNS only Auto
A       *.staging         <SERVER_IP>          DNS only Auto
```

**Important:** Set all records to "DNS only" (gray cloud icon) so that:
- SSL is handled by Let's Encrypt on your server (not Cloudflare)
- Wildcard subdomains work correctly for multi-tenancy

### 2.3 Cloudflare Security Settings

Even with "DNS only" mode, enable these security features:

1. **SSL/TLS** > **Edge Certificates**:
   - Always Use HTTPS: ON
   - Minimum TLS Version: 1.2

2. **Security** > **Settings**:
   - Security Level: Medium
   - Challenge Passage: 30 minutes
   - Browser Integrity Check: ON

3. **Security** > **Bots**:
   - Bot Fight Mode: ON (free)

---

## Phase 3: Coolify Installation

### 3.1 Install Coolify

```bash
# SSH as deploy user
ssh deploy@<SERVER_IP>

# Install Coolify
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | sudo bash
```

Installation takes 2-3 minutes. After completion:

1. Access Coolify at `http://<SERVER_IP>:8000`
2. Create admin account (use strong password)
3. Complete initial setup wizard

### 3.2 Configure Coolify Settings

In Coolify dashboard:

1. **Settings** > **Configuration**:
   - Instance's Domain: `coolify.yourbrand.com` (optional, for SSL on dashboard)
   - Auto Update: Enabled

2. **Servers** > **localhost**:
   - Verify Docker is detected
   - Check available resources

### 3.3 Connect GitHub Repository

1. **Sources** > **Add** > **GitHub App**
2. Follow OAuth flow to authorize Coolify
3. Select your repository
4. Grant repository access

Alternative: Use Deploy Key (more secure, read-only)
1. **Sources** > **Add** > **Deploy Key**
2. Copy the public key
3. Add to GitHub repo: Settings > Deploy Keys

---

## Phase 4: Application Setup in Coolify

### 4.1 Create Production Application

1. **Projects** > **Add** > Name: "YourBrand"
2. **Environments** > **Add** > Name: "production"
3. **Add Resource** > **Docker Compose**
4. Configure:
   - Name: `yourbrand-prod`
   - Source: GitHub (your repo)
   - Branch: `main`
   - Docker Compose Location: `docker-compose.yml`

### 4.2 Create Staging Application

1. In same project, **Environments** > **Add** > Name: "staging"
2. **Add Resource** > **Docker Compose**
3. Configure:
   - Name: `yourbrand-staging`
   - Source: GitHub (your repo)
   - Branch: `develop`
   - Docker Compose Location: `docker-compose.yml`

### 4.3 Production Environment Variables

In Coolify, set these for the production application:

```env
# Application
APP_NAME="YourBrand"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://yourbrand.com

# Database (isolated for production)
DB_CONNECTION=pgsql
DB_HOST=postgres-prod
DB_PORT=5432
DB_DATABASE=ecommerce_prod
DB_USERNAME=ecommerce_prod
DB_PASSWORD=<GENERATE_32_CHAR_PASSWORD>

# Redis (isolated for production)
REDIS_HOST=redis-prod
REDIS_PASSWORD=<GENERATE_32_CHAR_PASSWORD>
REDIS_PORT=6379
REDIS_CLIENT=phpredis

# Session & Cache
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
CACHE_STORE=redis
CACHE_PREFIX=prod_
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=<YOUR_RESEND_API_KEY>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@yourbrand.com
MAIL_FROM_NAME="${APP_NAME}"

# Hetzner Object Storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<HETZNER_KEY>
AWS_SECRET_ACCESS_KEY=<HETZNER_SECRET>
AWS_DEFAULT_REGION=fsn1
AWS_BUCKET=yourbrand-prod
AWS_ENDPOINT=https://fsn1.your-objectstorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

# eBay (Production)
EBAY_APP_ID=<YOUR_PRODUCTION_EBAY_APP_ID>
EBAY_CERT_ID=<YOUR_PRODUCTION_EBAY_CERT_ID>
EBAY_DEV_ID=<YOUR_EBAY_DEV_ID>
EBAY_SANDBOX=false
EBAY_MARKETPLACE=EBAY_US

# Other Services
ANTHROPIC_API_KEY=<YOUR_KEY>
UPC_DATABASE_API_KEY=<YOUR_KEY>

# Broadcasting (enable when ready)
BROADCAST_CONNECTION=log
```

### 4.4 Staging Environment Variables

```env
# Application
APP_NAME="YourBrand Staging"
APP_ENV=staging
APP_KEY=base64:GENERATE_DIFFERENT_KEY
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=https://staging.yourbrand.com

# Database (isolated for staging)
DB_CONNECTION=pgsql
DB_HOST=postgres-staging
DB_PORT=5432
DB_DATABASE=ecommerce_staging
DB_USERNAME=ecommerce_staging
DB_PASSWORD=<DIFFERENT_32_CHAR_PASSWORD>

# Redis (isolated for staging)
REDIS_HOST=redis-staging
REDIS_PASSWORD=<DIFFERENT_32_CHAR_PASSWORD>
REDIS_PORT=6379
REDIS_CLIENT=phpredis

# Session & Cache
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis
CACHE_PREFIX=staging_
QUEUE_CONNECTION=redis

# Mail (use log or mailtrap for staging)
MAIL_MAILER=log

# Hetzner Object Storage (separate bucket)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<HETZNER_KEY>
AWS_SECRET_ACCESS_KEY=<HETZNER_SECRET>
AWS_DEFAULT_REGION=fsn1
AWS_BUCKET=yourbrand-staging
AWS_ENDPOINT=https://fsn1.your-objectstorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

# eBay (Sandbox for testing)
EBAY_APP_ID=<YOUR_SANDBOX_EBAY_APP_ID>
EBAY_CERT_ID=<YOUR_SANDBOX_EBAY_CERT_ID>
EBAY_DEV_ID=<YOUR_EBAY_DEV_ID>
EBAY_SANDBOX=true
EBAY_MARKETPLACE=EBAY_US

# Other Services (can use same keys)
ANTHROPIC_API_KEY=<YOUR_KEY>
UPC_DATABASE_API_KEY=<YOUR_KEY>

BROADCAST_CONNECTION=log
```

### 4.5 Docker Compose Adjustments

Create `docker-compose.prod.yml` for production overrides:

```yaml
services:
  app:
    restart: always
    deploy:
      resources:
        limits:
          memory: 768M
        reservations:
          memory: 512M

  nginx:
    restart: always

  postgres:
    restart: always
    shm_size: 256mb
    deploy:
      resources:
        limits:
          memory: 512M

  redis:
    restart: always
    deploy:
      resources:
        limits:
          memory: 256M

  horizon:
    restart: always
    profiles: []
    deploy:
      resources:
        limits:
          memory: 256M

  scheduler:
    restart: always
    profiles: []
    deploy:
      resources:
        limits:
          memory: 128M
```

Create `docker-compose.staging.yml` for staging (lighter resources):

```yaml
services:
  app:
    restart: unless-stopped
    deploy:
      resources:
        limits:
          memory: 512M

  postgres:
    restart: unless-stopped
    deploy:
      resources:
        limits:
          memory: 256M

  redis:
    restart: unless-stopped
    deploy:
      resources:
        limits:
          memory: 128M

  horizon:
    restart: unless-stopped
    profiles: []
    deploy:
      resources:
        limits:
          memory: 128M

  scheduler:
    restart: unless-stopped
    profiles: []
    deploy:
      resources:
        limits:
          memory: 64M
```

---

## Phase 5: SSL/TLS Configuration

### 5.1 Create Cloudflare API Token

For wildcard SSL via DNS challenge:

1. Cloudflare Dashboard > **My Profile** > **API Tokens**
2. **Create Token** > **Custom Token**
3. Configure:
   - Token name: `Coolify SSL`
   - Permissions:
     - Zone > DNS > Edit
     - Zone > Zone > Read
   - Zone Resources: Include > Specific zone > `yourbrand.com`
4. Copy the token (shown only once)

### 5.2 Configure SSL in Coolify

For **Production** application:

1. Go to application > **Settings** > **Domains**
2. Add domains:
   ```
   yourbrand.com
   *.yourbrand.com
   ```
3. **SSL/TLS** settings:
   - Enable Let's Encrypt
   - DNS Provider: Cloudflare
   - API Token: Paste your token
4. Click **Generate Certificate**

For **Staging** application:

1. Add domains:
   ```
   staging.yourbrand.com
   *.staging.yourbrand.com
   ```
2. Same SSL configuration

### 5.3 Verify Wildcard SSL

After certificate generation (may take a few minutes):

```bash
# Test production wildcard
curl -I https://yourbrand.com
curl -I https://anyorg.yourbrand.com

# Test staging wildcard
curl -I https://staging.yourbrand.com
curl -I https://testorg.staging.yourbrand.com
```

All should return valid SSL certificates.

---

## Phase 6: CI/CD Pipeline

### 6.1 GitHub Actions Workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: Test and Deploy

on:
  push:
    branches:
      - main
      - develop
  pull_request:
    branches:
      - main
      - develop

env:
  PHP_VERSION: '8.4'
  NODE_VERSION: '20'

jobs:
  test:
    name: Run Tests
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
          POSTGRES_DB: test
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: pdo_pgsql, redis, gd, bcmath, intl, pcntl
          coverage: none

      - name: Get Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install Composer dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install NPM dependencies
        run: npm ci

      - name: Build assets
        run: npm run build

      - name: Prepare Laravel
        run: |
          cp .env.example .env
          php artisan key:generate
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: test
          DB_USERNAME: test
          DB_PASSWORD: test

      - name: Run PHP Linting
        run: ./vendor/bin/pint --test

      - name: Run Tests
        run: php artisan test
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: test
          DB_USERNAME: test
          DB_PASSWORD: test
          REDIS_HOST: 127.0.0.1

  deploy-staging:
    name: Deploy to Staging
    needs: test
    if: github.ref == 'refs/heads/develop' && github.event_name == 'push'
    runs-on: ubuntu-latest
    environment: staging

    steps:
      - name: Trigger Coolify Deployment
        run: |
          curl -X POST "${{ secrets.COOLIFY_WEBHOOK_STAGING }}" \
            -H "Authorization: Bearer ${{ secrets.COOLIFY_TOKEN }}" \
            --fail --silent --show-error

      - name: Wait for deployment
        run: sleep 30

      - name: Health check
        run: |
          curl --fail --silent --show-error \
            https://staging.yourbrand.com/health || exit 1

  deploy-production:
    name: Deploy to Production
    needs: test
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    runs-on: ubuntu-latest
    environment: production

    steps:
      - name: Trigger Coolify Deployment
        run: |
          curl -X POST "${{ secrets.COOLIFY_WEBHOOK_PROD }}" \
            -H "Authorization: Bearer ${{ secrets.COOLIFY_TOKEN }}" \
            --fail --silent --show-error

      - name: Wait for deployment
        run: sleep 30

      - name: Health check
        run: |
          curl --fail --silent --show-error \
            https://yourbrand.com/health || exit 1
```

### 6.2 Get Coolify Webhook URLs

In Coolify, for each application:

1. Go to application > **Webhooks**
2. Copy the webhook URL
3. Note the format: `https://coolify.yourbrand.com/api/v1/deploy?...`

### 6.3 Configure GitHub Secrets

In GitHub repo > **Settings** > **Secrets and variables** > **Actions**:

Add these secrets:
- `COOLIFY_TOKEN`: Your Coolify API token (from Coolify > Settings > API)
- `COOLIFY_WEBHOOK_STAGING`: Staging webhook URL
- `COOLIFY_WEBHOOK_PROD`: Production webhook URL

### 6.4 Configure GitHub Environments

In GitHub repo > **Settings** > **Environments**:

1. Create `staging` environment
   - No protection rules needed

2. Create `production` environment
   - Enable "Required reviewers" (optional, recommended)
   - Add yourself as reviewer

---

## Phase 7: Database Backup Strategy

### 7.1 Create Backup Script

Create `/opt/backup/backup.sh` on the server:

```bash
#!/bin/bash
set -euo pipefail

# Configuration
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/tmp/backups"
S3_BUCKET="yourbrand-backups"
S3_ENDPOINT="https://fsn1.your-objectstorage.com"
RETENTION_DAYS=30

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"

# Function to backup a database
backup_db() {
    local ENV=$1
    local CONTAINER=$2
    local DB_NAME=$3
    local DB_USER=$4

    echo "[$TIMESTAMP] Backing up $ENV database..."

    # Dump database
    docker exec "$CONTAINER" pg_dump -U "$DB_USER" "$DB_NAME" | gzip > "$BACKUP_DIR/${ENV}_db_${TIMESTAMP}.sql.gz"

    # Upload to S3
    aws s3 cp "$BACKUP_DIR/${ENV}_db_${TIMESTAMP}.sql.gz" \
        "s3://$S3_BUCKET/database/$ENV/" \
        --endpoint-url "$S3_ENDPOINT"

    echo "[$TIMESTAMP] $ENV backup uploaded successfully"
}

# Backup production
backup_db "prod" "yourbrand-prod-postgres-1" "ecommerce_prod" "ecommerce_prod"

# Backup staging (optional, less critical)
backup_db "staging" "yourbrand-staging-postgres-1" "ecommerce_staging" "ecommerce_staging"

# Clean up old backups (production only, keep 30 days)
echo "[$TIMESTAMP] Cleaning up old backups..."
aws s3 ls "s3://$S3_BUCKET/database/prod/" --endpoint-url "$S3_ENDPOINT" | while read -r line; do
    file_date=$(echo "$line" | awk '{print $1}')
    file_name=$(echo "$line" | awk '{print $4}')

    if [[ -n "$file_date" && -n "$file_name" ]]; then
        file_epoch=$(date -d "$file_date" +%s 2>/dev/null || echo 0)
        cutoff_epoch=$(date -d "-$RETENTION_DAYS days" +%s)

        if [[ $file_epoch -lt $cutoff_epoch && $file_epoch -gt 0 ]]; then
            echo "Deleting old backup: $file_name"
            aws s3 rm "s3://$S3_BUCKET/database/prod/$file_name" --endpoint-url "$S3_ENDPOINT"
        fi
    fi
done

# Clean local temp files
rm -rf "$BACKUP_DIR"/*

echo "[$TIMESTAMP] Backup complete"
```

### 7.2 Configure AWS CLI for Hetzner

```bash
# Install AWS CLI
sudo apt install awscli -y

# Configure for Hetzner Object Storage
aws configure
# AWS Access Key ID: <your-hetzner-key>
# AWS Secret Access Key: <your-hetzner-secret>
# Default region: fsn1
# Default output format: json
```

### 7.3 Schedule Backups

```bash
# Make script executable
sudo chmod +x /opt/backup/backup.sh

# Add to crontab (run at 3 AM daily)
sudo crontab -e
# Add this line:
0 3 * * * /opt/backup/backup.sh >> /var/log/backup.log 2>&1
```

### 7.4 Test Backup Restoration

```bash
# Download a backup
aws s3 cp s3://yourbrand-backups/database/prod/prod_db_20260120_030000.sql.gz \
    /tmp/restore.sql.gz --endpoint-url https://fsn1.your-objectstorage.com

# Decompress
gunzip /tmp/restore.sql.gz

# Restore to a test database (never directly to production!)
docker exec -i yourbrand-prod-postgres-1 \
    psql -U ecommerce_prod -d ecommerce_test < /tmp/restore.sql
```

---

## Phase 8: Health Check Endpoint

### 8.1 Add Health Route

Add to `routes/web.php`:

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;

Route::get('/health', function () {
    $checks = [
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),
        'services' => [],
    ];

    // Database check
    try {
        DB::connection()->getPdo();
        $checks['services']['database'] = 'connected';
    } catch (\Exception $e) {
        $checks['services']['database'] = 'disconnected';
        $checks['status'] = 'unhealthy';
    }

    // Redis check
    try {
        Redis::ping();
        $checks['services']['redis'] = 'connected';
    } catch (\Exception $e) {
        $checks['services']['redis'] = 'disconnected';
        $checks['status'] = 'unhealthy';
    }

    // Queue check
    try {
        $queueSize = Queue::size('default');
        $checks['services']['queue'] = $queueSize >= 0 ? 'running' : 'error';
    } catch (\Exception $e) {
        $checks['services']['queue'] = 'error';
        $checks['status'] = 'unhealthy';
    }

    $statusCode = $checks['status'] === 'healthy' ? 200 : 503;

    return response()->json($checks, $statusCode);
})->name('health');
```

---

## Phase 9: Monitoring Setup

### 9.1 Uptime Monitoring (Free)

Sign up at https://uptimerobot.com (free tier: 50 monitors)

Create monitors:
1. **Production Site**: `https://yourbrand.com/health`
2. **Staging Site**: `https://staging.yourbrand.com/health`

Configure alerts: Email + optional Slack/Discord webhook

### 9.2 Error Tracking (Optional)

For better error visibility, consider Sentry (free tier: 5K events/month):

1. Sign up at https://sentry.io
2. Create Laravel project
3. Install: `composer require sentry/sentry-laravel`
4. Add to `.env`:
   ```
   SENTRY_LARAVEL_DSN=https://xxx@xxx.ingest.sentry.io/xxx
   ```

### 9.3 Laravel Horizon Dashboard

Protect Horizon in `app/Providers/HorizonServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        // Only allow specific users or roles
        return in_array($user->email, [
            'admin@yourbrand.com',
        ]);
    });
}
```

Access at: `https://yourbrand.com/horizon`

---

## Phase 10: Security Checklist

### 10.1 Application Security

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] Unique `APP_KEY` for each environment
- [ ] HTTPS enforced (redirect all HTTP)
- [ ] CSRF protection enabled (Laravel default)
- [ ] XSS protection via Blade escaping
- [ ] SQL injection prevented via Eloquent
- [ ] Rate limiting on login routes
- [ ] Horizon dashboard protected
- [ ] Telescope disabled/protected in production

### 10.2 Server Security

- [ ] SSH key-only authentication
- [ ] Root login disabled
- [ ] Firewall (UFW) configured
- [ ] Fail2ban active
- [ ] Automatic security updates enabled
- [ ] Non-standard SSH port (optional)

### 10.3 Database Security

- [ ] Strong passwords (32+ characters, generated)
- [ ] PostgreSQL bound to localhost only (Docker internal network)
- [ ] Separate credentials per environment
- [ ] Regular backups verified

### 10.4 Secrets Management

- [ ] No secrets in Git repository
- [ ] `.env` in `.gitignore`
- [ ] Environment variables in Coolify (encrypted)
- [ ] Different API keys for staging vs production
- [ ] eBay OAuth tokens auto-refreshed

---

## Phase 11: Go-Live Checklist

### Pre-Launch (Staging)

- [ ] Deploy to staging successfully
- [ ] SSL certificate valid for `staging.yourbrand.com`
- [ ] Wildcard SSL works: `testorg.staging.yourbrand.com`
- [ ] Create test organization
- [ ] Verify subdomain routing works
- [ ] Test eBay sandbox integration
- [ ] Run database seeder
- [ ] Verify Horizon is processing jobs
- [ ] Test file uploads to S3
- [ ] Health check returns 200

### Pre-Launch (Production)

- [ ] All staging tests pass
- [ ] Production environment variables set
- [ ] Production eBay credentials configured
- [ ] SSL certificate valid for `yourbrand.com`
- [ ] Wildcard SSL works: `*.yourbrand.com`
- [ ] Database migrations run
- [ ] Backups configured and tested
- [ ] Monitoring set up

### Launch Day

- [ ] Deploy to production
- [ ] Verify health check: `https://yourbrand.com/health`
- [ ] Create first real organization
- [ ] Test subdomain: `firstorg.yourbrand.com`
- [ ] Verify eBay connection
- [ ] Monitor error logs for first 24 hours
- [ ] Check Horizon for job processing

### Post-Launch

- [ ] Uptime alerts working
- [ ] First backup completed successfully
- [ ] Document any issues encountered
- [ ] Plan first backup restoration test (within 1 week)

---

## Cost Summary

| Item | Monthly Cost |
|------|--------------|
| Hetzner CPX31 (4 vCPU, 8GB) | ~$16 |
| Hetzner Object Storage (100GB) | ~$5 |
| Cloudflare (DNS/CDN) | Free |
| Let's Encrypt SSL | Free |
| Coolify | Free |
| **Total** | **~$21/month** |

### Optional Add-ons

| Service | Cost | Notes |
|---------|------|-------|
| Resend (Email) | Free tier | 3,000 emails/month |
| Sentry (Errors) | Free tier | 5,000 events/month |
| Uptime Robot | Free tier | 50 monitors |
| Pusher (Realtime) | Free tier | 200K messages/day |

---

## Workflow Summary

```
Local Development          Staging                    Production
─────────────────         ──────────                 ───────────
Your Linux server    →    staging.yourbrand.com  →  yourbrand.com

git push develop     →    Auto-deploy to staging
                          Test SSL/subdomains
                          Verify features

git push main        →                           →  Auto-deploy to prod
                                                    Live for users
```

---

## Quick Reference Commands

```bash
# SSH to server
ssh deploy@<SERVER_IP>

# View application logs (production)
docker logs -f yourbrand-prod-app-1

# View Horizon logs
docker logs -f yourbrand-prod-horizon-1

# Access PostgreSQL (production)
docker exec -it yourbrand-prod-postgres-1 psql -U ecommerce_prod -d ecommerce_prod

# Access Redis CLI (production)
docker exec -it yourbrand-prod-redis-1 redis-cli

# Restart production stack
cd /path/to/coolify/data && docker compose -f yourbrand-prod.yml restart

# Manual backup
/opt/backup/backup.sh

# Check disk usage
df -h

# Check memory usage
free -h

# Check running containers
docker ps
```

---

## Next Steps

1. Purchase domain on Cloudflare
2. Create Hetzner account and server
3. Run server hardening script
4. Install Coolify
5. Configure applications and deploy
6. Test staging environment thoroughly
7. Go live with production

Ready to start implementation? Let me know which phase you'd like to begin with.
