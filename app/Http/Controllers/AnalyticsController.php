<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientDataException;
use App\Services\VisitAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controller for the Dashboard Analysis Module.
 * 
 * Handles analytics dashboard display with time period filtering
 * and delegates data calculation to VisitAnalyticsService.
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 11.1, 11.4
 */
class AnalyticsController extends Controller
{
    /**
     * Create a new AnalyticsController instance.
     *
     * @param VisitAnalyticsService $analyticsService
     */
    public function __construct(
        private VisitAnalyticsService $analyticsService
    ) {}

    /**
     * Display analytics dashboard with filters
     *
     * Requirements:
     * - 2.1: Provide time period filter options
     * - 2.2: Display analytics for "today"
     * - 2.3: Display analytics for "this week" (Monday start)
     * - 2.4: Display analytics for "this month"
     * - 2.5: Display date pickers for "custom range"
     * - 2.6: Display analytics for custom date range
     * - 2.7: Refresh visualizations when filter changes
     * - 11.1: Display loading indicator
     * - 11.4: Display descriptive error messages
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View|RedirectResponse
    {
        // Default to the last three months without issuing a redirect.
        $request->mergeIfMissing([
            'period' => 'custom',
            'start_date' => now()->subMonths(3)->startOfDay()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        // Requirements 2.5, 2.6, 12.3: Validate all request inputs
        $validated = $request->validate([
            'period' => ['required', 'in:today,this_week,this_month,custom'],
            'start_date' => ['required_if:period,custom', 'nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required_if:period,custom', 'nullable', 'date', 'after_or_equal:start_date'],
        ], [
            'period.required' => __('analytics.invalid_period'),
            'period.in' => __('analytics.invalid_period'),
            'start_date.required_if' => 'Start date is required when using custom period.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.before_or_equal' => __('analytics.invalid_date_range'),
            'end_date.required_if' => 'End date is required when using custom period.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => __('analytics.invalid_date_range'),
        ]);

        // Requirement 2.1: Parse period parameter (default to "today")
        $period = $validated['period'];

        // Initialize date variables
        $startDate = null;
        $endDate = null;

        // Requirement 2.2, 2.3, 2.4, 2.5, 2.6: Convert period to Carbon date range
        switch ($period) {
            case 'today':
                // Requirement 2.2: Current calendar day
                $startDate = Carbon::today();
                $endDate = Carbon::today();
                break;

            case 'this_week':
                // Requirement 2.3: Current week starting from Monday
                $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
                $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY);
                break;

            case 'this_month':
                // Requirement 2.4: Current calendar month
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;

            case 'custom':
                // Requirement 2.5, 2.6: Custom date range from user input
                $startDate = Carbon::parse($validated['start_date']);
                $endDate = Carbon::parse($validated['end_date']);
                break;
        }

        // Requirement 2.7: Call all service methods to gather analytics data
        try {
            // Get KPI metrics
            $kpiMetrics = $this->analyticsService->getKpiMetrics($startDate, $endDate);

            // Get daily trend
            $dailyTrend = $this->analyticsService->getDailyTrend($startDate, $endDate);

            // Get weekly trend (handle insufficient data exception)
            try {
                $weeklyTrend = $this->analyticsService->getWeeklyTrend($startDate, $endDate);
            } catch (InsufficientDataException $e) {
                // Requirement 11.4: Handle InsufficientDataException gracefully
                $weeklyTrend = null;
                session()->flash('info_weekly', $e->getMessage());
            }

            // Get monthly trend (handle insufficient data exception)
            try {
                $monthlyTrend = $this->analyticsService->getMonthlyTrend($startDate, $endDate);
            } catch (InsufficientDataException $e) {
                // Requirement 11.4: Handle InsufficientDataException gracefully
                $monthlyTrend = null;
                session()->flash('info_monthly', $e->getMessage());
            }

            // Get peak hours
            $peakHours = $this->analyticsService->getPeakHours($startDate, $endDate);

            // Get average duration
            $avgDuration = $this->analyticsService->getAverageDuration($startDate, $endDate);

            // Get purpose breakdown
            $purposeBreakdown = $this->analyticsService->getPurposeBreakdown($startDate, $endDate);
        } catch (\Exception $e) {
            // Requirement 11.4: Display descriptive error message for unexpected errors
            return back()->withErrors(['error' => 'Failed to load analytics data. Please try again.']);
        }

        // Requirement 2.7: Pass data and filter state to view
        return view('analytics.index', [
            'currentPeriod' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'kpiMetrics' => $kpiMetrics,
            'dailyTrend' => $dailyTrend,
            'weeklyTrend' => $weeklyTrend,
            'monthlyTrend' => $monthlyTrend,
            'peakHours' => $peakHours,
            'avgDuration' => $avgDuration,
            'purposeBreakdown' => $purposeBreakdown,
        ]);
    }

    /**
     * Export current analytics view as PDF
     *
     * Requirements:
     * - 10.1: Provide "Export PDF" button
     * - 10.2: Generate PDF with all visible charts and metrics
     * - 10.3: Include selected time period filter in PDF header
     * - 10.4: Include timestamp when report was generated
     * - 10.8: Format PDF with appropriate margins and page breaks
     * - 10.9: Display loading indicator during generation
     * - 10.10: Trigger download with filename format visit-analytics-report-{YYYY-MM-DD}.pdf
     * - 10.11: Display error message if PDF generation fails
     *
     * @param Request $request
     * @return Response|\Illuminate\Http\RedirectResponse
     */
    public function exportPdf(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        // Requirement 10.2: Validate same filters as index method
        $period = $request->get('period', 'today');

        // Initialize date variables
        $startDate = null;
        $endDate = null;

        // Convert period to Carbon date range (same logic as index method)
        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                $endDate = Carbon::today();
                break;

            case 'this_week':
                $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
                $endDate = Carbon::now()->endOfWeek(Carbon::SUNDAY);
                break;

            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;

            case 'custom':
                // Validate custom date inputs
                $request->validate([
                    'start_date' => ['required', 'date', 'before_or_equal:end_date'],
                    'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                ], [
                    'start_date.before_or_equal' => __('analytics.invalid_date_range'),
                    'end_date.after_or_equal' => __('analytics.invalid_date_range'),
                ]);

                $startDate = Carbon::parse($request->get('start_date'));
                $endDate = Carbon::parse($request->get('end_date'));
                break;

            default:
                // Invalid period, fallback to today
                $period = 'today';
                $startDate = Carbon::today();
                $endDate = Carbon::today();
                break;
        }

        try {
            // Requirement 10.8: Set timeout and memory limits for PDF generation
            set_time_limit(60);
            ini_set('memory_limit', '256M');

            // Requirement 10.2: Generate analytics data via service
            $kpiMetrics = $this->analyticsService->getKpiMetrics($startDate, $endDate);
            $dailyTrend = $this->analyticsService->getDailyTrend($startDate, $endDate);

            // Handle weekly trend (may throw exception for insufficient data)
            try {
                $weeklyTrend = $this->analyticsService->getWeeklyTrend($startDate, $endDate);
            } catch (InsufficientDataException $e) {
                $weeklyTrend = null;
            }

            // Handle monthly trend (may throw exception for insufficient data)
            try {
                $monthlyTrend = $this->analyticsService->getMonthlyTrend($startDate, $endDate);
            } catch (InsufficientDataException $e) {
                $monthlyTrend = null;
            }

            $peakHours = $this->analyticsService->getPeakHours($startDate, $endDate);
            $avgDuration = $this->analyticsService->getAverageDuration($startDate, $endDate);
            $purposeBreakdown = $this->analyticsService->getPurposeBreakdown($startDate, $endDate);

            // Requirement 10.2: Render 'analytics.pdf' Blade view with data
            // Requirement 10.3: Include selected time period filter in PDF header
            // Requirement 10.4: Include timestamp when report was generated
            $pdf = Pdf::loadView('analytics.pdf', [
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'generatedAt' => Carbon::now(),
                'kpiMetrics' => $kpiMetrics,
                'dailyTrend' => $dailyTrend,
                'weeklyTrend' => $weeklyTrend,
                'monthlyTrend' => $monthlyTrend,
                'peakHours' => $peakHours,
                'avgDuration' => $avgDuration,
                'purposeBreakdown' => $purposeBreakdown,
            ])
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false); // Security: disable external resources

            // Requirement 10.10: Return PDF download with filename format visit-analytics-report-{YYYY-MM-DD}.pdf
            $filename = 'visit-analytics-report-' . now()->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            // Requirement 10.11: Handle PDF generation errors with try-catch and logging
            Log::error('PDF generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => [
                    'period' => $period,
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                ],
            ]);

            return back()->with('error', __('analytics.pdf_generation_failed'));
        }
    }
}
