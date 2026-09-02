@extends('layouts.app')

@section('title', $visitor->name)

@section('actions')
    <div class="flex items-center gap-3">
        <a href="{{ route('visits.create', ['visitor_id' => $visitor->id]) }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Visit
        </a>
        <a href="{{ route('visitors.edit', $visitor) }}"
           class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            Edit
        </a>
        <a href="{{ route('visitors.index') }}"
           class="text-sm text-gray-400 hover:text-gray-600 transition-colors">← Back</a>
    </div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Profile Card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">

        <div class="text-center mb-5">
            @if($visitor->photo)
                <img src="{{ Storage::url($visitor->photo) }}" alt="{{ $visitor->name }}"
                     class="w-20 h-20 rounded-full object-cover mx-auto border-2 border-indigo-100"/>
            @else
                <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto">
                    <span class="text-indigo-700 text-2xl font-bold">{{ strtoupper(substr($visitor->name, 0, 2)) }}</span>
                </div>
            @endif
            <h2 class="text-base font-semibold text-gray-800 mt-3">{{ $visitor->name }}</h2>
            @if($visitor->company)
                <p class="text-sm text-gray-400">{{ $visitor->company }}</p>
            @endif

            @if($visitor->isCurrentlyCheckedIn())
                <span class="inline-flex items-center gap-1.5 mt-2 text-xs text-green-600 font-medium bg-green-50 px-3 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                    Currently On-site
                </span>
            @endif
        </div>

        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-400">Phone</dt>
                <dd class="text-gray-700 font-medium">{{ $visitor->phone }}</dd>
            </div>
            @if($visitor->email)
                <div class="flex justify-between">
                    <dt class="text-gray-400">Email</dt>
                    <dd class="text-gray-700 truncate ml-4">{{ $visitor->email }}</dd>
                </div>
            @endif
            <div class="flex justify-between">
                <dt class="text-gray-400">ID Type</dt>
                <dd class="text-gray-700">{{\App\Models\Visitor::idTypeLabel($visitor->id_type)}}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-400">ID Number</dt>
                <dd class="text-gray-700 font-mono">{{ $visitor->id_number }}</dd>
            </div>
            <div class="pt-2 border-t border-gray-100 flex justify-between">
                <dt class="text-gray-400">Total Visits</dt>
                <dd class="text-gray-700 font-semibold">{{ $visits->total() }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-400">Registered</dt>
                <dd class="text-gray-700">{{ $visitor->created_at->format('d M Y') }}</dd>
            </div>
        </dl>

    </div>

    {{-- Visit History --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200">

        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Visit History</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Host</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Purpose</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Check In</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Duration</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($visits as $visit)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5 text-gray-700">
                                {{ $visit->host->name }}
                                <p class="text-xs text-gray-400">{{ $visit->host->department }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 max-w-[160px] truncate">{{ $visit->purpose }}</td>
                            <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap text-xs">{{ $visit->check_in_at->format('d M Y, H:i') }}</td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $visit->duration() ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $visit->statusBadgeClass() }}">
                                    {{ $visit->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('visits.show', $visit) }}"
                                   class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">No visits yet</td>
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

</div>
@endsection
