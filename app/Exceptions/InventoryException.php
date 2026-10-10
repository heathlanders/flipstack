<?php

namespace App\Exceptions;

use Exception;

class InventoryException extends Exception
{
    public function __construct(string $message = 'Inventory operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'inventory_error',
        ], 422);
    }
}
