<!doctype html>
<html>
<body style="font-family: -apple-system, sans-serif; color: #1f2937;">
    <h2 style="margin: 0 0 8px;">{{ $report->name }}</h2>
    <p style="margin: 0 0 16px; color: #6b7280;">
        Scheduled report — {{ ucfirst($report->frequency) }} digest from FlipStack Analytics.
    </p>
    <p>The latest data is attached as a CSV. Filter range: {{ $report->filters['start_date'] ?? 'last 30 days' }} → {{ $report->filters['end_date'] ?? 'today' }}.</p>
    <p style="margin-top: 24px; font-size: 12px; color: #9ca3af;">
        To unsubscribe or change frequency, edit this report in Analytics → Reports.
    </p>
</body>
</html>
