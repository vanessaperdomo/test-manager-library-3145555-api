<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class TokenService
{
    /**
     * Generate a signed JWT token valid for 8 hours (28800 seconds).
     * Complies with api-contract.md §4:
     * "Token: JWT firmado, 8 horas, con sub y role. Sin datos personales dentro."
     */
    public static function generateToken(User $user): array
    {
        $expiresIn = 28800; // 8 horas en segundos
        $issuedAt = Carbon::now()->timestamp;
        $expireAt = $issuedAt + $expiresIn;

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'sub' => $user->id,
            'role' => $user->role,
            'iat' => $issuedAt,
            'exp' => $expireAt,
        ];

        $secret = config('app.key');

        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        return [
            'access_token' => $jwt,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'role' => $user->role,
        ];
    }

    /**
     * Verify and decode a JWT token.
     */
    public static function verifyToken(string $jwt): ?User
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        $secret = config('app.key');
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $expectedBase64UrlSignature = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedBase64UrlSignature, $base64UrlSignature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        if (!$payload || !isset($payload['sub'], $payload['exp'])) {
            return null;
        }

        // Validar expiración
        if (Carbon::now()->timestamp > $payload['exp']) {
            return null;
        }

        return User::find($payload['sub']);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
