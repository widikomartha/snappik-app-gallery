<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->integer('suspension_version')->default(0));
        foreach (['catalogue_revisions', 'archive_jobs'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->integer('operations_version')->default(0));
        }
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('subject_type', 32)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('before_reference', 128)->nullable();
            $table->string('after_reference', 128)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', fn (Blueprint $table) => $table->dropColumn(['subject_type', 'subject_id', 'before_reference', 'after_reference']));
        foreach (['catalogue_revisions', 'archive_jobs'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('operations_version'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('suspension_version'));
    }
};
