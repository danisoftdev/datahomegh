<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public static function forAmount(string $amount): self
    {
        return new self("Insufficient wallet balance for debit of {$amount}.");
    }
}
