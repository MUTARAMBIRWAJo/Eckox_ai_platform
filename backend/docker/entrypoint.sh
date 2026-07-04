#!/bin/sh
# =============================================================================
# Eckox AI Platform — Container Entrypoint (Render-compatible)
# =============================================================================

# ── 1. Resolve PORT ───────────────────────────────────────────────────────────
export PORT="${PORT:-10000}"
echo "[entrypoint] Container role: ${CONTAINER_ROLE:-web}"
echo "[entrypoint] Starting on port $PORT"

# ── 2. Generate nginx config from template (web mode only) ───────────────────────
if [ "${CONTAINER_ROLE}" != "worker" ]; then
    echo "[entrypoint] Generating nginx config for port $PORT..."
    envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

    # Quick syntax check
    nginx -t 2>&1
    if [ $? -ne 0 ]; then
        echo "[entrypoint] ERROR: nginx config syntax check failed — aborting"
        exit 1
    fi
    echo "[entrypoint] nginx config OK — will listen on 0.0.0.0:$PORT"
fi

# ── 3. Force database driver checks and production config sync ─────────────────
echo "[entrypoint] Clearing configuration caches..."
php /var/www/html/artisan config:clear 2>&1
php /var/www/html/artisan cache:clear 2>&1

# Force the database driver connection config cache to avoid default sqlite fallbacks
echo "[entrypoint] Caching Laravel config..."
php /var/www/html/artisan config:cache 2>&1 || {
    echo "[entrypoint] ERROR: Configuration cache failed — aborting"
    exit 1
}

# ── 4. Database migrations (CRITICAL: Must pass to deploy) ──────────────────────
echo "[entrypoint] Running database migrations..."
php /var/www/html/artisan migrate --force 2>&1
if [ $? -ne 0 ]; then
    echo "[entrypoint] FATAL ERROR: Database migrations failed! Aborting startup to block deployment."
    exit 1
fi
echo "[entrypoint] Database migrations completed successfully."

# ── 5. Cache remaining Laravel assets ──────────────────────────────────────────
if [ "${CONTAINER_ROLE}" != "worker" ]; then
    php /var/www/html/artisan storage:link 2>&1 || true
    php /var/www/html/artisan route:cache  2>&1 || true
    php /var/www/html/artisan view:cache   2>&1 || true
fi

# ── 6. Hand off to process ────────────────────────────────────────────────────
if [ "${CONTAINER_ROLE}" = "worker" ]; then
    echo "[entrypoint] Starting queue worker (no HTTP server needed)..."
    exec php /var/www/html/artisan queue:work redis \
        --queue=ai-decision,ai-high-priority,inbound-processing,crm-default,message-outbound,emails,pdf-generation \
        --sleep=3 \
        --tries=3 \
        --timeout=120 \
        --max-jobs=1000 \
        --max-time=3600
else
    echo "[entrypoint] Starting php-fpm + nginx via supervisord..."
    echo "[entrypoint] HTTP will be available at 0.0.0.0:$PORT"
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
