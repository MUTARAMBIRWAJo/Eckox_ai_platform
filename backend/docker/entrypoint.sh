#!/bin/sh
# =============================================================================
# Eckox AI Platform — Container Entrypoint (Render-compatible)
# =============================================================================
# Render injects $PORT at runtime. This script:
#   1. Substitutes $PORT into the nginx config template
#   2. Runs Laravel bootstrap optimizations
#   3. Runs database migrations (safe to run on every deploy)
#   4. Starts supervisord (nginx + php-fpm)
# =============================================================================

set -e

# ── 1. Resolve PORT ───────────────────────────────────────────────────────────
PORT="${PORT:-10000}"
echo "[entrypoint] Starting on port $PORT"

# Substitute __PORT__ placeholder in nginx config
sed "s/__PORT__/$PORT/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# ── 2. Laravel bootstrap cache (skip in worker mode) ─────────────────────────
if [ "${CONTAINER_ROLE}" != "worker" ]; then
    echo "[entrypoint] Caching Laravel config, routes, views..."
    php /var/www/html/artisan config:cache  2>&1 || true
    php /var/www/html/artisan route:cache   2>&1 || true
    php /var/www/html/artisan view:cache    2>&1 || true
fi

# ── 3. Run database migrations ────────────────────────────────────────────────
echo "[entrypoint] Running database migrations..."
php /var/www/html/artisan migrate --force 2>&1 || {
    echo "[entrypoint] WARNING: Migration failed — continuing startup"
}

# ── 4. Storage link ───────────────────────────────────────────────────────────
php /var/www/html/artisan storage:link 2>&1 || true

# ── 5. Hand off to supervisord or custom command ─────────────────────────────
if [ "${CONTAINER_ROLE}" = "worker" ]; then
    echo "[entrypoint] Starting queue worker..."
    exec php /var/www/html/artisan queue:work redis \
        --queue=ai-decision,ai-high-priority,inbound-processing,crm-default,message-outbound,emails,pdf-generation \
        --sleep=3 \
        --tries=3 \
        --timeout=120 \
        --max-jobs=1000 \
        --max-time=3600
else
    echo "[entrypoint] Starting nginx + php-fpm via supervisord..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
