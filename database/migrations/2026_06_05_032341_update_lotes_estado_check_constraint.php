<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ALTER TABLE ... CONSTRAINT es sintaxis de PostgreSQL; SQLite (tests) no la soporta.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE lotes DROP CONSTRAINT IF EXISTS lotes_estado_check");
        DB::statement("ALTER TABLE lotes ADD CONSTRAINT lotes_estado_check CHECK (estado IN ('disponible', 'en_uso', 'no_disponible', 'aprobado'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE lotes DROP CONSTRAINT IF EXISTS lotes_estado_check");
        DB::statement("ALTER TABLE lotes ADD CONSTRAINT lotes_estado_check CHECK (estado IN ('disponible', 'aprobado'))");
    }
};
