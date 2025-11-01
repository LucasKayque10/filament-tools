<?php

namespace LucasKayque\FilamentTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class MakeSmartBuildersCommand extends Command
{
    protected $signature = 'make:smart-builders {model}';
    protected $description = 'Cria FormBuilder, TableBuilder e InfolistBuilder com base nos campos da tabela do model, se ainda não existirem.';

    public function handle()
    {
        $modelInput = str_replace('/', '\\', $this->argument('model'));
        $modelNamespace = Str::startsWith($modelInput, ['App\\', '\\'])
            ? $modelInput
            : "App\\Models\\{$modelInput}";

        if (!class_exists($modelNamespace)) {
            $this->error("Model {$modelNamespace} não encontrado.");
            return;
        }

        $model = new $modelNamespace;
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            $this->error("Tabela '$table' não encontrada.");
            return;
        }

        $columns = collect(Schema::getColumnListing($table))
            ->reject(fn($column) => $column === 'id')
            ->values();

        if ($columns->isEmpty()) {
            $this->error("Tabela '$table' não possui colunas além de 'id'.");
            return;
        }

        // Caminho respeitando hierarquia
        $relativePath = str_replace('\\', '/', $this->argument('model')); // Sipom/Agente/Arma
        $builderPath = app_path("Filament/Builders/{$relativePath}");
        File::ensureDirectoryExists($builderPath);

        // Namespace correto
        $namespace = "App\\Filament\\Builders\\" . str_replace('/', '\\', $relativePath);

        $this->createFormBuilder($builderPath, $namespace, $columns);
        $this->createTableBuilder($builderPath, $namespace, $columns);
        $this->createInfolistBuilder($builderPath, $namespace, $columns);

        $this->info("Builders verificados/criados em: $builderPath");
    }

    protected function buildStub(string $stubFile, array $replacements): string
    {
        // Caminho do stub dentro do pacote
        $stubPath = __DIR__ . "/../Stubs/SmartBuilder/{$stubFile}.stub";

        // Permitir override por publicação (caso tenha sido publicado na app)
        //$publishedPath = base_path("stubs/smart-builders/{$stubFile}.stub");
        //if (file_exists($publishedPath)) {
        //    $stubPath = $publishedPath;
        //}

        if (! file_exists($stubPath)) {
            throw new \RuntimeException("Stub não encontrado: {$stubPath}");
        }

        $stub = file_get_contents($stubPath);

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ '.$key.' }}', $value, $stub);
        }

        return $stub;
    }

    protected function createFormBuilder($path, $namespace, $columns)
    {
        $file = "$path/FormBuilder.php";
        if (File::exists($file)) {
            $this->warn("FormBuilder já existe: $file");
            return;
        }

        $inputs = $columns->map(fn($col) => "            TextInput::make('{$col}')->required(),")->implode("\n");

        $contents = $this->buildStub('form', [
            'namespace' => $namespace,
            'inputs'    => $inputs,
        ]);

        File::put($file, $contents);
        $this->info("FormBuilder criado: $file");
    }

    protected function createTableBuilder($path, $namespace, $columns)
    {
        $file = "$path/TableBuilder.php";
        if (File::exists($file)) {
            $this->warn("TableBuilder já existe: $file");
            return;
        }

        $textColumns = $columns->map(fn($col) => "            TextColumn::make('{$col}')->searchable(),")->implode("\n");

        $contents = $this->buildStub('table', [
            'namespace' => $namespace,
            'columns'   => $textColumns,
        ]);

        File::put($file, $contents);
        $this->info("TableBuilder criado: $file");
    }

    protected function createInfolistBuilder($path, $namespace, $columns)
    {
        $file = "$path/InfolistBuilder.php";
        if (File::exists($file)) {
            $this->warn("InfolistBuilder já existe: $file");
            return;
        }

        $entries = $columns->map(fn($col) => "            TextEntry::make('{$col}'),")->implode("\n");

        $contents = $this->buildStub('infolist', [
            'namespace' => $namespace,
            'entries'   => $entries,
        ]);

        File::put($file, $contents);
        $this->info("InfolistBuilder criado: $file");
    }

}
