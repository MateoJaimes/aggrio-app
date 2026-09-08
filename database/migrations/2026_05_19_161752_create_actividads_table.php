<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            
            // Llave foránea: Esta actividad pertenece a un Lote específico
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            
            // Categorización de la tarea para futuros reportes y gráficas
            $table->string('tipo_actividad'); // Ej: Fertilización, Poda, Control de Plagas, Cosecha
            
            $table->date('fecha'); // Cuándo se realizó la labor
            
            // Decimal amplio (10 dígitos, 2 decimales) para soportar costos grandes
            $table->decimal('costo', 10, 2)->default(0); 
            
            $table->text('observaciones')->nullable(); // Notas adicionales del técnico
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};