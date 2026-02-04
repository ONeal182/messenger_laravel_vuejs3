<?php

namespace App\Http\Middleware;

use Closure;

class UpdateLastSeen
{
    public function handle($request, Closure $next)
    {
        if ($user = $request->user()) {
            $user->forceFill([
                'last_seen_at' => now(),
            ])->saveQuietly();
        }

        return $next($request);
    }
}
