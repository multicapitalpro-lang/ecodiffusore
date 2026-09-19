<?php

namespace App\Core;

use App\Models\ApiToken;

/**
 * Autenticacao por token (Authorization: Bearer <token>) pro futuro app mobile -- paralela a
 * App\Core\Auth (sessao/cookie, usado pelo painel web). Nao mexe em $_SESSION em nenhum momento.
 * Sem CSRF aqui de proposito: CSRF protege autenticacao por cookie (o navegador anexa sozinho);
 * um token Bearer so vai no request se quem chamou ja tiver o valor, entao nao ha o que forjar.
 */
class ApiAuth
{
    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? null) : null);

        if (!$header || !preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
            return null;
        }

        return $m[1];
    }

    public static function user(): ?array
    {
        static $cached = false;

        if ($cached !== false) {
            return $cached;
        }

        $token = self::bearerToken();
        $user = $token ? ApiToken::userForToken($token) : null;

        if ($user && $user['status'] !== 'active') {
            $user = null;
        }

        $cached = $user;

        return $user;
    }

    /** Encerra a requisicao com 401 se nao houver um token valido -- chamar no topo de todo
     *  metodo de Api\*Controller, exceto login(). */
    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            ApiResponse::error('Nao autenticado.', 401);
        }

        return $user;
    }
}
