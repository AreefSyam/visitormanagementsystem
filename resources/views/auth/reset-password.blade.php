<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Reset Password — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-50 antialiased min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        {{-- Logo / Brand --}}
        <div class="flex flex-col items-center mb-8">
            <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ config('app.name', 'VMS') }}</h1>
            <p class="text-sm text-gray-500 mt-1">Visitor Management System</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8">

            <h2 class="text-lg font-semibold text-gray-800 mb-1">Set a new password</h2>
            <p class="text-sm text-gray-500 mb-6">Choose a strong password to secure your account.</p>

            {{-- Token invalid/expired error --}}
            @if (session('error'))
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm mb-6"
                    role="alert" aria-live="assertive">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <div>
                        <p class="font-medium">{{ session('error') }}</p>
                        <a href="{{ route('password.request') }}"
                            class="mt-2 inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium underline underline-offset-2 transition-colors">
                            Request a new reset link
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            @endif

            {{-- General validation error summary (e.g. invalid token from Password::reset) --}}
            @if ($errors->has('email'))
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm mb-6"
                    role="alert" aria-live="assertive">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <div>
                        <p class="font-medium">This password reset link is invalid or has expired.</p>
                        <a href="{{ route('password.request') }}"
                            class="mt-2 inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium underline underline-offset-2 transition-colors">
                            Request a new reset link
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5" novalidate>
                @csrf

                {{-- Hidden token --}}
                <input type="hidden" name="token" value="{{ $token }}">

                {{-- Hidden email --}}
                <input type="hidden" name="email" value="{{ $email ?? old('email') }}">

                {{-- New Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                        New Password <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required
                        aria-required="true" aria-describedby="password-strength password-error"
                        class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                               {{ $errors->has('password') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}" />
                    @error('password')
                        <p id="password-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror

                    {{-- Password Strength Indicator --}}
                    <div id="password-strength" class="mt-2" aria-live="polite" aria-label="Password strength">
                        <div class="flex items-center gap-1.5 mb-1">
                            <div id="strength-bar-1"
                                class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                            <div id="strength-bar-2"
                                class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                            <div id="strength-bar-3"
                                class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                            <div id="strength-bar-4"
                                class="h-1.5 flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                        </div>
                        <p id="strength-label" class="text-xs text-gray-400"></p>
                    </div>

                    <p class="mt-1.5 text-xs text-gray-400">
                        Must be at least 8 characters with uppercase, lowercase, a number, and a symbol.
                    </p>
                </div>

                {{-- Confirm Password --}}
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Confirm New Password <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        autocomplete="new-password" required aria-required="true" aria-describedby="confirm-error"
                        class="w-full px-3 py-2 text-sm border rounded-lg outline-none transition
                               {{ $errors->has('password_confirmation') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' }}" />
                    @error('password_confirmation')
                        <p id="confirm-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <button type="submit"
                    class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Reset Password
                </button>

            </form>

            {{-- Request New Link fallback --}}
            <div class="mt-5 pt-5 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">
                    Link not working?
                    <a href="{{ route('password.request') }}"
                        class="text-indigo-600 hover:text-indigo-800 font-medium underline underline-offset-2 transition-colors">
                        Request New Link
                    </a>
                </p>
            </div>

        </div>

        {{-- Back to login --}}
        <p class="text-center text-sm text-gray-500 mt-6">
            <a href="{{ route('login') }}"
                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Login
            </a>
        </p>

    </div>

    {{-- Password strength indicator script --}}
    <script>
        (function() {
            const input = document.getElementById('password');
            if (!input) return;

            const bars = [1, 2, 3, 4].map(n => document.getElementById('strength-bar-' + n));
            const label = document.getElementById('strength-label');

            const levels = [{
                    color: 'bg-red-500',
                    text: 'Weak',
                    textColor: 'text-red-600'
                },
                {
                    color: 'bg-orange-400',
                    text: 'Fair',
                    textColor: 'text-orange-600'
                },
                {
                    color: 'bg-yellow-400',
                    text: 'Good',
                    textColor: 'text-yellow-600'
                },
                {
                    color: 'bg-green-500',
                    text: 'Strong',
                    textColor: 'text-green-600'
                },
            ];

            function calcStrength(pwd) {
                let score = 0;
                if (pwd.length >= 8) score++;
                if (pwd.length >= 12) score++;
                if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
                if (/\d/.test(pwd)) score++;
                if (/[^A-Za-z0-9]/.test(pwd)) score++;
                // Normalise to 0–3
                return Math.min(Math.floor(score * 4 / 5), 3);
            }

            input.addEventListener('input', function() {
                const pwd = this.value;

                if (!pwd) {
                    bars.forEach(b => {
                        b.className =
                            'h-1.5 flex-1 rounded-full bg-gray-200 transition-colors duration-300';
                    });
                    label.textContent = '';
                    label.className = 'text-xs text-gray-400';
                    return;
                }

                const strength = calcStrength(pwd);
                const level = levels[strength];

                bars.forEach((bar, idx) => {
                    bar.className = 'h-1.5 flex-1 rounded-full transition-colors duration-300 ' +
                        (idx <= strength ? level.color : 'bg-gray-200');
                });

                label.textContent = level.text;
                label.className = 'text-xs font-medium ' + level.textColor;
            });
        }());
    </script>

</body>

</html>
