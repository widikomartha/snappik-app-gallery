<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('suspended_at')->nullable();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('photographer', 'admin'))");
        // Case-insensitive uniqueness is enforced even outside registration.
        DB::statement('CREATE UNIQUE INDEX users_email_normalized_unique ON users (lower(email))');
        Schema::create('account_access', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->timestampTz('trial_started_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('access_ends_at')->nullable();
            $table->boolean('has_paid_access')->default(false);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE account_access ADD CONSTRAINT account_trial_pair CHECK ((trial_started_at IS NULL AND trial_ends_at IS NULL) OR (trial_started_at IS NOT NULL AND trial_ends_at = trial_started_at + interval \'168 hours\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('account_access');
        DB::statement('DROP INDEX users_email_normalized_unique');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('suspended_at'));
    }
};
