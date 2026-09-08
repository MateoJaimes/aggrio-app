<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fincas', function (Blueprint $table) {
            // Agregamos la columna 'estado', por defecto nacerá como 'pendiente'
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])
                  ->default('pendiente')
                  ->after('user_id'); // La colocamos visualmente después del user_id
        });
    }

    public function down(): void
    {
        Schema::table('fincas', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};