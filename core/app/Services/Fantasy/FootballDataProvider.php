<?php

namespace App\Services\Fantasy;

use App\Models\FantasyFixture;

/**
 * Abstraction over "where a fixture's result comes from" - the virtual league is the only
 * implementation today (VirtualFootballProvider), but every caller (GameweekFinalizer, the
 * fixtures/live-match-center controllers) goes through this interface rather than the virtual
 * engine directly, so swapping in a licensed real-data API later is a matter of binding a new
 * implementation in a service provider, not rewriting any call site.
 */
interface FootballDataProvider
{
    /** Called at/before kickoff: publishes a verifiable commitment to that fixture's result without revealing it. Returns the commitment hash. */
    public function commit(FantasyFixture $fixture): string;

    /** Called once the fixture is complete: computes (or fetches) and persists the final score, event timeline, and per-player box scores. */
    public function resolve(FantasyFixture $fixture): void;

    /** Called after full time: returns the seed/proof a viewer can use to independently verify the commitment published at kickoff, or null if this provider has nothing to reveal (e.g. a real-data provider). */
    public function reveal(FantasyFixture $fixture): ?string;
}
