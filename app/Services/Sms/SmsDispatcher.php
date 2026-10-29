<?php

namespace App\Services\Sms;

use App\Models\Contact;
use App\Models\SmsBroadcast;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * پردازش صف پیامک (مرحله ۶۵).
 *
 * - هر اجرا حداکثر batch_size پیام و در مجموع rate_per_minute پیام در دقیقه.
 * - پیام‌ها با یک شناسه claim و انتقال شرطی queued → sending برداشته
 *   می‌شوند؛ دو اجرای هم‌زمان (پس از پاسخ و cron) یک پیام را دو بار نمی‌فرستند.
 * - خطای گذرا تا max_attempts با فاصله فزاینده دوباره تلاش می‌شود؛ رد قطعی
 *   ارائه‌دهنده تکرار نمی‌شود.
 * - پیامک تبلیغاتی فقط در ساعات مجاز و فقط اگر رضایت مخاطب هنوز برقرار باشد.
 */
class SmsDispatcher
{
    public const LAST_RUN_CACHE_KEY = 'sms:last_dispatch_at';

    public function __construct(
        private readonly SmsProvider $provider,
        private readonly SmsService $sms,
    ) {}

    /**
     * @return array{sent: int, failed: int, retry: int, skipped: int}
     */
    public function run(?int $limit = null): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'retry' => 0, 'skipped' => 0];

        Cache::forever(self::LAST_RUN_CACHE_KEY, now()->toIso8601String());

        $this->closeStuck();

        $limit = min($limit ?? (int) config('sms.batch_size'), $this->remainingRate());

        if ($limit <= 0) {
            return $stats;
        }

        $ids = SmsMessage::where('status', SmsMessage::STATUS_QUEUED)
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->when(! $this->promoWindowOpen(), fn ($q) => $q->where('category', '!=', 'promotional'))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->finishBroadcasts();

            return $stats;
        }

        $claim = (string) Str::uuid();

        SmsMessage::whereIn('id', $ids)
            ->where('status', SmsMessage::STATUS_QUEUED)
            ->update([
                'status' => SmsMessage::STATUS_SENDING,
                'claim' => $claim,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        $messages = SmsMessage::where('claim', $claim)
            ->where('status', SmsMessage::STATUS_SENDING)
            ->orderBy('id')
            ->get();

        $messages = $this->skipWithoutConsent($messages, $stats);

        $templates = SmsTemplate::whereIn('key', $messages->pluck('template_key')->filter()->unique())->get()->keyBy('key');

        foreach ($messages->where('type', SmsMessage::TYPE_TEMPLATE) as $message) {
            $this->sendTemplateMessage($message, $templates->get($message->template_key), $stats);
        }

        $texts = $messages->where('type', SmsMessage::TYPE_TEXT)->groupBy('body');

        foreach ($texts as $body => $group) {
            foreach ($group->chunk((int) config('sms.bulk_chunk')) as $chunk) {
                $this->sendTextChunk((string) $body, $chunk->values(), $stats);
            }
        }

        $this->finishBroadcasts();

        return $stats;
    }

    public function promoWindowOpen(): bool
    {
        $hour = (int) now()->timezone(config('app.display_timezone'))->format('G');

        return $hour >= (int) config('sms.promo_hours.from') && $hour < (int) config('sms.promo_hours.to');
    }

    private function remainingRate(): int
    {
        $recent = SmsMessage::where('sent_at', '>=', now()->subMinute())->count();

        return max(0, (int) config('sms.rate_per_minute') - $recent);
    }

    private function closeStuck(): void
    {
        SmsMessage::where('status', SmsMessage::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes((int) config('sms.stuck_after_minutes')))
            ->update([
                'status' => SmsMessage::STATUS_FAILED,
                'error' => 'نتیجه ارسال نامعلوم است (اجرای ارسال قطع شد)؛ برای جلوگیری از پیام تکراری خودکار تکرار نمی‌شود.',
                'updated_at' => now(),
            ]);
    }

    /**
     * رضایت ممکن است پس از ثبت در صف لغو شده باشد.
     */
    private function skipWithoutConsent(Collection $messages, array &$stats): Collection
    {
        $promotional = $messages->where('category', 'promotional');

        if ($promotional->isEmpty()) {
            return $messages;
        }

        $consenting = Contact::whereIn('id', $promotional->pluck('contact_id')->filter())
            ->where('marketing_sms_consent', true)
            ->pluck('id')
            ->flip();

        return $messages->reject(function (SmsMessage $message) use ($consenting, &$stats) {
            if ($message->category !== 'promotional' || $consenting->has($message->contact_id)) {
                return false;
            }

            $message->update(['status' => SmsMessage::STATUS_SKIPPED, 'error' => 'رضایت پیامک تبلیغاتی وجود ندارد.']);
            $stats['skipped']++;

            return true;
        });
    }

    private function sendTemplateMessage(SmsMessage $message, ?SmsTemplate $template, array &$stats): void
    {
        if (! $template?->enabled) {
            $message->update(['status' => SmsMessage::STATUS_SKIPPED, 'error' => 'قالب پیامک غیرفعال یا حذف شده است.']);
            $stats['skipped']++;

            return;
        }

        try {
            $this->sms->deliverTemplate($template, $message, $message->variables ?? []);
        } catch (SmsException $e) {
            $this->fail(collect([$message]), $e, $stats);

            return;
        }

        $message->save();
        $stats['sent']++;
    }

    private function sendTextChunk(string $body, Collection $messages, array &$stats): void
    {
        try {
            $result = $this->provider->sendText($messages->pluck('mobile')->all(), $body);
        } catch (SmsException $e) {
            $this->fail($messages, $e, $stats);

            return;
        }

        $cost = $result['cost'] !== null ? round($result['cost'] / max(1, $messages->count()), 2) : null;

        foreach ($messages as $index => $message) {
            $message->update([
                'status' => SmsMessage::STATUS_SENT,
                'provider' => $this->provider->name(),
                'provider_message_id' => $result['message_ids'][$index] ?? null,
                'cost' => $cost,
                'response' => $result['response'],
                'error' => null,
                'sent_at' => now(),
            ]);
            $stats['sent']++;
        }
    }

    private function fail(Collection $messages, SmsException $e, array &$stats): void
    {
        foreach ($messages as $message) {
            $retry = $e->retryable && $message->attempts < (int) config('sms.max_attempts');

            $message->update([
                'status' => $retry ? SmsMessage::STATUS_QUEUED : SmsMessage::STATUS_FAILED,
                'available_at' => $retry ? now()->addMinutes((int) config('sms.retry_after_minutes') * $message->attempts) : $message->available_at,
                'error' => mb_substr($e->getMessage(), 0, 255),
                'response' => $e->response,
                'parameters' => $message->parameters,
            ]);

            $stats[$retry ? 'retry' : 'failed']++;
        }

        Log::warning('SMS sending failed.', [
            'sms_message_ids' => $messages->pluck('id')->all(),
            'retryable' => $e->retryable,
            'error' => $e->getMessage(),
        ]);
    }

    private function finishBroadcasts(): void
    {
        SmsBroadcast::where('status', SmsBroadcast::STATUS_SENDING)
            ->whereDoesntHave('messages', fn ($q) => $q->whereIn('status', [SmsMessage::STATUS_QUEUED, SmsMessage::STATUS_SENDING]))
            ->update(['status' => SmsBroadcast::STATUS_COMPLETED, 'completed_at' => now()]);
    }
}
