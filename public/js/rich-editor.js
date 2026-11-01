/*
 * ویرایشگر متن ساده و بدون وابستگی برای پنل (نسخه ۱.۱.۱).
 * هر <textarea data-rich-editor> به یک ناحیه قابل ویرایش با نوار ابزار تبدیل
 * می‌شود و HTML تمیزشده را در همان textarea نگه می‌دارد. پاک‌سازی نهایی و
 * قطعی همیشه سمت سرور (App\Support\RichText) انجام می‌شود؛ این پاک‌سازی فقط
 * برای پیش‌نمایش درست و چسباندن متن از Word یا سایت‌های دیگر است.
 */
(function () {
    'use strict';

    const ALLOWED = ['P', 'BR', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'LI', 'HR', 'BLOCKQUOTE', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'A'];
    const DROP = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'MATH', 'TEMPLATE', 'NOSCRIPT', 'HEAD', 'TITLE', 'META', 'LINK', 'FORM', 'INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'IMG', 'VIDEO', 'AUDIO', 'CANVAS'];
    const SAFE_HREF = /^(https?:\/\/|mailto:|tel:)[^\s"'<>]*$/i;

    function cleanNode(node) {
        Array.from(node.childNodes).forEach(function (child) {
            if (child.nodeType === Node.TEXT_NODE) {
                return;
            }

            if (child.nodeType !== Node.ELEMENT_NODE || DROP.includes(child.tagName)) {
                child.remove();
                return;
            }

            cleanNode(child);

            if (!ALLOWED.includes(child.tagName)) {
                const block = ['DIV', 'SECTION', 'ARTICLE'].includes(child.tagName) && child.childNodes.length;
                while (child.firstChild) {
                    node.insertBefore(child.firstChild, child);
                }
                if (block) {
                    node.insertBefore(document.createElement('br'), child);
                }
                child.remove();
                return;
            }

            const href = child.tagName === 'A' ? (child.getAttribute('href') || '').trim() : null;
            Array.from(child.attributes).forEach(function (attribute) {
                child.removeAttribute(attribute.name);
            });

            if (child.tagName === 'A') {
                if (href && SAFE_HREF.test(href)) {
                    child.setAttribute('href', href);
                } else {
                    while (child.firstChild) {
                        node.insertBefore(child.firstChild, child);
                    }
                    child.remove();
                }
            }

            // بند کاملاً خالی حذف می‌شود؛ بند دارای <br> عمداً خط خالی است
            if (child.isConnected && !['BR', 'HR'].includes(child.tagName) && child.textContent.trim() === '' && !child.querySelector('br, hr')) {
                child.remove();
            }
        });
    }

    function clean(html) {
        const doc = new DOMParser().parseFromString('<body>' + (html || '') + '</body>', 'text/html');
        cleanNode(doc.body);
        const result = doc.body.innerHTML.trim();

        // فقط تگ خالی یعنی متنی وارد نشده است
        return doc.body.textContent.trim() === '' && !doc.body.querySelector('hr') ? '' : result;
    }

    function escapeText(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\r?\n/g, '<br>');
    }

    const BLOCKS = [
        ['p', 'متن عادی'],
        ['h1', 'تیتر ۱'], ['h2', 'تیتر ۲'], ['h3', 'تیتر ۳'],
        ['h4', 'تیتر ۴'], ['h5', 'تیتر ۵'], ['h6', 'تیتر ۶'],
        ['blockquote', 'نقل قول'],
    ];

    const BUTTONS = [
        ['bold', 'bi-type-bold', 'پررنگ (Ctrl+B)'],
        ['italic', 'bi-type-italic', 'کج (Ctrl+I)'],
        ['underline', 'bi-type-underline', 'زیرخط (Ctrl+U)'],
        ['strikeThrough', 'bi-type-strikethrough', 'خط‌خورده'],
        ['|'],
        ['insertUnorderedList', 'bi-list-ul', 'فهرست نقطه‌ای'],
        ['insertOrderedList', 'bi-list-ol', 'فهرست شماره‌دار'],
        ['quote', 'bi-quote', 'نقل قول'],
        ['insertHorizontalRule', 'bi-dash-lg', 'خط جداکننده (hr)'],
        ['lineBreak', 'bi-arrow-return-left', 'شکستن خط (br) — یا Shift+Enter'],
        ['|'],
        ['link', 'bi-link-45deg', 'پیوند'],
        ['unlink', 'bi-link', 'حذف پیوند'],
        ['clear', 'bi-eraser', 'حذف قالب‌بندی'],
        ['|'],
        ['source', 'bi-code-slash', 'نمایش و ویرایش HTML'],
    ];

    function init(textarea) {
        const compact = textarea.dataset.richEditor === 'compact';
        const wrapper = document.createElement('div');
        wrapper.className = 'rich-editor' + (compact ? ' rich-editor-compact' : '');

        const toolbar = document.createElement('div');
        toolbar.className = 'rich-editor-toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'ابزار قالب‌بندی');

        const blockSelect = document.createElement('select');
        blockSelect.className = 'form-select form-select-sm';
        blockSelect.setAttribute('aria-label', 'نوع بند');
        BLOCKS.forEach(function ([tag, label]) {
            blockSelect.add(new Option(label, tag));
        });
        toolbar.appendChild(blockSelect);

        BUTTONS.forEach(function ([command, icon, title]) {
            if (command === '|') {
                const separator = document.createElement('span');
                separator.className = 'rich-editor-separator';
                toolbar.appendChild(separator);
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-secondary';
            button.dataset.command = command;
            button.title = title;
            button.setAttribute('aria-label', title);
            button.innerHTML = '<i class="bi ' + icon + '" aria-hidden="true"></i>';
            toolbar.appendChild(button);
        });

        const area = document.createElement('div');
        area.className = 'rich-editor-area form-control';
        area.contentEditable = 'true';
        area.dir = 'rtl';
        area.setAttribute('role', 'textbox');
        area.setAttribute('aria-multiline', 'true');
        area.dataset.placeholder = textarea.getAttribute('placeholder') || '';
        if (textarea.id) {
            const label = document.querySelector('label[for="' + textarea.id + '"]');
            if (label) {
                label.addEventListener('click', function () { area.focus(); });
                area.setAttribute('aria-label', label.textContent.trim());
            }
        }
        if (textarea.classList.contains('is-invalid')) {
            area.classList.add('is-invalid');
        }

        const initial = /<[a-z][\s\S]*>/i.test(textarea.value) ? clean(textarea.value) : escapeText(textarea.value);
        area.innerHTML = initial;

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(area);
        wrapper.appendChild(textarea);
        textarea.classList.add('rich-editor-source', 'ltr');
        textarea.hidden = true;

        let sourceMode = false;

        function sync() {
            if (!sourceMode) {
                textarea.value = clean(area.innerHTML);
            }
        }

        function exec(command, value) {
            area.focus();
            document.execCommand(command, false, value);
            sync();
            updateState();
        }

        function updateState() {
            const block = (document.queryCommandValue('formatBlock') || 'p').toLowerCase().replace(/[<>]/g, '');
            blockSelect.value = BLOCKS.some(([tag]) => tag === block) ? block : 'p';
        }

        try {
            document.execCommand('defaultParagraphSeparator', false, 'p');
        } catch (e) {
            // مرورگرهای قدیمی
        }

        blockSelect.addEventListener('change', function () {
            exec('formatBlock', '<' + blockSelect.value + '>');
        });

        toolbar.addEventListener('click', function (event) {
            const button = event.target.closest('button[data-command]');
            if (!button) {
                return;
            }

            const command = button.dataset.command;

            if (command === 'source') {
                sourceMode = !sourceMode;
                if (sourceMode) {
                    textarea.value = clean(area.innerHTML);
                } else {
                    area.innerHTML = clean(textarea.value);
                    sync();
                }
                textarea.hidden = !sourceMode;
                area.hidden = sourceMode;
                button.classList.toggle('active', sourceMode);
                toolbar.querySelectorAll('button:not([data-command="source"]), select').forEach(function (control) {
                    control.disabled = sourceMode;
                });
                (sourceMode ? textarea : area).focus();
                return;
            }

            if (command === 'quote') {
                exec('formatBlock', '<blockquote>');
            } else if (command === 'lineBreak') {
                exec('insertHTML', '<br>');
            } else if (command === 'link') {
                const url = window.prompt('نشانی پیوند (https://... یا tel: یا mailto:)', 'https://');
                if (url && SAFE_HREF.test(url.trim())) {
                    exec('createLink', url.trim());
                } else if (url) {
                    window.alert('فقط نشانی‌های https://، http://، tel: و mailto: پذیرفته می‌شوند.');
                }
            } else if (command === 'clear') {
                exec('removeFormat');
                exec('formatBlock', '<p>');
            } else {
                exec(command);
            }
        });

        area.addEventListener('input', sync);
        area.addEventListener('keyup', updateState);
        area.addEventListener('mouseup', updateState);

        // چسباندن: قالب‌بندی مجاز می‌ماند، بقیه (رنگ، فونت، اسکریپت) حذف می‌شود
        area.addEventListener('paste', function (event) {
            const data = event.clipboardData;
            if (!data) {
                return;
            }

            event.preventDefault();
            const html = data.getData('text/html');
            exec('insertHTML', html ? clean(html) : escapeText(data.getData('text/plain')));
        });

        textarea.form?.addEventListener('submit', function () {
            if (sourceMode) {
                textarea.value = clean(textarea.value);
            } else {
                sync();
            }
        });

        sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('textarea[data-rich-editor]').forEach(init);
    });
})();
