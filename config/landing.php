<?php

return [
    // حداکثر حجم ویدیوی MP4 صفحه فرود؛ upload_max_filesize و post_max_size هاست هم محدود می‌کنند
    'video_max_mb' => (int) env('LANDING_VIDEO_MAX_MB', 50),
];
