<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureClientAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->attributes->get('membership')?->canManage(), 403);

        return $next($request);
    }
}
