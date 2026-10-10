<?php

namespace App\Services\Shopify;

use Exception;

class ShopifyApiException extends Exception
{
    protected ?int $httpStatusCode;

    protected ?string $shopifyErrorCode;

    protected array $errorDetails;

    protected ?int $retryAfterSeconds;

    public function __construct(
        string $message = '',
        ?int $httpStatusCode = null,
        ?string $shopifyErrorCode = null,
        array $errorDetails = [],
        ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->shopifyErrorCode = $shopifyErrorCode;
        $this->errorDetails = $errorDetails;
        $this->retryAfterSeconds = $retryAfterSeconds;

        parent::__construct($message, $httpStatusCode ?? 0, $previous);
    }

    public static function fromResponse(array $response, int $statusCode, array $headers = []): self
    {
        $message = $response['errors'] ?? $response['error'] ?? 'Unknown Shopify API error';

        if (is_array($message)) {
            $message = json_encode($message);
        }

        $retryAfter = null;
        if ($statusCode === 429 && isset($headers['Retry-After'])) {
            $retryAfter = (int) $headers['Retry-After'];
        }

        return new self(
            message: $message,
            httpStatusCode: $statusCode,
            shopifyErrorCode: (string) $statusCode,
            errorDetails: $response,
            retryAfterSeconds: $retryAfter,
        );
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function getShopifyErrorCode(): ?string
    {
        return $this->shopifyErrorCode;
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
        return $this->httpStatusCode === 401;
    }
}
