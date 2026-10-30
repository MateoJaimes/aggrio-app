<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lotes', 'actividades', 'lecturas_iot', 'archivos_multimedia'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->softDeletes());
        }
        Schema::table('archivos_multimedia', function (Blueprint $t) {
            $t->string('storage_disk')->default('s3');
            $t->string('transcription_status')->default('not_requested');
            $t->text('transcription_text')->nullable();
            $t->timestamp('captured_at')->nullable();
            $t->decimal('latitud', 10, 8)->nullable();
            $t->decimal('longitud', 11, 8)->nullable();
            $t->decimal('gps_precision', 10, 2)->nullable();
        });
        Schema::table('lecturas_iot', function (Blueprint $t) {
            $t->string('origen')->default('dispositivo');
            $t->foreignId('dispositivo_id')->nullable()->constrained('dispositivos_iot');
            $t->string('event_id', 80)->nullable();
            $t->timestamp('received_at')->nullable();
            $t->unique(['dispositivo_id', 'event_id']);
        });
        Schema::create('upload_tickets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->string('modelo_tipo');
            $t->unsignedBigInteger('modelo_id');
            $t->string('file_key')->unique();
            $t->string('mime_type');
            $t->unsignedBigInteger('size_bytes');
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('resource_type');
            $t->unsignedBigInteger('resource_id');
            $t->string('action');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('reason', 1000)->nullable();
            $t->timestamp('created_at');
            $t->index(['resource_type', 'resource_id']);
        });
        Schema::create('idempotent_operations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('operation_key', 64);
            $t->string('request_hash', 64);
            $t->text('response')->nullable();
            $t->unsignedSmallInteger('status')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotent_operations');
        Schema::dropIfExists('audit_entries');
        Schema::dropIfExists('upload_tickets');
        Schema::table('lecturas_iot', function (Blueprint $t) {
            $t->dropUnique(['dispositivo_id', 'event_id']);
            $t->dropConstrainedForeignId('dispositivo_id');
            $t->dropColumn(['origen', 'event_id', 'received_at']);
        });
        Schema::table('archivos_multimedia', fn (Blueprint $t) => $t->dropColumn(['storage_disk', 'transcription_status', 'transcription_text', 'captured_at', 'latitud', 'longitud', 'gps_precision']));
        foreach (['lotes', 'actividades', 'lecturas_iot', 'archivos_multimedia'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
