# Design Document: Dashboard Analysis Module

## Overview

The Dashboard Analysis Module extends the existing visitor management dashboard with comprehensive visit analytics capabilities. The module provides administrators with time-filtered visualizations (daily/weekly/monthly trends, peak hours, visit duration statistics, and purpose breakdowns) and PDF export functionality.

### Technology Stack

- **Backend**: Laravel 13.8 (PHP 8.3)
- **Frontend**: Blade templates with Tailwind CSS 4.0
- **Charting**: [ApexCharts](https://apexcharts.com/) (modern, interactive, MIT-licensed, ~130KB gzipped)
- **PDF Generation**: [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) (widely adopted, pure PHP, no external dependencies)
- **Database**: Existing `visits`, `hosts`, `visitors` tables with indexes on `check_in_at` and `status`

### Design Principles

1. **Query Efficiency**: All aggregations performed at database level using Eloquent query scopes and raw SQL expressions
2. **Minimal JavaScript**: Use Alpine.js (Blade stack convention) for filter interactions; ApexCharts for visualization
3. **Responsive First**: Mobile-to-desktop layout using Tailwind breakpoints
4. **Accessibility**: ARIA labels on charts, keyboard navigation for filters, screen reader announcements
5. **Separation of Concerns**: Analytics logic in dedicated service class; controller handles HTTP; views handle presentation

## Architecture

### High-Level Component Structure

```
┌─────────────────────────────────────────────────────────────┐
│                     Browser / Client                         │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  analytics.blade.php (Blade + Alpine.js + ApexCharts)  │ │
│  └─────────────────┬────────────────────────────────────┬─┘ │
└────────────────────┼────────────────────────────────────┼───┘
                     │ HTTP GET                           │ POST
                     │ /dashboard/analytics               │ /dashboard/analytics/export-pdf
                     ▼                                    ▼
┌─────────────────────────────────────────────────────────────┐
│                   AnalyticsController                        │
│  - index(): filters, passes to service, returns view         │
│  - exportPdf(): generates PDF via service, returns download  │
└─────────────────────┬──────────────────────────────────────┘
                      │ calls
                      ▼
┌─────────────────────────────────────────────────────────────┐
│               VisitAnalyticsService                          │
│  - getKpiMetrics(period)                                     │
│  - getDailyTrend(period)                                     │
│  - getWeeklyTrend(period)                                    │
│  - getMonthlyTrend(period)                                   │
│  - getPeakHours(period)                                      │
│  - getAverageDuration(period)                                │
│  - getPurposeBreakdown(period)                               │
└─────────────────────┬──────────────────────────────────────┘
                      │ queries
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                Visit Model + Query Scopes                    │
│  - scopeInPeriod($query, $start, $end)                      │
│  - scopeCompleted($query)                                    │
│  - scopeExcludingCancelled($query)                           │
└─────────────────────┬──────────────────────────────────────┘
                      │ reads
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database (MySQL/PostgreSQL)               │
│  visits (id, visitor_id, host_id, purpose, check_in_at,     │
│          check_out_at, status, badge_number, notes)          │
│  Indexes: check_in_at, status, [visitor_id, check_in_at]    │
└─────────────────────────────────────────────────────────────┘
```

### Request Flow

1. **Filter Selection**: User selects time period (today/week/month/custom) → Alpine.js updates query params → GET `/dashboard/analytics?period=this_week&start=&end=`
2. **Data Retrieval**: Controller validates filters → calls `VisitAnalyticsService` methods → Service queries Visit model with scopes → returns structured arrays
3. **Rendering**: Controller passes data to Blade view → ApexCharts renders line/bar charts client-side → Tailwind CSS applies responsive styling
4. **PDF Export**: User clicks "Export PDF" → POST `/dashboard/analytics/export-pdf` with current filters → Service generates HTML via Blade partial → Dompdf converts to PDF → Controller returns file download

## Components and Interfaces

### 1. AnalyticsController

**Location**: `app/Http/Controllers/AnalyticsController.php`

**Responsibilities**:

- Parse and validate time period query parameters
- Delegate analytics calculations to service
- Return JSON for AJAX requests (future enhancement)
- Handle PDF export requests

**Methods**:

```php
class AnalyticsController extends Controller
{
    public function __construct(
        private VisitAnalyticsService $analyticsService
    ) {}

    /**
     * Display analytics dashboard with filters
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        // Validate: period (enum), start_date, end_date
        // Build DatePeriod object
        // Call service methods
        // Return view with data + filter state
    }

    /**
     * Export current analytics view as PDF
     *
     * @param Request $request
     * @return Response (PDF download)
     */
    public function exportPdf(Request $request): Response
    {
        // Validate same filters as index()
        // Generate data via service
        // Render pdf.blade.php partial with chart images
        // Return PDF with filename visit-analytics-report-{date}.pdf
    }
}
```

**Routes** (added to `routes/web.php`):

```php
Route::get('/dashboard/analytics', [AnalyticsController::class, 'index'])
    ->name('analytics.index')
    ->middleware(['auth', 'admin']); // admin middleware to be created

Route::post('/dashboard/analytics/export-pdf', [AnalyticsController::class, 'exportPdf'])
    ->name('analytics.export')
    ->middleware(['auth', 'admin']);
```

### 2. VisitAnalyticsService

**Location**: `app/Services/VisitAnalyticsService.php`

**Responsibilities**:

- Encapsulate all analytics query logic
- Return structured data (arrays/collections) for view consumption
- Cache results per request lifecycle (optional: Redis cache for expensive queries)

**Interface**:

```php
class VisitAnalyticsService
{
    /**
     * Get KPI metrics: total visits, active, completed, average duration
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array ['total_visits' => int, 'active_visits' => int,
     *                'completed_visits' => int, 'avg_duration' => ?string]
     */
    public function getKpiMetrics(Carbon $startDate, Carbon $endDate): array;

    /**
     * Get daily visit counts grouped by date
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{date: '2026-01-15', count: 23}, ...]
     */
    public function getDailyTrend(Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Get weekly visit counts grouped by ISO week start date
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{week_start: '2026-01-13', count: 87}, ...]
     * @throws InsufficientDataException if period < 14 days
     */
    public function getWeeklyTrend(Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Get monthly visit counts grouped by month
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{month: '2026-01', label: 'Jan 2026', count: 234}, ...]
     * @throws InsufficientDataException if period < 30 days
     */
    public function getMonthlyTrend(Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Get visit counts by hour (0-23) and identify peak hours
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array ['hourly_counts' => [0 => 5, 1 => 2, ..., 23 => 8],
     *                'peak_hours' => [14, 15, 10]]
     */
    public function getPeakHours(Carbon $startDate, Carbon $endDate): array;

    /**
     * Calculate average duration from completed visits
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array ['avg_minutes' => ?float, 'formatted' => ?string,
     *                'completed_count' => int]
     */
    public function getAverageDuration(Carbon $startDate, Carbon $endDate): array;

    /**
     * Get visit counts grouped by purpose (top 10)
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{purpose: 'Meeting', count: 45, percentage: 23.4}, ...]
     */
    public function getPurposeBreakdown(Carbon $startDate, Carbon $endDate): Collection;
}
```

**Implementation Notes**:

- Use `Visit::query()->inPeriod($start, $end)->excludingCancelled()` as base query
- Group by date: `DB::raw("DATE(check_in_at) as date")`
- Group by hour: `DB::raw("HOUR(check_in_at) as hour")`
- Average duration: `AVG(TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at))`
- Handle null purposes: `COALESCE(purpose, 'Unspecified')`

### 3. Visit Model Enhancements

**Location**: `app/Models/Visit.php` (extend existing)

**New Query Scopes**:

```php
/**
 * Scope to filter visits within a date range
 */
public function scopeInPeriod($query, Carbon $startDate, Carbon $endDate)
{
    return $query->whereBetween('check_in_at', [
        $startDate->startOfDay(),
        $endDate->endOfDay()
    ]);
}

/**
 * Scope to get only completed visits (with check-out)
 */
public function scopeCompleted($query)
{
    return $query->where('status', 'checked_out')
                 ->whereNotNull('check_out_at');
}

/**
 * Scope to exclude cancelled visits
 */
public function scopeExcludingCancelled($query)
{
    return $query->where('status', '!=', 'cancelled');
}
```

### 4. Admin Middleware

**Location**: `app/Http/Middleware/EnsureUserIsAdmin.php` (new)

**Purpose**: Restrict analytics dashboard to administrators only (Requirement 1)

**Implementation**:

```php
public function handle(Request $request, Closure $next)
{
    if (! $request->user() || ! $request->user()->isAdmin()) {
        abort(403, 'Unauthorized access');
    }
    return $next($request);
}
```

**User Model Enhancement**:

```php
// Add to User model
public function isAdmin(): bool
{
    return $this->role === 'admin'; // Assumes 'role' column exists
}
```

**Migration** (if role column doesn't exist):

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('user')->after('email');
    $table->index('role');
});
```

### 5. Frontend Components

#### Analytics View

**Location**: `resources/views/analytics/index.blade.php`

**Structure**:

```blade
@extends('layouts.app')

@section('title', 'Visit Analytics')

@section('content')

{{-- Time Period Filter Bar --}}
<div x-data="filterController()" class="mb-6 bg-white rounded-xl border p-4">
    {{-- Period buttons: Today, This Week, This Month, Custom Range --}}
    {{-- Custom date pickers (show when custom selected) --}}
    {{-- Apply button submits GET request --}}
</div>

{{-- KPI Cards Grid --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    {{-- Total Visits, Active, Completed, Avg Duration --}}
</div>

{{-- Charts Row 1: Daily/Weekly/Monthly Trends --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    <div class="bg-white rounded-xl border p-5">
        <h3>Daily Trend</h3>
        <div id="dailyTrendChart"></div>
    </div>
    <div class="bg-white rounded-xl border p-5">
        <h3>Weekly Trend</h3>
        <div id="weeklyTrendChart"></div>
    </div>
</div>

{{-- Charts Row 2: Peak Hours & Purpose Breakdown --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    <div class="bg-white rounded-xl border p-5">
        <h3>Peak Visiting Hours</h3>
        <div id="peakHoursChart"></div>
    </div>
    <div class="bg-white rounded-xl border p-5">
        <h3>Visit Purpose Breakdown</h3>
        <div id="purposeChart"></div>
    </div>
</div>

{{-- Export Button --}}
<div class="text-right mb-6">
    <form method="POST" action="{{ route('analytics.export') }}">
        @csrf
        {{-- Hidden inputs for current filters --}}
        <button type="submit" class="btn-primary">
            <svg>...</svg> Export PDF
        </button>
    </form>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    // ApexCharts initialization
    // Parse PHP data: @json($dailyTrendData)
    // Render charts in mounted lifecycle
</script>
@endpush
```

**Alpine.js Filter Controller**:

```javascript
function filterController() {
    return {
        period: "{{ $currentPeriod }}",
        startDate: "{{ $startDate }}",
        endDate: "{{ $endDate }}",
        showCustomInputs: false,

        selectPeriod(period) {
            this.period = period;
            this.showCustomInputs = period === "custom";
            if (period !== "custom") {
                this.applyFilter();
            }
        },

        applyFilter() {
            const params = new URLSearchParams({
                period: this.period,
                start_date: this.startDate,
                end_date: this.endDate,
            });
            window.location.href = `/dashboard/analytics?${params}`;
        },
    };
}
```

#### PDF Export View

**Location**: `resources/views/analytics/pdf.blade.php`

**Purpose**: Render-only template for PDF generation (no interactive charts)

**Structure**:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Visit Analytics Report</title>
    <style>
        /* Inline CSS for PDF rendering */
        /* Table styles, KPI card styles, chart placeholder styles */
    </style>
</head>
<body>
    <header>
        <h1>Visit Analytics Report</h1>
        <p>Period: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</p>
        <p>Generated: {{ now()->format('d M Y H:i') }}</p>
    </header>

    {{-- KPI Metrics Table --}}
    <section>
        <h2>Key Metrics</h2>
        <table>
            <tr>
                <td>Total Visits</td>
                <td>{{ $kpiMetrics['total_visits'] }}</td>
            </tr>
            {{-- Other KPIs --}}
        </table>
    </section>

    {{-- Chart Images (base64 encoded from server-side rendering) --}}
    {{-- OR: Static tables with data --}}
    <section>
        <h2>Daily Visit Trend</h2>
        <img src="{{ $dailyChartImage }}" alt="Daily trend chart">
    </section>

    {{-- Purpose Breakdown Table --}}
    <section>
        <h2>Visit Purposes</h2>
        <table>
            @foreach($purposeBreakdown as $item)
            <tr>
                <td>{{ $item['purpose'] }}</td>
                <td>{{ $item['count'] }}</td>
                <td>{{ $item['percentage'] }}%</td>
            </tr>
            @endforeach
        </table>
    </section>
</body>
</html>
```

**PDF Generation Approach**:

Option 1 (Recommended): **Tables Only** - Render data as HTML tables in PDF (no chart images). Simpler, faster, no external dependencies.

Option 2: **Chart Images** - Use [quickchart.io](https://quickchart.io/) API or PhantomJS to render ApexCharts as images server-side. More complex, slower, adds external dependency.

**Decision**: Use **Option 1** for MVP. ApexCharts images can be added later if needed.

### 6. ApexCharts Configuration

**Daily/Weekly/Monthly Trend Charts** (Line Chart):

```javascript
const dailyTrendOptions = {
    chart: {
        type: "line",
        height: 300,
        toolbar: { show: false },
        animations: { enabled: true },
    },
    series: [
        {
            name: "Visits",
            data: dailyTrendData.map((d) => d.count),
        },
    ],
    xaxis: {
        categories: dailyTrendData.map((d) => d.date),
        labels: {
            formatter: (val) =>
                new Date(val).toLocaleDateString("en-MY", {
                    month: "short",
                    day: "numeric",
                }),
        },
    },
    yaxis: {
        title: { text: "Visit Count" },
        labels: { formatter: (val) => Math.round(val) },
    },
    stroke: {
        curve: "smooth",
        width: 3,
    },
    colors: ["#4F46E5"], // Indigo-600
    tooltip: {
        x: { format: "dd MMM yyyy" },
    },
    dataLabels: { enabled: false },
};

new ApexCharts(
    document.querySelector("#dailyTrendChart"),
    dailyTrendOptions,
).render();
```

**Peak Hours Chart** (Bar Chart):

```javascript
const peakHoursOptions = {
    chart: {
        type: 'bar',
        height: 300
    },
    series: [{
        name: 'Check-ins',
        data: hourlyCountsArray // [5, 12, 8, ..., 23]
    }],
    xaxis: {
        categories: ['00:00', '01:00', ..., '23:00'],
        title: { text: 'Hour of Day' }
    },
    yaxis: {
        title: { text: 'Check-in Count' }
    },
    colors: peakHoursArray.map((hour, index) =>
        hour === peakHour ? '#10B981' : '#6366F1' // Green for peak, indigo otherwise
    ),
    plotOptions: {
        bar: {
            distributed: true,
            dataLabels: { position: 'top' }
        }
    }
};
```

**Purpose Breakdown Chart** (Horizontal Bar Chart):

```javascript
const purposeOptions = {
    chart: {
        type: "bar",
        height: 350,
    },
    series: [
        {
            name: "Visits",
            data: purposeData.map((p) => p.count),
        },
    ],
    xaxis: {
        categories: purposeData.map((p) => p.purpose),
        title: { text: "Visit Count" },
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: "70%",
        },
    },
    colors: ["#8B5CF6"], // Purple-500
    dataLabels: {
        enabled: true,
        formatter: (val, opts) => {
            const percentage = purposeData[opts.dataPointIndex].percentage;
            return `${val} (${percentage}%)`;
        },
    },
};
```

## Data Models

### Visit Model (Extended)

**Existing Fields** (from migration):

- `id`: Primary key
- `visitor_id`: Foreign key to visitors table
- `host_id`: Foreign key to hosts table
- `purpose`: String - visit reason
- `check_in_at`: Timestamp - entry time
- `check_out_at`: Nullable timestamp - exit time
- `status`: Enum ['checked_in', 'checked_out', 'cancelled']
- `badge_number`: Nullable string
- `notes`: Nullable text
- `created_at`, `updated_at`

**Indexes** (existing):

- `status`
- `check_in_at`
- `[visitor_id, check_in_at]`

**New Scopes** (see Components section above):

- `inPeriod($start, $end)`
- `completed()`
- `excludingCancelled()`

### User Model (Extended)

**New Field**:

- `role`: String (default 'user') - Values: 'user', 'admin'

**New Method**:

- `isAdmin(): bool`

**Migration**:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('user')->after('email');
    $table->index('role');
});
```

### Analytics Data Transfer Objects

**Purpose**: Structure data passed from service to controller to view

```php
// app/DataTransferObjects/AnalyticsData.php
class AnalyticsData
{
    public function __construct(
        public readonly array $kpiMetrics,
        public readonly Collection $dailyTrend,
        public readonly ?Collection $weeklyTrend,
        public readonly ?Collection $monthlyTrend,
        public readonly array $peakHours,
        public readonly array $avgDuration,
        public readonly Collection $purposeBreakdown,
        public readonly Carbon $startDate,
        public readonly Carbon $endDate,
        public readonly string $period
    ) {}
}
```

## Error Handling

### Controller-Level Validation

```php
public function index(Request $request): View
{
    $validated = $request->validate([
        'period' => ['required', 'in:today,this_week,this_month,custom'],
        'start_date' => ['required_if:period,custom', 'date', 'before_or_equal:end_date'],
        'end_date' => ['required_if:period,custom', 'date', 'after_or_equal:start_date']
    ]);

    // ...
}
```

**Error Messages** (stored in `lang/en/analytics.php`):

```php
return [
    'invalid_period' => 'Selected time period is invalid.',
    'invalid_date_range' => 'End date must be after start date.',
    'insufficient_data_weekly' => 'Weekly trends require at least 14 days of data.',
    'insufficient_data_monthly' => 'Monthly trends require at least 30 days of data.',
    'no_visits_in_period' => 'No visits found for the selected period.',
    'pdf_generation_failed' => 'Failed to generate PDF report. Please try again.',
];
```

### Service-Level Exceptions

```php
// app/Exceptions/InsufficientDataException.php
class InsufficientDataException extends Exception
{
    public static function forWeeklyTrend(): self
    {
        return new self(__('analytics.insufficient_data_weekly'));
    }

    public static function forMonthlyTrend(): self
    {
        return new self(__('analytics.insufficient_data_monthly'));
    }
}
```

**Usage in Service**:

```php
public function getWeeklyTrend(Carbon $start, Carbon $end): Collection
{
    if ($start->diffInDays($end) < 14) {
        throw InsufficientDataException::forWeeklyTrend();
    }
    // ... query logic
}
```

**Controller Handling**:

```php
try {
    $weeklyTrend = $this->analyticsService->getWeeklyTrend($startDate, $endDate);
} catch (InsufficientDataException $e) {
    $weeklyTrend = null;
    session()->flash('info', $e->getMessage());
}
```

### View-Level Empty States

```blade
@if($weeklyTrend === null)
    <div class="text-center py-10">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3">...</svg>
        <p class="text-sm text-gray-500">{{ session('info') }}</p>
    </div>
@elseif($weeklyTrend->isEmpty())
    <div class="text-center py-10">
        <p class="text-sm text-gray-500">No visits found for this period</p>
    </div>
@else
    <div id="weeklyTrendChart"></div>
@endif
```

### PDF Generation Errors

```php
try {
    $pdf = Pdf::loadView('analytics.pdf', $data);
    return $pdf->download("visit-analytics-report-{$date}.pdf");
} catch (\Exception $e) {
    Log::error('PDF generation failed', [
        'error' => $e->getMessage(),
        'filters' => $request->all()
    ]);

    return back()->with('error', __('analytics.pdf_generation_failed'));
}
```

## Testing Strategy

### Unit Tests

**Target**: Service layer logic, model scopes, data transformations

**Tools**: PHPUnit (ships with Laravel 13)

**Test Cases**:

1. **VisitAnalyticsServiceTest**:
    - `test_getKpiMetrics_returns_correct_counts()`
    - `test_getKpiMetrics_excludes_cancelled_visits()`
    - `test_getKpiMetrics_calculates_average_duration_correctly()`
    - `test_getDailyTrend_groups_by_date()`
    - `test_getDailyTrend_groups_by_week_when_period_exceeds_90_days()`
    - `test_getWeeklyTrend_throws_exception_when_period_less_than_14_days()`
    - `test_getMonthlyTrend_throws_exception_when_period_less_than_30_days()`
    - `test_getPeakHours_identifies_top_3_hours()`
    - `test_getPurposeBreakdown_limits_to_top_10()`
    - `test_getPurposeBreakdown_handles_null_purposes_as_unspecified()`

2. **VisitModelScopeTest**:
    - `test_inPeriod_scope_filters_by_date_range()`
    - `test_completed_scope_returns_only_checked_out_visits()`
    - `test_excludingCancelled_scope_filters_cancelled_status()`

3. **AdminMiddlewareTest**:
    - `test_admin_user_can_access_analytics()`
    - `test_regular_user_receives_403()`
    - `test_guest_is_redirected_to_login()`

**Example Test**:

```php
public function test_getKpiMetrics_excludes_cancelled_visits(): void
{
    // Arrange
    Visit::factory()->create(['status' => 'checked_in', 'check_in_at' => now()]);
    Visit::factory()->create(['status' => 'checked_out', 'check_in_at' => now()]);
    Visit::factory()->create(['status' => 'cancelled', 'check_in_at' => now()]); // Should be excluded

    $service = new VisitAnalyticsService();

    // Act
    $metrics = $service->getKpiMetrics(now()->startOfDay(), now()->endOfDay());

    // Assert
    $this->assertEquals(2, $metrics['total_visits']);
}
```

### Integration Tests

**Target**: Controller endpoints, full request-response cycle

**Tools**: Laravel's HTTP testing (`$this->get()`, `$this->post()`)

**Test Cases**:

1. **AnalyticsControllerTest**:
    - `test_index_requires_authentication()`
    - `test_index_requires_admin_role()`
    - `test_index_displays_analytics_page_with_today_filter()`
    - `test_index_applies_custom_date_range_filter()`
    - `test_index_validates_date_range_inputs()`
    - `test_exportPdf_generates_download_response()`
    - `test_exportPdf_includes_current_filters_in_filename()`
    - `test_exportPdf_returns_error_on_generation_failure()`

**Example Test**:

```php
public function test_index_requires_admin_role(): void
{
    // Arrange
    $regularUser = User::factory()->create(['role' => 'user']);

    // Act
    $response = $this->actingAs($regularUser)->get(route('analytics.index'));

    // Assert
    $response->assertForbidden();
}
```

### Browser Tests (Optional)

**Target**: JavaScript chart rendering, filter interactions

**Tools**: Laravel Dusk (Headless Chrome)

**Test Cases**:

- `test_daily_trend_chart_renders()`
- `test_filter_buttons_update_url_params()`
- `test_custom_date_picker_shows_when_custom_selected()`
- `test_chart_tooltips_display_on_hover()`

**Note**: Browser tests are optional for MVP. Prioritize unit and integration tests.

## Accessibility

### WCAG 2.1 AA Compliance

1. **Keyboard Navigation**:
    - All filter buttons focusable with `tabindex`
    - Date pickers keyboard-accessible (native HTML5 inputs)
    - Export button reachable via Tab key

2. **Screen Reader Support**:
    - ARIA labels on charts: `<div id="dailyTrendChart" role="img" aria-label="Daily visit trend line chart"></div>`
    - Chart data tables hidden visually but available to screen readers: `<table class="sr-only">`
    - Status announcements on filter changes: `<div aria-live="polite" class="sr-only">{{ $announcementMessage }}</div>`

3. **Color Contrast**:
    - KPI card text: Gray-900 on white (21:1 ratio - AAA)
    - Chart colors: Ensure 4.5:1 contrast against white background
    - Peak hour highlight: Green-500 (#10B981) - verify contrast

4. **Focus Indicators**:
    - Tailwind's `focus:ring` utility on all interactive elements
    - Custom focus styles for ApexCharts (if needed)

5. **Responsive Text**:
    - Minimum font size: 14px (text-sm)
    - Chart axis labels: 12px minimum

### Implementation Example

```blade
<div id="dailyTrendChart"
     role="img"
     aria-label="Daily visit trend showing {{ $dailyTrend->sum('count') }} total visits from {{ $startDate->format('M d') }} to {{ $endDate->format('M d') }}">
</div>

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
```

## Performance Considerations

### Database Query Optimization

1. **Indexes**: Ensure `check_in_at` and `status` columns are indexed (already exists)
2. **Eager Loading**: Not applicable (no N+1 queries in aggregate queries)
3. **Query Caching**: Cache expensive queries (e.g., monthly trend over 1 year) for 5 minutes

**Implementation**:

```php
public function getMonthlyTrend(Carbon $start, Carbon $end): Collection
{
    $cacheKey = "analytics.monthly_trend.{$start->format('Ymd')}.{$end->format('Ymd')}";

    return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($start, $end) {
        return Visit::query()
            ->selectRaw('DATE_FORMAT(check_in_at, "%Y-%m") as month, COUNT(*) as count')
            ->inPeriod($start, $end)
            ->excludingCancelled()
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    });
}
```

3. **Pagination**: Not applicable (aggregated data is small - max 365 daily points)

### Frontend Performance

1. **Chart Rendering**: ApexCharts is performant for datasets < 10,000 points (our max: 365 daily points)
2. **Lazy Loading**: Defer chart rendering until user scrolls to viewport (optional)
3. **Asset Bundling**: Load ApexCharts from CDN (cached across sites) or bundle with Vite

**ApexCharts Loading**:

```blade
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"
        integrity="sha384-..."
        crossorigin="anonymous"></script>
@endpush
```

**Alternative (Vite bundling)**:

```bash
npm install apexcharts
```

```javascript
// resources/js/analytics.js
import ApexCharts from "apexcharts";
window.ApexCharts = ApexCharts;
```

### PDF Generation Performance

1. **Timeout**: Dompdf can be slow for complex layouts. Set 60s timeout:

```php
set_time_limit(60);
$pdf = Pdf::loadView('analytics.pdf', $data)
    ->setPaper('a4', 'portrait')
    ->setOption('isHtml5ParserEnabled', true)
    ->setOption('isRemoteEnabled', false); // Security: disable external resources
```

2. **Queue Long Reports**: For reports > 1 year, queue PDF generation:

```php
if ($startDate->diffInDays($endDate) > 365) {
    GenerateAnalyticsPdfJob::dispatch($request->user(), $startDate, $endDate);
    return back()->with('success', 'PDF report is being generated. You will receive an email when ready.');
}
```

3. **Memory Limit**: Increase for large datasets:

```php
ini_set('memory_limit', '256M');
```

## Security Considerations

1. **Authorization**: Admin middleware on all analytics routes
2. **Input Validation**: Strict validation on date inputs to prevent SQL injection
3. **CSRF Protection**: Laravel's `@csrf` token on export form
4. **Rate Limiting**: Throttle PDF export to 10 requests per minute per user

**Rate Limiting**:

```php
// In RouteServiceProvider or routes/web.php
Route::post('/dashboard/analytics/export-pdf', [AnalyticsController::class, 'exportPdf'])
    ->middleware(['auth', 'admin', 'throttle:10,1']); // 10 requests per minute
```

5. **SQL Injection Prevention**: Use Eloquent query builder (parameterized queries) - already implemented
6. **XSS Prevention**: Blade's `{{ }}` auto-escapes output - already implemented
7. **PDF External Resources**: Disable in Dompdf to prevent SSRF attacks (see Performance section)

## Deployment Checklist

### Pre-Deployment

- [ ] Run migrations: `php artisan migrate`
- [ ] Seed admin user if needed: `php artisan db:seed --class=AdminSeeder`
- [ ] Install NPM dependencies if using Vite bundling: `npm install && npm run build`
- [ ] Install Composer dependencies: `composer require barryvdh/laravel-dompdf`
- [ ] Clear cache: `php artisan config:cache && php artisan route:cache`

### Configuration

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Configure cache driver (Redis recommended for analytics caching)
- [ ] Set appropriate `memory_limit` and `max_execution_time` in PHP config
- [ ] Configure queue worker if using async PDF generation

### Post-Deployment

- [ ] Verify admin user can access `/dashboard/analytics`
- [ ] Verify regular user receives 403
- [ ] Test PDF export with sample data
- [ ] Monitor error logs for PDF generation failures
- [ ] Set up monitoring for slow queries (> 3 seconds)

## Future Enhancements

### Phase 2 (Post-MVP)

1. **Real-Time Updates**: Use Laravel Echo + WebSockets to update KPI cards live
2. **Comparison Mode**: Side-by-side comparison of two time periods
3. **Department Filtering**: Filter analytics by specific host department
4. **Visitor Type Segmentation**: Analyze by visitor company, ID type, etc.
5. **Scheduled Reports**: Cron job to email weekly/monthly PDF reports to admins
6. **Export to Excel**: Alternative export format using `maatwebsite/excel`
7. **Chart Annotations**: Mark holidays, events on charts (ApexCharts annotations feature)
8. **Predictive Analytics**: Use Laravel's machine learning integrations to forecast future trends

### Technical Debt

- [ ] Add API endpoints for AJAX chart updates (avoid full page reload on filter change)
- [ ] Implement service-level caching strategy (document cache invalidation rules)
- [ ] Add comprehensive browser tests (Dusk)
- [ ] Set up performance monitoring (Laravel Telescope in local/staging)

---

## Appendices

### A. Database Query Examples

**Daily Trend Query**:

```sql
SELECT
    DATE(check_in_at) as date,
    COUNT(*) as count
FROM visits
WHERE check_in_at BETWEEN '2026-01-01 00:00:00' AND '2026-01-31 23:59:59'
  AND status != 'cancelled'
GROUP BY DATE(check_in_at)
ORDER BY date ASC
```

**Peak Hours Query**:

```sql
SELECT
    HOUR(check_in_at) as hour,
    COUNT(*) as count
FROM visits
WHERE check_in_at BETWEEN '2026-01-01 00:00:00' AND '2026-01-31 23:59:59'
  AND status != 'cancelled'
GROUP BY HOUR(check_in_at)
ORDER BY hour ASC
```

**Average Duration Query**:

```sql
SELECT
    AVG(TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at)) as avg_minutes,
    COUNT(*) as completed_count
FROM visits
WHERE check_in_at BETWEEN '2026-01-01 00:00:00' AND '2026-01-31 23:59:59'
  AND status = 'checked_out'
  AND check_out_at IS NOT NULL
```

### B. ApexCharts Configuration Reference

Full configuration objects documented in Components section (Section 5.6).

### C. External Resources

- [ApexCharts Documentation](https://apexcharts.com/docs/) - Chart configuration and API reference
- [Laravel Dompdf Documentation](https://github.com/barryvdh/laravel-dompdf) - PDF generation guide
- [Tailwind CSS Documentation](https://tailwindcss.com/docs) - Styling utilities
- [Alpine.js Documentation](https://alpinejs.dev/) - Frontend interactivity
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/) - Accessibility checklist

---

**Document Version**: 1.0  
**Last Updated**: 2026-06-24  
**Author**: Dashboard Analysis Module Design Team
