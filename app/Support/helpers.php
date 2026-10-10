<?php

use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use App\Support\AuditHelper;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('audit_event')) {
    function audit_event(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $category = null,
        ?User $actor = null,
        ?Organization $organization = null,
    ): ?UserActivityLog {
        return AuditHelper::event($action, $description, $subject, $metadata, $severity, $category, $actor, $organization);
    }
}

if (! function_exists('audit_security')) {
    function audit_security(string $action, string $description, array $metadata = []): ?UserActivityLog
    {
        return AuditHelper::security($action, $description, $metadata);
    }
}

if (! function_exists('withAuditActor')) {
    function withAuditActor(?User $actor, Closure $callback, array $metadata = [], ?Organization $organization = null): mixed
    {
        return AuditHelper::withActor($actor, $callback, $metadata, $organization);
    }
}
