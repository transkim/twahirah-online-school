<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Asks MTN Mobile Money, Airtel Money or a card provider to collect a payment.
 * The provider later tells the school the money arrived, and the subscription starts.
 */
interface PaymentGateway
{
    /**
     * @return array{message: string, redirect_url: ?string}
     */
    public function request(Payment $payment): array;
}
