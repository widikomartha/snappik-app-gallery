<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('review_reason', 64)->nullable();
            $table->unique(['merchant_id', 'environment', 'provider_transaction_id'], 'orders_provider_transaction_unique');
            $table->unique(['id', 'user_id']);
            $table->index(['last_checked_at', 'id']);
        });
        DB::statement('ALTER TABLE orders DROP CONSTRAINT order_state_valid');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT order_state_valid CHECK (state IN ('created','creating','unknown','pending','review','paid','failed'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT order_paid_pair CHECK (
            (paid_at IS NULL AND applied_at IS NULL) OR
            (paid_at IS NOT NULL AND applied_at IS NOT NULL AND provider_transaction_id IS NOT NULL AND state = 'paid'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_checkout_history() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_TABLE_NAME = 'orders' AND TG_OP = 'UPDATE' THEN
                    IF (to_jsonb(NEW) - ARRAY['state','provider_state','snap_token','checkout_url','failure','last_checked_at','updated_at','paid_at','applied_at','provider_transaction_id','review_reason'])
                        IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['state','provider_state','snap_token','checkout_url','failure','last_checked_at','updated_at','paid_at','applied_at','provider_transaction_id','review_reason']) THEN
                        RAISE EXCEPTION 'Order purchase terms are immutable';
                    END IF;
                    IF OLD.applied_at IS NOT NULL AND (NEW.paid_at, NEW.applied_at, NEW.provider_transaction_id, NEW.state)
                        IS DISTINCT FROM (OLD.paid_at, OLD.applied_at, OLD.provider_transaction_id, OLD.state) THEN
                        RAISE EXCEPTION 'Applied payment is immutable';
                    END IF;
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'Checkout history is immutable';
            END;
            $$;
            SQL);
        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->string('source', 16);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('receipt_digest', 64)->nullable()->unique();
            $table->char('result_digest', 64)->nullable();
            $table->string('state', 16)->default('pending');
            $table->string('provider_state', 32)->nullable();
            $table->string('failure', 64)->nullable();
            $table->integer('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('enqueued_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->index(['state', 'available_at']);
            $table->index(['order_id', 'created_at']);
        });
        Schema::create('entitlement_grants', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreign(['order_id', 'user_id'])->references(['id', 'user_id'])->on('orders')->restrictOnDelete();
            $table->foreignUuid('payment_event_id')->constrained('payment_events')->restrictOnDelete();
            $table->timestampTz('paid_at');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->integer('duration_days');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE entitlement_grants ADD CONSTRAINT grant_duration_valid CHECK (duration_days = 30 AND ends_at = starts_at + interval '720 hours' AND starts_at >= paid_at)");
        DB::statement("ALTER TABLE payment_events ADD CONSTRAINT payment_event_state_valid CHECK (state IN ('pending','processing','retry','processed','review') AND attempts >= 0 AND source IN ('callback','scheduled','resume','admin','operator'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_payment_history() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_TABLE_NAME = 'payment_events' AND TG_OP = 'UPDATE' AND OLD.state NOT IN ('processed','review') THEN
                    IF (NEW.id, NEW.order_id, NEW.source, NEW.actor_id, NEW.receipt_digest, NEW.created_at)
                        IS NOT DISTINCT FROM (OLD.id, OLD.order_id, OLD.source, OLD.actor_id, OLD.receipt_digest, OLD.created_at) THEN
                        RETURN NEW;
                    END IF;
                END IF;
                RAISE EXCEPTION 'Payment history is immutable';
            END;
            $$;
            CREATE TRIGGER payment_event_history BEFORE UPDATE OR DELETE ON payment_events FOR EACH ROW EXECUTE FUNCTION protect_payment_history();
            CREATE TRIGGER entitlement_grant_history BEFORE UPDATE OR DELETE ON entitlement_grants FOR EACH ROW EXECUTE FUNCTION protect_payment_history();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_grants');
        Schema::dropIfExists('payment_events');
        DB::statement('DROP FUNCTION IF EXISTS protect_payment_history()');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT order_paid_pair');
        // Destructive schema rollback is intended for disposable databases only.
        DB::statement('DROP TRIGGER order_history ON orders');
        DB::table('orders')->whereIn('state', ['paid', 'failed'])->update(['state' => 'review']);
        DB::statement('ALTER TABLE orders DROP CONSTRAINT order_state_valid');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT order_state_valid CHECK (state IN ('created','creating','unknown','pending','review'))");
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_provider_transaction_unique');
            $table->dropUnique(['id', 'user_id']);
            $table->dropIndex(['last_checked_at', 'id']);
            $table->dropColumn(['paid_at', 'applied_at', 'provider_transaction_id', 'review_reason']);
        });
        // Restore the prior trigger without referencing removed columns.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_checkout_history() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_TABLE_NAME = 'orders' AND TG_OP = 'UPDATE' THEN
                    IF (to_jsonb(NEW) - ARRAY['state','provider_state','snap_token','checkout_url','failure','last_checked_at','updated_at'])
                        IS NOT DISTINCT FROM (to_jsonb(OLD) - ARRAY['state','provider_state','snap_token','checkout_url','failure','last_checked_at','updated_at']) THEN
                        RETURN NEW;
                    END IF;
                END IF;
                RAISE EXCEPTION 'Checkout history is immutable';
            END;
            $$;
            CREATE TRIGGER order_history BEFORE UPDATE OR DELETE ON orders FOR EACH ROW EXECUTE FUNCTION protect_checkout_history();
            SQL);
    }
};
