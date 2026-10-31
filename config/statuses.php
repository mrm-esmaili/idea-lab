<?php

return [
    'idea' => [
        'draft' => 'پیش‌نویس',
        'testing' => 'در حال آزمایش',
        'validated' => 'تأییدشده',
        'rejected' => 'ردشده',
        'building' => 'در حال ساخت',
        'launched' => 'عرضه‌شده',
    ],

    'lead' => [
        'new' => 'جدید',
        'contacted' => 'تماس گرفته شد',
        'interested' => 'علاقه‌مند',
        'prepaid' => 'پیش‌پرداخت',
        'customer' => 'مشتری',
        'rejected' => 'رد شده',
    ],

    // وضعیت پیگیری فروش (دستی، توسط مدیر)
    'preorder' => [
        'requested' => 'درخواست جدید',
        'contacted' => 'تماس گرفته شده',
        'interested' => 'علاقه‌مند',
        'rejected' => 'رد شده',
    ],

    // وضعیت مالی سفارش (فقط سیستم، پس از تأیید درگاه)
    'preorder_payment' => [
        'unpaid' => 'پرداخت‌نشده',
        'paid' => 'پرداخت‌شده',
        'partially_refunded' => 'بخشی بازپرداخت‌شده',
        'refunded' => 'بازپرداخت‌شده',
    ],

    'refund_request' => [
        'pending' => 'در انتظار بررسی',
        'approved' => 'تأییدشده، در انتظار اجرا',
        'rejected' => 'ردشده',
        'completed' => 'بازپرداخت‌شده',
    ],

    'campaign' => [
        'planned' => 'برنامه‌ریزی‌شده',
        'running' => 'در حال اجرا',
        'paused' => 'متوقف',
        'ended' => 'پایان‌یافته',
    ],

    'sms_category' => [
        'otp' => 'کد ورود',
        'transactional' => 'تراکنشی',
        'promotional' => 'تبلیغاتی',
    ],

    'sms_variable' => [
        'code' => 'کد یک‌بارمصرف',
        'name' => 'نام مخاطب',
        'idea' => 'نام ایده',
        'amount' => 'مبلغ (تومان)',
        'ref_id' => 'کد پیگیری پرداخت',
        'reference' => 'شماره پیگیری واریز',
        'link' => 'لینک پیگیری سفارش',
    ],

    'sms_message' => [
        'queued' => 'در صف',
        'sending' => 'در حال ارسال',
        'sent' => 'ارسال شد',
        'failed' => 'ناموفق',
        'skipped' => 'ارسال نشد',
        'cancelled' => 'لغو شد',
    ],

    'experiment' => [
        'planned' => 'برنامه‌ریزی‌شده',
        'running' => 'در حال اجرا',
        'ended' => 'منتظر تصمیم',
        'decided' => 'تصمیم‌گرفته',
        'cancelled' => 'لغو شد',
    ],

    'experiment_decision' => [
        'continue' => 'ادامه',
        'modify' => 'اصلاح',
        'stop' => 'توقف',
        'execute' => 'اجرا',
    ],

    'sms_broadcast' => [
        'draft' => 'پیش‌نویس',
        'sending' => 'در حال ارسال',
        'completed' => 'تمام‌شده',
        'cancelled' => 'لغو شد',
    ],

    'audit_action' => [
        'refund.requested' => 'ثبت درخواست بازپرداخت',
        'refund.approved' => 'تأیید درخواست',
        'refund.rejected' => 'رد درخواست',
        'refund.executed' => 'ثبت اجرای بازپرداخت',
        'expense.created' => 'ثبت هزینه',
        'expense.updated' => 'ویرایش هزینه',
        'expense.deleted' => 'حذف هزینه',
        'sms.broadcast_confirmed' => 'تأیید ارسال گروهی',
        'sms.broadcast_cancelled' => 'لغو ارسال گروهی',
        'sms.manual_sent' => 'ارسال پیامک دستی',
        'backup.created' => 'ساخت پشتیبان',
        'system.migrated' => 'اجرای migration',
        'system.optimized' => 'ساخت کش',
        'backup.downloaded' => 'دریافت پشتیبان',
        'experiment.created' => 'تعریف آزمایش',
        'experiment.updated' => 'ویرایش آزمایش',
        'experiment.cancelled' => 'لغو آزمایش',
        'experiment.decided' => 'ثبت تصمیم',
        'experiment.reviewed' => 'ارزیابی پس از تصمیم',
    ],

    // وضعیت هر تراکنش درگاه
    'payment' => [
        'pending' => 'در انتظار',
        'paid' => 'موفق',
        'failed' => 'ناموفق',
        'cancelled' => 'لغوشده',
    ],
];
