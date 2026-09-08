<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fincas', function (Blueprint $table) {
            // Eliminamos el string viejo
            $table->dropColumn('ubicacion_gps');
            // Agregamos Latitud y Longitud precisas (hasta 8 decimales para GPS exacto)
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fincas', function (Blueprint $table) {
            $table->string('ubicacion_gps')->nullable();
            $table->dropColumn(['latitud', 'longitud']);
        });
    }
};