<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    {{-- Prevent automatic zoom on input focus for iOS (Req 15.3) --}}
    <meta name="format-detection" content="telephone=no" />
    <title>Login — {{ config('app.name', 'VMS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-50 antialiased">

    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">

        {{-- Card --}}
        <div class="w-full max-w-md">

            {{-- Logo / Branding --}}
            <div class="flex flex-col items-center mb-8">
                <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ config('app.name', 'VMS') }}</h1>
                <p class="mt-1 text-sm text-gray-500">Visitor Management System</p>
            </div>

            {{-- Form Card --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8">

                <h2 class="text-lg font-semibold text-gray-800 mb-6">Sign in to your account</h2>

                {{-- Session-level flash error (e.g. account suspended, rate limited) --}}
                @if (session('error'))
                    <div role="alert"
                        class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-5 text-sm">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" novalidate aria-label="Login form">
                    @csrf

                    {{-- Email --}}
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Email address
                        </label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            autocomplete="email" inputmode="email" required aria-label="Email address"
                            aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            class="w-full px-3 py-2.5 text-base border rounded-lg outline-none transition
                                {{ $errors->has('email')
                                    ? 'border-red-400 bg-red-50 focus:ring-2 focus:ring-red-300 focus:border-red-400'
                                    : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"
                            placeholder="you@example.com" />
                        @error('email')
                            <p id="email-error" role="alert" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div class="mb-5">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                            <a href="{{ route('password.request') }}"
                                class="text-xs text-indigo-600 hover:text-indigo-700 font-medium transition-colors"
                                tabindex="5">
                                Forgot Password?
                            </a>
                        </div>
                        {{-- Password wrapper for show/hide toggle --}}
                        <div class="relative">
                            <input id="password" type="password" name="password" autocomplete="current-password"
                                required aria-label="Password"
                                aria-describedby="{{ $errors->has('password') ? 'password-error' : '' }}"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                class="w-full px-3 py-2.5 pr-11 text-base border rounded-lg outline-none transition
                                    {{ $errors->has('password')
                                        ? 'border-red-400 bg-red-50 focus:ring-2 focus:ring-red-300 focus:border-red-400'
                                        : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}"
                                placeholder="••••••••" />
                            {{-- Show / hide password toggle (Req 14.2–14.6) --}}
                            <button type="button" id="toggle-password" onclick="togglePasswordVisibility()"
                                aria-label="Show password" aria-pressed="false"
                                class="absolute inset-y-0 right-0 flex items-center justify-center w-11 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none focus:text-indigo-600"
                                tabindex="3">
                                {{-- Eye icon (password hidden state) --}}
                                <svg id="icon-eye" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{-- Eye-slash icon (password visible state) --}}
                                <svg id="icon-eye-slash" class="w-5 h-5 hidden" fill="none" stroke="currentColor"
                                    stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p id="password-error" role="alert"
                                class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Remember Me --}}
                    <div class="flex items-center mb-6">
                        <input id="remember" type="checkbox" name="remember" aria-label="Remember me"
                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer"
                            tabindex="4" />
                        <label for="remember" class="ml-2 block text-sm text-gray-700 cursor-pointer select-none">
                            Remember me
                        </label>
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="w-full flex items-center justify-center px-5 py-3 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-base font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        aria-label="Sign in" style="min-height: 48px;">
                        Sign in
                    </button>

                </form>

            </div>

            {{-- Footer links --}}
            <div class="mt-6 text-center space-y-2">
                <p class="text-sm text-gray-600">
                    Don't have an account?
                    <a href="{{ route('register') }}"
                        class="text-indigo-600 hover:text-indigo-700 font-medium transition-colors">
                        Register
                    </a>
                </p>
            </div>

        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const input = document.getElementById('password');
            const btn = document.getElementById('toggle-password');
            const iconEye = document.getElementById('icon-eye');
            const iconEyeSlash = document.getElementById('icon-eye-slash');

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            // Toggle icons (Req 14.5)
            iconEye.classList.toggle('hidden', isHidden);
            iconEyeSlash.classList.toggle('hidden', !isHidden);

            // Update aria-label and aria-pressed for screen readers (Req 14.6)
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        }
    </script>

</body>

</html>
