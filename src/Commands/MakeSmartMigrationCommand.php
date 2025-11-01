<?php

namespace LucasKayque\FilamentTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeSmartMigrationCommand extends Command
{
    protected $signature = 'make:smart-migration {table} {--columns=}';
    protected $description = 'Cria uma migration com base no nome da tabela e colunas. Adiciona relacionamentos automaticamente.';

    public function handle()
    {
        $table = $this->argument('table');
        $columns = explode(',', $this->option('columns'));
        $className = 'Create' . Str::studly($table) . 'Table';
        $fileName = date('Y_m_d_His') . '_create_' . $table . '_table.php';
        $filePath = database_path('migrations/' . $fileName);

        $migration = "<?php\n\nuse Illuminate\Database\Migrations\Migration;\n";
        $migration .= "use Illuminate\Database\Schema\Blueprint;\n";
        $migration .= "use Illuminate\Support\Facades\Schema;\n\n";
        $migration .= "return new class extends Migration\n{\n";
        $migration .= "    public function up(): void\n    {\n";
        $migration .= "        Schema::create('$table', function (Blueprint \$table) {\n";
        $migration .= "            \$table->id();\n";

        foreach ($columns as $column) {
            $column = trim($column);

            if (Str::endsWith($column, '_id')) {
                $relatedTable = Str::plural(Str::beforeLast($column, '_id'));
                $migration .= "            \$table->foreignId('$column')\n";
                $migration .= "                ->nullable()\n";
                $migration .= "                ->constrained('$relatedTable')\n";
                $migration .= "                ->onUpdate('cascade');\n";
            } else {
                $migration .= "            \$table->string('$column')\n";
                $migration .= "                ->nullable()\n";
                $migration .= "                ->default(null);\n";
            }
        }

        $migration .= "            \$table->timestamps();\n";
        $migration .= "        });\n";
        $migration .= "    }\n\n";
        $migration .= "    public function down(): void\n    {\n";
        $migration .= "        Schema::dropIfExists('$table');\n";
        $migration .= "    }\n";
        $migration .= "};\n";

        File::put($filePath, $migration);

        $this->info("Migration criada: $fileName");
    }
}
