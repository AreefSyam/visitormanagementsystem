<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ValidateSession
{
    /**
     * Session timeout in seconds (120 minutes).
     */
    private const SESSION_TIMEOUT = 7200; // 120 minutes * 60 seconds

    /**
     * Handle an incoming request.
     *
     * Validates session integrity by checking:
     * - Session exists in database
     * - User agent matches session record
     * - Session hasn't exceeded timeout (120 minutes)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate for authenticated users
        if (! Auth::check()) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();
        $currentUserAgent = $request->userAgent();

        // Retrieve session record from database
        $sessionRecord = DB::table('sessions')
            ->where('id', $sessionId)
            ->first();

        // Verify session exists in database
        if (! $sessionRecord) {
            return $this->destroySessionAndRedirect($request, 'Session not found in database');
        }

        // Verify user agent matches session record
        if ($sessionRecord->user_agent !== $currentUserAgent) {
            return $this->destroySessionAndRedirect($request, 'User agent mismatch detected');
        }

        // Check session timeout (120 minutes of inactivity)
        $lastActivity = $sessionRecord->last_activity;
        $currentTime = time();

        if (($currentTime - $lastActivity) > self::SESSION_TIMEOUT) {
            return $this->destroySessionAndRedirect($request, 'Session has expired due to inactivity');
        }

        // Update last_activity timestamp
        DB::table('sessions')
            ->where('id', $sessionId)
            ->update(['last_activity' => $currentTime]);

        return $next($request);
    }

    /**
     * Destroy the session and redirect to login page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $reason
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function destroySessionAndRedirect(Request $request, string $reason): Response
    {
        // Log session validation failure
        logger()->warning('Session validation failed', [
            'reason' => $reason,
            'session_id' => $request->session()->getId(),
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Destroy the session
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect to login with timeout message
        // Try to use named route, fall back to URL if route doesn't exist yet
        try {
            $loginUrl = route('login');
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
            $loginUrl = '/login';
        }

        return redirect($loginUrl)->with('status', 'Your session has expired. Please log in again.');
    }
}
