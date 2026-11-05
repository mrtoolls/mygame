<?php

namespace App\Support;

class CoinVendorPaymentMethod
{
    public static function chatSummary(array $method): string
    {
        $type = (string) ($method['type'] ?? 'payment');
        $labels = [
            'bank' => 'Bank transfer',
            'mobile_money' => 'Mobile money',
            'crypto' => 'Crypto',
            'other' => 'Payment details',
        ];
        $fieldsByType = [
            'bank' => ['bank_name' => 'Bank', 'account_number' => 'Account number', 'account_name' => 'Account name'],
            'mobile_money' => ['provider' => 'Provider', 'phone_number' => 'Phone number', 'account_name' => 'Account name'],
            'crypto' => ['network' => 'Network', 'wallet_address' => 'Wallet address', 'memo' => 'Memo / tag'],
            'other' => ['details' => 'Details'],
        ];

        $title = trim((string) ($method['label'] ?? '')) ?: ($labels[$type] ?? ucfirst(str_replace('_', ' ', $type)));
        $details = [];
        foreach ($fieldsByType[$type] ?? [] as $field => $label) {
            $value = trim((string) ($method[$field] ?? ''));
            if ($value !== '') $details[] = $label . ': ' . $value;
        }

        // Keep existing vendor methods readable until their owners replace the former generic
        // instructions field with the new typed account fields.
        if ($type !== 'other') {
            $instructions = trim((string) ($method['details'] ?? ''));
            if ($instructions !== '') $details[] = 'Instructions: ' . $instructions;
        }

        return $title . ($details ? ': ' . implode(' · ', $details) : '');
    }
}
