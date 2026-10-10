<?php

namespace App\Services;

class BarcodeValidationService
{
    /**
     * Validate any barcode (UPC-A, UPC-E, EAN-13, EAN-8)
     */
    public function validate(string $barcode): array
    {
        $barcode = $this->sanitize($barcode);

        $result = [
            'valid' => false,
            'type' => null,
            'formatted' => $barcode,
            'errors' => [],
        ];

        // Detect barcode type
        $length = strlen($barcode);

        switch ($length) {
            case 12:
                $result['type'] = 'UPC-A';
                $result['valid'] = $this->validateUpcA($barcode);
                break;

            case 8:
                $result['type'] = 'EAN-8';
                $result['valid'] = $this->validateEan8($barcode);
                break;

            case 13:
                $result['type'] = 'EAN-13';
                $result['valid'] = $this->validateEan13($barcode);
                break;

            case 6:
            case 7:
                $result['type'] = 'UPC-E';
                $result['valid'] = $this->validateUpcE($barcode);
                if ($result['valid']) {
                    $result['formatted'] = $this->expandUpcE($barcode);
                }
                break;

            default:
                $result['errors'][] = "Invalid barcode length: {$length}. Expected 6-8 or 12-13 digits.";

                return $result;
        }

        if (! $result['valid']) {
            $result['errors'][] = "Invalid check digit for {$result['type']} barcode.";
        }

        return $result;
    }

    /**
     * Validate UPC-A (12 digits)
     */
    public function validateUpcA(string $barcode): bool
    {
        if (strlen($barcode) !== 12 || ! ctype_digit($barcode)) {
            return false;
        }

        $checkDigit = (int) substr($barcode, -1);
        $calculated = $this->calculateUpcCheckDigit(substr($barcode, 0, 11));

        return $checkDigit === $calculated;
    }

    /**
     * Validate EAN-13 (13 digits)
     */
    public function validateEan13(string $barcode): bool
    {
        if (strlen($barcode) !== 13 || ! ctype_digit($barcode)) {
            return false;
        }

        $checkDigit = (int) substr($barcode, -1);
        $calculated = $this->calculateEan13CheckDigit(substr($barcode, 0, 12));

        return $checkDigit === $calculated;
    }

    /**
     * Validate EAN-8 (8 digits)
     */
    public function validateEan8(string $barcode): bool
    {
        if (strlen($barcode) !== 8 || ! ctype_digit($barcode)) {
            return false;
        }

        $checkDigit = (int) substr($barcode, -1);
        $calculated = $this->calculateEan8CheckDigit(substr($barcode, 0, 7));

        return $checkDigit === $calculated;
    }

    /**
     * Validate UPC-E (6-8 digits)
     */
    public function validateUpcE(string $barcode): bool
    {
        if (! ctype_digit($barcode)) {
            return false;
        }

        // Expand to UPC-A format and validate
        $expanded = $this->expandUpcE($barcode);

        return $this->validateUpcA($expanded);
    }

    /**
     * Calculate UPC check digit (UPC-A, UPC-E)
     */
    private function calculateUpcCheckDigit(string $barcode): int
    {
        $sum = 0;
        for ($i = 0; $i < strlen($barcode); $i++) {
            $digit = (int) $barcode[$i];
            $multiplier = ($i % 2 === 0) ? 3 : 1;
            $sum += $digit * $multiplier;
        }

        $mod = $sum % 10;

        return $mod === 0 ? 0 : 10 - $mod;
    }

    /**
     * Calculate EAN-13 check digit
     */
    private function calculateEan13CheckDigit(string $barcode): int
    {
        $sum = 0;
        for ($i = 0; $i < strlen($barcode); $i++) {
            $digit = (int) $barcode[$i];
            $multiplier = ($i % 2 === 0) ? 1 : 3;
            $sum += $digit * $multiplier;
        }

        $mod = $sum % 10;

        return $mod === 0 ? 0 : 10 - $mod;
    }

    /**
     * Calculate EAN-8 check digit
     */
    private function calculateEan8CheckDigit(string $barcode): int
    {
        $sum = 0;
        for ($i = 0; $i < strlen($barcode); $i++) {
            $digit = (int) $barcode[$i];
            $multiplier = ($i % 2 === 0) ? 3 : 1;
            $sum += $digit * $multiplier;
        }

        $mod = $sum % 10;

        return $mod === 0 ? 0 : 10 - $mod;
    }

    /**
     * Expand UPC-E to UPC-A format
     */
    private function expandUpcE(string $upcE): string
    {
        // Remove check digit if present
        if (strlen($upcE) === 7) {
            $upcE = substr($upcE, 0, 6);
        } elseif (strlen($upcE) === 8) {
            $upcE = substr($upcE, 1, 6);
        }

        $lastDigit = (int) substr($upcE, -1);
        $firstFive = substr($upcE, 0, 5);

        $expanded = match (true) {
            $lastDigit <= 2 => $firstFive[0].$firstFive[1].$lastDigit.'0000'.$firstFive[2].$firstFive[3].$firstFive[4],
            $lastDigit === 3 => $firstFive[0].$firstFive[1].$firstFive[2].'00000'.$firstFive[3].$firstFive[4],
            $lastDigit === 4 => $firstFive[0].$firstFive[1].$firstFive[2].$firstFive[3].'00000'.$firstFive[4],
            default => $firstFive.'0000'.$lastDigit,
        };

        $checkDigit = $this->calculateUpcCheckDigit($expanded);

        return $expanded.$checkDigit;
    }

    /**
     * Sanitize barcode input
     */
    private function sanitize(string $barcode): string
    {
        $barcode = str_replace([' ', '-', '_'], '', $barcode);

        return preg_replace('/[^0-9]/', '', $barcode);
    }

    /**
     * Format barcode for display
     */
    public function format(string $barcode): string
    {
        $barcode = $this->sanitize($barcode);
        $length = strlen($barcode);

        return match ($length) {
            12 => substr($barcode, 0, 1).'-'.substr($barcode, 1, 5).'-'.substr($barcode, 6, 5).'-'.substr($barcode, -1),
            13 => substr($barcode, 0, 1).'-'.substr($barcode, 1, 6).'-'.substr($barcode, 7, 5).'-'.substr($barcode, -1),
            8 => substr($barcode, 0, 4).'-'.substr($barcode, 4),
            default => $barcode,
        };
    }
}
