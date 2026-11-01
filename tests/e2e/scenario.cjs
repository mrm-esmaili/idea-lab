// سناریوی آزمون عملیاتی انتها‌به‌انتها (مراحل ۹۷ تا ۹۹)؛ با tests/e2e/run.sh اجرا شود.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');

const BASE = process.env.E2E_BASE;
const GW = process.env.E2E_GATEWAY;
const OUT = process.env.E2E_OUT;
const results = [];
let failed = 0;

const check = (ok, label, detail = '') => {
    results.push(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ' — ' + detail : ''}`);
    if (!ok) failed++;
    console.log(results[results.length - 1]);
};

const db = (sql) => execFileSync(process.env.E2E_DB_CLI || 'mariadb', [
    '--default-character-set=utf8mb4', '-h', process.env.DB_HOST, '-P', process.env.DB_PORT, '-u', process.env.DB_USERNAME,
    `-p${process.env.DB_PASSWORD}`, process.env.DB_DATABASE, '-N', '-e', sql,
]).toString().trim();

const gateway = async (path, method = 'GET') => (await fetch(GW + path, { method })).json();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const tomanText = (n) => n.toLocaleString('en-US') + ' تومان';
// صفحات عمومی اعداد فارسی دارند (۱۲۰٬۰۰۰)
const faTomanText = (n) => tomanText(n).replace(/,/g, '٬').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

// ارسال فرمی که پنجره تأیید دارد
const submitConfirmed = async (page, formSelector) => {
    await page.click(`${formSelector} button[type=submit]`);
    await page.waitForSelector('.modal.show');
    await Promise.all([page.waitForNavigation(), page.click('.modal.show [data-confirm-ok]')]);
};
const submit = async (page, selector) => Promise.all([page.waitForNavigation(), page.click(selector)]);
const shot = (page, name) => page.screenshot({ path: `${OUT}/${name}.png`, fullPage: true });

const openPages = [];

(async () => {
    const browser = await chromium.launch();
    browser.on('disconnected', () => {});
    const track = (page) => { openPages.push(page); return page; };
    const admin = track(await browser.newPage({ viewport: { width: 1366, height: 900 } }));
    const jsErrors = [];
    admin.on('pageerror', (e) => jsErrors.push(e.message));

    // ---------- نصب (مرحله ۹۶)
    await admin.goto(`${BASE}/install?token=${process.env.E2E_INSTALL_TOKEN}`);
    await admin.fill('#name', 'مالک آزمون');
    await admin.fill('#email', 'owner@e2e.test');
    await admin.fill('#password', 'E2e-Owner-Pass-2026');
    await admin.fill('#password_confirmation', 'E2e-Owner-Pass-2026');
    await admin.click('button[type=submit]');
    await admin.waitForSelector('.alert-success');
    check(Number(db('SELECT COUNT(*) FROM users WHERE role = "super_admin"')) === 1, 'نصب از مرورگر: migration و ساخت مدیر کل');

    await admin.goto(`${BASE}/login`);
    await admin.fill('#email', 'owner@e2e.test');
    await admin.fill('#password', 'E2e-Owner-Pass-2026');
    await submit(admin, 'button[type=submit]');
    check(admin.url().endsWith('/admin'), 'ورود مدیر کل');

    // ---------- قالب‌های پیامک
    const templates = { otp: 100001, payment_paid: 100002, refund_requested: 100003, refund_approved: 100004, refund_rejected: 100005, refund_executed: 100006 };
    for (const [key, templateId] of Object.entries(templates)) {
        const id = db(`SELECT id FROM sms_templates WHERE \`key\` = '${key}'`);
        await admin.goto(`${BASE}/admin/sms/templates/${id}/edit`);
        await admin.fill('#provider_template_id', String(templateId));
        if (await admin.locator('#enabled').count()) await admin.check('#enabled');
        await submit(admin, 'form button[type=submit].btn-primary');
    }
    check(Number(db('SELECT COUNT(*) FROM sms_templates WHERE provider_template_id IS NOT NULL AND enabled = 1')) === 6, 'تنظیم و فعال‌سازی ۶ قالب پیامک از پنل');

    // ---------- ایده، تنظیمات، شرایط بازپرداخت، صفحه فرود، کمپین (مرحله ۹۷)
    await admin.goto(`${BASE}/ideas/create`);
    await admin.fill('#name', 'باشگاه‌یار آزمون');
    await admin.fill('#description', 'نرم‌افزار نوبت‌دهی باشگاه‌ها');
    await submit(admin, 'form[action$="/ideas"] button[type=submit]');
    const ideaId = admin.url().match(/ideas\/(\d+)/)[1];

    await admin.goto(`${BASE}/ideas/${ideaId}/settings`);
    await admin.fill('#preorder_amount_toman', '۱۲۰٬۰۰۰');
    await admin.fill('#budget_toman', '5000000');
    await submit(admin, 'form button[type=submit].btn-primary');

    await admin.goto(`${BASE}/ideas/${ideaId}/refund-policy`);
    await admin.check('#refundable_yes');
    await admin.fill('#refund_window_days', '14');
    await admin.fill('#terms', 'تا ۱۴ روز پس از پرداخت، کل مبلغ قابل بازپرداخت است.');
    await submitConfirmed(admin, 'form[action$="/refund-policy"]');
    check(db(`SELECT preorder_amount_rial FROM ideas WHERE id = ${ideaId}`) === '1200000', 'مبلغ پیش‌پرداخت با رقم فارسی به ریال ذخیره شد');

    await admin.goto(`${BASE}/ideas/${ideaId}/landing-pages/create`);
    await admin.fill('#name', 'صفحه معرفی');
    await admin.fill('#slug', 'e2e-clubyar');
    await admin.fill('#title', 'نوبت‌دهی آنلاین باشگاه');
    await admin.fill('#description', 'پیش‌خرید با بازپرداخت کامل تا ۱۴ روز');
    await submit(admin, 'form button[type=submit].btn-primary');
    const pageId = db("SELECT id FROM landing_pages WHERE slug = 'e2e-clubyar'");
    check(db(`SELECT is_active FROM landing_pages WHERE id = ${pageId}`) === '1', 'ساخت و انتشار صفحه فرود');

    await admin.goto(`${BASE}/admin/campaigns/create`);
    await admin.selectOption('#landing_page_id', pageId);
    await admin.fill('#name', 'دیوار آزمون');
    await admin.fill('#slug', 'divar-e2e');
    await admin.fill('#source', 'divar');
    await admin.fill('#medium', 'paid');
    await admin.selectOption('#status', 'running');
    await submit(admin, 'form button[type=submit].btn-primary');
    const trackingUrl = await admin.inputValue('#tracking-url');
    const campaignId = admin.url().match(/campaigns\/(\d+)/)[1];
    check(trackingUrl.includes('utm_campaign=divar-e2e'), 'ساخت کمپین و لینک رهگیری');

    await admin.goto(`${BASE}/admin/expenses/create`);
    await admin.selectOption('#campaign_id', campaignId);
    await admin.fill('#amount_toman', '1,000,000');
    await admin.fill('#description', 'آگهی دیوار هفته اول');
    await submit(admin, 'form[action$="/admin/expenses"] button[type=submit]');
    check(db(`SELECT amount_rial FROM expenses WHERE campaign_id = ${campaignId}`) === '10000000', 'ثبت هزینه کمپین');

    // ---------- مشتری: بازدید از لینک کمپین، علاقه‌مندی، پیش‌خرید و پرداخت
    const customerCtx = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const customer = track(await customerCtx.newPage());
    customer.on('pageerror', (e) => jsErrors.push('customer: ' + e.message));
    const MOBILE = '09125550001';

    await customer.goto(trackingUrl);
    await sleep(3500);
    await customer.fill('#lead-name', 'سارا مشتری');
    await customer.fill('#lead-phone', '۰۹۱۲۵۵۵۰۰۰۱');
    await customer.check('form[action$="/leads"] input[name=consent]');
    await customer.check('form[action$="/leads"] input[name=marketing_consent]');
    await submit(customer, 'form[action$="/leads"] button[type=submit]');
    check(db(`SELECT campaign FROM leads WHERE phone = '${MOBILE}'`) === 'divar-e2e', 'ثبت «خبرم کن» با شماره فارسی و انتساب کمپین');
    check(db(`SELECT intent FROM leads WHERE phone = '${MOBILE}'`) === 'notify_when_ready', 'نوع درخواست «خبرم کن» ثبت شد');
    check(db(`SELECT marketing_sms_consent FROM contacts WHERE mobile = '${MOBILE}'`) === '1', 'ثبت رضایت پیامک تبلیغاتی');

    await sleep(3500);
    await customer.fill('#preorder-name', 'سارا مشتری');
    await customer.fill('#preorder-phone', MOBILE);
    await customer.check('form[action$="/preorders"] input[name=consent]');
    await customer.check('form[action$="/preorders"] input[name=accept_terms]');
    await submit(customer, 'form[action$="/preorders"] button[type=submit]');
    check(customer.url().includes('/checkout'), 'ثبت پیش‌خرید و ورود به صفحه پرداخت');
    await shot(customer, '01-checkout');

    await submit(customer, 'form[action$="/pay"] button[type=submit]');
    check(customer.url().startsWith(GW + '/pg/StartPay/'), 'انتقال به درگاه');
    check((await customer.textContent('#amount')) === '1200000', 'مبلغ ارسالی به درگاه به ریال', await customer.textContent('#amount'));
    const callbackUrl = await customer.getAttribute('#pay', 'href');
    await submit(customer, '#pay');
    check(customer.url().includes('/receipt'), 'بازگشت از درگاه و نمایش رسید');
    check((await customer.content()).includes(faTomanText(120000)), 'مبلغ رسید به تومان (اعداد فارسی)');
    await shot(customer, '02-receipt');

    // تکرار بازگشت از درگاه (رفرش یا حمله تکرار)
    await customer.goto(callbackUrl);
    await sleep(1000);
    check(db("SELECT COUNT(*) FROM payments WHERE status = 'paid'") === '1', 'تکرار callback پرداخت دوم نمی‌سازد');
    check(db(`SELECT payment_status FROM preorders WHERE phone = '${MOBILE}'`) === 'paid', 'وضعیت سفارش پرداخت‌شده');

    let gw = await gateway('/__state');
    const paidSms = gw.sms.filter((m) => m.templateId === templates.payment_paid);
    check(paidSms.length === 1 && paidSms[0].mobile === MOBILE && paidSms[0].parameters.AMOUNT === '120,000', 'پیامک پرداخت موفق، یک بار، با مبلغ تومان', JSON.stringify(paidSms[0]?.parameters));

    // ---------- حساب مشتری و درخواست بازپرداخت (مرحله ۹۸)
    await customer.goto(`${BASE}/account/login`);
    await customer.fill('#mobile', MOBILE);
    await submit(customer, 'form button[type=submit]');
    gw = await gateway('/__state');
    const otp = gw.sms.filter((m) => m.templateId === templates.otp).pop();
    check(Boolean(otp?.parameters?.CODE), 'ارسال کد ورود از SMS.ir');
    check(db("SELECT parameters FROM sms_messages WHERE template_key = 'otp' ORDER BY id DESC LIMIT 1").includes('***'), 'کد ورود در سابقه پیامک ذخیره نمی‌شود');
    await customer.fill('#code', otp.parameters.CODE);
    await submit(customer, 'form[action$="/account/verify"] button[type=submit]');
    check(customer.url().endsWith('/account'), 'ورود مشتری با کد یک‌بارمصرف');

    // تا ۲۴ ساعت پس از پرداخت درخواست آنلاین ممکن نیست (نسخه ۱.۱.۱)
    check((await customer.content()).includes('ساعت پس از پرداخت ممکن است'), 'بازپرداخت در ۲۴ ساعت اول پس از پرداخت بسته است');
    db(`UPDATE payments SET verified_at = verified_at - INTERVAL 25 HOUR WHERE preorder_id = (SELECT id FROM preorders WHERE phone = '${MOBILE}')`);
    await customer.reload();

    await customer.click('details summary:has-text("درخواست بازپرداخت")');
    await customer.fill('textarea[name=reason]', 'زمان راه‌اندازی برای ما مناسب نیست.');
    await customer.check('form[action*="/refund"] input[name=confirm]');
    let refundDialog = '';
    customer.once('dialog', (dialog) => { refundDialog = dialog.message(); dialog.accept(); });
    await submit(customer, 'form[action*="/refund"] button[type=submit]');
    check(refundDialog.includes('مطمئن'), 'هشدار تأیید پیش از ثبت بازپرداخت');
    const requestId = db('SELECT id FROM refund_requests ORDER BY id DESC LIMIT 1');
    check(db(`SELECT status FROM refund_requests WHERE id = ${requestId}`) === 'pending', 'ثبت درخواست بازپرداخت توسط مشتری');
    await shot(customer, '03-account-refund');

    await admin.goto(`${BASE}/admin/refunds/${requestId}`);
    await admin.fill('#approve-amount', '100,000');
    await admin.fill('#approve-note', 'کسر هزینه راه‌اندازی');
    await submitConfirmed(admin, 'form[action$="/approve"]');
    check(db(`SELECT status FROM refund_requests WHERE id = ${requestId}`) === 'approved', 'تأیید بخشی (۱۰۰ از ۱۲۰ هزار تومان)');
    check(db(`SELECT payment_status FROM preorders WHERE phone = '${MOBILE}'`) === 'paid', 'تأیید به‌تنهایی وضعیت سفارش را بازپرداخت‌شده نمی‌کند');

    const execute = async (amount, reference) => {
        await admin.goto(`${BASE}/admin/refunds/${requestId}`);
        await admin.fill('#exec-amount', amount);
        await admin.fill('#exec-ref', reference);
        await submitConfirmed(admin, 'form[action$="/execute"]');
    };
    await execute('60,000', 'PAYA-E2E-1');
    await execute('40,000', 'PAYA-E2E-1');
    check((await admin.content()).includes('همین مرجع'), 'مرجع تکراری رد شد');
    await execute('50,000', 'PAYA-E2E-2');
    check((await admin.content()).includes('بیشتر'), 'اجرای بیش از مبلغ تأییدشده رد شد');
    await execute('40,000', 'PAYA-E2E-2');
    check(db(`SELECT status FROM refund_requests WHERE id = ${requestId}`) === 'completed', 'درخواست پس از اجرای کامل «انجام‌شده»');
    check(db('SELECT SUM(amount_rial) FROM refunds') === '1000000', 'جمع بازپرداخت‌ها دقیقاً ۱۰۰ هزار تومان');
    check(db(`SELECT payment_status FROM preorders WHERE phone = '${MOBILE}'`) === 'partially_refunded', 'وضعیت سفارش: بخشی بازپرداخت‌شده');
    check(Number(db("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'refund.%'")) === 4, 'سابقه ممیزی: درخواست، تأیید، دو اجرا');
    await shot(admin, '04-refund');

    gw = await gateway('/__state');
    const refundSms = gw.sms.filter((m) => [templates.refund_requested, templates.refund_approved, templates.refund_executed].includes(m.templateId));
    check(refundSms.map((m) => m.templateId).join(',') === [templates.refund_requested, templates.refund_approved, templates.refund_executed, templates.refund_executed].join(','), 'پیامک‌های جداگانه دریافت، تأیید و هر واریز', refundSms.map((m) => m.parameters.AMOUNT).join(' / '));

    // ---------- گزارش‌ها
    await admin.goto(`${BASE}/admin/finance`);
    const finance = await admin.content();
    check(finance.includes(tomanText(120000)) && finance.includes(tomanText(100000)), 'گزارش مالی: دریافت ۱۲۰ و بازپرداخت ۱۰۰ هزار تومان');
    await admin.goto(`${BASE}/admin/reports/funnel?idea=${ideaId}`);
    const funnel = await admin.locator('.progress').count();
    check(funnel === 4 && (await admin.content()).includes('100.00% بازدیدکنندگان'), 'قیف تبدیل: ۱ بازدیدکننده ← ۱ سرنخ ← ۱ پیش‌خرید ← ۱ پرداخت');
    await admin.goto(`${BASE}/admin/campaigns/compare?campaigns[]=${campaignId}`);
    check((await admin.content()).includes(tomanText(1000000)), 'مقایسه کمپین: هزینه واقعی ثبت‌شده');
    await shot(admin, '05-funnel');

    // ---------- SMS.ir: ارسال گروهی و خطاهای ارائه‌دهنده (مرحله ۹۹)
    await admin.goto(`${BASE}/admin/sms/broadcasts/create?ideas[]=${ideaId}`);
    await admin.fill('#title', 'اطلاع‌رسانی آزمایشی');
    await admin.fill('#body', 'باشگاه‌یار به‌زودی عرضه می‌شود.');
    await submit(admin, 'form[action$="/admin/sms/broadcasts"] button[type=submit]');
    await admin.check('#confirm');
    await submit(admin, 'form[action$="/confirm"] button[type=submit]');
    await sleep(1000);
    gw = await gateway('/__state');
    const bulk = gw.sms.filter((m) => m.type === 'bulk');
    check(bulk.length === 1 && bulk[0].mobile === MOBILE && bulk[0].text.endsWith('لغو۱۱') && bulk[0].lineNumber === 30001234, 'ارسال گروهی تبلیغاتی آزمایشی از خط اختصاصی با «لغو۱۱»');

    await gateway('/__sms/mode?m=reject', 'POST');
    const other = await (await browser.newContext()).newPage();
    await other.goto(`${BASE}/account/login`);
    await other.fill('#mobile', '09125550002');
    await submit(other, 'form button[type=submit]');
    const failedOtp = db("SELECT CONCAT(status, '|', error) FROM sms_messages WHERE mobile = '09125550002' ORDER BY id DESC LIMIT 1");
    check(failedOtp.startsWith('failed|') && failedOtp.includes('اعتبار کافی نیست'), 'رد ارائه‌دهنده (اعتبار ناکافی) ثبت و به کاربر خطا نمایش داده شد', failedOtp);
    check(other.url().includes('/account/login'), 'کاربر وارد مرحله کد نمی‌شود وقتی پیامک ارسال نشده');

    await gateway('/__sms/mode?m=down', 'POST');
    const contactId = db(`SELECT id FROM contacts WHERE mobile = '${MOBILE}'`);
    await admin.goto(`${BASE}/admin/contacts/${contactId}`);
    await admin.fill('#sms-body', 'پیگیری سفارش شما انجام شد.');
    await submitConfirmed(admin, 'form[action$="/sms"]');
    await sleep(1000);
    const retry = db("SELECT CONCAT(status, '|', attempts) FROM sms_messages WHERE type = 'text' AND category = 'transactional' ORDER BY id DESC LIMIT 1");
    check(retry === 'queued|1', 'خطای گذرای ارائه‌دهنده: پیام برای تلاش دوباره در صف ماند', retry);
    await gateway('/__sms/mode?m=ok', 'POST');

    // ---------- سلامت
    const health = await fetch(`${BASE}/up`);
    check(health.status === 200, 'پایش سلامت /up');
    check(jsErrors.length === 0, 'بدون خطای جاوااسکریپت', jsErrors.join(' | '));

    await browser.close();
    console.log(`\n${results.length - failed} passed, ${failed} failed`);
    process.exit(failed ? 1 : 0);
})().catch(async (e) => {
    console.error(e);
    // تصویر همه صفحات باز برای تشخیص خطا
    for (const [i, page] of openPages.entries()) {
        await page.screenshot({ path: `${OUT}/failure-${i}.png`, fullPage: true }).catch(() => {});
        console.error(`page ${i}: ${page.url()}`);
    }
    process.exit(1);
});
