<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestampsTz();
        });
        Schema::create('voucher_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->integer('number');
            $table->string('discount_type', 16);
            $table->bigInteger('discount_value');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->bigInteger('total_limit')->nullable();
            $table->bigInteger('per_user_limit')->nullable();
            $table->boolean('enabled');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['voucher_id', 'number']);
            $table->unique(['id', 'voucher_id']);
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreign(['current_version_id', 'id'])->references(['id', 'voucher_id'])->on('voucher_versions')->restrictOnDelete();
        });
        // TASK-010 will bind these stable order UUIDs to the order table. There is
        // deliberately no public reservation/redemption write endpoint in TASK-009.
        Schema::create('voucher_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id')->unique();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('voucher_version_id');
            $table->foreign(['voucher_version_id', 'voucher_id'])->references(['id', 'voucher_id'])->on('voucher_versions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('state', 16)->default('held');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->index(['voucher_id', 'user_id', 'state']);
        });
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained('voucher_reservations')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
        Schema::table('audit_events', function (Blueprint $table) {
            $table->unsignedBigInteger('target_user_id')->nullable()->change();
            $table->foreignId('voucher_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('voucher_version_id')->nullable()->constrained()->restrictOnDelete();
        });
        DB::statement("ALTER TABLE vouchers ADD CONSTRAINT voucher_code_normalized CHECK (code <> '' AND code = upper(btrim(code)))");
        DB::statement("ALTER TABLE voucher_versions ADD CONSTRAINT voucher_terms_valid CHECK (
            number > 0 AND starts_at < ends_at AND
            ((discount_type = 'percentage' AND discount_value BETWEEN 1 AND 99) OR (discount_type = 'fixed' AND discount_value > 0)) AND
            (total_limit IS NULL OR total_limit > 0) AND (per_user_limit IS NULL OR per_user_limit > 0))");
        DB::statement("ALTER TABLE voucher_reservations ADD CONSTRAINT voucher_reservation_state CHECK (
            (state = 'held' AND resolved_at IS NULL) OR (state IN ('consumed', 'released') AND resolved_at IS NOT NULL))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_voucher_history() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_TABLE_NAME = 'vouchers' AND TG_OP = 'UPDATE' THEN
                    IF NEW.code IS DISTINCT FROM OLD.code THEN
                        RAISE EXCEPTION 'Voucher code identity is immutable';
                    END IF;
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'Voucher history is immutable';
            END;
            $$;
            CREATE TRIGGER voucher_identity BEFORE UPDATE OR DELETE ON vouchers
                FOR EACH ROW EXECUTE FUNCTION protect_voucher_history();
            CREATE TRIGGER voucher_version_history BEFORE UPDATE OR DELETE ON voucher_versions
                FOR EACH ROW EXECUTE FUNCTION protect_voucher_history();
            CREATE TRIGGER voucher_redemption_history BEFORE UPDATE OR DELETE ON voucher_redemptions
                FOR EACH ROW EXECUTE FUNCTION protect_voucher_history();
            SQL);
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_version_id');
            $table->dropConstrainedForeignId('voucher_id');
        });
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('voucher_reservations');
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropForeign(['current_version_id', 'id']));
        Schema::dropIfExists('voucher_versions');
        Schema::dropIfExists('vouchers');
        DB::statement('DROP FUNCTION IF EXISTS protect_voucher_history()');
        // Keep existing audit rows. Account actions still require their target;
        // a rollback must not delete historical voucher administration events.
    }
};
