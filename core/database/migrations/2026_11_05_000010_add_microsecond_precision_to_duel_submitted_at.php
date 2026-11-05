<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ff_duel_squads.submitted_at is the deciding factor in the very last rung of the duel tie-break
// chain (points -> goals -> captain points -> earlier submission - see
// FantasyDuelService::finish()). A plain timestamp()/datetime() column only has whole-second
// resolution, so two different players who each genuinely submit their own squad within the same
// wall-clock second (easy near a 3-minute build deadline, especially once several players are all
// watching the same countdown) would come back with an IDENTICAL stored submitted_at and be
// wrongly treated as a true simultaneous tie (splitting the pot) instead of the server correctly
// knowing which one actually came first. Widening to DATETIME(6) (microsecond precision, which
// PHP's now() already carries - only the column was truncating it) fixes that without touching
// the tie-break logic itself: two players auto-submitted at the exact same build_deadline_at
// still compare equal (a real simultaneous tie, correctly still splits the pot), only the
// false-positive case of two different human timestamps landing in the same second is fixed.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ff_duel_squads') || !Schema::hasColumn('ff_duel_squads', 'submitted_at')) return;
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::statement('ALTER TABLE `ff_duel_squads` MODIFY `submitted_at` DATETIME(6) NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('ff_duel_squads') || !Schema::hasColumn('ff_duel_squads', 'submitted_at')) return;
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::statement('ALTER TABLE `ff_duel_squads` MODIFY `submitted_at` DATETIME NOT NULL');
    }
};
