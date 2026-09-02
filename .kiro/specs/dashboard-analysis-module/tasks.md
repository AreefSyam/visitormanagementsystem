# Implementation Plan: Dashboard Analysis Module

## Overview

This implementation plan breaks down the Dashboard Analysis Module into incremental coding tasks. The module adds comprehensive visit analytics to the existing Laravel visitor management system, including time-filtered visualizations, KPI metrics, and PDF export capabilities. Each task builds upon previous steps, with no orphaned code.

## Tasks

- [x]   1. Set up database foundation and admin access
    - [x] 1.1 Create migration to add 'role' column to users table
        - Add `role` column (string, default 'user') with index
        - Add values: 'user', 'admin'
        - _Requirements: 1.1_
    - [x] 1.2 Add isAdmin() method to User model
        - Implement `isAdmin()` method that checks if role === 'admin'
        - _Requirements: 1.1_
    - [x] 1.3 Create EnsureUserIsAdmin middleware
        - Create `app/Http/Middleware/EnsureUserIsAdmin.php`
        - Check authenticated user has admin role, return 403 if not
        - Register middleware in `bootstrap/app.php` or `app/Http/Kernel.php`
        - _Requirements: 1.1, 1.2_

- [x]   2. Extend Visit model with query scopes
    - [x] 2.1 Add scopeInPeriod to Visit model
        - Implement scope to filter visits between start and end dates
        - Use `whereBetween` on `check_in_at` column
        - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_
    - [x] 2.2 Add scopeCompleted to Visit model
        - Implement scope to filter only checked-out visits with non-null `check_out_at`
        - _Requirements: 7.1, 7.2, 7.3_
    - [x] 2.3 Add scopeExcludingCancelled to Visit model
        - Implement scope to exclude visits with status 'cancelled'
        - _Requirements: 15.3_

- [x]   3. Create analytics service layer
    - [x] 3.1 Create VisitAnalyticsService class
        - Create `app/Services/VisitAnalyticsService.php`
        - Set up constructor and class structure
        - _Requirements: 2.7, 11.5_
    - [x] 3.2 Implement getKpiMetrics method
        - Calculate total visits, active visits, completed visits counts
        - Calculate average duration in hours/minutes format
        - Return structured array with all KPI data
        - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 7.1, 7.2_
    - [x] 3.3 Implement getDailyTrend method
        - Query visits grouped by DATE(check_in_at)
        - Apply inPeriod and excludingCancelled scopes
        - When period > 90 days, group by week instead
        - Return Collection with date and count
        - _Requirements: 3.1, 3.2, 3.3, 3.4_
    - [x] 3.4 Implement getWeeklyTrend method
        - Query visits grouped by ISO week (Monday-Sunday)
        - Throw InsufficientDataException if period < 14 days
        - Return Collection with week_start and count
        - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_
    - [x] 3.5 Implement getMonthlyTrend method
        - Query visits grouped by calendar month (YYYY-MM format)
        - Throw InsufficientDataException if period < 30 days
        - Return Collection with month, label, and count
        - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
    - [x] 3.6 Implement getPeakHours method
        - Query visits grouped by HOUR(check_in_at) (0-23)
        - Calculate hourly counts array
        - Identify top 3 peak hours
        - Return array with hourly_counts and peak_hours
        - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_
    - [x] 3.7 Implement getAverageDuration method
        - Calculate AVG(TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at))
        - Only include completed visits with non-null check_out_at
        - Format as hours and minutes
        - Return array with avg_minutes, formatted, and completed_count
        - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_
    - [x] 3.8 Implement getPurposeBreakdown method
        - Query visits grouped by purpose (use COALESCE for null handling)
        - Calculate count and percentage for each purpose
        - Limit to top 10 purposes sorted by count DESC
        - Handle null/empty purposes as "Unspecified"
        - Return Collection with purpose, count, percentage
        - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_
    - [x] 3.9 Add query caching to expensive methods
        - Implement Cache::remember for monthly/weekly trends (5 min TTL)
        - Use cache keys based on start/end dates
        - _Requirements: 11.3_

- [x]   4. Create exception handling for insufficient data
    - [x] 4.1 Create InsufficientDataException class
        - Create `app/Exceptions/InsufficientDataException.php`
        - Add static factory methods: `forWeeklyTrend()` and `forMonthlyTrend()`
        - Use localized error messages
        - _Requirements: 4.5, 5.5_

- [~] 5. Checkpoint - Verify service layer functionality
    - Run tests for service methods
    - Ensure all query scopes work correctly
    - Ensure all tests pass, ask the user if questions arise.

- [ ]   6. Create analytics controller
    - [x] 6.1 Create AnalyticsController with index method
        - Create `app/Http/Controllers/AnalyticsController.php`
        - Inject VisitAnalyticsService via constructor
        - Implement index() method with request validation
        - Parse period parameter (today/this_week/this_month/custom)
        - Convert period to Carbon date range
        - Call all service methods to gather analytics data
        - Handle InsufficientDataException gracefully
        - Pass data and filter state to view
        - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 11.1, 11.4_
    - [-] 6.2 Add request validation to index method
        - Validate 'period' enum (today, this_week, this_month, custom)
        - Validate 'start_date' required_if period=custom, date format, before_or_equal end_date
        - Validate 'end_date' required_if period=custom, date format, after_or_equal start_date
        - Return validation errors with localized messages
        - _Requirements: 2.5, 2.6, 12.3_
    - [-] 6.3 Implement exportPdf method in AnalyticsController
        - Validate same filters as index method
        - Generate analytics data via service
        - Render 'analytics.pdf' Blade view with data
        - Use barryvdh/laravel-dompdf to convert HTML to PDF
        - Set timeout and memory limits
        - Return PDF download with filename format: visit-analytics-report-{YYYY-MM-DD}.pdf
        - Handle PDF generation errors with try-catch and logging
        - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.8, 10.9, 10.10, 10.11_

- [ ]   7. Define analytics routes with middleware
    - [-] 7.1 Add analytics routes to routes/web.php
        - GET /dashboard/analytics → AnalyticsController@index
        - POST /dashboard/analytics/export-pdf → AnalyticsController@exportPdf
        - Apply auth and admin middleware to both routes
        - Apply throttle middleware (10 requests/min) to export route
        - _Requirements: 1.1, 1.2, 1.3_

- [x]   8. Create language files for error messages
    - [x] 8.1 Create analytics language file
        - Create `lang/en/analytics.php`
        - Define error messages: invalid_period, invalid_date_range, insufficient_data_weekly, insufficient_data_monthly, no_visits_in_period, pdf_generation_failed
        - _Requirements: 11.4, 12.1, 12.2, 12.3_

- [ ]   9. Install and configure frontend dependencies
    - [x] 9.1 Install barryvdh/laravel-dompdf package
        - Run `composer require barryvdh/laravel-dompdf`
        - Publish config if needed
        - Configure Dompdf options (disable remote resources for security)
        - _Requirements: 10.1, 10.2_
    - [~] 9.2 Add ApexCharts CDN to analytics view
        - Reference ApexCharts from CDN with integrity hash
        - Or install via npm and bundle with Vite (optional)
        - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 6.1, 6.2, 6.3, 6.4, 8.1, 8.2, 8.3, 8.4_

- [ ]   10. Create main analytics dashboard view
    - [~] 10.1 Create analytics index.blade.php layout
        - Create `resources/views/analytics/index.blade.php`
        - Extend main app layout
        - Set up Alpine.js data context for filters
        - Create responsive grid structure using Tailwind
        - _Requirements: 13.1, 13.2, 13.3_
    - [~] 10.2 Implement time period filter bar
        - Add filter buttons: Today, This Week, This Month, Custom Range
        - Use Alpine.js to handle filter state
        - Show/hide custom date picker inputs based on selection
        - Submit GET request with query params on filter change
        - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_
    - [~] 10.3 Implement KPI cards grid
        - Create 4 KPI cards: Total Visits, Active Visits, Completed Visits, Avg Duration
        - Display formatted numbers with thousand separators
        - Use Tailwind for responsive grid (1 col mobile, 2 col tablet, 4 col desktop)
        - Handle null/zero values gracefully
        - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_
    - [~] 10.4 Create chart containers for daily/weekly/monthly trends
        - Add three chart divs with unique IDs: dailyTrendChart, weeklyTrendChart, monthlyTrendChart
        - Use responsive 2-column grid on desktop, single column on mobile
        - Add empty state handling for null or empty trend data
        - Add loading indicators
        - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3, 5.4, 5.5, 11.1, 12.1, 12.5_
    - [~] 10.5 Create chart containers for peak hours and purpose breakdown
        - Add two chart divs: peakHoursChart, purposeChart
        - Use responsive 2-column grid layout
        - Add empty state handling
        - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_
    - [~] 10.6 Add Export PDF button and form
        - Create POST form to analytics.export route
        - Include CSRF token
        - Pass current filter values as hidden inputs
        - Style button with icon and loading state
        - _Requirements: 10.1, 10.2, 10.9_

- [ ]   11. Implement ApexCharts initialization scripts
    - [~] 11.1 Initialize daily trend line chart
        - Parse PHP data using @json() blade directive
        - Configure ApexCharts line chart with smooth curve
        - Set x-axis categories to dates, y-axis to visit counts
        - Add tooltip with date formatting
        - Apply Indigo-600 color scheme
        - Render chart in dailyTrendChart container
        - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 14.1, 14.2, 14.3_
    - [~] 11.2 Initialize weekly trend line chart
        - Parse PHP data with week_start dates
        - Configure line chart similar to daily trend
        - Handle null data (insufficient period warning)
        - _Requirements: 4.1, 4.2, 4.3, 4.4, 14.1, 14.2, 14.3_
    - [~] 11.3 Initialize monthly trend line chart
        - Parse PHP data with month labels
        - Configure line chart with month/year x-axis labels
        - Handle null data (insufficient period warning)
        - _Requirements: 5.1, 5.2, 5.3, 5.4, 14.1, 14.2, 14.3_
    - [~] 11.4 Initialize peak hours bar chart
        - Parse hourly counts array (0-23)
        - Configure vertical bar chart with hour labels
        - Highlight peak hours with different color (Green-500 for peak, Indigo-500 for others)
        - Add data labels on bars
        - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 14.2, 14.3_
    - [~] 11.5 Initialize purpose breakdown horizontal bar chart
        - Parse purpose data with counts and percentages
        - Configure horizontal bar chart
        - Display count and percentage in data labels
        - Apply Purple-500 color scheme
        - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 14.2, 14.3_

- [ ]   12. Implement Alpine.js filter controller
    - [~] 12.1 Create filterController Alpine.js component
        - Define reactive data: period, startDate, endDate, showCustomInputs
        - Implement selectPeriod(period) method to update state
        - Implement applyFilter() method to build query params and navigate
        - Handle custom date range visibility toggle
        - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

- [ ]   13. Create PDF export view template
    - [~] 13.1 Create analytics pdf.blade.php template
        - Create `resources/views/analytics/pdf.blade.php`
        - Use standalone HTML structure (no external CSS)
        - Add inline styles for PDF rendering
        - Create header with report title, date range, generation timestamp
        - _Requirements: 10.3, 10.4_
    - [~] 13.2 Add KPI metrics table to PDF template
        - Render all KPI values in a simple HTML table
        - Format numbers consistently
        - _Requirements: 10.7, 9.1, 9.2, 9.3, 9.4_
    - [~] 13.3 Add data tables to PDF template
        - Create tables for: daily trend data, peak hours data, purpose breakdown
        - Use simple HTML tables (no chart images for MVP)
        - Format data clearly with headers
        - Add page breaks where appropriate
        - _Requirements: 10.5, 10.6, 10.7, 10.8_

- [~] 14. Checkpoint - Test analytics dashboard end-to-end
    - Access dashboard as admin user
    - Test all time period filters
    - Verify all charts render correctly
    - Test PDF export functionality
    - Ensure all tests pass, ask the user if questions arise.

- [ ]   15. Implement accessibility features
    - [~] 15.1 Add ARIA labels to charts
        - Add role="img" and descriptive aria-label to each chart container
        - Include summary data in labels (total visits, date range)
        - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_
    - [~] 15.2 Create hidden data tables for screen readers
        - Add sr-only tables beneath each chart with full data
        - Include proper table captions and headers
        - _Requirements: 14.1_
    - [~] 15.3 Add keyboard navigation to filters
        - Ensure all filter buttons are focusable
        - Add focus:ring styles using Tailwind
        - Test tab navigation flow
        - _Requirements: 14.1_
    - [~] 15.4 Add ARIA live regions for filter changes
        - Create sr-only div with aria-live="polite"
        - Announce filter changes to screen readers
        - _Requirements: 2.7_

- [ ]   16. Implement empty state handling
    - [~] 16.1 Add empty state messages to views
        - Display "No visits found for this period" when data is empty
        - Display "Invalid date range selected" for invalid custom ranges
        - Display period-specific messages for weekly/monthly insufficient data
        - Add empty state illustrations or icons
        - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [ ]   17. Add responsive layout refinements
    - [~] 17.1 Test and refine mobile layout
        - Verify single-column layout on screens < 1024px
        - Verify two-column layout on screens >= 1024px
        - Test chart scaling and aspect ratios
        - Ensure touch interactions work for chart tooltips
        - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 14.4_

- [ ]   18. Implement data accuracy validation
    - [~] 18.1 Add data integrity checks to service methods
        - Verify only valid check-in timestamps are included
        - Exclude cancelled visits from all calculations
        - Handle null values safely in all aggregations
        - Use timezone-aware date bucketing
        - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_

- [x]   19. Create database seeder for admin user (optional)
    - [x] 19.1 Create AdminUserSeeder
        - Create `database/seeders/AdminUserSeeder.php`
        - Seed at least one admin user for testing
        - _Requirements: 1.1_

- [ ]   20. Final integration and testing
    - [~] 20.1 Run full test suite
        - Execute PHPUnit tests for service, controller, middleware
        - Verify all analytics calculations are accurate
        - Test edge cases: empty data, single day period, year-long period
        - _Requirements: All_
    - [~] 20.2 Performance testing
        - Test dashboard load time with large datasets (>10k visits)
        - Verify queries complete within 3 seconds
        - Test PDF generation with various data sizes
        - _Requirements: 11.2, 11.3_
    - [~] 20.3 Security testing
        - Verify admin middleware blocks non-admin users
        - Test CSRF protection on PDF export
        - Verify rate limiting on export endpoint
        - Test SQL injection prevention in date filters
        - _Requirements: 1.1, 1.2, 1.3_

- [~] 21. Final checkpoint - Complete implementation review
    - Review all implemented features against requirements
    - Verify all acceptance criteria are met
    - Perform final code cleanup
    - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks build incrementally: database → models → service → controller → routes → views → frontend
- Each task references specific requirements for traceability
- Testing checkpoints ensure incremental validation
- No test sub-tasks are marked optional (\*) as this is a critical analytics feature requiring reliability
- All code should follow Laravel best practices and existing project conventions
- ApexCharts configuration is detailed in the design document for reference
- PDF export uses table-based approach (no chart images) for MVP simplicity
- Accessibility compliance is prioritized throughout implementation

## Task Dependency Graph

```json
{
    "waves": [
        { "id": 0, "tasks": ["1.1", "3.1", "4.1", "8.1"] },
        { "id": 1, "tasks": ["1.2", "2.1", "9.1"] },
        { "id": 2, "tasks": ["1.3", "2.2", "2.3", "3.2"] },
        { "id": 3, "tasks": ["3.3", "3.4", "3.5", "19.1"] },
        { "id": 4, "tasks": ["3.6", "3.7", "3.8"] },
        { "id": 5, "tasks": ["3.9", "6.1"] },
        { "id": 6, "tasks": ["6.2", "6.3", "7.1"] },
        { "id": 7, "tasks": ["9.2", "10.1"] },
        { "id": 8, "tasks": ["10.2", "10.3", "10.4"] },
        { "id": 9, "tasks": ["10.5", "10.6", "13.1"] },
        { "id": 10, "tasks": ["11.1", "11.2", "11.3", "13.2"] },
        { "id": 11, "tasks": ["11.4", "11.5", "12.1", "13.3"] },
        { "id": 12, "tasks": ["15.1", "15.2", "16.1"] },
        { "id": 13, "tasks": ["15.3", "15.4", "17.1"] },
        { "id": 14, "tasks": ["18.1", "20.1"] },
        { "id": 15, "tasks": ["20.2", "20.3"] }
    ]
}
```
