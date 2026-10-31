<?php

/*
 * داده نمونه روی نسخه ۱.۰ (پیش از ارتقا). با پوشه برنامه نسخه ۱.۰ به‌عنوان
 * پوشه جاری اجرا می‌شود: php /path/to/seed-v10.php
 */

require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Support\Str;

User::create(['name' => 'مالک', 'email' => 'owner@upgrade.test', 'password' => bcrypt('Upgrade-pass-2026'), 'role' => 'super_admin']);

$idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing', 'preorder_amount_rial' => 1_200_000]);
$policy = $idea->refundPolicies()->create(['version' => 1, 'is_refundable' => true, 'refund_window_days' => 14, 'terms' => 'تا ۱۴ روز قابل بازپرداخت است.']);

// صفحه با محتوای نسخه ۱.۰: ویژگی‌ها رشته ساده
$page = $idea->landingPages()->create([
    'name' => 'صفحه قدیمی', 'slug' => 'legacy', 'title' => 'باشگاهت را هوشمند مدیریت کن', 'cta_text' => 'ثبت‌نام', 'is_active' => true,
    'content' => [
        'body_title' => 'چرا باشگاه‌یار؟', 'body_text' => 'متن معرفی',
        'features' => ['ویژگی قدیمی', 'گزارش مالی'],
        'faq' => [['q' => 'کی عرضه می‌شود؟', 'a' => 'به‌زودی']],
        'show_lead_form' => true, 'show_preorder_form' => true,
    ],
]);
$idea->landingPages()->create(['name' => 'بدون محتوا', 'slug' => 'empty', 'title' => 'صفحه بدون محتوا', 'is_active' => true]);

$buyer = Contact::create(['mobile' => '09121234567', 'name' => 'علی']);
$page->leads()->create(['contact_id' => $buyer->id, 'name' => 'علی', 'phone' => '09121234567', 'status' => 'new', 'campaign' => 'divar', 'consented_at' => now()]);
$page->leads()->create(['name' => 'سارا', 'phone' => '09125556677', 'status' => 'contacted', 'consented_at' => now()]);

$preorder = $page->preorders()->create([
    'contact_id' => $buyer->id, 'name' => 'علی', 'phone' => '09121234567', 'amount_rial' => 1_200_000,
    'refund_policy_id' => $policy->id, 'terms_accepted_at' => now(), 'payment_status' => 'paid',
    'consented_at' => now(), 'public_token' => (string) Str::uuid(),
]);
$preorder->payments()->create(['gateway' => 'zarinpal', 'authority' => 'A' . Str::random(30), 'amount_rial' => 1_200_000, 'status' => 'paid', 'ref_id' => '123456', 'verified_at' => now()]);

echo "seeded idea {$idea->id}, pages 2, leads 2, paid preorder {$preorder->id}\n";
