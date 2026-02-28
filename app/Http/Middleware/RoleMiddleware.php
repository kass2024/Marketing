<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage:
     * ->middleware('role:admin')
     * ->middleware('role:admin,client')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Get authenticated user
        $user = $request->user();

        // If not authenticated
        if (!$user) {
            return redirect()->route('login');
        }

        // If no roles specified, allow access
        if (empty($roles)) {
            return $next($request);
        }

        // Super Admin always allowed
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Normalize roles (remove spaces just in case)
        $roles = array_map('trim', $roles);

        // Check if user role matches allowed roles
        if (!in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}