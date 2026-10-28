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
    ],

    // وضعیت هر تراکنش درگاه
    'payment' => [
        'pending' => 'در انتظار',
        'paid' => 'موفق',
        'failed' => 'ناموفق',
        'cancelled' => 'لغوشده',
    ],
];
