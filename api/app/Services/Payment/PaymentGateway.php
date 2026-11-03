<?php

namespace App\Services\Payment;

/**
 * A mobile-money collection provider (docs/delivery/WALLET_PLAN.md
 * section 6). Replaces the unused v1 `charge(Order)` interface.
 *
 * Only the collection half exists so far, because that is all a dispatch
 * order needs today: the buyer pays the frozen delivery fee after the
 * seller accepts. Refunds, payouts, signed webhooks and statements join
 * this interface with the real provider (Dalipay, Part B) and the ledger
 * phases, so nothing here pretends to do work it can't.
 *
 * Rule for every implementation: initiating a collection never means the
 * money arrived. Only queryCollection() (and, with a real provider, a
 * verified webhook) can report `succeeded`, and only the server calls it.
 */
interface PaymentGateway
{
    /** Short id stored in payments.provider, e.g. "fake". */
    public function name(): string;

    public function capabilities(): GatewayCapabilities;

    /** Sends the USSD push / payment prompt. Never blocks waiting for the payer. */
    public function initiateCollection(CollectionRequest $request): CollectionResult;

    /** Asks the provider what happened to a collection it accepted. */
    public function queryCollection(string $providerReference): CollectionStatus;
}
