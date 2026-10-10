<?php

namespace App\Exceptions;

use Exception;

class CategoryException extends Exception
{
    public function __construct(string $message = 'Category operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'category_error',
        ], 422);
    }
}
