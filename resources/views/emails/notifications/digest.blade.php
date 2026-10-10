@php
    $label = ucfirst($frequency);
    $appUrl = config('app.url');
    $categoryLabels = [
        'orders' => 'Orders',
        'inventory' => 'Inventory',
        'financial' => 'Financial',
        'platform' => 'Platforms',
        'customer' => 'Customers',
        'system' => 'System',
        'other' => 'Other',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $label }} Notification Digest</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#f6f8fa; margin:0; padding:24px; color:#1a202c;">
    <div style="max-width: 640px; margin: 0 auto; background:#ffffff; border-radius:8px; padding:24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <h1 style="font-size:20px; margin:0 0 4px; color:#1a202c;">{{ $label }} Notification Digest</h1>
        <p style="margin:0 0 16px; color:#4a5568;">
            Hi {{ $user->name }}, here are the {{ $notifications->count() }} unread notification(s) since your last {{ $frequency }} digest.
        </p>

        @foreach ($grouped as $category => $items)
            <h2 style="font-size:14px; text-transform:uppercase; letter-spacing:0.05em; color:#718096; border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-top:24px;">
                {{ $categoryLabels[$category] ?? ucfirst($category) }}
                <span style="color:#a0aec0; font-weight:normal;">({{ $items->count() }})</span>
            </h2>
            <ul style="list-style:none; padding:0; margin:0;">
                @foreach ($items as $n)
                    @php
                        $title = data_get($n->data, 'title') ?: data_get($n->data, 'type', 'Notification');
                        $msg = data_get($n->data, 'message');
                        $action = data_get($n->data, 'action_url');
                        $actionText = data_get($n->data, 'action_text', 'View');
                        $priority = data_get($n->data, 'priority', 'medium');
                        $color = match($priority) {
                            'critical' => '#e53e3e',
                            'high' => '#dd6b20',
                            'medium' => '#3182ce',
                            'low' => '#38a169',
                            default => '#718096',
                        };
                    @endphp
                    <li style="border-left:3px solid {{ $color }}; padding:10px 12px; margin:8px 0; background:#f7fafc; border-radius:4px;">
                        <strong style="display:block; color:#1a202c;">{{ $title }}</strong>
                        @if ($msg)
                            <span style="color:#4a5568; font-size:14px;">{{ $msg }}</span>
                        @endif
                        @if ($action)
                            <div style="margin-top:6px;">
                                <a href="{{ str_starts_with($action, 'http') ? $action : rtrim($appUrl, '/') . $action }}"
                                   style="color:#3182ce; text-decoration:none; font-size:13px;">{{ $actionText }} →</a>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach

        <div style="margin-top:32px; padding-top:16px; border-top:1px solid #e2e8f0; color:#a0aec0; font-size:12px;">
            <a href="{{ rtrim($appUrl, '/') }}/notifications" style="color:#3182ce; text-decoration:none;">View all notifications</a>
            &middot;
            <a href="{{ rtrim($appUrl, '/') }}/notifications/preferences" style="color:#3182ce; text-decoration:none;">Manage digest frequency</a>
        </div>
    </div>
</body>
</html>
