@extends('layouts.app')

@section('title', 'Register Visit')

@section('actions')
    <a href="{{ route('visits.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-gray-200 p-6">

        <div class="flex items-center gap-3 mb-5 pb-5 border-b border-gray-100">
            <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800">Visitor Check-In</h2>
                <p class="text-xs text-gray-400">{{ now()->format('l, d F Y — H:i') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('visits.store') }}" class="space-y-5">
            @csrf

            {{-- Visitor --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-gray-700">
                        Visitor <span class="text-red-500">*</span>
                    </label>
                    <a href="{{ route('visitors.create') }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ New Visitor</a>
                </div>
                <select name="visitor_id" required
                        class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition bg-white
                               {{ $errors->has('visitor_id') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}">
                    <option value="">— Select a visitor —</option>
                    @foreach($visitors as $visitor)
                        <option value="{{ $visitor->id }}"
                                {{ old('visitor_id', request('visitor_id')) == $visitor->id ? 'selected' : '' }}>
                            {{ $visitor->name }}
                            @if($visitor->company) ({{ $visitor->company }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('visitor_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Host --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-gray-700">
                        Visiting <span class="text-red-500">*</span>
                    </label>
                    <a href="{{ route('hosts.create') }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ New Host</a>
                </div>
                <select name="host_id" required
                        class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition bg-white
                               {{ $errors->has('host_id') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}">
                    <option value="">— Select a host —</option>
                    @foreach($hosts as $host)
                        <option value="{{ $host->id }}" {{ old('host_id') == $host->id ? 'selected' : '' }}>
                            {{ $host->name }} — {{ $host->department }}
                        </option>
                    @endforeach
                </select>
                @error('host_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Purpose --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Purpose of Visit <span class="text-red-500">*</span>
                </label>
                <input type="text" name="purpose" value="{{ old('purpose') }}" required
                       placeholder="e.g. Meeting, Delivery, Interview…"
                       class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                              {{ $errors->has('purpose') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"/>
                @error('purpose') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                {{-- Badge Number --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Badge Number</label>
                    <input type="text" name="badge_number" value="{{ old('badge_number') }}"
                           placeholder="Optional"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"/>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}"
                           placeholder="Optional"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"/>
                </div>

            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Check In
                </button>
                <a href="{{ route('visits.index') }}"
                   class="px-5 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</div>
@endsection
