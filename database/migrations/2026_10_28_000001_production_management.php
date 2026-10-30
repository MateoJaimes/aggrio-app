<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['fincas', 'lotes'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('uuid')->nullable()->unique();
                $t->json('parametros')->nullable();
            });
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                }
            });
        }
        // Typed records share identity, ownership, lineage and auditing. Their
        // business fields are validated by ProductionSchema, never arbitrary JSON.
        Schema::create('production_records', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('finca_id')->constrained('fincas')->restrictOnDelete();
            $t->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $t->string('tipo', 32)->index();
            $t->string('nombre');
            $t->date('fecha')->nullable()->index();
            $t->string('estado', 40)->default('activo');
            foreach (['cultivo_id', 'ciclo_id', 'cosecha_id', 'costo_id', 'venta_id'] as $column) {
                $t->foreignId($column)->nullable()->constrained('production_records')->restrictOnDelete();
            }
            $t->foreignId('actividad_id')->nullable()->constrained('actividades')->restrictOnDelete();
            $t->json('datos');
            $t->decimal('total', 18, 2)->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('registrado_por')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('dispositivo', 255)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['finca_id', 'tipo', 'fecha']);
        });
        Schema::table('actividades', function (Blueprint $t) {
            $t->foreignId('cultivo_id')->nullable()->constrained('production_records')->restrictOnDelete();
            $t->foreignId('ciclo_id')->nullable()->constrained('production_records')->restrictOnDelete();
        });
        // Preserve the original lot data. Classification is deliberately pending:
        // a crop's permanence cannot safely be inferred from its name.
        DB::table('lotes')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                if (empty($row->tipo_cultivo)) {
                    continue;
                }
                $id = DB::table('production_records')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'finca_id' => $row->finca_id, 'lote_id' => $row->id,
                    'tipo' => 'cultivos', 'nombre' => $row->tipo_cultivo.' · '.$row->nombre,
                    'fecha' => $row->fecha_siembra, 'estado' => 'por_revisar',
                    'datos' => json_encode(['especie' => $row->tipo_cultivo, 'variedad' => $row->variedad,
                        'area' => $row->hectareas, 'tipo' => 'por_clasificar', 'migrado' => true]),
                    'total' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('actividades')->where('lote_id', $row->id)->update(['cultivo_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cultivo_id');
            $t->dropConstrainedForeignId('ciclo_id');
        });
        Schema::dropIfExists('production_records');
        foreach (['fincas', 'lotes'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['uuid', 'parametros']));
        }
    }
};
