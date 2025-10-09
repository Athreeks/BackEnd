<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next)
    {
        // Cek apakah pengguna sudah login DAN memiliki role 'admin'
        if ($request->user() && $request->user()->role === 'admin') {
            return $next($request);
        }

        // Jika tidak, kirim respons error
        return response()->json(['message' => 'Akses ditolak. Hanya untuk Admin.'], 403);
    }
}
