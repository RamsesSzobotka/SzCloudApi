<?php

namespace App\utils;

class Security
{
    private const COOKIE_PATH = '/';
    private const COOKIE_SECURE = true;
    private const COOKIE_HTTPONLY = true;
    private const COOKIE_SAMESITE = 'lax';

    // ─── Cookie names ──────────────────────────────────────────────

    public const ACCESS_TOKEN = 'access_token';
    public const REFRESH_TOKEN = 'refresh_token';

    // ─── TTLs (minutes) ───────────────────────────────────────────

    public const ACCESS_TTL = 15;            // 15 minutos
    public const REFRESH_TTL = 60 * 24 * 7;  // 7 días

    // ─── Auth check ───────────────────────────────────────────────

    public static function isOwner(): \App\Models\User
    {
        $user = auth("api")->user();
        if (!$user) {
            abort(401, 'No autenticado');
        }
        return $user;
    }

    // ─── Cookie builders ──────────────────────────────────────────

    public static function makeCookie(string $name, string $value, int $minutes)
    {
        return cookie()->make(
            $name,
            $value,
            $minutes,
            self::COOKIE_PATH,
            null,
            self::COOKIE_SECURE,
            self::COOKIE_HTTPONLY,
            false,
            self::COOKIE_SAMESITE
        );
    }

    public static function clearCookie(string $name)
    {
        return cookie()->make(
            $name,
            '',
            -1,
            self::COOKIE_PATH,
            null,
            self::COOKIE_SECURE,
            self::COOKIE_HTTPONLY,
            false,
            self::COOKIE_SAMESITE
        );
    }

    // ─── Set cookies on response ──────────────────────────────────

    public static function withAccessCookie($response, string $token)
    {
        return $response->withCookie(
            self::makeCookie(self::ACCESS_TOKEN, $token, self::ACCESS_TTL)
        );
    }

    public static function withRefreshCookie($response, string $token)
    {
        return $response->withCookie(
            self::makeCookie(self::REFRESH_TOKEN, $token, self::REFRESH_TTL)
        );
    }

    public static function withAuthCookies($response, string $accessToken, string $refreshToken)
    {
        return self::withRefreshCookie(
            self::withAccessCookie($response, $accessToken),
            $refreshToken
        );
    }

    public static function clearAuthCookies($response)
    {
        return $response
            ->withCookie(self::clearCookie(self::ACCESS_TOKEN))
            ->withCookie(self::clearCookie(self::REFRESH_TOKEN));
    }
}