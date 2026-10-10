<?php

namespace App\Services\NotificationDispatch;

use App\Models\Notification\NotificationRule;
use App\Models\User\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Resolves NotificationRule recipient rows into concrete notifiable targets.
 *
 * Returns a flat collection mixing User models and AnonymousNotifiable
 * instances (for external_email / supplier / customer routes). Callers iterate
 * and call ->notify() on each.
 */
class RecipientResolver
{
    public function resolveForStep(NotificationRule $rule, int $step, array $context): Collection
    {
        $recipients = $rule->recipientsAtStep($step)->get();

        return $recipients->flatMap(fn ($r) => $this->resolveOne($r->recipient_type, $r->recipient_value, $rule->organization_id, $context));
    }

    public function resolveDefaults(array $defaults, int $organizationId, int $step, array $context): Collection
    {
        return collect($defaults)
            ->filter(fn ($d) => ($d['step'] ?? 0) === $step)
            ->flatMap(fn ($d) => $this->resolveOne($d['type'], $d['value'], $organizationId, $context));
    }

    private function resolveOne(string $type, string $value, int $organizationId, array $context): Collection
    {
        return match ($type) {
            'user' => collect(User::where('id', $value)
                ->where('organization_id', $organizationId)
                ->get()),

            'role' => User::where('organization_id', $organizationId)
                ->whereHas('roles', fn ($q) => $q->where('slug', $value))
                ->get()
                ->collect(),

            'external_email' => collect([
                NotificationFacade::route('mail', $value),
            ]),

            'supplier' => $this->resolveSupplier($value, $organizationId),

            'customer' => $this->resolveCustomer($value, $context),

            default => collect(),
        };
    }

    private function resolveSupplier(string $value, int $organizationId): Collection
    {
        $supplierClass = '\App\Models\Inventory\Supplier';
        if (! class_exists($supplierClass)) {
            return collect();
        }

        $supplier = $supplierClass::where('id', $value)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $supplier || ! ($supplier->email ?? null)) {
            return collect();
        }

        return collect([NotificationFacade::route('mail', $supplier->email)]);
    }

    private function resolveCustomer(string $value, array $context): Collection
    {
        // Convention: value is a context path like "order.customer_email"
        // pulled from the dispatch context array.
        $email = data_get($context, str_replace('.', '.', $value));

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return collect();
        }

        return collect([NotificationFacade::route('mail', $email)]);
    }
}
