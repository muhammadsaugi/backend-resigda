<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifikasi signature JWT ID Token dari Google Identity Services secara
 * kriptografis, tanpa dependency composer baru (sengaja ditulis manual pakai
 * openssl_verify bawaan PHP + JWK Google, mengikuti pola yang sama dipakai
 * library firebase/php-jwt) — supaya endpoint google-auth tidak lagi
 * mempercayai begitu saja field email/name yang dikirim klien.
 */
class GoogleTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * Return payload JWT yang sudah terverifikasi (email, name, sub, picture, dst)
     * kalau valid, atau null kalau signature/klaim tidak valid.
     */
    public function verify(string $idToken, string $expectedClientId): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        $signature = $this->base64UrlDecode($sigB64);

        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            return null;
        }

        $jwk = $this->findJwk($header['kid']);
        if (! $jwk) {
            return null;
        }

        $publicKey = openssl_pkey_get_public($this->jwkToPem($jwk['n'], $jwk['e']));
        if (! $publicKey) {
            return null;
        }

        $signedData = $headerB64 . '.' . $payloadB64;
        $isValidSignature = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        if (! $isValidSignature) {
            return null;
        }

        if (($payload['aud'] ?? null) !== $expectedClientId) {
            return null;
        }
        if (! in_array($payload['iss'] ?? '', self::VALID_ISSUERS, true)) {
            return null;
        }
        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }
        $emailVerified = $payload['email_verified'] ?? false;
        if ($emailVerified !== true && $emailVerified !== 'true') {
            return null;
        }
        if (empty($payload['email'])) {
            return null;
        }

        return $payload;
    }

    private function findJwk(string $kid): ?array
    {
        $jwks = Cache::remember('google_jwks', now()->addHour(), function () {
            $response = Http::timeout(10)->get(self::JWKS_URL);

            return $response->successful() ? $response->json() : ['keys' => []];
        });

        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kid'] ?? null) === $kid) {
                return $key;
            }
        }

        // Kunci tidak ketemu (mis. baru saja dirotasi Google) — coba sekali lagi
        // tanpa cache sebelum menyerah.
        Cache::forget('google_jwks');
        $response = Http::timeout(10)->get(self::JWKS_URL);
        $jwks = $response->successful() ? $response->json() : ['keys' => []];
        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kid'] ?? null) === $kid) {
                Cache::put('google_jwks', $jwks, now()->addHour());

                return $key;
            }
        }

        return null;
    }

    /** Rakit PEM SubjectPublicKeyInfo (RSA) dari komponen JWK n/e — DER encoding manual. */
    private function jwkToPem(string $nB64Url, string $eB64Url): string
    {
        $n = $this->base64UrlDecode($nB64Url);
        $e = $this->base64UrlDecode($eB64Url);

        $rsaPublicKey = $this->derSequence($this->derInteger($n) . $this->derInteger($e));

        // OID rsaEncryption (1.2.840.113549.1.1.1) + NULL
        $algorithmIdentifier = $this->derSequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");

        $spki = $this->derSequence($algorithmIdentifier . $this->derBitString($rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function derInteger(string $bin): string
    {
        if (strlen($bin) === 0 || ord($bin[0]) > 0x7f) {
            $bin = "\x00" . $bin;
        }

        return "\x02" . $this->derLength(strlen($bin)) . $bin;
    }

    private function derSequence(string $contents): string
    {
        return "\x30" . $this->derLength(strlen($contents)) . $contents;
    }

    private function derBitString(string $contents): string
    {
        $contents = "\x00" . $contents; // 0 unused bits di byte terakhir
        return "\x03" . $this->derLength(strlen($contents)) . $contents;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
