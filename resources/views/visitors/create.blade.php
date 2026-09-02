@extends('layouts.app')

@section('title', 'Register Visitor')

@section('actions')
    <a href="{{ route('visitors.index') }}"
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

        <h2 class="text-base font-semibold text-gray-800 mb-5">Visitor Details</h2>

        <form method="POST" action="{{ route('visitors.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                {{-- Name --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus
                           class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                                  {{ $errors->has('name') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"/>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Phone <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required
                           class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                                  {{ $errors->has('phone') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"/>
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                                  {{ $errors->has('email') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"/>
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Company --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Company / Organisation</label>
                    <input type="text" name="company" value="{{ old('company') }}"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"/>
                </div>

                {{-- ID Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        ID Type <span class="text-red-500">*</span>
                    </label>
                    <select name="id_type" required
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-white">
                        @foreach(['ic' => 'IC / MyKad', 'passport' => 'Passport', 'driving_license' => 'Driving License', 'other' => 'Other'] as $val => $label)
                            <option value="{{ $val }}" {{ old('id_type', 'ic') === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- ID Number --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        ID Number <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="id_number" value="{{ old('id_number') }}" required
                           class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                                  {{ $errors->has('id_number') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"/>
                    @error('id_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Photo --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Photo <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="file" name="photo" accept="image/*"
                           class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition"/>
                    @error('photo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Register Visitor
                </button>
                <a href="{{ route('visitors.index') }}"
                   class="px-5 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</div>
@endsection
