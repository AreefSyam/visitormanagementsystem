@extends('layouts.app')

@section('title', 'Visit Analytics')

@section('actions')
    <form method="POST" action="{{ route('analytics.export') }}" class="inline-block">
        @csrf
        <input type="hidden" name="period" value="{{ $currentPeriod }}">
        @if($currentPeriod === 'custom')
            <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
            <input type="hidden" name="end_date" value="{{ $endDate->format('Y-m-d') }}">
        @endif
        <button type="submit"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export PDF
        </button>
    </form>
@endsection

@section('content')

{{-- Alpine.js Filter Controller --}}
<div x-data="filterController()" class="mb-6 bg-white rounded-xl border border-gray-200 p-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        {{-- Period Filter Buttons --}}
        <div class="flex flex-wrap gap-2">
            <button type="button"
                    @click="selectPeriod('today')"
                    :class="period === 'today' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                Today
            </button>
            <button type="button"
                    @click="selectPeriod('this_week')"
                    :class="period === 'this_week' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                This Week
            </button>
            <button type="button"
                    @click="selectPeriod('this_month')"
                    :class="period === 'this_month' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                This Month
            </button>
            <button type="button"
                    @click="selectPeriod('custom')"
                    :class="period === 'custom' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                Custom Range
            </button>
        </div>

        {{-- Current Period Display --}}
        <div class="text-sm text-gray-600">
            <span class="font-medium">Period:</span>
            {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
        </div>
    </div>

    {{-- Custom Date Range Inputs --}}
    <div x-show="showCustomInputs" x-collapse class="mt-4 pt-4 border-t border-gray-200">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date"
                       id="start_date"
                       x-model="startDate"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex-1">
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date"
                       id="end_date"
                       x-model="endDate"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="button"
                        @click="applyFilter()"
                        class="w-full md:w-auto px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Apply
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Flash Messages --}}
@if(session('info_weekly'))
    <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg text-sm" role="alert">
        {{ session('info_weekly') }}
    </div>
@endif
@if(session('info_monthly'))
    <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg text-sm" role="alert">
        {{ session('info_monthly') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm" role="alert">
        {{ session('error') }}
    </div>
@endif

{{-- KPI Cards Grid --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    @php
        $cards = [
            [
                'label' => 'Total Visits',
                'value' => $kpiMetrics['total_visits'],
                'sub' => 'in selected period',
                'color' => 'bg-blue-50 text-blue-600',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            ],
            [
                'label' => 'Active Visits',
                'value' => $kpiMetrics['active_visits'],
                'sub' => 'currently on-site',
                'color' => 'bg-green-50 text-green-600',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            [
                'label' => 'Completed Visits',
                'value' => $kpiMetrics['completed_visits'],
                'sub' => 'checked out',
                'color' => 'bg-purple-50 text-purple-600',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            [
                'label' => 'Avg Duration',
                'value' => $avgDuration['formatted'] ?? 'N/A',
                'sub' => $avgDuration['completed_count'] . ' completed visits',
                'color' => 'bg-orange-50 text-orange-600',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
        ];
    @endphp

    @foreach($cards as $card)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">
                        @if(is_numeric($card['value']))
                            {{ number_format($card['value']) }}
                        @else
                            {{ $card['value'] }}
                        @endif
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ $card['sub'] }}</p>
                </div>
                <div class="w-10 h-10 {{ $card['color'] }} rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        {!! $card['icon'] !!}
                    </svg>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Charts Row 1: Daily/Weekly/Monthly Trends --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    {{-- Daily Trend Chart --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Daily Visit Trend</h3>
        @if($dailyTrend->isEmpty())
            <div class="text-center py-10">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p class="text-sm text-gray-500">No visits found for this period</p>
            </div>
        @else
            <div id="dailyTrendChart" role="img" aria-label="Daily visit trend showing {{ $dailyTrend->sum('count') }} total visits"></div>
            {{-- Hidden data table for screen readers --}}
            <table class="sr-only">
                <caption>Daily visit counts</caption>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Visit Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dailyTrend as $day)
                    <tr>
                        <td>{{ $day->date }}</td>
                        <td>{{ $day->count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Weekly Trend Chart --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Weekly Visit Trend</h3>
        @if($weeklyTrend === null)
            <div class="text-center py-10">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-gray-500">{{ session('info_weekly', 'Insufficient data for weekly trend') }}</p>
            </div>
        @elseif($weeklyTrend->isEmpty())
            <div class="text-center py-10">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p class="text-sm text-gray-500">No visits found for this period</p>
            </div>
        @else
            <div id="weeklyTrendChart" role="img" aria-label="Weekly visit trend showing {{ $weeklyTrend->sum('count') }} total visits"></div>
        @endif
    </div>
</div>

{{-- Charts Row 2: Monthly Trend --}}
<div class="grid grid-cols-1 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Monthly Visit Trend</h3>
        @if($monthlyTrend === null)
            <div class="text-center py-10">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-gray-500">{{ session('info_monthly', 'Insufficient data for monthly trend') }}</p>
            </div>
        @elseif($monthlyTrend->isEmpty())
            <div class="text-center py-10">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p class="text-sm text-gray-500">No visits found for this period</p>
            </div>
        @else
            <div id="monthlyTrendChart" role="img" aria-label="Monthly visit trend showing {{ $monthlyTrend->sum('count') }} total visits"></div>
        @endif
    </div>
</div>

{{-- Charts Row 3: Peak Hours & Purpose Breakdown --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    {{-- Peak Hours Chart --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-base font-semibold text-gray-800 mb-1">Peak Visiting Hours</h3>
        <p class="text-xs text-gray-400 mb-4">Check-in times by hour</p>
        @if(empty($peakHours['hourly_counts']) || array_sum($peakHours['hourly_counts']) === 0)
            <div class="text-center py-10">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-gray-500">No check-ins found for this period</p>
            </div>
        @else
            <div id="peakHoursChart" role="img" aria-label="Peak visiting hours chart"></div>
            @if(!empty($peakHours['peak_hours']))
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($peakHours['peak_hours'] as $hour)
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-50 text-green-700 text-xs font-medium rounded-full">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Peak: {{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00
                        </span>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    {{-- Purpose Breakdown Chart --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-base font-semibold text-gray-800 mb-1">Visit Purpose Breakdown</h3>
        <p class="text-xs text-gray-400 mb-4">Top reasons for visits</p>
        @if($purposeBreakdown->isEmpty())
            <div class="text-center py-10">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm text-gray-500">No visit purposes recorded</p>
            </div>
        @else
            <div id="purposeChart" role="img" aria-label="Visit purpose breakdown chart"></div>
        @endif
    </div>
</div>

{{-- ARIA Live Region for Filter Changes --}}
<div aria-live="polite" class="sr-only" id="filterAnnouncement"></div>

@endsection

@push('scripts')
{{-- ApexCharts CDN --}}
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"
        integrity="sha384-7xZJCIfsIYUjmSPWNjQfMohxRfkGM05FkKLXwTa8vR0FT4D4dW/z9HcqJ0LOhDmq"
        crossorigin="anonymous"></script>

<script>
// Alpine.js Filter Controller
function filterController() {
    return {
        period: "{{ $currentPeriod }}",
        startDate: "{{ $currentPeriod === 'custom' ? $startDate->format('Y-m-d') : '' }}",
        endDate: "{{ $currentPeriod === 'custom' ? $endDate->format('Y-m-d') : '' }}",
        showCustomInputs: {{ $currentPeriod === 'custom' ? 'true' : 'false' }},

        selectPeriod(period) {
            this.period = period;
            this.showCustomInputs = period === 'custom';
            if (period !== 'custom') {
                this.applyFilter();
            }
        },

        applyFilter() {
            const params = new URLSearchParams({
                period: this.period,
            });
            
            if (this.period === 'custom') {
                params.append('start_date', this.startDate);
                params.append('end_date', this.endDate);
            }
            
            // Announce filter change for screen readers
            const announcement = document.getElementById('filterAnnouncement');
            announcement.textContent = 'Filtering analytics by ' + this.period.replace('_', ' ');
            
            window.location.href = `{{ route('analytics.index') }}?${params}`;
        }
    };
}

// Chart Data from PHP
const dailyTrendData = @json($dailyTrend);
const weeklyTrendData = @json($weeklyTrend);
const monthlyTrendData = @json($monthlyTrend);
const peakHoursData = @json($peakHours);
const purposeBreakdownData = @json($purposeBreakdown);

// Initialize Daily Trend Chart
@if(!$dailyTrend->isEmpty())
const dailyTrendOptions = {
    chart: {
        type: 'line',
        height: 300,
        toolbar: { show: false },
        animations: { enabled: true },
    },
    series: [{
        name: 'Visits',
        data: dailyTrendData.map(d => d.count),
    }],
    xaxis: {
        categories: dailyTrendData.map(d => d.date),
        labels: {
            formatter: function(val) {
                if (!val) return '';
                const date = new Date(val);
                return date.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
            }
        }
    },
    yaxis: {
        title: { text: 'Visit Count' },
        labels: { formatter: val => Math.round(val) }
    },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    colors: ['#4F46E5'],
    tooltip: {
        x: { 
            formatter: function(val) {
                const date = new Date(dailyTrendData[val - 1].date);
                return date.toLocaleDateString('en-MY', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            }
        }
    },
    dataLabels: { enabled: false }
};

new ApexCharts(document.querySelector('#dailyTrendChart'), dailyTrendOptions).render();
@endif

// Initialize Weekly Trend Chart
@if($weeklyTrend !== null && !$weeklyTrend->isEmpty())
const weeklyTrendOptions = {
    chart: {
        type: 'line',
        height: 300,
        toolbar: { show: false },
        animations: { enabled: true },
    },
    series: [{
        name: 'Visits',
        data: weeklyTrendData.map(d => d.count),
    }],
    xaxis: {
        categories: weeklyTrendData.map(d => d.week_start),
        labels: {
            formatter: function(val) {
                if (!val) return '';
                const date = new Date(val);
                return date.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
            }
        }
    },
    yaxis: {
        title: { text: 'Visit Count' },
        labels: { formatter: val => Math.round(val) }
    },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    colors: ['#4F46E5'],
    tooltip: {
        x: { 
            formatter: function(val) {
                const date = new Date(weeklyTrendData[val - 1].week_start);
                return 'Week of ' + date.toLocaleDateString('en-MY', { month: 'short', day: 'numeric', year: 'numeric' });
            }
        }
    },
    dataLabels: { enabled: false }
};

new ApexCharts(document.querySelector('#weeklyTrendChart'), weeklyTrendOptions).render();
@endif

// Initialize Monthly Trend Chart
@if($monthlyTrend !== null && !$monthlyTrend->isEmpty())
const monthlyTrendOptions = {
    chart: {
        type: 'line',
        height: 300,
        toolbar: { show: false },
        animations: { enabled: true },
    },
    series: [{
        name: 'Visits',
        data: monthlyTrendData.map(d => d.count),
    }],
    xaxis: {
        categories: monthlyTrendData.map(d => d.label),
    },
    yaxis: {
        title: { text: 'Visit Count' },
        labels: { formatter: val => Math.round(val) }
    },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    colors: ['#4F46E5'],
    dataLabels: { enabled: false }
};

new ApexCharts(document.querySelector('#monthlyTrendChart'), monthlyTrendOptions).render();
@endif

// Initialize Peak Hours Chart
@if(!empty($peakHours['hourly_counts']) && array_sum($peakHours['hourly_counts']) > 0)
const peakHour = {{ !empty($peakHours['peak_hours']) ? $peakHours['peak_hours'][0] : 'null' }};
const hourlyCountsArray = @json(array_values($peakHours['hourly_counts']));

const peakHoursOptions = {
    chart: {
        type: 'bar',
        height: 300,
        toolbar: { show: false }
    },
    series: [{
        name: 'Check-ins',
        data: hourlyCountsArray
    }],
    xaxis: {
        categories: Array.from({length: 24}, (_, i) => String(i).padStart(2, '0') + ':00'),
        title: { text: 'Hour of Day' }
    },
    yaxis: {
        title: { text: 'Check-in Count' },
        labels: { formatter: val => Math.round(val) }
    },
    colors: Array.from({length: 24}, (_, i) => i === peakHour ? '#10B981' : '#6366F1'),
    plotOptions: {
        bar: {
            distributed: true,
            borderRadius: 4
        }
    },
    legend: { show: false },
    dataLabels: { enabled: false }
};

new ApexCharts(document.querySelector('#peakHoursChart'), peakHoursOptions).render();
@endif

// Initialize Purpose Breakdown Chart
@if(!$purposeBreakdown->isEmpty())
const purposeOptions = {
    chart: {
        type: 'bar',
        height: 350,
        toolbar: { show: false }
    },
    series: [{
        name: 'Visits',
        data: purposeBreakdownData.map(p => p.count)
    }],
    xaxis: {
        categories: purposeBreakdownData.map(p => p.purpose),
        title: { text: 'Visit Count' }
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '70%',
            borderRadius: 4
        }
    },
    colors: ['#8B5CF6'],
    dataLabels: {
        enabled: true,
        formatter: function(val, opts) {
            const percentage = purposeBreakdownData[opts.dataPointIndex].percentage;
            return `${val} (${percentage}%)`;
        }
    }
};

new ApexCharts(document.querySelector('#purposeChart'), purposeOptions).render();
@endif
</script>
@endpush
