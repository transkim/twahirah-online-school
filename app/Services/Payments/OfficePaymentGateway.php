<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Used until the school's Mobile Money and card accounts are connected. The payment waits
 * until the office sees the money and confirms it, which starts the subscription.
 */
class OfficePaymentGateway implements PaymentGateway
{
    public function request(Payment $payment): array
    {
        return [
            'message' => 'Send UGX '.number_format($payment->amount_ugx)." to the school's Mobile Money number with reference {$payment->reference}, or pay at the office. Your subscription starts as soon as the office confirms it.",
            'redirect_url' => null,
        ];
    }
}
