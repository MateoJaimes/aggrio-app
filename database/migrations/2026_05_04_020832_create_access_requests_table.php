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
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('id_type');
            $table->string('id_number');
            $table->string('landname'); // Nombre de la finca
            $table->string('country');
            $table->string('department');
            $table->string('city');
            $table->string('email')->unique();
            // Estado por defecto: pending
            $table->enum('status', ['pending', 'approved', 'waitlisted', 'denied'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
