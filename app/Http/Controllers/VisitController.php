<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Models\Host;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function index(): View
    {
        $search     = request('search');
        $status     = request('status');
        $department = request('department');
        $date       = request('date');

        $visits = Visit::with(['visitor', 'host'])
            ->when($search, fn ($q) => $q
                ->whereHas('visitor', fn ($v) => $v->where('name', 'like', "%{$search}%"))
                ->orWhereHas('host', fn ($h) => $h->where('name', 'like', "%{$search}%"))
            )
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($department, fn ($q) => $q
                ->whereHas('host', fn ($h) => $h->where('department', $department))
            )
            ->when($date, fn ($q) => $q->whereDate('check_in_at', $date))
            ->latest('check_in_at')
            ->paginate(20)
            ->withQueryString();

        $departments = Host::distinct()->orderBy('department')->pluck('department');

        return view('visits.index', compact('visits', 'search', 'status', 'department', 'date', 'departments'));
    }

    public function create(): View
    {
        $visitors = Visitor::orderBy('name')->get();
        $hosts    = Host::active()->orderBy('name')->get();

        return view('visits.create', compact('visitors', 'hosts'));
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $visit = Visit::create([
            ...$request->validated(),
            'check_in_at' => now(),
            'status'      => 'checked_in',
        ]);

        return redirect()
            ->route('visits.show', $visit)
            ->with('success', 'Visitor checked in successfully.');
    }

    public function show(Visit $visit): View
    {
        $visit->load(['visitor', 'host']);

        return view('visits.show', compact('visit'));
    }

    public function checkout(Visit $visit): RedirectResponse
    {
        if ($visit->status !== 'checked_in') {
            return back()->with('error', 'This visit is not active.');
        }

        $visit->update([
            'check_out_at' => now(),
            'status'       => 'checked_out',
        ]);

        return redirect()
            ->route('visits.show', $visit)
            ->with('success', "{$visit->visitor->name} has been checked out.");
    }

    public function cancel(Visit $visit): RedirectResponse
    {
        if ($visit->status !== 'checked_in') {
            return back()->with('error', 'Only active visits can be cancelled.');
        }

        $visit->update(['status' => 'cancelled']);

        return redirect()
            ->route('visits.index')
            ->with('success', 'Visit cancelled.');
    }
}
