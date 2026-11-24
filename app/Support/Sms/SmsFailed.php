<?php

namespace App\Support\Sms;

use RuntimeException;

/** A provider refused a text or could not be reached (REQ-36-09). */
class SmsFailed extends RuntimeException {}
