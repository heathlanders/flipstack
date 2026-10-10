<?php

namespace App\Jobs\Notification;

use App\Models\Notification\NotificationRule;
use App\Services\NotificationDispatch\EventDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EscalateNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $eventKey,
        public int $organizationId,
        public array $args,
        public array $context,
        public ?string $relatedId,
        public int $step,
    ) {}

    public function handle(EventDispatcher $dispatcher): void
    {
        $events = config('notifications-events.events', []);
        $event = $events[$this->eventKey] ?? null;
        if (! $event) {
            return;
        }

        $rule = NotificationRule::where('organization_id', $this->organizationId)
            ->where('event_key', $this->eventKey)
            ->where('active', true)
            ->first();

        if (! $rule) {
            return;
        }

        $hasMore = $rule->recipients()->where('escalation_step', $this->step)->exists();
        if (! $hasMore) {
            return;
        }

        $dispatcher->fireStep(
            eventKey: $this->eventKey,
            organizationId: $this->organizationId,
            rule: $rule,
            event: $event,
            step: $this->step,
            args: $this->args,
            context: $this->context,
        );

        if ($rule->escalation_minutes) {
            self::dispatch(
                eventKey: $this->eventKey,
                organizationId: $this->organizationId,
                args: $this->args,
                context: $this->context,
                relatedId: $this->relatedId,
                step: $this->step + 1,
            )->delay(now()->addMinutes($rule->escalation_minutes));
        }
    }
}
