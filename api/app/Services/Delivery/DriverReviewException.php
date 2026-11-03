<?php

namespace App\Services\Delivery;

use RuntimeException;

/** An admin decision that can't apply to the driver's current state. The message is shown to the admin as-is. */
class DriverReviewException extends RuntimeException {}
