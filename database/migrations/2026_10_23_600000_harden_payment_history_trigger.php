<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_payment_history() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                -- Check the table before resolving fields absent on entitlement_grants.
                IF TG_TABLE_NAME = 'payment_events' AND TG_OP = 'UPDATE' THEN
                    IF OLD.state NOT IN ('processed','review') THEN
                        IF (NEW.id, NEW.order_id, NEW.source, NEW.actor_id, NEW.receipt_digest, NEW.created_at)
                            IS NOT DISTINCT FROM (OLD.id, OLD.order_id, OLD.source, OLD.actor_id, OLD.receipt_digest, OLD.created_at) THEN
                            RETURN NEW;
                        END IF;
                    END IF;
                END IF;
                RAISE EXCEPTION 'Payment history is immutable';
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        // The corrected function is compatible with the previous schema. Keep its
        // protection until the payment-processing migration removes the function.
    }
};
