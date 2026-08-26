<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header hardening dasar yang tidak ada sama sekali secara default di Laravel.
 * Paling relevan untuk Portal ASN (dashboard Bagian Hukum/Inspektorat) — tanpa
 * X-Frame-Options, halaman admin yang sedang login bisa di-iframe situs lain
 * dan dipancing klik ke tombol sungguhan (clickjacking) lewat overlay tak
 * terlihat, misalnya tombol hapus regulasi.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");

        return $response;
    }
}
