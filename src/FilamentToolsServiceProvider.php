<?php

namespace LucasKayque\FilamentTools;

use Illuminate\Support\ServiceProvider;
use LucasKayque\FilamentTools\Commands;
//use LucasKayque\FilamentTools\Commands\MakeSmartModelCommand;

class FilamentToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Registra comandos
        if ($this->app->runningInConsole()) {
            $this->commands([
                //MakeSmartModelCommand::class,
                Commands\MakeSmartModelCommand::class,
                Commands\MakeSmartBuildersCommand::class,
                Commands\MakeSmartMigrationCommand::class,
            ]);

            // Caso queira publicar arquivos futuramente:
            // $this->publishes([
            //     __DIR__ . '/../config/filament-tools.php' => config_path('filament-tools.php'),
            // ], 'filament-tools-config');
        }
    }
}
