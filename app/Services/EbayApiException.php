<?php

namespace App\Services;

use Exception;

class EbayApiException extends Exception
{
    protected ?string $ebayErrorCode;

    protected array $ebayResponse;

    protected ?int $httpStatusCode;

    public function __construct(
        string $message,
        ?string $ebayErrorCode = null,
        array $ebayResponse = [],
        ?int $httpStatusCode = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->ebayErrorCode = $ebayErrorCode;
        $this->ebayResponse = $ebayResponse;
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * Get the eBay error code
     */
    public function getEbayErrorCode(): ?string
    {
        return $this->ebayErrorCode;
    }

    /**
     * Get the full eBay response
     */
    public function getEbayResponse(): array
    {
        return $this->ebayResponse;
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get the HTTP status code (alias for getStatusCode)
     */
    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get all eBay errors from the response
     */
    public function getErrors(): array
    {
        return $this->ebayResponse['errors'] ?? [];
    }

    /**
     * Get all eBay warnings from the response
     */
    public function getWarnings(): array
    {
        return $this->ebayResponse['warnings'] ?? [];
    }

    /**
     * Check if this is a specific eBay error
     */
    public function isError(int $errorId): bool
    {
        foreach ($this->getErrors() as $error) {
            if (($error['errorId'] ?? null) == $errorId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this is a token-related error
     */
    public function isTokenError(): bool
    {
        return $this->httpStatusCode === 401 ||
               $this->isError(25002) || // Invalid access token
               $this->ebayErrorCode === 'invalid_token';
    }

    /**
     * Check if this is a rate limit error
     */
    public function isRateLimitError(): bool
    {
        return $this->httpStatusCode === 429 ||
               $this->isError(25008); // Rate limit exceeded
    }

    /**
     * Check if this is a validation error
     */
    public function isValidationError(): bool
    {
        return $this->httpStatusCode === 400;
    }

    /**
     * Check if this is a not found error
     */
    public function isNotFoundError(): bool
    {
        return $this->httpStatusCode === 404;
    }

    /**
     * Check if this error is retryable
     */
    public function isRetryable(): bool
    {
        // Server errors
        if ($this->httpStatusCode >= 500) {
            return true;
        }

        // Rate limiting
        if ($this->isRateLimitError()) {
            return true;
        }

        // Token errors (can retry after refresh)
        if ($this->isTokenError()) {
            return true;
        }

        return false;
    }

    /**
     * Get a user-friendly error message
     */
    public function getUserMessage(): string
    {
        if ($this->isTokenError()) {
            return 'Your eBay authorization has expired. Please reconnect your eBay account.';
        }

        if ($this->isRateLimitError()) {
            return 'eBay API rate limit reached. Please try again in a few minutes.';
        }

        if ($this->isValidationError()) {
            return 'The listing data is invalid: '.$this->message;
        }

        if ($this->httpStatusCode >= 500) {
            return 'eBay is temporarily unavailable. Please try again later.';
        }

        return $this->message;
    }

    /**
     * Create from an HTTP response
     */
    public static function fromResponse(array $response, int $statusCode): self
    {
        $message = 'Unknown eBay API error';
        $errorCode = null;

        if (isset($response['errors'][0])) {
            $error = $response['errors'][0];
            $message = $error['message'] ?? $error['longMessage'] ?? $message;
            $errorCode = isset($error['errorId']) ? (string) $error['errorId'] : null;
        } elseif (isset($response['error_description'])) {
            $message = $response['error_description'];
            $errorCode = $response['error'] ?? null;
        } elseif (isset($response['message'])) {
            $message = $response['message'];
        }

        return new self($message, $errorCode, $response, $statusCode);
    }

    /**
     * Convert to array for logging
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'ebay_error_code' => $this->ebayErrorCode,
            'http_status_code' => $this->httpStatusCode,
            'errors' => $this->getErrors(),
            'warnings' => $this->getWarnings(),
        ];
    }
}
