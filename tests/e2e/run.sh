#!/usr/bin/env bash
# آزمون عملیاتی انتها‌به‌انتها (مراحل ۹۷ تا ۹۹) در مرورگر واقعی.
#
# پیش‌نیاز: Node و Playwright (Chromium)، یک پایگاه داده خالی MySQL/MariaDB
# که با متغیرهای زیر قابل دسترس است. برنامه با تنظیمات production اجرا و
# زرین‌پال و SMS.ir با tests/e2e/fake-gateways.mjs شبیه‌سازی می‌شوند.
#
#   E2E_DB_DATABASE=idealab_e2e E2E_DB_USERNAME=... E2E_DB_PASSWORD=... tests/e2e/run.sh
#
# خروجی: گزارش هر بررسی در خروجی و تصاویر در storage/e2e/
set -euo pipefail
cd "$(dirname "$0")/../.."

if [ -f bootstrap/cache/config.php ]; then
    echo "Config cache is active; run 'php artisan optimize:clear' first." >&2
    exit 1
fi

APP_PORT=${E2E_APP_PORT:-8125}
GW_PORT=${E2E_GATEWAY_PORT:-8200}
OUT=storage/e2e
mkdir -p "$OUT"

export APP_ENV=production APP_DEBUG=false APP_URL="http://127.0.0.1:${APP_PORT}"
export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
export INSTALL_TOKEN=e2e-install-token-$$
export DB_CONNECTION=${E2E_DB_CONNECTION:-mariadb} DB_HOST=${E2E_DB_HOST:-127.0.0.1} DB_PORT=${E2E_DB_PORT:-3306}
export DB_DATABASE=${E2E_DB_DATABASE:?set E2E_DB_DATABASE} DB_USERNAME=${E2E_DB_USERNAME:?} DB_PASSWORD=${E2E_DB_PASSWORD:-}
export SESSION_DRIVER=database CACHE_STORE=database LOG_LEVEL=warning
export SMS_DRIVER=smsir SMSIR_API_KEY=e2e-sms-key SMSIR_LINE_NUMBER=30001234 SMSIR_BASE_URL="http://127.0.0.1:${GW_PORT}/v1" SMS_PROMO_HOURS=0-24
export ZARINPAL_MERCHANT_ID=e2e-merchant-0000000000000000000000 ZARINPAL_SANDBOX=true
export ZARINPAL_API_URL="http://127.0.0.1:${GW_PORT}" ZARINPAL_START_PAY_URL="http://127.0.0.1:${GW_PORT}/pg/StartPay/"
export PUBLIC_FORM_MIN_SECONDS=3 APP_DISPLAY_TIMEZONE=Asia/Tehran

# پایگاه داده آزمون از صفر؛ نصب واقعی (migration و مدیر کل) را خود سناریو از /install انجام می‌دهد
php artisan db:wipe --force >/dev/null

node tests/e2e/fake-gateways.mjs "$GW_PORT" > "$OUT/gateways.log" 2>&1 &
GW_PID=$!
# server.php پوشه جاری را public در نظر می‌گیرد
(cd public && PHP_CLI_SERVER_WORKERS=4 exec php -S "127.0.0.1:${APP_PORT}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php) > "$OUT/server.log" 2>&1 &
APP_PID=$!
trap 'kill $GW_PID $APP_PID 2>/dev/null || true' EXIT
sleep 2

E2E_BASE="$APP_URL" E2E_GATEWAY="http://127.0.0.1:${GW_PORT}" E2E_OUT="$OUT" E2E_INSTALL_TOKEN="$INSTALL_TOKEN" \
    node tests/e2e/scenario.cjs
