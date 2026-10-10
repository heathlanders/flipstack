<?php

namespace App\Notifications\Orders;

use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentFailedNotification extends BaseNotification
{
    public function __construct(
        public object $payment
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_ORDER;
    }

    public function getTitle(): string
    {
        return 'Payment Failed';
    }

    public function getMessage(): string
    {
        return sprintf(
            'Order #%s - %s',
            $this->payment->order_number ?? 'Unknown',
            $this->payment->failure_reason ?? 'Payment processing failed'
        );
    }

    public function getActionUrl(): ?string
    {
        return $this->payment->retry_url ?? route('dashboard');
    }

    public function getActionText(): string
    {
        return 'Retry Payment';
    }

    protected function getData(): array
    {
        return [
            'order_id' => $this->payment->order_id ?? null,
            'order_number' => $this->payment->order_number ?? 'Unknown',
            'customer_email' => $this->payment->customer_email ?? 'Unknown',
            'amount' => $this->payment->amount ?? 0,
            'failure_reason' => $this->payment->failure_reason ?? 'Payment processing failed',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $orderNumber = $this->payment->order_number ?? 'Unknown';
        $customerEmail = $this->payment->customer_email ?? 'Unknown';
        $failureReason = $this->payment->failure_reason ?? 'Unknown';
        $amount = $this->payment->amount ?? 0;
        $retryUrl = $this->payment->retry_url ?? route('dashboard');

        return (new MailMessage)
            ->error()
            ->subject('Payment Failed - Order #'.$orderNumber)
            ->greeting('Payment Failed')
            ->line('The payment for order #'.$orderNumber.' has failed.')
            ->line('**Customer:** '.$customerEmail)
            ->line('**Reason:** '.$failureReason)
            ->line('**Amount:** $'.number_format($amount, 2))
            ->action('Retry Payment', $retryUrl)
            ->line('Please follow up with the customer.');
    }
}
