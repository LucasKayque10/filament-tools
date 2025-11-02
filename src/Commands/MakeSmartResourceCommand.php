<?php

namespace LucasKayque\FilamentTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeSmartResourceCommand extends Command
{
    protected $signature = 'make:smart-resource {resourceName} {--panel=} {--model=}';
    protected $description = 'Cria um resource Filament com padrão';

    public function handle()
    {
        $resource = Str::studly($this->argument('resourceName'));
        $panel = Str::studly($this->option('panel'));
        $modelInput = $this->option('model');

        if (! $resource || ! $panel || ! $modelInput) {
            $this->error('Parâmetros obrigatórios: {resourceName} --panel= --model=');
            return Command::FAILURE;
        }

        // Normaliza o caminho do model
        $modelInput = str_replace('/', '\\', $modelInput);
        $model = Str::startsWith($modelInput, ['App\\', '\\']) ? $modelInput : "App\\Models\\{$modelInput}";
        $modelBase = class_basename($model);

        // Gera os builders
        $this->call('make:smart-builders', [
            'model' => $modelInput,
        ]);

        // Caminhos
        $resourcePath = app_path("Filament/{$panel}/Resources/{$resource}Resource.php");
        $pagesPath = app_path("Filament/{$panel}/Resources/{$resource}Resource/Pages");

        (new Filesystem)->ensureDirectoryExists($pagesPath);

        // Criação segura do Resource
        if (! file_exists($resourcePath)) {
            file_put_contents($resourcePath, $this->buildResourceClass($resource, $panel, $model, $modelBase));
            $this->info("✓ Resource criado: {$resourcePath}");
        } else {
            $this->warn("✗ Resource já existe: {$resourcePath}");
        }

        // Criação segura das páginas
        foreach (['List', 'Create', 'Edit', 'View'] as $type) {
            $pageFile = "{$pagesPath}/{$type}{$resource}.php";

            if (! file_exists($pageFile)) {
                file_put_contents($pageFile, $this->buildPageClass($resource, $panel, $type));
                $this->info("✓ Página criada: {$pageFile}");
            } else {
                $this->warn("✗ Página já existe: {$pageFile}");
            }
        }

        $this->info("✔ Resource {$resource} no painel {$panel} finalizado.");
        return Command::SUCCESS;
    }

    protected function buildResourceClass($resource, $panel, $model, $modelBase)
    {
        $stubPath = __DIR__ . '/../Stubs/Resource/resource.stub';

        if (! file_exists($stubPath)) {
            $this->error("Stub não encontrado em: {$stubPath}");
            return '';
        }

        $stub = file_get_contents($stubPath);

        $replacements = [
            '{$resource}' => $resource,
            '{$panel}' => $panel,
            '{$model}' => $model,
            '{$modelBase}' => $modelBase,
            '{$label}' => $this->label($resource),
            '{$pluralLabel}' => $this->pluralLabel($resource),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function buildPageClass($resource, $panel, $type)
    {
        $stubPath = __DIR__ . "/../Stubs/Page/" . strtolower($type) . ".stub";

        if (! file_exists($stubPath)) {
            $this->error("Stub não encontrado para página: {$type} ({$stubPath})");
            return '';
        }

        $stub = file_get_contents($stubPath);

        $replacements = [
            '{$panel}' => $panel,
            '{$resource}' => $resource,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function label($name)
    {
        return Str::headline($name);
    }

    protected function pluralLabel($name)
    {
        return Str::plural(Str::headline($name));
    }
}
