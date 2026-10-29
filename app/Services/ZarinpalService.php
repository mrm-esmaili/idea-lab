<?php

namespace App\Services;

use App\Services\Payments\ZarinpalException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZarinpalService
{
    /** کدهای موفقیت تأیید: ۱۰۰ تأیید شد، ۱۰۱ قبلاً تأیید شده بود */
    private const VERIFY_SUCCESS_CODES = [100, 101];

    private function apiBaseUrl(): string
    {
        return config('services.zarinpal.api_url') ?: (config('services.zarinpal.sandbox')
            ? 'https://sandbox.zarinpal.com'
            : 'https://api.zarinpal.com');
    }

    public function startPayUrl(string $authority): string
    {
        $baseUrl = config('services.zarinpal.start_pay_url') ?: (config('services.zarinpal.sandbox')
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://www.zarinpal.com/pg/StartPay/');

        return $baseUrl . $authority;
    }

    /**
     * @return array{authority: string, payment_url: string, response: array}
     */
    public function requestPayment(
        int $amountRial,
        string $callbackUrl,
        string $description,
        string $mobile
    ): array {
        $result = $this->post('/pg/v4/payment/request.json', [
            'merchant_id' => $this->merchantId(),
            'amount' => $amountRial,
            'currency' => 'IRR',
            'callback_url' => $callbackUrl,
            'description' => $description,
            'metadata' => [
                'mobile' => $mobile,
            ],
        ]);

        $code = $result['data']['code'] ?? null;
        $authority = $result['data']['authority'] ?? null;

        if ($code !== 100 || ! is_string($authority) || $authority === '') {
            throw $this->gatewayError('Zarinpal did not create the payment authority.', $result);
        }

        return [
            'authority' => $authority,
            'payment_url' => $this->startPayUrl($authority),
            'response' => $this->safeData($result),
        ];
    }

    /**
     * @return array{code: int, ref_id: string, card_pan: ?string, response: array}
     */
    public function verifyPayment(
        string $authority,
        int $amountRial
    ): array {
        // مبلغ ذخیره‌شده سمت سرور ارسال می‌شود؛ مغایرت مبلغ را درگاه رد می‌کند
        $result = $this->post('/pg/v4/payment/verify.json', [
            'merchant_id' => $this->merchantId(),
            'authority' => $authority,
            'amount' => $amountRial,
        ]);

        $code = $result['data']['code'] ?? null;

        if (! in_array($code, self::VERIFY_SUCCESS_CODES, true)) {
            throw $this->gatewayError('Zarinpal verification was not successful.', $result);
        }

        $refId = $result['data']['ref_id'] ?? null;

        if ($refId === null || $refId === '') {
            throw new ZarinpalException('Zarinpal returned no reference ID.', null, $this->safeData($result));
        }

        return [
            'code' => $code,
            'ref_id' => (string) $refId,
            'card_pan' => $result['data']['card_pan'] ?? null,
            'response' => $this->safeData($result),
        ];
    }

    private function merchantId(): string
    {
        $merchantId = config('services.zarinpal.merchant_id');

        if (! $merchantId) {
            throw new RuntimeException('Zarinpal merchant ID is not configured.');
        }

        return $merchantId;
    }

    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post($this->apiBaseUrl() . $path, $payload);
        } catch (ConnectionException $e) {
            throw new ZarinpalException('Zarinpal is unreachable.', null, [], $e);
        }

        // زرین‌پال خطاها را با HTTP 4xx و بدنه JSON برمی‌گرداند
        $json = $response->json();

        if (! is_array($json)) {
            throw new ZarinpalException(
                'Zarinpal returned an invalid response (HTTP ' . $response->status() . ').'
            );
        }

        return $json;
    }

    private function gatewayError(string $message, array $result): ZarinpalException
    {
        $code = $result['errors']['code'] ?? $result['data']['code'] ?? null;

        return new ZarinpalException(
            $message,
            is_numeric($code) ? (int) $code : null,
            $this->safeData($result)
        );
    }

    /**
     * فقط فیلدهای غیرحساس برای ثبت در پایگاه داده (بدون card_hash).
     */
    private function safeData(array $result): array
    {
        return array_filter([
            'data' => is_array($result['data'] ?? null)
                ? Arr::only($result['data'], ['code', 'message', 'ref_id', 'card_pan', 'fee_type', 'fee'])
                : null,
            'errors' => is_array($result['errors'] ?? null) && $result['errors'] !== []
                ? Arr::only($result['errors'], ['code', 'message'])
                : null,
        ]);
    }
}
