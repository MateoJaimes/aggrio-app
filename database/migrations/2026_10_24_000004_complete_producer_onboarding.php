<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('temporary_password_expires_at')->nullable();
        });
        Schema::table('access_requests', function (Blueprint $table) {
            $table->string('invitation_status')->nullable()->index();
            $table->uuid('invitation_version')->nullable();
            $table->text('invitation_secret')->nullable();
            $table->unsignedInteger('invitation_attempts')->default(0);
            $table->timestamp('invitation_attempted_at')->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('access_requests', fn (Blueprint $t) => $t->dropColumn(['invitation_status', 'invitation_version', 'invitation_secret', 'invitation_attempts', 'invitation_attempted_at', 'invitation_sent_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['must_change_password', 'temporary_password_expires_at']));
    }
};
