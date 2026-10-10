<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $message = 'Insufficient stock available', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'insufficient_stock',
        ], 422);
    }
}
