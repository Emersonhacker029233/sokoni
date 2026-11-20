<?php

namespace App\Services\Payment\Dalipay;

use RuntimeException;

/** Dalipay didn't give a usable answer. The message never contains a key. */
class DalipayException extends RuntimeException {}
