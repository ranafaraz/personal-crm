<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill missing pivot rows where a contact is referenced on an
        // opportunity via email_messages or follow_ups but the opportunity_contact
        // row was never written. Idempotent — the NOT EXISTS guard skips existing
        // rows. Plain INSERT + CURRENT_TIMESTAMP keeps this portable across
        // MySQL/MariaDB and the SQLite test database.
        DB::statement("
            INSERT INTO opportunity_contact (opportunity_id, contact_id, created_at, updated_at)
            SELECT DISTINCT em.opportunity_id, em.contact_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM email_messages em
            WHERE em.opportunity_id IS NOT NULL
              AND em.contact_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM opportunity_contact oc
                  WHERE oc.opportunity_id = em.opportunity_id
                    AND oc.contact_id = em.contact_id
              )
        ");

        DB::statement("
            INSERT INTO opportunity_contact (opportunity_id, contact_id, created_at, updated_at)
            SELECT DISTINCT fu.opportunity_id, fu.contact_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM follow_ups fu
            WHERE fu.opportunity_id IS NOT NULL
              AND fu.contact_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM opportunity_contact oc
                  WHERE oc.opportunity_id = fu.opportunity_id
                    AND oc.contact_id = fu.contact_id
              )
        ");
    }

    public function down(): void
    {
        // Backfill-only migration — no rollback needed.
    }
};
