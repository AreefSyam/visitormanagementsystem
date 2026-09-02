@extends('layouts.app')

@section('title', 'Visitors')

@section('actions')
    <a href="{{ route('visitors.create') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Visitor
    </a>
@endsection

@section('content')

{{-- Search --}}
<form method="GET" class="mb-5">
    <div class="flex gap-3">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Search by name, email, phone or ID…"
                   class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white"/>
        </div>
        <button type="submit"
                class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors">
            Search
        </button>
        @if($search)
            <a href="{{ route('visitors.index') }}"
               class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                Clear
            </a>
        @endif
    </div>
</form>

<div class="bg-white rounded-xl border border-gray-200">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <p class="text-sm text-gray-500">
            {{ $visitors->total() }} {{ Str::plural('visitor', $visitors->total()) }} found
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Name</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Contact</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">ID</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Company</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Total Visits</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Registered</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($visitors as $visitor)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-indigo-700 text-xs font-semibold">
                                        {{ strtoupper(substr($visitor->name, 0, 2)) }}
                                    </span>
                                </div>
                                <a href="{{ route('visitors.show', $visitor) }}"
                                   class="font-medium text-gray-800 hover:text-indigo-600 transition-colors">
                                    {{ $visitor->name }}
                                </a>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <p>{{ $visitor->phone }}</p>
                            @if($visitor->email)
                                <p class="text-xs text-gray-400">{{ $visitor->email }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <p class="text-xs text-gray-400">{{ \App\Models\Visitor::idTypeLabel($visitor->id_type) }}</p>
                            <p>{{ $visitor->id_number }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $visitor->company ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-full">
                                {{ $visitor->visits_count }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-400 text-xs whitespace-nowrap">{{ $visitor->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2 justify-end">
                                <a href="{{ route('visitors.show', $visitor) }}"
                                   class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                                <span class="text-gray-300">|</span>
                                <a href="{{ route('visitors.edit', $visitor) }}"
                                   class="text-xs text-gray-500 hover:text-gray-700 font-medium">Edit</a>
                                <span class="text-gray-300">|</span>
                                <form method="POST" action="{{ route('visitors.destroy', $visitor) }}"
                                      onsubmit="return confirm('Remove {{ addslashes($visitor->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400 text-sm">
                            @if($search)
                                No visitors match "{{ $search }}"
                            @else
                                No visitors registered yet.
                                <a href="{{ route('visitors.create') }}" class="text-indigo-600 hover:underline ml-1">Add one</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($visitors->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $visitors->links() }}
        </div>
    @endif

</div>
@endsection
