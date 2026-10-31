#!/usr/bin/env bash
# آزمون ارتقا (مرحله ۱۳۷): نسخه ۱.۰ با داده نمونه ← نسخه فعلی، مثل سرور واقعی.
#
#   UPGRADE_DB_DATABASE=idealab_upgrade UPGRADE_DB_USERNAME=... UPGRADE_DB_PASSWORD=... tests/upgrade/rehearse.sh [ref نسخه ۱.۰]
#
# ۱. ساخت نسخه ۱.۰ از Git (پیش‌فرض a328d7d، همان بسته مستقرشده) با تنظیمات production و داده نمونه
# ۲. عکس‌برداری از همه ردیف‌ها
# ۳. نصب نخست ۱.۱ مثل File Manager: Extract بسته روی پوشه و `system:finish-update`
# ۴. مقایسه داده، بررسی صفحات عمومی و پنل در وب‌سرور
# ۵. به‌روزرسانی بعدی با سامانه به‌روزرسانی (system:update) و بازگرداندن آن
#
# پایگاه داده آزمون پاک و از نو ساخته می‌شود. vendor از همین مخزن کپی می‌شود
# (composer.lock از ۱.۰ تغییر نکرده است).
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
BASE_REF=${1:-a328d7d}
WORK=${UPGRADE_WORK_DIR:-$REPO/storage/upgrade-rehearsal}
PORT=${UPGRADE_APP_PORT:-8135}
APP="$WORK/server"
SCRIPTS="$REPO/tests/upgrade"
PASS=0
FAIL=0

check() {
    if [ "$1" = "0" ]; then echo "PASS  $2"; PASS=$((PASS + 1)); else echo "FAIL  $2"; FAIL=$((FAIL + 1)); fi
}

rm -rf "$WORK" && mkdir -p "$APP"

# ---------- ۱. نسخه ۱.۰
git -C "$REPO" archive "$BASE_REF" | tar -x -C "$APP"
cp -a "$REPO/vendor" "$APP/"
mkdir -p "$APP"/storage/{app/public,framework/{cache/data,sessions,views},logs} "$APP/bootstrap/cache" "$APP/public/uploads"

cat > "$APP/.env" <<ENV
APP_NAME=IdeaLab
APP_ENV=production
APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')
APP_DEBUG=false
APP_URL=http://127.0.0.1:${PORT}
APP_LOCALE=fa
DB_CONNECTION=${UPGRADE_DB_CONNECTION:-mariadb}
DB_HOST=${UPGRADE_DB_HOST:-127.0.0.1}
DB_PORT=${UPGRADE_DB_PORT:-3306}
DB_DATABASE=${UPGRADE_DB_DATABASE:?set UPGRADE_DB_DATABASE}
DB_USERNAME=${UPGRADE_DB_USERNAME:?}
DB_PASSWORD=${UPGRADE_DB_PASSWORD:-}
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
SMS_DRIVER=log
LOG_LEVEL=warning
ENV

cd "$APP"
php artisan db:wipe --force >/dev/null
php artisan migrate --force >/dev/null
php "$SCRIPTS/seed-v10.php"
php artisan optimize >/dev/null
check "$([ ! -f VERSION ] && echo 0 || echo 1)" "نسخه ۱.۰ بدون فایل VERSION و با کش پیکربندی فعال (مثل سرور)"
php "$SCRIPTS/snapshot.php" save "$WORK/before.json"

# ---------- ۲. بسته نسخه فعلی
VERSION_NOW=$(tr -d '[:space:]' < "$REPO/VERSION")
(cd "$REPO" && php artisan release:build "$VERSION_NOW" --output="$WORK/idea-lab-$VERSION_NOW.zip" >/dev/null)

# ---------- ۳. نصب نخست مثل File Manager
php -r '$z = new ZipArchive; $z->open($argv[1]); $z->extractTo($argv[2]); $z->close();' "$WORK/idea-lab-$VERSION_NOW.zip" "$APP"
php artisan system:finish-update >/dev/null
check "$([ "$(cat VERSION)" = "$VERSION_NOW" ] && echo 0 || echo 1)" "نسخه $VERSION_NOW نصب شد"
MIGRATIONS=$(php artisan migrate:status 2>&1 || true)
check "$(grep -q ' Ran' <<<"$MIGRATIONS" && ! grep -q 'Pending' <<<"$MIGRATIONS" && echo 0 || echo 1)" "همه migrationها اجرا شد"
if php "$SCRIPTS/snapshot.php" compare "$WORK/before.json"; then check 0 "داده پیش از ارتقا بدون تغییر"; else check 1 "داده پیش از ارتقا بدون تغییر"; fi

db() { php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(eval("return " . $argv[1] . ";"), JSON_UNESCAPED_UNICODE);' "$1"; }
check "$([ "$(db 'App\Models\Lead::pluck("intent")->unique()->values()->all()')" = '["interest"]' ] && echo 0 || echo 1)" "سرنخ‌های قبلی «ثبت علاقه» ماندند"
check "$([ "$(db 'App\Models\SystemUpdate::sole()->only("from_version","to_version","method","status")')" = "{\"from_version\":\"1.0.0\",\"to_version\":\"$VERSION_NOW\",\"method\":\"manual\",\"status\":\"completed\"}" ] && echo 0 || echo 1)" "نصب در تاریخچه به‌روزرسانی ثبت شد"
check "$([ "$(db 'App\Models\SmsTemplate::where("key","payment_paid")->value("parameters")["link"]')" = '""' ] && echo 0 || echo 1)" "پارامتر لینک پیامک پرداخت خالی (قالب فعلی SMS.ir بدون تغییر)"

# ---------- ۴. صفحات در وب‌سرور
(cd public && exec php -S "127.0.0.1:$PORT" "$APP/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php" > "$WORK/server.log" 2>&1) &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/up" && break; sleep 0.2; done

status() { curl -s -o /dev/null -w '%{http_code}' -b "$WORK/jar" -c "$WORK/jar" "http://127.0.0.1:$PORT$1"; }
check "$([ "$(status /up)" = 200 ] && echo 0 || echo 1)" "/up پاسخ 200"
LEGACY=$(curl -s "http://127.0.0.1:$PORT/l/legacy")
check "$(grep -q '<h3>ویژگی قدیمی</h3>' <<<"$LEGACY" && echo 0 || echo 1)" "ویژگی متنی نسخه ۱.۰ به‌صورت کارت نمایش داده شد"
check "$(grep -q '۱۲۰٬۰۰۰ تومان' <<<"$LEGACY" && grep -q 'id="preorder-form"' <<<"$LEGACY" && echo 0 || echo 1)" "پیش‌خرید با مبلغ و اعداد فارسی"
check "$(grep -q 'name="intent" value="notify_when_ready"' <<<"$LEGACY" && grep -q 'پیگیری سفارش و ورود' <<<"$LEGACY" && echo 0 || echo 1)" "فرم «خبرم کن» و پاورقی جدید"
check "$([ "$(status /l/empty)" = 200 ] && echo 0 || echo 1)" "صفحه بدون محتوا"

TOKEN=$(curl -s -c "$WORK/jar" "http://127.0.0.1:$PORT/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
curl -s -o /dev/null -b "$WORK/jar" -c "$WORK/jar" --data-urlencode "_token=$TOKEN" --data-urlencode email=owner@upgrade.test --data-urlencode password=Upgrade-pass-2026 "http://127.0.0.1:$PORT/login"
for path in /admin /ideas/1 /admin/leads /admin/preorders /admin/reports/funnel /admin/system /admin/updates /admin/settings/site /landing-pages/1/edit /landing-pages/1/preview; do
    check "$([ "$(status "$path")" = 200 ] && echo 0 || echo 1)" "پنل $path"
done

# ---------- ۵. به‌روزرسانی بعدی با سامانه به‌روزرسانی و بازگرداندن
NEXT=$(php -r '[$a, $b, $c] = explode(".", explode("-", $argv[1])[0]); echo "$a.$b." . ($c + 1);' "$VERSION_NOW")
(cd "$REPO" && php artisan release:build "$NEXT" --output="$WORK/idea-lab-$NEXT.zip" >/dev/null)
mkdir -p storage/app/updates && cp "$WORK/idea-lab-$NEXT.zip" storage/app/updates/
php artisan system:update "storage/app/updates/idea-lab-$NEXT.zip" >/dev/null && php artisan system:finish-update >/dev/null
check "$([ "$(cat VERSION)" = "$NEXT" ] && [ "$(status /up)" = 200 ] && echo 0 || echo 1)" "به‌روزرسانی $NEXT با system:update"
check "$([ -n "$(ls storage/app/backups/*.sql.gz 2>/dev/null)" ] && echo 0 || echo 1)" "پشتیبان خودکار پایگاه داده پیش از به‌روزرسانی"
php artisan system:rollback >/dev/null
check "$([ "$(cat VERSION)" = "$VERSION_NOW" ] && [ "$(status /up)" = 200 ] && [ "$(status /l/legacy)" = 200 ] && echo 0 || echo 1)" "بازگرداندن به $VERSION_NOW"
if php "$SCRIPTS/snapshot.php" compare "$WORK/before.json" >/dev/null; then check 0 "داده پس از به‌روزرسانی و بازگرداندن همچنان بدون تغییر"; else check 1 "داده پس از به‌روزرسانی و بازگرداندن همچنان بدون تغییر"; fi
check "$([ ! -s "$WORK/server.log" ] || ! grep -q 'Fatal\|Exception' "$WORK/server.log" && echo 0 || echo 1)" "بدون خطای PHP در وب‌سرور"

echo
echo "$PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
