<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * support_agents.payment_details used to be a single free-text blob (admin typed everything -
 * bank name, account number, instructions - into one textarea). The admin UI now collects
 * structured payment methods (bank/crypto/mobile money, each with their own fields) plus a
 * separate instructions field, stored as {"methods": [...], "instructions": "..."}.
 *
 * This wraps any existing plain-text value into that same shape (as a zero-method row carrying
 * the old text as "instructions") so no agent's existing payment info is silently dropped once
 * the model starts treating the column as JSON. Safe to re-run: a value that already decodes to
 * the new {"methods": ...} shape is left untouched.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('support_agents') || !Schema::hasColumn('support_agents', 'payment_details')) {
            return;
        }

        DB::table('support_agents')->whereNotNull('payment_details')->where('payment_details', '!=', '')
            ->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->payment_details, true);
                    if (is_array($decoded) && array_key_exists('methods', $decoded)) {
                        continue; // Already in the new shape.
                    }

                    DB::table('support_agents')->where('id', $row->id)->update([
                        'payment_details' => json_encode(['methods' => [], 'instructions' => (string) $row->payment_details]),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data-shape change only (still a TEXT column) - not reversing, matching this app's
        // convention of treating data-normalization migrations as forward-only.
    }
};
