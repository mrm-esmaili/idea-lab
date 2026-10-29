// درگاه زرین‌پال و SMS.ir شبیه‌سازی‌شده برای آزمون انتها‌به‌انتها (بدون وابستگی).
// برنامه با درخواست HTTP واقعی به این سرور وصل می‌شود؛ فقط قراردادهای API
// که برنامه استفاده می‌کند پیاده شده است.
//
//   node tests/e2e/fake-gateways.mjs [port=8200]
//
// زرین‌پال:  POST /pg/v4/payment/request.json ، POST /pg/v4/payment/verify.json ، GET /pg/StartPay/{authority}
// SMS.ir:    POST /v1/send/verify ، POST /v1/send/bulk  (سرآیند X-API-KEY باید e2e-sms-key باشد)
// کنترل:     GET /__state ، POST /__sms/mode?m=ok|reject|down ، POST /__reset
import http from 'node:http';

const port = Number(process.argv[2] || 8200);
const MERCHANT = 'e2e-merchant-0000000000000000000000';
const SMS_KEY = 'e2e-sms-key';

let state;
const reset = () => {
    state = { payments: {}, sms: [], smsMode: 'ok', counter: 0 };
};
reset();

const json = (res, status, body) => {
    res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify(body));
};

const readBody = (req) => new Promise((resolve) => {
    let data = '';
    req.on('data', (chunk) => (data += chunk));
    req.on('end', () => {
        try { resolve(data ? JSON.parse(data) : {}); } catch { resolve({}); }
    });
});

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, `http://127.0.0.1:${port}`);
    const body = req.method === 'POST' ? await readBody(req) : {};

    // ---- کنترل آزمون
    if (url.pathname === '/__state') return json(res, 200, state);
    if (url.pathname === '/__reset') { reset(); return json(res, 200, { ok: true }); }
    if (url.pathname === '/__sms/mode') { state.smsMode = url.searchParams.get('m') || 'ok'; return json(res, 200, { mode: state.smsMode }); }

    // ---- زرین‌پال
    if (url.pathname === '/pg/v4/payment/request.json') {
        if (body.merchant_id !== MERCHANT) return json(res, 400, { data: [], errors: { code: -9, message: 'The input params invalid.' } });
        const authority = 'A' + String(++state.counter).padStart(35, '0');
        state.payments[authority] = { amount: body.amount, callback: body.callback_url, mobile: body.metadata?.mobile, verified: 0, status: null };
        return json(res, 200, { data: { code: 100, message: 'Success', authority, fee_type: 'Merchant', fee: 0 }, errors: [] });
    }

    if (url.pathname.startsWith('/pg/StartPay/')) {
        const authority = url.pathname.split('/').pop();
        const payment = state.payments[authority];
        if (!payment) { res.writeHead(404); return res.end('unknown authority'); }
        const back = (status) => `${payment.callback}${payment.callback.includes('?') ? '&' : '?'}Authority=${authority}&Status=${status}`;
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        return res.end(`<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>درگاه آزمایشی</title>
<body style="font-family:sans-serif;padding:2rem"><h1>درگاه پرداخت آزمایشی</h1>
<p>مبلغ: <span id="amount">${payment.amount}</span> ریال</p>
<a id="pay" href="${back('OK')}">پرداخت</a> | <a id="cancel" href="${back('NOK')}">انصراف</a></body></html>`);
    }

    if (url.pathname === '/pg/v4/payment/verify.json') {
        const payment = state.payments[body.authority];
        if (!payment || body.merchant_id !== MERCHANT) return json(res, 400, { data: [], errors: { code: -54, message: 'Invalid authority.' } });
        if (Number(body.amount) !== Number(payment.amount)) return json(res, 400, { data: [], errors: { code: -50, message: 'Amount mismatch.' } });
        payment.verified++;
        payment.refId ??= 900000 + state.counter;
        // تأیید دوباره همان تراکنش: کد 101
        return json(res, 200, { data: { code: payment.verified === 1 ? 100 : 101, message: 'Verified', card_hash: 'SECRET-CARD-HASH', card_pan: '603799******1234', ref_id: payment.refId, fee_type: 'Merchant', fee: 0 }, errors: [] });
    }

    // ---- SMS.ir
    if (url.pathname === '/v1/send/verify' || url.pathname === '/v1/send/bulk') {
        if (req.headers['x-api-key'] !== SMS_KEY) return json(res, 401, { status: 0, message: 'کلید API نامعتبر است', data: null });
        if (state.smsMode === 'down') return json(res, 503, { status: 0, message: 'Service unavailable' });
        if (state.smsMode === 'reject') return json(res, 400, { status: 0, message: 'اعتبار کافی نیست', data: null });

        if (url.pathname === '/v1/send/verify') {
            const id = 7000000 + state.sms.length + 1;
            state.sms.push({ type: 'verify', id, mobile: body.mobile, templateId: body.templateId, parameters: Object.fromEntries((body.parameters || []).map((p) => [p.name, p.value])) });
            return json(res, 200, { status: 1, message: 'موفق', data: { messageId: id, cost: 1.5 } });
        }

        const ids = (body.mobiles || []).map((mobile) => {
            const id = 8000000 + state.sms.length + 1;
            state.sms.push({ type: 'bulk', id, mobile, lineNumber: body.lineNumber, text: body.messageText });
            return id;
        });
        return json(res, 200, { status: 1, message: 'موفق', data: { packId: 'pack-' + ids[0], messageIds: ids, cost: 2 * ids.length } });
    }

    json(res, 404, { error: 'not found' });
});

server.listen(port, '127.0.0.1', () => console.log(`fake gateways on http://127.0.0.1:${port}`));
