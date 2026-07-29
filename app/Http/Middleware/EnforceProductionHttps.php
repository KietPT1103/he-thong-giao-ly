<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceProductionHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.env') === 'production' && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
