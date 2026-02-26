<?php

namespace Rocont\CompassChannel;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Rocont\CompassChannel\Support\CompassClientFactory;

class CompassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/compass.php', 'compass');

        $this->app->singleton(CompassClientFactory::class, fn() => new CompassClientFactory());

        $this->app->singleton(CompassChannel::class, function ($app) {
            return new CompassChannel(
                $app->make(CompassClientFactory::class),
                $app['config'],
                $app['config']['compass']['default']
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/compass.php' => config_path('compass.php'),
        ], 'config');

        // Регистрируем новый канал уведомлений "compass"
        Notification::extend('compass', fn($app) => $app->make(CompassChannel::class));
    }
}