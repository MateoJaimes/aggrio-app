<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->string('estado')->default('disponible')->after('finca_id');
        });

        // Si ya existen lotes, marcarlos como disponibles
        DB::table('lotes')->whereNull('estado')->update(['estado' => 'disponible']);
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
