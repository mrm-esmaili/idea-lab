<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZarinpalService
{
    private function apiBaseUrl(): string
    {
        return config('services.zarinpal.sandbox')
            ? 'https://sandbox.zarinpal.com'
            : 'https://api.zarinpal.com';
    }

    public function startPayUrl(string $authority): string
    {
        $baseUrl = config('services.zarinpal.sandbox')
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://www.zarinpal.com/pg/StartPay/';

        return $baseUrl . $authority;
    }

    public function requestPayment(
        int $amountRial,
        string $callbackUrl,
        string $description,
        string $mobile
    ): array {
        $merchantId = config('services.zarinpal.merchant_id');

        if (!$merchantId) {
            throw new RuntimeException(
                'Zarinpal merchant ID is not configured.'
            );
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->post(
                $this->apiBaseUrl() . '/pg/v4/payment/request.json',
                [
                    'merchant_id' => $merchantId,
                    'amount' => $amountRial,
                    'callback_url' => $callbackUrl,
                    'description' => $description,
                    'metadata' => [
                        'mobile' => $mobile,
                    ],
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Zarinpal request API returned HTTP ' .
                $response->status()
            );
        }

        $result = $response->json();

        if (
            ($result['data']['code'] ?? null) !== 100 ||
            empty($result['data']['authority'])
        ) {
            throw new RuntimeException(
                'Zarinpal did not create the payment authority.'
            );
        }

        return [
            'authority' => $result['data']['authority'],
            'payment_url' => $this->startPayUrl(
                $result['data']['authority']
            ),
        ];
    }

    public function verifyPayment(
        string $authority,
        int $amountRial
    ): array {
        $merchantId = config('services.zarinpal.merchant_id');

        if (!$merchantId) {
            throw new RuntimeException(
                'Zarinpal merchant ID is not configured.'
            );
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->post(
                $this->apiBaseUrl() . '/pg/v4/payment/verify.json',
                [
                    'merchant_id' => $merchantId,
                    'authority' => $authority,
                    'amount' => $amountRial,
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Zarinpal verification API returned HTTP ' .
                $response->status()
            );
        }

        $result = $response->json();

        $code = $result['data']['code'] ?? null;

        if (!in_array($code, [100, 101], true)) {
            throw new RuntimeException(
                'Zarinpal verification was not successful.'
            );
        }

        return [
            'code' => $code,
            'ref_id' => $result['data']['ref_id'] ?? null,
        ];
    }
}
