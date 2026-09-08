<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fincas', function (Blueprint $table) {
            $table->id();
            // Clave foránea que conecta la finca con el usuario aprobado
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Datos específicos del negocio cacaotero
            $table->string('nombre');
            $table->string('ubicacion_gps')->nullable(); // Ideal para mapas futuros
            $table->decimal('hectareas_totales', 8, 2)->nullable();
            $table->string('tipo_suelo')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fincas');
    }
};
