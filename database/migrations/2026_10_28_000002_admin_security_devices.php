<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedInteger('two_factor_version')->default(1);
            $t->string('two_factor_label', 100)->default('Aplicación principal');
        });
        Schema::create('trusted_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('secret_hash', 64);
            $t->string('credential_hash', 64);
            $t->string('agent_hash', 64);
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('last_used_at');
            $t->timestamp('expires_at')->index();
            $t->timestamps();
        });
        Schema::create('authenticator_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->text('secret');
            $t->string('mode', 10)->default('add');
            $t->unsignedInteger('security_version');
            $t->string('session_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('action', 60);
            $t->string('device_name', 100)->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('authenticator_devices');
        Schema::dropIfExists('trusted_devices');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_version', 'two_factor_label']));
    }
};
