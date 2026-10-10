<?php

namespace App\Services\NotificationDispatch;

use App\Jobs\Notification\EscalateNotificationJob;
use App\Models\Notification\NotificationRule;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves a notification event into a notification class + recipients,
 * applies the dedup window, dispatches the notification, and queues
 * escalation if configured.
 */
class EventDispatcher
{
    public function __construct(
        private RecipientResolver $resolver,
        private Dedup $dedup,
    ) {}

    /**
     * @param  string  $eventKey  Registered key in config/notifications-events.php
     * @param  array  $args  Constructor args for the notification class
     * @param  array  $context  Auxiliary data used for recipient resolution (e.g. ['order' => [...]])
     * @param  string|null  $relatedId  Identifier for dedup (e.g. order id, po id). Null = global.
     */
    public function dispatch(
        string $eventKey,
        int $organizationId,
        array $args = [],
        array $context = [],
        ?string $relatedId = null,
    ): void {
        $events = config('notifications-events.events', []);
        $event = $events[$eventKey] ?? null;

        if (! $event) {
            Log::warning('notification.event.unregistered', ['event_key' => $eventKey]);

            return;
        }

        $class = $event['class'] ?? null;
        if (! $class || ! class_exists($class) || ! is_subclass_of($class, Notification::class)) {
            throw new InvalidArgumentException("notification class for {$eventKey} is invalid: {$class}");
        }

        $rule = NotificationRule::where('organization_id', $organizationId)
            ->where('event_key', $eventKey)
            ->where('active', true)
            ->first();

        $dedupMinutes = $rule->dedup_minutes
            ?? $event['dedup_minutes']
            ?? config('notifications-events.defaults.dedup_minutes', 60);

        if (! $this->dedup->claim($organizationId, $eventKey, $relatedId, $dedupMinutes)) {
            return;
        }

        $this->fireStep($eventKey, $organizationId, $rule, $event, 0, $args, $context);

        if ($rule && $rule->escalation_minutes) {
            EscalateNotificationJob::dispatch(
                eventKey: $eventKey,
                organizationId: $organizationId,
                args: $args,
                context: $context,
                relatedId: $relatedId,
                step: 1,
            )->delay(now()->addMinutes($rule->escalation_minutes));
        }
    }

    public function fireStep(
        string $eventKey,
        int $organizationId,
        ?NotificationRule $rule,
        array $event,
        int $step,
        array $args,
        array $context,
    ): void {
        $class = $event['class'];

        $recipients = $rule
            ? $this->resolver->resolveForStep($rule, $step, $context)
            : $this->resolver->resolveDefaults($event['default_recipients'] ?? [], $organizationId, $step, $context);

        if ($recipients->isEmpty()) {
            Log::info('notification.event.no_recipients', [
                'event_key' => $eventKey,
                'organization_id' => $organizationId,
                'step' => $step,
            ]);

            return;
        }

        $notification = new $class(...$args);

        foreach ($recipients as $notifiable) {
            try {
                $notifiable->notify($notification);
            } catch (\Throwable $e) {
                Log::error('notification.event.dispatch_failed', [
                    'event_key' => $eventKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
