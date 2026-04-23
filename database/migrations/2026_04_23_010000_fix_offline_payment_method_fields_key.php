<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fixes an inconsistency between the earlier seed migration (2026_04_20_030000)
 * and what the offline-payment list/edit blades expect.
 *
 *   Seed stored each field as  ['field_name' => X, 'placeholder' => Y]
 *   Blades read                ['field_name' => X, 'field_data'  => Y]
 *
 * This caused "Undefined array key 'field_data'" on the offline-payment list
 * page for any install that had the seed applied. Migration is idempotent —
 * rows already carrying the correct key are left alone.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('offline_payment_methods')) {
            return;
        }

        DB::table('offline_payment_methods')->orderBy('id')->each(function ($row) {
            $fields = json_decode($row->method_fields ?? '[]', true);
            if (!is_array($fields)) return;

            $changed = false;
            foreach ($fields as &$field) {
                if (is_array($field) && !array_key_exists('field_data', $field)) {
                    // Use placeholder (the original seed's label) or fall back
                    // to a humanised version of the field_name.
                    $field['field_data'] = $field['placeholder']
                        ?? ucfirst(str_replace('_', ' ', $field['field_name'] ?? 'value'));
                    $changed = true;
                }
            }
            unset($field);

            if ($changed) {
                DB::table('offline_payment_methods')
                    ->where('id', $row->id)
                    ->update(['method_fields' => json_encode($fields)]);
            }
        });
    }

    public function down(): void
    {
        // No safe reversal — the original seed's `placeholder` key wasn't
        // unique; restoring it would require a backup. Left as a no-op.
    }
};
