<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @param mixed ...$roles
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$roles): Response|RedirectResponse
    {
        if (!auth()->check()){
            return  redirect()->route('login');
        }
        $user = Auth::user();
        if ($user && in_array($user->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
