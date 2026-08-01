<?php

namespace App\Providers;

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
