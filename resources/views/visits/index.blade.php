@extends('layouts.app')

@section('title', 'Visit History')

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

{{-- Filters --}}
<form method="GET" class="mb-5 flex flex-wrap gap-3">
    <div class="relative flex-1 min-w-48 max-w-sm">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="search" value="{{ $search }}"
               placeholder="Search visitor or host…"
               class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white"/>
    </div>

    <select name="status"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
        <option value="">All Statuses</option>
        <option value="checked_in"  {{ $status === 'checked_in'  ? 'selected' : '' }}>Checked In</option>
        <option value="checked_out" {{ $status === 'checked_out' ? 'selected' : '' }}>Checked Out</option>
        <option value="cancelled"   {{ $status === 'cancelled'   ? 'selected' : '' }}>Cancelled</option>
    </select>

    <select name="department"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
        <option value="">All Departments</option>
        @foreach($departments as $dept)
            <option value="{{ $dept }}" {{ $department === $dept ? 'selected' : '' }}>{{ $dept }}</option>
        @endforeach
    </select>

    <input type="date" name="date" value="{{ $date }}"
           class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white"/>

    <button type="submit"
            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors">
        Filter
    </button>

    @if($search || $status || $department || $date)
        <a href="{{ route('visits.index') }}"
           class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">Clear</a>
    @endif
</form>

<div class="bg-white rounded-xl border border-gray-200">

    <div class="px-5 py-4 border-b border-gray-100">
        <p class="text-sm text-gray-500">{{ number_format($visits->total()) }} {{ Str::plural('visit', $visits->total()) }} found</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Visitor</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Host / Dept</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Purpose</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Check In</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Check Out</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Duration</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($visits as $visit)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('visitors.show', $visit->visitor) }}"
                               class="font-medium text-gray-800 hover:text-indigo-600 transition-colors">
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
                        <td class="px-5 py-3.5 text-gray-600 max-w-[160px]">
                            <span class="truncate block">{{ $visit->purpose }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap text-xs">
                            {{ $visit->check_in_at->format('d M Y') }}<br>
                            <span class="text-gray-400">{{ $visit->check_in_at->format('H:i') }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap text-xs">
                            @if($visit->check_out_at)
                                {{ $visit->check_out_at->format('d M Y') }}<br>
                                <span class="text-gray-400">{{ $visit->check_out_at->format('H:i') }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                            {{ $visit->duration() ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $visit->statusBadgeClass() }}">
                                {{ $visit->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <a href="{{ route('visits.show', $visit) }}"
                               class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-gray-400 text-sm">
                            @if($search || $status || $department || $date)
                                No visits match the current filters
                            @else
                                No visits recorded yet.
                                <a href="{{ route('visits.create') }}" class="text-indigo-600 hover:underline ml-1">Register one</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($visits->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $visits->links() }}
        </div>
    @endif

</div>
@endsection
