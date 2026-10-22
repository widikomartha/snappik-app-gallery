<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_proof_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('state');
            $table->text('root'); // encrypted metadata, including the resource key
            $table->string('object_key')->nullable();
            $table->string('failure')->nullable();
            $table->unsignedBigInteger('completed_files')->default(0);
            $table->unsignedBigInteger('completed_bytes')->default(0);
            $table->unsignedBigInteger('zip_bytes')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('drive_proof_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('run_id')->constrained('drive_proof_runs')->cascadeOnDelete();
            $table->string('file_id', 256);
            $table->string('kind');
            $table->text('path');
            $table->text('metadata'); // encrypted provider snapshot/resource key
            $table->unsignedBigInteger('bytes')->default(0);
            $table->boolean('listed')->default(false);
            $table->text('page_token')->nullable();
            $table->unique(['run_id', 'file_id']);
            $table->index(['run_id', 'kind', 'listed']);
        });
        Schema::create('drive_proof_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('drive_proof_nodes')->cascadeOnDelete();
            $table->char('token_digest', 64);
            $table->unique(['node_id', 'token_digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_proof_pages');
        Schema::dropIfExists('drive_proof_nodes');
        Schema::dropIfExists('drive_proof_runs');
    }
};
