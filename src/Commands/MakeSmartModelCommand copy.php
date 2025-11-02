<?php

namespace LucasKayque\FilamentTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MakeSmartModelCommand extends Command
{
    protected $signature = 'make:smart-model {name} {--table=}';
    protected $description = 'Gera model com fillable, belongsTo, hasMany e filtro dinâmico';

    public function handle()
    {
        $hasManyComments = [];
        $nameInput = $this->argument('name');
        $className = class_basename($nameInput);
        $table = $this->option('table') ?? Str::snake(Str::pluralStudly($className));
        $modelPath = app_path('Models/' . $nameInput . '.php');
        $modelDir = dirname($modelPath);
        $namespace = 'App\\Models' . (Str::contains($nameInput, '/') ? '\\' . str_replace('/', '\\', Str::beforeLast($nameInput, '/')) : '');

        if (!Schema::hasTable($table)) {
            $this->error("Tabela '$table' não encontrada.");
            return;
        }

        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }

        if (file_exists($modelPath)) {
            $this->error("Model já existe: {$modelPath}");
            return;
        }

        $columns = Schema::getColumnListing($table);
        $fillable = collect($columns)->reject(fn($col) => in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))->values();

        // belongsTo: colunas que terminam com _id
        $belongsTo = collect($columns)
            ->filter(fn($col) => Str::endsWith($col, '_id'))
            ->map(function ($col) use ($className, &$hasManyComments) {
                $relation = Str::camel(str_replace('_id', '', $col));
                $relatedClass = Str::studly($relation);

                // Sugerir hasMany no model relacionado
                $hasManyComments[] = "// Adicionar em {$relatedClass}:\n" .
                                    "// public function " . Str::camel(Str::plural($className)) .
                                    "() { return \$this->hasMany({$className}::class, '{$col}'); }";

                return "    public function {$relation}()\n    {\n        return \$this->belongsTo({$relatedClass}::class);\n    }\n";
            });

        $filterConditions = $fillable->map(function ($col) {
            return "            ->when(\$filters['$col'] ?? null, fn(\$q, \$v) => \$q->where('$col', 'ilike', \"%\$v%\"))";
        });

        $modelContent = "<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Contracts\Auditable;

class {$className} extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected \$table = '$table';

    protected \$fillable = [
        " . $fillable->map(fn($f) => "'$f'")->implode(",\n        ") . "
    ];

" . $belongsTo->implode("\n") . "

" . (!empty($hasManyComments) ? '// ---- HasMany Sugeridos ----' . "\n" . implode("\n", $hasManyComments) . "\n" : '') . "

    public function scopeFilter(Builder \$query, array \$filters): Builder
    {
        return \$query
" . $filterConditions->implode("\n") . ";
    }
}
";

        file_put_contents($modelPath, $modelContent);
        $this->info("Model {$className} criado em: {$modelPath}");
    }
}
