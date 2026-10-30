<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('account_status')->default('active')->index();
            $t->timestamp('suspended_at')->nullable();
            $t->text('suspension_reason')->nullable();
        });
        Schema::create('account_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->string('previous_status');
            $t->string('new_status');
            $t->text('reason');
            $t->timestamp('created_at');
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_events');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['account_status', 'suspended_at', 'suspension_reason']));
    }
};
