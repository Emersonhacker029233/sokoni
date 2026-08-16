<?php

namespace App\Observers;

use App\Models\Customer;

/** Keeps SellerProfile.customer_count denormalised off the customers table — same shape as ReviewObserver. */
class CustomerObserver
{
    public function created(Customer $customer): void
    {
        $this->recalculate($customer);
    }

    public function deleted(Customer $customer): void
    {
        $this->recalculate($customer);
    }

    private function recalculate(Customer $customer): void
    {
        $seller = $customer->seller;
        if (! $seller) {
            return;
        }

        $seller->forceFill(['customer_count' => $seller->followers()->count()])->save();
    }
}
