<?php

namespace App\Http\Middleware;

use Closure;
use App\Exceptions\ERetailException;

class HandleERetailErrors
{
    public function handle($request, Closure $next)
    {
        try {
            return $next($request);
        } catch (ERetailException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage()
                ], 500);
            }
            
            return redirect()
                ->back()
                ->with('error', 'Error de comunicación con eRetail: ' . $e->getMessage())
                ->withInput();
        }
    }
}