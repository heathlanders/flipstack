<?php

namespace App\Exceptions;

use Exception;

class ProductException extends Exception
{
    public function __construct(string $message = 'Product operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'product_error',
        ], 422);
    }
}
