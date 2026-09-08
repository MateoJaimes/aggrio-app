<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturas_iot', function (Blueprint $table) {
            $table->id();
            
            // ¿En qué lote de cultivo está este sensor?
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            
            // Identificador único del dispositivo físico (ej: MAC Address del ESP32)
            $table->string('mac_dispositivo')->nullable(); 
            
            // Qué estamos midiendo: 'temperatura', 'humedad_suelo', 'radiacion_solar'
            $table->string('tipo_medicion'); 
            
            // El valor numérico exacto (ej: 28.5)
            $table->decimal('valor', 8, 2); 
            
            // La unidad de medida (ej: '°C', '%', 'W/m2')
            $table->string('unidad'); 
            
            // Cuándo se tomó el dato exactamente en campo
            $table->timestamp('fecha_medicion')->useCurrent();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas_iot');
    }
};