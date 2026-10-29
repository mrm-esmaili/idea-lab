<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\LandingPage;
use Illuminate\Support\Facades\DB;

/**
 * ثبت پیامدهای ارسال فرم برای پرونده مخاطب:
 * - علاقه به ایده (مرحله ۳۵)؛ اولین منبع و کمپین حفظ می‌شود.
 * - منبع جذب (مرحله ۳۸)؛ فقط اولین تماس ثبت می‌شود.
 */
class ContactActivity
{
    public function recordSubmission(
        Contact $contact,
        LandingPage $page,
        string $kind,
        ?string $source,
        ?string $medium,
        ?string $campaign,
    ): void {
        DB::table('contact_idea_interests')->insertOrIgnore([
            'contact_id' => $contact->id,
            'idea_id' => $page->idea_id,
            'source' => $kind,
            'landing_page_id' => $page->id,
            'campaign' => $campaign,
            'created_at' => now(),
        ]);

        if ($contact->first_landing_page_id === null) {
            $contact->forceFill([
                'first_landing_page_id' => $page->id,
                'first_source' => $source,
                'first_medium' => $medium,
                'first_campaign' => $campaign,
            ])->save();
        }
    }
}
