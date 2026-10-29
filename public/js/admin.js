/*
 * تأیید عملیات حساس: هر فرم با data-confirm پیش از ارسال یک پنجره تأیید
 * Bootstrap نشان می‌دهد. در متن، {name} با مقدار فیلد همان فرم جایگزین
 * می‌شود (برای select و radio برچسب گزینه انتخاب‌شده) و در <bdi> بدون شکست خط
 * درج می‌شود تا مقدار لاتین مثل «PAYA-12» در متن فارسی به‌هم نریزد. data-confirm-variant
 * رنگ دکمه تأیید را تعیین می‌کند (پیش‌فرض primary؛ danger برای حذف/رد).
 */
(() => {
    'use strict';

    let modal;
    let pending;

    const ensureModal = () => {
        if (modal) return modal;
        const el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.setAttribute('aria-labelledby', 'confirm-title');
        el.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="confirm-title">تأیید عملیات</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                    </div>
                    <div class="modal-body" style="white-space: pre-line"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn" data-confirm-ok>تأیید</button>
                    </div>
                </div>
            </div>`;
        document.body.append(el);
        el.querySelector('[data-confirm-ok]').addEventListener('click', () => {
            const { form, submitter } = pending;
            form.dataset.confirmed = '1';
            bootstrap.Modal.getInstance(el).hide();
            form.requestSubmit(submitter || undefined);
        });
        modal = el;
        return el;
    };

    const fieldText = (form, name) => {
        const fields = form.elements.namedItem(name);
        if (!fields) return '';
        const field = fields instanceof RadioNodeList ? [...fields].find((f) => f.checked) : fields;
        if (!field) return '—';
        if (field.tagName === 'SELECT') return field.selectedOptions[0]?.textContent.trim() ?? '';
        if (field.type === 'radio' || field.type === 'checkbox') return field.labels?.[0]?.textContent.trim() ?? field.value;
        // انتخابگر شمسی: متن نمایشی فیلد کنار فیلد پنهان
        if (field.type === 'hidden' && field.previousElementSibling?.matches('.jdp-wrapper > input[type=text]')) return field.previousElementSibling.value;
        return field.value.trim() || '—';
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
        if (form.dataset.confirmed) {
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        if (!form.reportValidity()) return;

        const el = ensureModal();
        pending = { form, submitter: event.submitter };
        const body = el.querySelector('.modal-body');
        body.replaceChildren();
        form.dataset.confirm.split(/(\{[\w.\-\[\]]+\})/).forEach((part) => {
            const token = part.match(/^\{(.+)\}$/);
            if (!token) return body.append(part);
            const value = document.createElement('bdi');
            value.className = 'fw-semibold text-nowrap';
            value.textContent = fieldText(form, token[1]);
            body.append(value);
        });
        const ok = el.querySelector('[data-confirm-ok]');
        ok.className = `btn btn-${form.dataset.confirmVariant || 'primary'}`;
        ok.textContent = form.dataset.confirmLabel || 'تأیید';
        const instance = bootstrap.Modal.getOrCreateInstance(el);
        el.addEventListener('shown.bs.modal', () => ok.focus(), { once: true });
        instance.show();
    }, true);
})();
