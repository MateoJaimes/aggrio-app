<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_iot', function (Blueprint $table) {
            $table->id();

            // Lote en el que está instalado el sensor. Las lecturas que envíe con
            // su API key se guardan siempre en este lote (RF002).
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();

            $table->string('nombre');
            $table->string('mac_dispositivo', 50)->nullable()->unique();

            // Sólo se guarda el hash SHA-256; la key en claro se muestra una vez.
            $table->string('api_key_hash', 64)->unique();

            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_conexion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_iot');
    }
};
