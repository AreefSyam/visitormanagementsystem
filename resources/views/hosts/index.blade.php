@extends('layouts.app')

@section('title', 'Hosts')

@section('actions')
    <a href="{{ route('hosts.create') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Host
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
               placeholder="Search hosts…"
               class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white"/>
    </div>
    <select name="department"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
        <option value="">All Departments</option>
        @foreach($departments as $dept)
            <option value="{{ $dept }}" {{ $department === $dept ? 'selected' : '' }}>{{ $dept }}</option>
        @endforeach
    </select>
    <button type="submit"
            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors">
        Filter
    </button>
    @if($search || $department)
        <a href="{{ route('hosts.index') }}"
           class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">Clear</a>
    @endif
</form>

<div class="bg-white rounded-xl border border-gray-200">

    <div class="px-5 py-4 border-b border-gray-100">
        <p class="text-sm text-gray-500">{{ $hosts->total() }} {{ Str::plural('host', $hosts->total()) }} found</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Name</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Department</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Contact</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Total Visits</th>
                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($hosts as $host)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-purple-700 text-xs font-semibold">
                                        {{ strtoupper(substr($host->name, 0, 2)) }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $host->name }}</p>
                                    @if($host->position)
                                        <p class="text-xs text-gray-400">{{ $host->position }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $host->department }}</td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <p>{{ $host->email }}</p>
                            @if($host->phone)
                                <p class="text-xs text-gray-400">{{ $host->phone }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-purple-50 text-purple-700 text-xs font-semibold rounded-full">
                                {{ $host->visits_count }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            @if($host->is_active)
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2 justify-end">
                                <a href="{{ route('hosts.edit', $host) }}"
                                   class="text-xs text-gray-500 hover:text-gray-700 font-medium">Edit</a>
                                <span class="text-gray-300">|</span>
                                <form method="POST" action="{{ route('hosts.destroy', $host) }}"
                                      onsubmit="return confirm('Remove {{ addslashes($host->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400 text-sm">
                            @if($search || $department)
                                No hosts match the current filters
                            @else
                                No hosts added yet.
                                <a href="{{ route('hosts.create') }}" class="text-indigo-600 hover:underline ml-1">Add one</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($hosts->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $hosts->links() }}
        </div>
    @endif

</div>
@endsection
