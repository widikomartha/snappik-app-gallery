<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('price_idr');
            $table->integer('duration_days');
            $table->timestampTz('created_at')->useCurrent();
        });
        Schema::create('plans', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->foreignId('current_version_id')->constrained('plan_versions')->restrictOnDelete();
        });
        $version = DB::table('plan_versions')->insertGetId(['price_idr' => 29000, 'duration_days' => 30]);
        DB::table('plans')->insert(['code' => 'pro', 'current_version_id' => $version]);
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('quote_digest', 64);
            $table->foreignId('plan_version_id')->constrained()->restrictOnDelete();
            $table->bigInteger('base_idr');
            $table->bigInteger('discount_idr');
            $table->bigInteger('payable_idr');
            $table->string('currency', 3)->default('IDR');
            $table->integer('duration_days');
            $table->foreignId('voucher_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('voucher_version_id')->nullable();
            $table->string('voucher_code', 64)->nullable();
            $table->foreign(['voucher_version_id', 'voucher_id'])->references(['id', 'voucher_id'])->on('voucher_versions')->restrictOnDelete();
            $table->string('provider_order_id', 64)->unique();
            $table->string('merchant_id');
            $table->string('environment', 16)->default('sandbox');
            $table->bigInteger('channel_minimum');
            $table->string('state', 24)->default('created');
            $table->string('provider_state', 32)->nullable();
            $table->text('snap_token')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestampTz('checkout_expires_at');
            $table->string('failure', 64)->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['id', 'user_id', 'voucher_id', 'voucher_version_id']);
            $table->index(['user_id', 'created_at']);
        });
        Schema::table('voucher_reservations', function (Blueprint $table) {
            $table->foreign(['order_id', 'user_id', 'voucher_id', 'voucher_version_id'])->references(['id', 'user_id', 'voucher_id', 'voucher_version_id'])->on('orders')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE plan_versions ADD CONSTRAINT plan_terms_valid CHECK (price_idr BETWEEN 1 AND 9007199254740991 AND duration_days = 30)');
        DB::statement("ALTER TABLE plans ADD CONSTRAINT single_pro_plan CHECK (code = 'pro')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT order_terms_valid CHECK (
            base_idr > 0 AND base_idr <= 9007199254740991 AND discount_idr >= 0 AND discount_idr < base_idr
            AND payable_idr = base_idr - discount_idr AND channel_minimum > 0 AND payable_idr >= channel_minimum
            AND currency = 'IDR' AND duration_days = 30 AND environment = 'sandbox' AND merchant_id <> ''
            AND provider_order_id = 'sp-' || id::text
            AND ((voucher_id IS NULL AND voucher_version_id IS NULL AND voucher_code IS NULL AND discount_idr = 0)
                OR (voucher_id IS NOT NULL AND voucher_version_id IS NOT NULL AND voucher_code IS NOT NULL)))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT order_state_valid CHECK (state IN ('created', 'creating', 'unknown', 'pending', 'review'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_checkout_history() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_TABLE_NAME = 'orders' AND TG_OP = 'UPDATE' THEN
                    IF (to_jsonb(NEW) - ARRAY['state','provider_state','snap_token','checkout_url','failure','last_checked_at','updated_at'])
                        IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['state','provider_state','snap_token','checkout_url','failure','last_checked_at','updated_at']) THEN
                        RAISE EXCEPTION 'Order purchase terms are immutable';
                    END IF;
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'Checkout history is immutable';
            END;
            $$;
            CREATE TRIGGER order_history BEFORE UPDATE OR DELETE ON orders FOR EACH ROW EXECUTE FUNCTION protect_checkout_history();
            CREATE TRIGGER plan_version_history BEFORE UPDATE OR DELETE ON plan_versions FOR EACH ROW EXECUTE FUNCTION protect_checkout_history();
            SQL);
    }

    public function down(): void
    {
        Schema::table('voucher_reservations', fn (Blueprint $table) => $table->dropForeign(['order_id', 'user_id', 'voucher_id', 'voucher_version_id']));
        Schema::dropIfExists('orders');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('plan_versions');
        DB::statement('DROP FUNCTION IF EXISTS protect_checkout_history()');
    }
};
