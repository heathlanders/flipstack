<?php

namespace App\Services\Amazon;

use Exception;

class AmazonApiException extends Exception
{
    protected ?int $httpStatusCode;

    protected ?string $amazonErrorCode;

    protected array $errorDetails;

    protected ?int $retryAfterSeconds;

    public function __construct(
        string $message = '',
        ?int $httpStatusCode = null,
        ?string $amazonErrorCode = null,
        array $errorDetails = [],
        ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->amazonErrorCode = $amazonErrorCode;
        $this->errorDetails = $errorDetails;
        $this->retryAfterSeconds = $retryAfterSeconds;

        parent::__construct($message, $httpStatusCode ?? 0, $previous);
    }

    public static function fromResponse(array $response, int $statusCode, array $headers = []): self
    {
        $errors = $response['errors'] ?? [];
        $firstError = $errors[0] ?? [];

        $message = $firstError['message'] ?? $response['message'] ?? 'Unknown Amazon SP-API error';
        $code = $firstError['code'] ?? null;

        $retryAfter = null;
        if ($statusCode === 429) {
            $retryHeader = $headers['x-amzn-RateLimit-Limit'][0] ?? $headers['Retry-After'][0] ?? null;
            if ($retryHeader) {
                $retryAfter = (int) ceil(1 / (float) $retryHeader);
            } else {
                $retryAfter = 2;
            }
        }

        return new self(
            message: $message,
            httpStatusCode: $statusCode,
            amazonErrorCode: $code,
            errorDetails: $response,
            retryAfterSeconds: $retryAfter,
        );
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function getAmazonErrorCode(): ?string
    {
        return $this->amazonErrorCode;
    }

    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isRetryable(): bool
    {
        return in_array($this->httpStatusCode, [429, 500, 502, 503, 504]);
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatusCode === 429;
    }

    public function isNotFound(): bool
    {
        return $this->httpStatusCode === 404;
    }

    public function isUnauthorized(): bool
    {
        return in_array($this->httpStatusCode, [401, 403]);
    }

    public function isQuotaExceeded(): bool
    {
        return $this->amazonErrorCode === 'QuotaExceeded';
    }
}
