<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request/step transition is not allowed from its current status.
 * The message is safe to show to the user.
 */
class InvalidTransition extends RuntimeException {}
