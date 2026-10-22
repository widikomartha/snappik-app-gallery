<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->timestampTz('selection_updated_at')->nullable());
        // Historical reopen times were not recorded. Only backfill known acknowledgements.
        DB::statement('UPDATE projects SET selection_updated_at = GREATEST(
            (SELECT MAX(updated_at) FROM choices WHERE project_id = projects.id),
            (SELECT MAX(confirmed_at) FROM selection_confirmations WHERE project_id = projects.id))');
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('selection_updated_at'));
    }
};
