@extends('layouts.app')

@section('title', 'Visit Details')

@section('actions')
    <a href="{{ route('visits.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Visit History
    </a>
@endsection

@section('content')
<div class="max-w-2xl space-y-5">

    {{-- Status Banner --}}
    @if($visit->isActive())
        <div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-xl px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1.5 text-green-700 font-semibold text-sm">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    Active Visit
                </span>
                <span class="text-green-600 text-sm">
                    Checked in {{ $visit->check_in_at->diffForHumans() }}
                </span>
            </div>
            <form method="POST" action="{{ route('visits.checkout', $visit) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Check Out
                </button>
            </form>
        </div>
    @elseif($visit->status === 'checked_out')
        <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-5 py-4">
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            <span class="text-gray-600 text-sm font-medium">Visit completed</span>
            @if($visit->duration())
                <span class="text-gray-400 text-sm">— duration {{ $visit->duration() }}</span>
            @endif
        </div>
    @else
        <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-5 py-4">
            <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span class="text-red-600 text-sm font-medium">Visit cancelled</span>
        </div>
    @endif

    {{-- Visit Details Card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">

        <h2 class="text-sm font-semibold text-gray-800 mb-4">Visit Information</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">

            <div>
                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Visitor</dt>
                <dd>
                    <a href="{{ route('visitors.show', $visit->visitor) }}"
                       class="font-medium text-gray-800 hover:text-indigo-600 transition-colors">
                        {{ $visit->visitor->name }}
                    </a>
                    @if($visit->visitor->company)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $visit->visitor->company }}</p>
                    @endif
                    <p class="text-xs text-gray-400">{{ $visit->visitor->phone }}</p>
                </dd>
            </div>

            <div>
                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Host</dt>
                <dd>
                    <p class="font-medium text-gray-800">{{ $visit->host->name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $visit->host->department }}</p>
                    @if($visit->host->position)
                        <p class="text-xs text-gray-400">{{ $visit->host->position }}</p>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Purpose</dt>
                <dd class="text-gray-700">{{ $visit->purpose }}</dd>
            </div>

            <div>
                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Status</dt>
                <dd>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $visit->statusBadgeClass() }}">
                        {{ $visit->statusLabel() }}
                    </span>
                </dd>
            </div>

            <div>
                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Check In</dt>
                <dd class="text-gray-700">{{ $visit->check_in_at->format('d M Y, H:i') }}</dd>
            </div>

            <div>
                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Check Out</dt>
                <dd class="text-gray-700">
                    {{ $visit->check_out_at?->format('d M Y, H:i') ?? '—' }}
                </dd>
            </div>

            @if($visit->badge_number)
                <div>
                    <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Badge Number</dt>
                    <dd class="text-gray-700 font-mono">{{ $visit->badge_number }}</dd>
                </div>
            @endif

            @if($visit->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Notes</dt>
                    <dd class="text-gray-700">{{ $visit->notes }}</dd>
                </div>
            @endif

        </div>

    </div>

    {{-- Cancel --}}
    @if($visit->isActive())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-700">Cancel this visit</p>
                <p class="text-xs text-gray-400 mt-0.5">This will mark the visit as cancelled without a check-out time.</p>
            </div>
            <form method="POST" action="{{ route('visits.cancel', $visit) }}"
                  onsubmit="return confirm('Cancel this visit?')">
                @csrf
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                    Cancel Visit
                </button>
            </form>
        </div>
    @endif

</div>
@endsection
