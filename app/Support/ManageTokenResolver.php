<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Token de gestão de cobrança pública.
 *
 * Prioridade: header X-Manage-Token (recomendado).
 * Fallbacks: body/query manage_token, query manage (?manage=) — legado (URL com token).
 */
final class ManageTokenResolver
{
    public static function resolve(Request $request): ?string
    {
        $header = $request->header('X-Manage-Token');
        if (is_array($header)) {
            $header = $header[0] ?? null;
        }
        if ($header !== null && $header !== '') {
            return (string) $header;
        }

        $t = $request->input('manage_token')
            ?? $request->query('manage_token')
            ?? $request->query('manage');

        return $t !== null && $t !== '' ? (string) $t : null;
    }
}
