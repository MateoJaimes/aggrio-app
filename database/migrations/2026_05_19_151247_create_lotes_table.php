<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('finca_id')->constrained('fincas')->cascadeOnDelete();
            
            $table->string('nombre'); 
            $table->decimal('hectareas', 8, 2); 
            
            // 🚀 AHORA ES MULTI-CULTIVO
            $table->string('tipo_cultivo'); // Ej: 'Cacao', 'Café', 'Aguacate'
            $table->string('variedad')->nullable(); // Ej: 'CCN51', 'Caturra', 'Hass'
            
            $table->date('fecha_siembra')->nullable();
            
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};