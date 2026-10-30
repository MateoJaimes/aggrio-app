<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_tickets', fn (Blueprint $t) => $t->string('storage_disk')->default('s3'));
    }

    public function down(): void
    {
        Schema::table('upload_tickets', fn (Blueprint $t) => $t->dropColumn('storage_disk'));
    }
};
