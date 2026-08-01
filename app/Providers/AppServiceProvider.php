<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Derrière Caddy (HTTPS) : forcer les URLs d'assets en https
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Vue d'autorisation OAuth pour les clients MCP.
        // Doc : https://laravel.com/docs/mcp#oauth
        Passport::authorizationView(function (array $parameters) {
            return view('mcp.authorize', $parameters);
        });

        Passport::tokensCan([
            'mcp:use' => 'Utiliser le serveur MCP LED Display',
        ]);

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }
}
