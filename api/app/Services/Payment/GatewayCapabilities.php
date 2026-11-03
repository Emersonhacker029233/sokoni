<?php

namespace App\Services\Payment;

/** What a gateway can really do, read by the API before offering a feature to the apps. */
final readonly class GatewayCapabilities
{
    /** @param  list<string>  $channels  mobile-money networks the payer may choose */
    public function __construct(
        public bool $collections,
        public bool $sandbox,
        public array $channels = [],
    ) {}
}
