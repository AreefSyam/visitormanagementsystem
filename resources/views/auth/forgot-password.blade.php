<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Forgot Password — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-50 antialiased min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        {{-- Logo / Brand --}}
        <div class="flex items-center justify-center gap-3 mb-8">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div>
                <p class="text-gray-900 text-base font-semibold leading-tight">VMS</p>
                <p class="text-gray-500 text-xs">Visitor Management</p>
            </div>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8">

            {{-- Heading --}}
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-gray-900">Forgot your password?</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
            </div>

            {{-- Success flash message --}}
            @if (session('success'))
                <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm mb-6"
                    role="alert">
                    <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                {{-- Email --}}
                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Email address
                    </label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                        autocomplete="email" autofocus
                        aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}"
                        class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                               {{ $errors->has('email')
                                   ? 'border-red-400 focus:ring-2 focus:ring-red-300'
                                   : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"
                        placeholder="you@example.com" />
                    @error('email')
                        <p id="email-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit button --}}
                <button type="submit"
                    class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700
                           text-white text-sm font-medium rounded-lg transition-colors focus:outline-none
                           focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Send Password Reset Link
                </button>
            </form>

        </div>

        {{-- Back to login --}}
        <div class="mt-5 text-center">
            <a href="{{ route('login') }}"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Login
            </a>
        </div>

    </div>

</body>

</html>
