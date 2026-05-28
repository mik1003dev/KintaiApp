<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

class EnsureUserEmailIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (
            $user &&
            $user->role !== 'admin' &&
            $user instanceof MustVerifyEmail &&
            ! $user->hasVerifiedEmail()
        ) {
            return $request->expectsJson()
                ? abort(403, 'メール認証が完了していません。')
                : redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
