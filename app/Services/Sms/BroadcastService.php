<?php

namespace App\Services\Sms;

use App\Models\Contact;
use App\Models\SmsBroadcast;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ContactSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ارسال گروهی تبلیغاتی (مراحل ۶۲ و ۶۵).
 *
 * - گیرندگان: اعضای یک گروه یا قواعد انتخاب مخاطب، و همیشه فقط دارندگان
 *   رضایت پیامک تبلیغاتی.
 * - مخاطبی که در frequency_cap_hours گذشته پیامک تبلیغاتی گرفته (یا در صف
 *   دارد) کنار گذاشته می‌شود.
 * - تأیید نهایی با تعداد پیش‌نمایش‌شده مقایسه می‌شود؛ اگر در این فاصله
 *   تغییر کرده باشد، ارسال انجام نمی‌شود تا مدیر دوباره بررسی کند.
 * - هر مخاطب در هر ارسال حداکثر یک پیام دارد (کلید یکتا broadcast:id:contact).
 */
class BroadcastService
{
    public function __construct(
        private readonly ContactSegment $segment,
        private readonly SmsService $sms,
        private readonly AuditLogger $audit,
    ) {}

    public function text(SmsBroadcast $broadcast): string
    {
        $footer = trim((string) config('sms.promo_footer'));

        return trim($broadcast->body) . ($footer !== '' ? "\n" . $footer : '');
    }

    public function audience(SmsBroadcast $broadcast): Builder
    {
        if ($broadcast->audience === 'group') {
            return $broadcast->group
                ? $broadcast->group->membersQuery()
                : Contact::query()->whereRaw('1 = 0');
        }

        return $this->segment->apply(Contact::query(), $broadcast->rules ?? []);
    }

    public function eligible(SmsBroadcast $broadcast): Builder
    {
        $since = now()->subHours($broadcast->frequency_cap_hours);

        return $this->audience($broadcast)
            ->where('contacts.marketing_sms_consent', true)
            ->when($broadcast->frequency_cap_hours > 0, fn ($q) => $q->whereNotExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from('sms_messages')
                    ->whereColumn('sms_messages.contact_id', 'contacts.id')
                    ->where('sms_messages.category', 'promotional')
                    ->whereIn('sms_messages.status', [SmsMessage::STATUS_QUEUED, SmsMessage::STATUS_SENDING, SmsMessage::STATUS_SENT])
                    ->where('sms_messages.created_at', '>=', $since)
            ));
    }

    /**
     * @return array{matched: int, no_consent: int, recently_messaged: int, eligible: int}
     */
    public function preview(SmsBroadcast $broadcast): array
    {
        $matched = $this->audience($broadcast)->count();
        $consenting = $this->audience($broadcast)->where('contacts.marketing_sms_consent', true)->count();
        $eligible = $this->eligible($broadcast)->count();

        return [
            'matched' => $matched,
            'no_consent' => $matched - $consenting,
            'recently_messaged' => $consenting - $eligible,
            'eligible' => $eligible,
        ];
    }

    /**
     * @throws RuntimeException
     */
    public function confirm(SmsBroadcast $broadcast, int $expected, User $user): int
    {
        $count = DB::transaction(function () use ($broadcast, $expected, $user) {
            $locked = SmsBroadcast::whereKey($broadcast->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw new RuntimeException('این ارسال قبلاً تأیید یا لغو شده است.');
            }

            $contacts = $this->eligible($locked)->get(['contacts.id', 'contacts.mobile']);

            if ($contacts->count() !== $expected) {
                throw new RuntimeException("تعداد گیرندگان از زمان پیش‌نمایش تغییر کرده است ({$contacts->count()} به‌جای {$expected})؛ دوباره بررسی و تأیید کنید.");
            }

            if ($contacts->isEmpty()) {
                throw new RuntimeException('گیرنده واجد شرایطی وجود ندارد.');
            }

            $text = $this->text($locked);
            $now = now();

            foreach ($contacts->chunk(500) as $chunk) {
                SmsMessage::insert($chunk->map(fn (Contact $contact) => [
                    'mobile' => $contact->mobile,
                    'contact_id' => $contact->id,
                    'broadcast_id' => $locked->id,
                    'user_id' => $user->id,
                    'type' => SmsMessage::TYPE_TEXT,
                    'body' => $text,
                    'category' => 'promotional',
                    'provider' => app(SmsProvider::class)->name(),
                    'status' => SmsMessage::STATUS_QUEUED,
                    'attempts' => 0,
                    'available_at' => $now,
                    'dedupe_key' => "broadcast:{$locked->id}:{$contact->id}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            $locked->forceFill([
                'status' => SmsBroadcast::STATUS_SENDING,
                'recipients_count' => $contacts->count(),
                'confirmed_by' => $user->id,
                'confirmed_at' => $now,
            ])->save();

            $this->audit->log($locked, 'sms.broadcast_confirmed', [
                'recipients' => $contacts->count(),
                'text' => $text,
            ], $user);

            return $contacts->count();
        });

        $this->sms->dispatchSoon();

        return $count;
    }

    /**
     * @return int تعداد پیام لغوشده از صف
     */
    public function cancel(SmsBroadcast $broadcast, User $user): int
    {
        return DB::transaction(function () use ($broadcast, $user) {
            $locked = SmsBroadcast::whereKey($broadcast->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [SmsBroadcast::STATUS_DRAFT, SmsBroadcast::STATUS_SENDING], true)) {
                throw new RuntimeException('این ارسال قابل لغو نیست.');
            }

            // پیام در حال ارسال به ارائه‌دهنده رفته و قابل بازگشت نیست
            $cancelled = SmsMessage::where('broadcast_id', $locked->id)
                ->where('status', SmsMessage::STATUS_QUEUED)
                ->update(['status' => SmsMessage::STATUS_CANCELLED, 'updated_at' => now()]);

            $locked->forceFill(['status' => SmsBroadcast::STATUS_CANCELLED, 'completed_at' => now()])->save();

            $this->audit->log($locked, 'sms.broadcast_cancelled', ['cancelled_messages' => $cancelled], $user);

            return $cancelled;
        });
    }
}
