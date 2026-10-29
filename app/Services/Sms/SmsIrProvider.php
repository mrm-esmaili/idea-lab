<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * ارسال قالب‌دار SMS.ir (API نسخه ۱، متد send/verify).
 * پاسخ موفق: status = 1.
 */
class SmsIrProvider implements SmsProvider
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    public function name(): string
    {
        return 'smsir';
    }

    public function sendTemplate(string $mobile, int $templateId, array $parameters): array
    {
        if (! $this->apiKey) {
            throw new SmsException('SMS.ir API key is not configured.');
        }

        $payload = [
            'mobile' => $mobile,
            'templateId' => $templateId,
            'parameters' => collect($parameters)
                ->map(fn ($value, $name) => ['name' => (string) $name, 'value' => (string) $value])
                ->values()
                ->all(),
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['X-API-KEY' => $this->apiKey])
                ->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/') . '/send/verify', $payload);
        } catch (ConnectionException $e) {
            throw new SmsException('SMS.ir is unreachable.', null, $e);
        }

        $json = $response->json();
        $status = is_array($json) ? ($json['status'] ?? null) : null;

        if ($status !== 1) {
            throw new SmsException(
                'SMS.ir rejected the message: ' . (is_array($json) ? ($json['message'] ?? 'unknown') : 'HTTP ' . $response->status()),
                is_numeric($status) ? (int) $status : null
            );
        }

        return [
            'message_id' => isset($json['data']['messageId']) ? (string) $json['data']['messageId'] : null,
            'cost' => isset($json['data']['cost']) ? (float) $json['data']['cost'] : null,
        ];
    }
}
