<?php

namespace App\Services\Payment;

use App\Models\Order;

/**
 * Not called anywhere in v1 — payment is cash-on-delivery / pay-on-pickup
 * only (see CLAUDE.md feature 8). Defined now so ClickPesa USSD push
 * (Mixx by Yas, Airtel Money) can drop in later without touching the
 * order/checkout flow.
 */
interface PaymentGateway
{
    public function charge(Order $order): PaymentResult;
}
