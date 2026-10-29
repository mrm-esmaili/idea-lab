/*
 * انتخابگر تاریخ شمسی بدون وابستگی.
 *
 * هر <input type="date"> (مگر با data-native) به یک فیلد متنی شمسی و یک
 * فیلد پنهان با همان name تبدیل می‌شود؛ سرور همچنان تاریخ میلادی
 * YYYY-MM-DD دریافت می‌کند و بدون جاوااسکریپت ورودی عادی تاریخ کار می‌کند.
 * min و max ورودی اصلی رعایت می‌شود. تایپ مستقیم «1405/08/07» با رقم
 * فارسی یا لاتین هم پذیرفته است.
 */
(() => {
    'use strict';

    const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    const WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    const div = (a, b) => Math.trunc(a / b);
    const fa = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const latin = (value) => String(value).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    const pad = (n) => String(n).padStart(2, '0');

    function cal(jy) {
        const gy = jy + 621;
        let leapJ = -14;
        let jp = BREAKS[0];
        let jump = 0;
        for (let i = 1; i < BREAKS.length; i++) {
            const jm = BREAKS[i];
            jump = jm - jp;
            if (jy < jm) break;
            leapJ += div(jump, 33) * 8 + div(jump % 33, 4);
            jp = jm;
        }
        let n = jy - jp;
        leapJ += div(n, 33) * 8 + div((n % 33) + 3, 4);
        if (jump % 33 === 4 && jump - n === 4) leapJ++;
        const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
        const march = 20 + leapJ - leapG;
        if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
        let leap = (((n + 1) % 33) - 1) % 4;
        if (leap === -1) leap = 4;
        return { leap, gy, march };
    }

    function g2d(gy, gm, gd) {
        const d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * ((gm + 9) % 12) + 2, 5) + gd - 34840408;
        return d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    }

    function d2g(jdn) {
        let j = 4 * jdn + 139361631;
        j += div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        const i = div(j % 1461, 4) * 5 + 308;
        const gd = div(i % 153, 5) + 1;
        const gm = (div(i, 153) % 12) + 1;
        const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
        return [gy, gm, gd];
    }

    function j2d(jy, jm, jd) {
        const r = cal(jy);
        return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
    }

    function d2j(jdn) {
        const gy = d2g(jdn)[0];
        let jy = gy - 621;
        const r = cal(jy);
        let k = jdn - g2d(gy, 3, r.march);
        if (k >= 0) {
            if (k <= 185) return [jy, 1 + div(k, 31), (k % 31) + 1];
            k -= 186;
        } else {
            jy--;
            k += 179;
            if (r.leap === 1) k++;
        }
        return [jy, 7 + div(k, 30), (k % 30) + 1];
    }

    const toJalali = (iso) => {
        const [y, m, d] = iso.split('-').map(Number);
        return d2j(g2d(y, m, d));
    };
    const toIso = (jy, jm, jd) => d2g(j2d(jy, jm, jd)).map((v, i) => (i ? pad(v) : String(v))).join('-');
    const monthLength = (jy, jm) => (jm <= 6 ? 31 : jm <= 11 ? 30 : cal(jy).leap === 0 ? 30 : 29);
    const todayIso = () => {
        const now = new Date();
        return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    };
    const display = (iso) => {
        if (!iso) return '';
        const [y, m, d] = toJalali(iso);
        return `${y}/${pad(m)}/${pad(d)}`;
    };
    const parse = (text) => {
        const match = latin(text).trim().match(/^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$/);
        if (!match) return null;
        const [jy, jm, jd] = match.slice(1).map(Number);
        if (jm < 1 || jm > 12 || jd < 1 || jd > monthLength(jy, jm)) return null;
        return toIso(jy, jm, jd);
    };

    let openPicker = null;

    function enhance(original) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = original.name;
        hidden.value = original.value;

        const text = document.createElement('input');
        text.type = 'text';
        text.id = original.id || '';
        text.className = original.className;
        text.required = original.required;
        text.disabled = original.disabled;
        text.inputMode = 'numeric';
        text.autocomplete = 'off';
        text.placeholder = '1405/01/01';
        text.setAttribute('dir', 'ltr');
        text.setAttribute('aria-haspopup', 'dialog');
        ['aria-label', 'aria-describedby', 'aria-invalid'].forEach((a) => original.hasAttribute(a) && text.setAttribute(a, original.getAttribute(a)));
        text.value = display(original.value);

        const min = original.min || null;
        const max = original.max || null;

        const wrapper = document.createElement('div');
        wrapper.className = 'jdp-wrapper';
        // نمایش پیام خطای Bootstrap (.is-invalid ~ .invalid-feedback)
        if (original.classList.contains('is-invalid')) wrapper.classList.add('is-invalid');
        original.replaceWith(wrapper);
        wrapper.append(text, hidden);

        const setValue = (iso) => {
            hidden.value = iso || '';
            text.value = display(iso);
            text.classList.remove('jdp-invalid');
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };

        text.addEventListener('change', () => {
            if (text.value.trim() === '') return setValue('');
            const iso = parse(text.value);
            if (iso && (!min || iso >= min) && (!max || iso <= max)) {
                setValue(iso);
            } else {
                text.classList.add('jdp-invalid');
                text.setCustomValidity('تاریخ شمسی معتبر وارد کنید، مثلاً 1405/08/07');
                text.reportValidity();
                text.setCustomValidity('');
            }
        });

        const open = () => {
            if (text.disabled) return;
            if (openPicker) openPicker.close();
            const base = hidden.value || (max && todayIso() > max ? max : min && todayIso() < min ? min : todayIso());
            let [vy, vm] = toJalali(base);

            const panel = document.createElement('div');
            panel.className = 'jdp-panel shadow';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-label', 'انتخاب تاریخ شمسی');

            const close = () => {
                panel.remove();
                document.removeEventListener('mousedown', outside);
                openPicker = null;
            };
            const outside = (e) => {
                if (!wrapper.contains(e.target)) close();
            };

            const render = () => {
                panel.innerHTML = '';
                const head = document.createElement('div');
                head.className = 'jdp-head';
                const nav = (label, delta, aria) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'btn btn-sm btn-light';
                    b.textContent = label;
                    b.setAttribute('aria-label', aria);
                    b.addEventListener('click', () => {
                        vm += delta;
                        if (vm < 1) { vm = 12; vy--; }
                        if (vm > 12) { vm = 1; vy++; }
                        render();
                    });
                    return b;
                };
                const title = document.createElement('div');
                title.className = 'jdp-title';
                const month = document.createElement('select');
                month.className = 'form-select form-select-sm';
                month.setAttribute('aria-label', 'ماه');
                MONTHS.forEach((name, i) => month.add(new Option(name, i + 1, false, i + 1 === vm)));
                month.addEventListener('change', () => { vm = Number(month.value); render(); });
                const year = document.createElement('input');
                year.type = 'number';
                year.className = 'form-control form-control-sm';
                year.value = vy;
                year.setAttribute('aria-label', 'سال');
                year.addEventListener('change', () => { const y = Number(latin(year.value)); if (y > 1300 && y < 1500) { vy = y; render(); } });
                title.append(month, year);
                head.append(nav('›', -1, 'ماه قبل'), title, nav('‹', 1, 'ماه بعد'));
                panel.append(head);

                const grid = document.createElement('div');
                grid.className = 'jdp-grid';
                WEEKDAYS.forEach((w) => {
                    const c = document.createElement('div');
                    c.className = 'jdp-weekday';
                    c.textContent = w;
                    grid.append(c);
                });
                // روز هفته اول ماه؛ شنبه = 0
                const firstIso = toIso(vy, vm, 1);
                const offset = (new Date(`${firstIso}T12:00:00`).getDay() + 1) % 7;
                for (let i = 0; i < offset; i++) grid.append(document.createElement('div'));
                const today = todayIso();
                for (let d = 1; d <= monthLength(vy, vm); d++) {
                    const iso = toIso(vy, vm, d);
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'jdp-day';
                    b.textContent = fa(d);
                    b.dataset.iso = iso;
                    b.setAttribute('aria-label', `${fa(d)} ${MONTHS[vm - 1]} ${fa(vy)}`);
                    if (iso === hidden.value) b.classList.add('active');
                    if (iso === today) b.classList.add('today');
                    if ((min && iso < min) || (max && iso > max)) b.disabled = true;
                    b.addEventListener('click', () => { setValue(iso); close(); text.focus(); });
                    grid.append(b);
                }
                panel.append(grid);

                const foot = document.createElement('div');
                foot.className = 'jdp-foot';
                const action = (label, fn, cls) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = `btn btn-sm ${cls}`;
                    b.textContent = label;
                    b.addEventListener('click', fn);
                    return b;
                };
                const todayAllowed = (!min || today >= min) && (!max || today <= max);
                if (todayAllowed) foot.append(action('امروز', () => { setValue(today); close(); }, 'btn-outline-primary'));
                if (!text.required) foot.append(action('پاک کردن', () => { setValue(''); close(); }, 'btn-outline-secondary'));
                foot.append(action('بستن', close, 'btn-link'));
                panel.append(foot);
            };

            render();
            wrapper.append(panel);
            document.addEventListener('mousedown', outside);
            openPicker = { close };
            (panel.querySelector('.jdp-day.active') || panel.querySelector('.jdp-day.today:not(:disabled)'))?.focus({ preventScroll: true });
        };

        text.addEventListener('click', open);
        text.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || (e.altKey && e.key === 'ArrowDown')) { e.preventDefault(); open(); }
        });
        wrapper.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && openPicker) { openPicker.close(); text.focus(); }
        });
    }

    window.JalaliDatepicker = { toJalali, toIso, display, parse };

    document.querySelectorAll('input[type=date]:not([data-native])').forEach(enhance);
})();
