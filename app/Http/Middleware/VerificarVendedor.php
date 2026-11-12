<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarVendedor
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->tipo !== 'vendedor') {
            abort(403);
        }

        return $next($request);
    }
}
