<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email')));
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('public-create-expense', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('public-expense-show', function (Request $request) {
            $hash = (string) $request->route('hash', '');

            return Limit::perMinute(60)->by($request->ip().'|'.$hash);
        });

        RateLimiter::for('public-validate-participant', function (Request $request) {
            $hash = (string) $request->route('hash', '');

            return Limit::perMinute(20)->by($request->ip().'|'.$hash);
        });

        RateLimiter::for('public-submit-proof', function (Request $request) {
            $hash = (string) $request->route('hash', '');

            return Limit::perMinute(10)->by($request->ip().'|'.$hash);
        });

        RateLimiter::for('public-sensitive-mutation', function (Request $request) {
            $hash = (string) $request->route('hash', '');

            return Limit::perMinute(40)->by($request->ip().'|'.$hash);
        });

        RateLimiter::for('public-charge-action', function (Request $request) {
            $charge = (string) $request->route('charge', '');

            return Limit::perMinute(30)->by($request->ip().'|'.$charge);
        });

        RateLimiter::for('public-proof-download', function (Request $request) {
            $charge = (string) $request->route('charge', '');

            return Limit::perMinute(20)->by($request->ip().'|download|'.$charge);
        });

        RateLimiter::for('public-proof-preview', function (Request $request) {
            $charge = (string) $request->route('charge', '');

            return Limit::perMinute(20)->by($request->ip().'|preview|'.$charge);
        });
    }
}
