<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        abort_unless($user->is_active, 403);

        // The first version intentionally supports one active tenant per identity.
        // Tenant selection is derived from the authenticated membership, never from a request parameter.
        $memberships = $user->memberships()->where('is_active', true)->with('organization')->get();

        if ($memberships->count() !== 1) {
            throw new AccessDeniedHttpException('La cuenta debe tener exactamente una membresía activa.');
        }

        $membership = $memberships->first();
        abort_unless($membership->organization?->is_active, 403);
        $request->attributes->set('tenant', $membership->organization);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
