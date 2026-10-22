<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('gallery_modules')->restrictOnDelete();
            $table->foreignUuid('revision_id')->constrained('catalogue_revisions')->restrictOnDelete();
            $table->bigInteger('gate_version');
            $table->string('manifest_digest', 64);
            $table->string('state', 16)->default('queued');
            $table->bigInteger('total_files')->default(0);
            $table->bigInteger('total_bytes')->default(0);
            $table->bigInteger('path_bytes')->default(0);
            $table->bigInteger('completed_files')->default(0);
            $table->bigInteger('completed_bytes')->default(0);
            $table->uuid('attempt_id')->nullable();
            $table->timestampTz('lease_until')->nullable()->index();
            $table->text('object_key')->nullable();
            $table->bigInteger('zip_bytes')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('failure')->nullable();
            $table->timestampsTz();
            $table->unique(['module_id', 'revision_id', 'gate_version']);
        });
        Schema::create('archive_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('archive_job_id')->constrained()->restrictOnDelete();
            $table->string('file_id', 256);
            $table->text('path');
            $table->bigInteger('bytes');
            $table->text('metadata');
            $table->unique(['archive_job_id', 'file_id']);
        });
        // Written before any provider request; survives a crash even between create-upload and its reply.
        Schema::create('archive_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('archive_job_id')->constrained()->restrictOnDelete();
            $table->text('object_key');
            $table->boolean('cleanup_pending')->default(true)->index();
            $table->string('cleanup_failure')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_attempts');
        Schema::dropIfExists('archive_entries');
        Schema::dropIfExists('archive_jobs');
    }
};
