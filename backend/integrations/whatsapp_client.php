<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/app.php';

if (!function_exists('whatsapp_send_text')) {
    function whatsapp_send_text($phone, $message)
    {
        $phone = normalize_phone($phone);
        $message = trim((string)$message);

        if ($phone === '' || $message === '') {
            return [
                'success' => false,
                'http_code' => 422,
                'response' => 'Missing phone or message',
            ];
        }

        if (WHATSAPP_TOKEN === '' || WHATSAPP_PHONE_NUMBER_ID === '') {
            return [
                'success' => false,
                'http_code' => 500,
                'response' => 'WhatsApp credentials are not configured',
            ];
        }

        $endpoint = 'https://graph.facebook.com/v21.0/' . rawurlencode(WHATSAPP_PHONE_NUMBER_ID) . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . WHATSAPP_TOKEN,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            return [
                'success' => false,
                'http_code' => 500,
                'response' => $curlErr,
            ];
        }

        $ok = $httpCode >= 200 && $httpCode < 300;
        return [
            'success' => $ok,
            'http_code' => $httpCode,
            'response' => $resp,
        ];
    }
}
