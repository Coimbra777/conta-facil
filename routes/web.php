<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Redirecionamentos legados (URLs públicas antigas)
|--------------------------------------------------------------------------
*/
Route::get('/public/expenses/{hash}', function (Request $request, string $hash) {
    $manage = $request->query('manage');
    if ($manage !== null && $manage !== '') {
        // Fragment não vai para referrer nem servidor; a SPA persiste em localStorage e remove da URL.
        return redirect("/p/{$hash}#manage=".rawurlencode((string) $manage), 302);
    }

    return redirect("/p/{$hash}", 302);
});

/** Links antigos por participante: redireciona para o fluxo único /p/{hash}. */
Route::get('/p/{expenseHash}/{participantHash}', function (string $expenseHash, string $participantHash) {
    return redirect("/p/{$expenseHash}", 302);
});

/*
|--------------------------------------------------------------------------
| SPA React (produção: assets em public/spa; desenvolvimento: Vite em :5173)
|--------------------------------------------------------------------------
| Não captura /api/* (API em routes/api.php).
*/
Route::get('/{any?}', function () {
    return view('spa');
})->where('any', '^(?!api(?:/|$)).*');
