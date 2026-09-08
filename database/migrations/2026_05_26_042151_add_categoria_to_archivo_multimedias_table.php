<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos_multimedia', function (Blueprint $table) {
            // Agregamos la columna 'categoria'. 
            // Le ponemos 'seguimiento' por defecto para que los archivos viejos no den error.
            $table->string('categoria')->default('seguimiento')->after('tipo_archivo');
        });
    }

    public function down(): void
    {
        Schema::table('archivos_multimedia', function (Blueprint $table) {
            // Esto permite deshacer el cambio si en el futuro lo necesitamos
            $table->dropColumn('categoria');
        });
    }
};