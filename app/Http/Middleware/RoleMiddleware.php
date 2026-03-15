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
     * Example usage:
     * ->middleware('role:admin')
     * ->middleware('role:client')
     * ->middleware('role:admin,client')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Not logged in
        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin bypass (full access)
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Normalize roles
        $roles = array_map('trim', $roles);

        // Map system roles
        $roleMap = [
            'admin' => ['super_admin','agent'],
            'client' => ['client'],
        ];

        // Build allowed roles list
        $allowedRoles = [];

        foreach ($roles as $role) {

            if (isset($roleMap[$role])) {
                $allowedRoles = array_merge($allowedRoles, $roleMap[$role]);
            } else {
                $allowedRoles[] = $role;
            }

        }

        // Remove duplicates
        $allowedRoles = array_unique($allowedRoles);

        // Check permission
        if (!in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}