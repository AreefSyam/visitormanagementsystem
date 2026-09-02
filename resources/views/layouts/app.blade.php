<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-50 antialiased">

    <div class="flex h-screen overflow-hidden">

        {{-- ── Sidebar ── --}}
        <aside class="w-64 bg-indigo-950 flex flex-col flex-shrink-0">

            {{-- Logo --}}
            <div class="px-6 py-5 border-b border-indigo-800/60">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white text-sm font-semibold leading-tight">VMS</p>
                        <p class="text-indigo-400 text-xs">Visitor Management</p>
                    </div>
                </div>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">

                @php
                    // $routeName = used to generate the URL
                    // $matchPattern = used to detect active state (supports wildcards)
                    $navLink = fn(string $routeName, string $matchPattern, string $label, string $icon) => [
                        'active' => request()->routeIs($matchPattern),
                        'href' => route($routeName),
                        'label' => $label,
                        'icon' => $icon,
                    ];
                    $links = [
                        $navLink(
                            'dashboard',
                            'dashboard',
                            'Dashboard',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
                        ),
                        $navLink(
                            'visits.create',
                            'visits.create',
                            'New Visit',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                        ),
                        $navLink(
                            'visits.index',
                            'visits.*',
                            'Visit History',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>',
                        ),
                        $navLink(
                            'visitors.index',
                            'visitors.*',
                            'Visitors',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                        ),
                        $navLink(
                            'hosts.index',
                            'hosts.*',
                            'Hosts',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
                        ),
                    ];

                    // Add Analytics link (TEMPORARILY visible for all authenticated users - for testing)
                    // TODO: Restore admin check after testing
                    if (auth()->check()) {
                        $links[] = $navLink(
                            'analytics.index',
                            'analytics.*',
                            'Analytics',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                        );
                    }
                @endphp

                @foreach ($links as $link)
                    <a href="{{ $link['href'] }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $link['active'] ? 'bg-indigo-600 text-white' : 'text-indigo-300 hover:bg-indigo-800/60 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.75"
                            viewBox="0 0 24 24">
                            {!! $link['icon'] !!}
                        </svg>
                        {{ $link['label'] }}
                    </a>
                @endforeach

            </nav>

            {{-- Footer --}}
            <div class="px-3 py-4 border-t border-indigo-800/60">
                <div class="flex items-center justify-between gap-2 px-2 py-2 rounded-lg bg-indigo-900/40">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-xs font-semibold">
                                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-white text-xs font-medium truncate">{{ auth()->user()->name ?? 'User' }}</p>
                            @if (auth()->user()->isAdmin())
                                <p class="text-indigo-400 text-[10px]">Administrator</p>
                            @else
                                <p class="text-indigo-400 text-[10px]">{{ auth()->user()->email ?? '' }}</p>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
                        @csrf
                        <button type="submit"
                            class="p-1.5 hover:bg-indigo-800 rounded text-indigo-300 hover:text-white transition-colors"
                            title="Logout">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
                <p class="text-indigo-500 text-xs mt-2 px-2">{{ now()->format('l, d M Y') }}</p>
            </div>

        </aside>

        {{-- ── Main ── --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Top bar --}}
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
                <h1 class="text-lg font-semibold text-gray-800">@yield('title', 'Dashboard')</h1>
                <div class="flex items-center gap-3">
                    @yield('actions')
                </div>
            </header>

            {{-- Flash messages --}}
            @if (session('success') || session('error'))
                <div class="px-6 pt-4">
                    @if (session('success'))
                        <div
                            class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div
                            class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            @endif

            {{-- Page content --}}
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>

        </div>
    </div>

</body>

</html>
