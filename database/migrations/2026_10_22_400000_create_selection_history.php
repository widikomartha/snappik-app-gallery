<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogue_entries', function (Blueprint $table) {
            $table->string('parent_file_id', 256)->nullable();
            $table->index(['revision_id', 'parent_file_id']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->bigInteger('selection_epoch')->default(1);
            $table->bigInteger('selection_version')->default(1);
        });
        Schema::create('selection_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->bigInteger('epoch');
            $table->bigInteger('version');
            $table->timestampTz('confirmed_at');
            $table->unique(['project_id', 'epoch']);
        });
        Schema::create('confirmation_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('confirmation_id')->constrained('selection_confirmations')->restrictOnDelete();
            $table->string('file_id', 256);
            $table->text('name');
            $table->text('path');
            $table->foreignUuid('revision_id')->constrained('catalogue_revisions')->restrictOnDelete();
            $table->unique(['confirmation_id', 'file_id']);
        });
        Schema::create('mutation_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->uuid('operation_id');
            $table->string('payload_digest', 64);
            $table->jsonb('result');
            $table->unique(['project_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutation_receipts');
        Schema::dropIfExists('confirmation_choices');
        Schema::dropIfExists('selection_confirmations');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['selection_epoch', 'selection_version']));
        Schema::table('catalogue_entries', function (Blueprint $table) {
            $table->dropIndex(['revision_id', 'parent_file_id']);
            $table->dropColumn('parent_file_id');
        });
    }
};
