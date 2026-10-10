<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $label }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { color: #6b7280; font-size: 9px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; padding: 6px 4px; border-bottom: 1px solid #d1d5db; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
        td { padding: 4px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
        tr:nth-child(even) td { background: #fafafa; }
        .empty { color: #9ca3af; padding: 30px; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $label }}</h1>
    <div class="meta">
        Period: {{ $filters['start_date'] ?? '—' }} to {{ $filters['end_date'] ?? '—' }}
        @if (! empty($filters['compare_start_date']))
            · Compare: {{ $filters['compare_start_date'] }} to {{ $filters['compare_end_date'] }}
        @endif
        · Generated {{ now()->toDateTimeString() }}
    </div>

    @if (empty($rows))
        <div class="empty">No data for this period.</div>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach (array_keys($columns) as $key)
                            @php
                                $val = is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');
                                $display = is_scalar($val) || $val === null ? $val : json_encode($val);
                            @endphp
                            <td>{{ $display }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
