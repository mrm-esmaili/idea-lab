<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * SMS.ir (API نسخه ۱).
 * - قالب‌دار: send/verify
 * - متن آزاد گروهی از خط اختصاصی: send/bulk (نیازمند SMSIR_LINE_NUMBER)
 * پاسخ موفق: status = 1.
 */
class SmsIrProvider implements SmsProvider
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
        private readonly ?string $lineNumber = null,
    ) {}

    public function name(): string
    {
        return 'smsir';
    }

    public function sendTemplate(string $mobile, int $templateId, array $parameters): array
    {
        $json = $this->post('send/verify', [
            'mobile' => $mobile,
            'templateId' => $templateId,
            'parameters' => collect($parameters)
                ->map(fn ($value, $name) => ['name' => (string) $name, 'value' => (string) $value])
                ->values()
                ->all(),
        ]);

        return [
            'message_id' => isset($json['data']['messageId']) ? (string) $json['data']['messageId'] : null,
            'cost' => isset($json['data']['cost']) ? (float) $json['data']['cost'] : null,
            'response' => $json,
        ];
    }

    public function sendText(array $mobiles, string $text): array
    {
        if (! $this->lineNumber) {
            throw new SmsException('SMS.ir line number (SMSIR_LINE_NUMBER) is not configured.');
        }

        $json = $this->post('send/bulk', [
            'lineNumber' => (int) $this->lineNumber,
            'messageText' => $text,
            'mobiles' => array_values($mobiles),
            'sendDateTime' => null,
        ]);

        $ids = array_values((array) ($json['data']['messageIds'] ?? []));

        return [
            'message_ids' => array_map(
                fn ($index) => isset($ids[$index]) ? (string) $ids[$index] : null,
                array_keys(array_values($mobiles))
            ),
            'cost' => isset($json['data']['cost']) ? (float) $json['data']['cost'] : null,
            'response' => $json,
        ];
    }

    private function post(string $path, array $payload): array
    {
        if (! $this->apiKey) {
            throw new SmsException('SMS.ir API key is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['X-API-KEY' => $this->apiKey])
                ->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/') . '/' . $path, $payload);
        } catch (ConnectionException $e) {
            throw new SmsException('SMS.ir is unreachable.', null, $e, retryable: true);
        }

        $json = $response->json();
        $status = is_array($json) ? ($json['status'] ?? null) : null;

        if ($status !== 1) {
            throw new SmsException(
                'SMS.ir rejected the message: ' . (is_array($json) ? ($json['message'] ?? 'unknown') : 'HTTP ' . $response->status()),
                is_numeric($status) ? (int) $status : null,
                retryable: $response->serverError() || $response->status() === 429,
                response: is_array($json) ? $json : ['http_status' => $response->status()],
            );
        }

        return $json;
    }
}
