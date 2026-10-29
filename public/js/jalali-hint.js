// معادل شمسی زیر هر ورودی تاریخ (ورودی‌ها میلادی ارسال می‌شوند)
(() => {
    const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long', timeZone: 'UTC' });

    document.querySelectorAll('input[type=date]').forEach((input) => {
        const hint = document.createElement('div');
        hint.className = 'form-text jalali-hint';
        hint.setAttribute('aria-live', 'polite');
        (input.closest('.input-group') || input).insertAdjacentElement('afterend', hint);

        const update = () => {
            const [y, m, d] = (input.value || '').split('-').map(Number);
            if (!(y && m && d)) {
                hint.textContent = '';
                return;
            }
            const parts = Object.fromEntries(formatter.formatToParts(new Date(Date.UTC(y, m - 1, d, 12))).map((p) => [p.type, p.value]));
            hint.textContent = `${parts.weekday} ${parts.day} ${parts.month} ${parts.year}`;
        };

        input.addEventListener('input', update);
        input.addEventListener('change', update);
        update();
    });
})();
