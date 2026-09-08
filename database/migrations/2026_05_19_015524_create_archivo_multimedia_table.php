<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos_multimedia', function (Blueprint $table) {
            $table->id();
            // Esta línea mágica crea dos columnas: fileable_id y fileable_type
            // Así el sistema sabrá si la foto es de una Finca (ID 1) o de un Lote (ID 5)
            $table->morphs('fileable'); 
            
            $table->string('ruta_archivo'); // Aquí guardaremos el Link de la foto/audio
            $table->string('tipo_archivo'); // Ej: 'foto_finca', 'audio_tecnico', 'foto_plaga'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_multimedia');
    }
};