<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Visit Analytics Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #1f2937;
            padding: 20px;
        }

        .header {
            border-bottom: 3px solid #4F46E5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #4F46E5;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .header .meta {
            color: #6b7280;
            font-size: 11px;
        }

        .header .meta span {
            margin-right: 15px;
        }

        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e5e7eb;
        }

        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .kpi-row {
            display: table-row;
        }

        .kpi-cell {
            display: table-cell;
            width: 25%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }

        .kpi-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
        }

        .kpi-sub {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 11px;
        }

        table thead {
            background-color: #f3f4f6;
        }

        table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            color: #374151;
            border-bottom: 2px solid #d1d5db;
        }

        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
        }

        .badge-green {
            background-color: #d1fae5;
            color: #065f46;
        }

        .no-data {
            text-align: center;
            color: #9ca3af;
            padding: 30px;
            font-style: italic;
        }

        .page-break {
            page-break-after: always;
        }

        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <h1>📊 Visit Analytics Report</h1>
        <div class="meta">
            <span><strong>Period:</strong> {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</span>
            <span><strong>Generated:</strong> {{ $generatedAt->format('d M Y H:i') }}</span>
            <span><strong>Filter:</strong> {{ ucwords(str_replace('_', ' ', $period)) }}</span>
        </div>
    </div>

    {{-- KPI Metrics Section --}}
    <div class="section">
        <h2 class="section-title">Key Performance Indicators</h2>
        <div class="kpi-grid">
            <div class="kpi-row">
                <div class="kpi-cell">
                    <div class="kpi-label">Total Visits</div>
                    <div class="kpi-value">{{ number_format($kpiMetrics['total_visits']) }}</div>
                    <div class="kpi-sub">in selected period</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-label">Active Visits</div>
                    <div class="kpi-value">{{ number_format($kpiMetrics['active_visits']) }}</div>
                    <div class="kpi-sub">currently on-site</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-label">Completed Visits</div>
                    <div class="kpi-value">{{ number_format($kpiMetrics['completed_visits']) }}</div>
                    <div class="kpi-sub">checked out</div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-label">Avg Duration</div>
                    <div class="kpi-value">{{ $avgDuration['formatted'] ?? 'N/A' }}</div>
                    <div class="kpi-sub">{{ $avgDuration['completed_count'] }} completed</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daily Trend Data --}}
    <div class="section">
        <h2 class="section-title">Daily Visit Trend</h2>
        @if ($dailyTrend->isEmpty())
            <p class="no-data">No visits recorded during this period</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Visit Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dailyTrend as $day)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($day->date)->format('D, d M Y') }}</td>
                            <td class="text-right">{{ number_format($day->count) }}</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight: bold; background-color: #f3f4f6;">
                        <td>Total</td>
                        <td class="text-right">{{ number_format($dailyTrend->sum('count')) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    {{-- Weekly Trend Data --}}
    @if ($weeklyTrend !== null && !$weeklyTrend->isEmpty())
        <div class="section">
            <h2 class="section-title">Weekly Visit Trend</h2>
            <table>
                <thead>
                    <tr>
                        <th>Week Starting</th>
                        <th class="text-right">Visit Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeklyTrend as $week)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($week->week_start)->format('d M Y') }}</td>
                            <td class="text-right">{{ number_format($week->count) }}</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight: bold; background-color: #f3f4f6;">
                        <td>Total</td>
                        <td class="text-right">{{ number_format($weeklyTrend->sum('count')) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- Monthly Trend Data --}}
    @if ($monthlyTrend !== null && !$monthlyTrend->isEmpty())
        <div class="section page-break">
            <h2 class="section-title">Monthly Visit Trend</h2>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Visit Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monthlyTrend as $month)
                        <tr>
                            <td>{{ $month->label }}</td>
                            <td class="text-right">{{ number_format($month->count) }}</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight: bold; background-color: #f3f4f6;">
                        <td>Total</td>
                        <td class="text-right">{{ number_format($monthlyTrend->sum('count')) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- Peak Hours Data --}}
    @if (!empty($peakHours['hourly_counts']) && array_sum($peakHours['hourly_counts']) > 0)
        <div class="section">
            <h2 class="section-title">Peak Visiting Hours</h2>
            @if (!empty($peakHours['peak_hours']))
                <p style="margin-bottom: 10px; color: #065f46; font-weight: bold;">
                    🏆 Peak Hours:
                    @foreach ($peakHours['peak_hours'] as $hour)
                        {{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00{{ !$loop->last ? ', ' : '' }}
                    @endforeach
                </p>
            @endif
            <table>
                <thead>
                    <tr>
                        <th>Hour</th>
                        <th class="text-right">Check-ins</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($peakHours['hourly_counts'] as $hour => $count)
                        @if ($count > 0)
                            <tr>
                                <td>{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00 -
                                    {{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:59</td>
                                <td class="text-right">{{ number_format($count) }}</td>
                                <td class="text-center">
                                    @if (in_array($hour, $peakHours['peak_hours']))
                                        <span class="badge badge-green">Peak</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Purpose Breakdown --}}
    @if (!$purposeBreakdown->isEmpty())
        <div class="section">
            <h2 class="section-title">Visit Purpose Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purposeBreakdown as $item)
                        <tr>
                            <td>{{ $item->purpose }}</td>
                            <td class="text-right">{{ number_format($item->count) }}</td>
                            <td class="text-right">{{ number_format($item->percentage, 1) }}%</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight: bold; background-color: #f3f4f6;">
                        <td>Total</td>
                        <td class="text-right">{{ number_format($purposeBreakdown->sum('count')) }}</td>
                        <td class="text-right">100%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <p>This report was generated automatically by the Visitor Management System</p>
        <p>© {{ now()->year }} - Confidential Information</p>
    </div>
</body>

</html>
