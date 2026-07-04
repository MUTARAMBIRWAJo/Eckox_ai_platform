#!/bin/sh
# =============================================================================
# Eckox AI Platform — Container Entrypoint (Render-compatible)
# =============================================================================
# Fixes applied vs previous version:
#   - NO set -e: prevents container exit on non-critical startup errors
#   - Uses envsubst instead of sed for ${PORT} substitution (more reliable)
#   - Starts php-fpm first, waits until ready, THEN starts nginx
#   - Worker mode runs queue:work directly without nginx
# =============================================================================

# ── Resolve PORT ──────────────────────────────────────────────────────────────
export PORT="${PORT:-10000}"
echo "[entrypoint] Container role: ${CONTAINER_ROLE:-web}"
echo "[entrypoint] Starting on port $PORT"

# ── Generate nginx config from template (web mode only) ───────────────────────
if [ "${CONTAINER_ROLE}" != "worker" ]; then
    echo "[entrypoint] Generating nginx config for port $PORT..."
    # envsubst only replaces ${PORT} — leaves other nginx vars untouched
    envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

    # Quick syntax check before committing to startup
    nginx -t 2>&1
    if [ $? -ne 0 ]; then
        echo "[entrypoint] ERROR: nginx config syntax check failed — aborting"
        exit 1
    fi
    echo "[entrypoint] nginx config OK — will listen on 0.0.0.0:$PORT"
fi

# ── Laravel bootstrap (best-effort — don't abort on failure) ──────────────────
echo "[entrypoint] Running Laravel bootstrap..."
php /var/www/html/artisan storage:link       2>&1 || echo "[entrypoint] storage:link skipped"
php /var/www/html/artisan config:cache       2>&1 || echo "[entrypoint] config:cache skipped (no DB yet)"
php /var/www/html/artisan route:cache        2>&1 || echo "[entrypoint] route:cache skipped"
php /var/www/html/artisan view:cache         2>&1 || echo "[entrypoint] view:cache skipped"

# ── Database migrations ───────────────────────────────────────────────────────
echo "[entrypoint] Running migrations..."
php /var/www/html/artisan migrate --force    2>&1 || echo "[entrypoint] WARNING: Migration failed — check DB connectivity"

# ── Hand off to appropriate process ──────────────────────────────────────────
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
