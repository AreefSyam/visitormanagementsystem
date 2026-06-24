<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $visitorsToday   = Visit::today()->count();
        $activeVisitors  = Visit::checkedIn()->count();
        $monthlyVisitors = Visit::thisMonth()->count();
        $totalVisitors   = Visitor::count();

        $topDepartments = Visit::select('hosts.department', DB::raw('COUNT(*) as visit_count'))
            ->join('hosts', 'visits.host_id', '=', 'hosts.id')
            ->thisMonth()
            ->groupBy('hosts.department')
            ->orderByDesc('visit_count')
            ->limit(5)
            ->get();

        $recentVisits = Visit::with(['visitor', 'host'])
            ->latest('check_in_at')
            ->limit(10)
            ->get();

        $activeVisitsList = Visit::with(['visitor', 'host'])
            ->checkedIn()
            ->latest('check_in_at')
            ->limit(8)
            ->get();

        return view('dashboard.index', compact(
            'visitorsToday',
            'activeVisitors',
            'monthlyVisitors',
            'totalVisitors',
            'topDepartments',
            'recentVisits',
            'activeVisitsList',
        ));
    }
}
