@extends('layouts.app')

@section('title', 'Dashboard')

@section('actions')
    <a href="{{ route('visits.create') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Visit
    </a>
@endsection

@section('content')

{{-- Stat Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">

    @php
        $cards = [
            [
                'label' => 'Visitors Today',
                'value' => $visitorsToday,
                'sub'   => 'check-ins so far',
                'color' => 'bg-blue-50 text-blue-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
            ],
            [
                'label' => 'Active Now',
                'value' => $activeVisitors,
                'sub'   => 'currently on-site',
                'color' => 'bg-green-50 text-green-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            [
                'label' => 'This Month',
                'value' => $monthlyVisitors,
                'sub'   => now()->format('F Y'),
                'color' => 'bg-purple-50 text-purple-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            ],
            [
                'label' => 'Total Visitors',
                'value' => $totalVisitors,
                'sub'   => 'registered profiles',
                'color' => 'bg-orange-50 text-orange-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
        ];
    @endphp

    @foreach($cards as $card)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($card['value']) }}</p>
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

    {{-- Active Visitors --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Active Visitors</h2>
            @if($activeVisitors > 0)
                <span class="flex items-center gap-1.5 text-xs text-green-600 font-medium">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    {{ $activeVisitors }} on-site
                </span>
            @endif
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($activeVisitsList as $visit)
                <div class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-indigo-700 text-xs font-semibold">
                                {{ strtoupper(substr($visit->visitor->name, 0, 2)) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $visit->visitor->name }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                → {{ $visit->host->name }} &middot; {{ $visit->host->department }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                        <span class="text-xs text-gray-400">{{ $visit->check_in_at->format('H:i') }}</span>
                        <a href="{{ route('visits.show', $visit) }}"
                           class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <svg class="w-10 h-10 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <p class="text-sm text-gray-400">No active visitors right now</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Top Departments --}}
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Top Departments</h2>
            <p class="text-xs text-gray-400 mt-0.5">Visits this month</p>
        </div>
        <div class="p-5 space-y-4">
            @forelse($topDepartments as $dept)
                @php $max = $topDepartments->first()->visit_count; @endphp
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm text-gray-700 truncate max-w-[70%]">{{ $dept->department }}</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $dept->visit_count }}</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500 rounded-full transition-all"
                             style="width: {{ $max > 0 ? round(($dept->visit_count / $max) * 100) : 0 }}%">
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 text-center py-6">No visits this month yet</p>
            @endforelse
        </div>
    </div>

</div>

{{-- Recent Visits --}}
<div class="bg-white rounded-xl border border-gray-200">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Recent Visits</h2>
        <a href="{{ route('visits.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Visitor</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Host</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Purpose</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Check In</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($recentVisits as $visit)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('visits.show', $visit) }}" class="font-medium text-gray-800 hover:text-indigo-600 transition-colors">
                                {{ $visit->visitor->name }}
                            </a>
                            @if($visit->visitor->company)
                                <p class="text-xs text-gray-400">{{ $visit->visitor->company }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            {{ $visit->host->name }}
                            <p class="text-xs text-gray-400">{{ $visit->host->department }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600 max-w-[200px] truncate">{{ $visit->purpose }}</td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">{{ $visit->check_in_at->format('d M, H:i') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $visit->statusBadgeClass() }}">
                                {{ $visit->statusLabel() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm">No visits recorded yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
