<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->bigInteger('allowance');
            $table->text('share_token');
            $table->string('share_digest', 64)->unique();
            $table->bigInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('selection_locked_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'archived_at']);
        });
        Schema::create('gallery_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->boolean('published')->default(false);
            $table->string('password_hash')->nullable();
            $table->bigInteger('gate_version')->default(1);
            $table->timestampTz('deadline')->nullable();
            $table->text('source')->nullable();
            $table->uuid('published_revision_id')->nullable();
            $table->uuid('requested_revision_id')->nullable();
            $table->timestampsTz();
            $table->unique(['project_id', 'type']);
        });
        Schema::create('catalogue_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('gallery_modules')->restrictOnDelete();
            $table->string('state', 24)->default('queued');
            $table->text('source');
            $table->string('failure')->nullable();
            $table->string('manifest_digest', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });
        Schema::table('gallery_modules', function (Blueprint $table) {
            $table->foreign('published_revision_id')->references('id')->on('catalogue_revisions')->restrictOnDelete();
            $table->foreign('requested_revision_id')->references('id')->on('catalogue_revisions')->restrictOnDelete();
        });
        Schema::create('catalogue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('revision_id')->constrained('catalogue_revisions')->restrictOnDelete();
            $table->string('file_id', 256);
            $table->string('kind', 16);
            $table->text('name');
            $table->text('path');
            $table->bigInteger('bytes')->default(0);
            $table->text('metadata');
            $table->boolean('listed')->default(false);
            $table->text('page_token')->nullable();
            $table->unique(['revision_id', 'file_id']);
            $table->index(['revision_id', 'kind', 'listed']);
        });
        Schema::create('catalogue_pages', function (Blueprint $table) {
            $table->foreignId('entry_id')->constrained('catalogue_entries')->restrictOnDelete();
            $table->string('token_digest', 64);
            $table->primary(['entry_id', 'token_digest']);
        });
        // Current identity/snapshot persistence supports configuration invariants.
        // Client mutation/confirmation endpoints belong to TASK-005.
        Schema::create('choices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->string('file_id', 256);
            $table->text('name');
            $table->text('path');
            $table->foreignUuid('revision_id')->constrained('catalogue_revisions')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['project_id', 'file_id']);
        });
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_positive_allowance CHECK (allowance > 0)');
        DB::statement("ALTER TABLE gallery_modules ADD CONSTRAINT gallery_module_type CHECK (type IN ('selection', 'delivery'))");
        DB::statement('ALTER TABLE catalogue_entries ADD CONSTRAINT catalogue_nonnegative_bytes CHECK (bytes >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('choices');
        Schema::dropIfExists('catalogue_pages');
        Schema::dropIfExists('catalogue_entries');
        Schema::table('gallery_modules', function (Blueprint $table) {
            $table->dropForeign(['published_revision_id']);
            $table->dropForeign(['requested_revision_id']);
        });
        Schema::dropIfExists('catalogue_revisions');
        Schema::dropIfExists('gallery_modules');
        Schema::dropIfExists('projects');
    }
};
