<?php

namespace App\Services\Payment;

use App\Models\Order;
use RuntimeException;

/**
 * MOCK: v1 has no online payment gateway — checkout only offers cash on
 * delivery / pay on pickup, so nothing should ever call charge(). This
 * binding exists purely so the `PaymentGateway` interface is satisfiable
 * in the container ahead of a real ClickPesa integration.
 */
class UnimplementedPaymentGateway implements PaymentGateway
{
    public function charge(Order $order): PaymentResult
    {
        throw new RuntimeException('No payment gateway is configured — v1 is cash-on-delivery / pay-on-pickup only.');
    }
}
