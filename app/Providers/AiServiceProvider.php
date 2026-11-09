<?php

namespace App\Providers;

use App\Services\AI\AiManager;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiManager::class, function ($app) {
            return new AiManager($app);
        });

        $this->app->bind(AiProviderInterface::class, function ($app) {
            return $app->make(AiManager::class)->provider();
        });
    }
}
