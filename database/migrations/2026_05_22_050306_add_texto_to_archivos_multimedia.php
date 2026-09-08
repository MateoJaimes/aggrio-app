<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos_multimedia', function (Blueprint $table) {
            // 1. Agregamos el texto (si no existe aún)
            if (!Schema::hasColumn('archivos_multimedia', 'contenido_texto')) {
                $table->text('contenido_texto')->nullable()->after('modelo_id');
            }
            
            // 2. Hacemos opcionales las columnas de archivos que SÍ sabemos que tienes
            if (Schema::hasColumn('archivos_multimedia', 'ruta_archivo')) {
                $table->string('ruta_archivo')->nullable()->change();
            }
            if (Schema::hasColumn('archivos_multimedia', 'tipo_archivo')) {
                $table->string('tipo_archivo')->nullable()->change();
            }

            // 3. 🚀 CORRECCIÓN: Como 'peso_bytes' no existía, la CREAMOS directamente
            if (!Schema::hasColumn('archivos_multimedia', 'peso_bytes')) {
                $table->integer('peso_bytes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('archivos_multimedia', function (Blueprint $table) {
            $table->dropColumn('contenido_texto');
            $table->dropColumn('peso_bytes');
        });
    }
};