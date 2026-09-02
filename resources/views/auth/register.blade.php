<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Register — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-50 antialiased">

    <div class="min-h-screen flex items-center justify-center px-4 py-12 sm:px-6 lg:px-8">

        <div class="w-full max-w-md">

            {{-- Logo / Brand --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 rounded-xl mb-4">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-gray-900">Create your account</h1>
                <p class="mt-1 text-sm text-gray-500">{{ config('app.name') }} — Visitor Management</p>
            </div>

            {{-- Card --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-8 py-8">

                {{-- General session error --}}
                @if (
                    $errors->any() &&
                        !$errors->has('name') &&
                        !$errors->has('email') &&
                        !$errors->has('password') &&
                        !$errors->has('password_confirmation') &&
                        !$errors->has('terms'))
                    <div role="alert"
                        class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.submit') }}" aria-label="Registration form" novalidate>
                    @csrf

                    {{-- Name --}}
                    <div class="mb-5">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Full name
                        </label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}"
                            autocomplete="name" required aria-required="true"
                            aria-describedby="{{ $errors->has('name') ? 'name-error' : 'name-hint' }}"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" placeholder="Jane Smith"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition
                                focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white hover:border-gray-400' }}" />
                        <p id="name-hint" class="sr-only">Enter your full name (2–255 characters)</p>
                        @error('name')
                            <p id="name-error" role="alert" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Email address
                        </label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            autocomplete="email" required aria-required="true"
                            aria-describedby="{{ $errors->has('email') ? 'email-error' : 'email-hint' }}"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            placeholder="jane@example.com"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition
                                focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white hover:border-gray-400' }}" />
                        <p id="email-hint" class="sr-only">Enter a valid email address</p>
                        @error('email')
                            <p id="email-error" role="alert" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div class="mb-2">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Password
                        </label>
                        <input id="password" type="password" name="password" autocomplete="new-password" required
                            aria-required="true"
                            aria-describedby="password-strength {{ $errors->has('password') ? 'password-error' : 'password-hint' }}"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            placeholder="Minimum 8 characters"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition
                                focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white hover:border-gray-400' }}" />
                        <p id="password-hint" class="sr-only">
                            Must be at least 8 characters with uppercase, lowercase, number, and special character
                        </p>
                        @error('password')
                            <p id="password-error" role="alert"
                                class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Password Strength Indicator --}}
                    <div id="password-strength" class="mb-5" aria-live="polite" aria-atomic="true">
                        {{-- Strength bar --}}
                        <div class="flex gap-1 mb-1" aria-hidden="true">
                            <div id="strength-bar-1"
                                class="h-1 flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                            <div id="strength-bar-2"
                                class="h-1 flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                            <div id="strength-bar-3"
                                class="h-1 flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                        </div>
                        <p id="strength-label" class="text-xs text-gray-400">
                            Password strength
                        </p>
                    </div>

                    {{-- Password Confirmation --}}
                    <div class="mb-5">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Confirm password
                        </label>
                        <input id="password_confirmation" type="password" name="password_confirmation"
                            autocomplete="new-password" required aria-required="true"
                            aria-describedby="{{ $errors->has('password_confirmation') ? 'password-confirmation-error' : 'password-confirmation-hint' }}"
                            aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                            placeholder="Re-enter your password"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition
                                focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                {{ $errors->has('password_confirmation') ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white hover:border-gray-400' }}" />
                        <p id="password-confirmation-hint" class="sr-only">Re-enter the same password to confirm</p>
                        @error('password_confirmation')
                            <p id="password-confirmation-error" role="alert"
                                class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Terms of Service --}}
                    <div class="mb-6">
                        <div class="flex items-start gap-3">
                            <input id="terms" type="checkbox" name="terms" value="1"
                                {{ old('terms') ? 'checked' : '' }} required aria-required="true"
                                aria-describedby="{{ $errors->has('terms') ? 'terms-error' : 'terms-label' }}"
                                aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 flex-shrink-0 cursor-pointer" />
                            <label id="terms-label" for="terms"
                                class="text-sm text-gray-600 cursor-pointer leading-snug">
                                I agree to the
                                <span
                                    class="font-medium text-indigo-600 hover:text-indigo-700 underline underline-offset-2">Terms
                                    of Service</span>
                                and
                                <span
                                    class="font-medium text-indigo-600 hover:text-indigo-700 underline underline-offset-2">Privacy
                                    Policy</span>
                            </label>
                        </div>
                        @error('terms')
                            <p id="terms-error" role="alert"
                                class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
                            text-white font-medium text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none
                            focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        aria-label="Create account">
                        Register
                    </button>

                </form>

            </div>

            {{-- Login link --}}
            <p class="mt-6 text-center text-sm text-gray-500">
                Already have an account?
                <a href="{{ route('login') }}"
                    class="font-medium text-indigo-600 hover:text-indigo-700 underline underline-offset-2 transition-colors"
                    aria-label="Go to login page">
                    Login
                </a>
            </p>

        </div>
    </div>

    {{-- Password Strength Indicator Script --}}
    <script>
        (function() {
            const passwordInput = document.getElementById('password');
            const strengthLabel = document.getElementById('strength-label');
            const bars = [
                document.getElementById('strength-bar-1'),
                document.getElementById('strength-bar-2'),
                document.getElementById('strength-bar-3'),
            ];

            /**
             * Evaluate password strength.
             * Returns: 0 = empty, 1 = weak, 2 = medium, 3 = strong
             */
            function evaluateStrength(password) {
                if (!password) return 0;

                const hasUpper = /[A-Z]/.test(password);
                const hasLower = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[!@#$%^&*()+\-=\[\]{}|;:,.<>?]/.test(password);
                const meetsComplexity = hasUpper && hasLower && hasNumber && hasSpecial;

                if (!meetsComplexity || password.length < 8) return 1; // Weak
                if (password.length >= 13) return 3; // Strong
                if (password.length >= 10) return 2; // Medium
                return 1; // Weak
            }

            function applyStrength(level) {
                // Reset all bars
                bars.forEach(bar => {
                    bar.className = 'h-1 flex-1 rounded-full bg-gray-200 transition-colors duration-300';
                });

                if (level === 0) {
                    strengthLabel.textContent = 'Password strength';
                    strengthLabel.className = 'text-xs text-gray-400';
                    return;
                }

                if (level === 1) {
                    bars[0].classList.replace('bg-gray-200', 'bg-red-500');
                    strengthLabel.textContent = 'Weak';
                    strengthLabel.className = 'text-xs text-red-600 font-medium';
                } else if (level === 2) {
                    bars[0].classList.replace('bg-gray-200', 'bg-yellow-400');
                    bars[1].classList.replace('bg-gray-200', 'bg-yellow-400');
                    strengthLabel.textContent = 'Medium';
                    strengthLabel.className = 'text-xs text-yellow-600 font-medium';
                } else {
                    bars[0].classList.replace('bg-gray-200', 'bg-green-500');
                    bars[1].classList.replace('bg-gray-200', 'bg-green-500');
                    bars[2].classList.replace('bg-gray-200', 'bg-green-500');
                    strengthLabel.textContent = 'Strong';
                    strengthLabel.className = 'text-xs text-green-600 font-medium';
                }
            }

            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    applyStrength(evaluateStrength(this.value));
                });
            }
        })();
    </script>

</body>

</html>
