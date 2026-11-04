<?php

namespace App\Support;

/**
 * A self-contained PRNG seeded from an arbitrary string, entirely independent of PHP's global
 * mt_rand/rand state - seeding those with mt_srand() would corrupt randomness for every other
 * unrelated piece of code running in the same request (other games, CSRF tokens, etc.), which is
 * why this exists instead. xorshift128+ seeded from two 64-bit halves of SHA-256(seed): fast,
 * well-distributed, and - critically - exactly reproducible from the same seed string every time,
 * which is what lets a simulated match's commitment hash be verified after the fact (see
 * VirtualFootballProvider::reveal()) and lets the simulation's own tests assert determinism.
 */
class DeterministicRandom
{
    private int $s0;
    private int $s1;

    public function __construct(string $seed)
    {
        $hash = hash('sha256', $seed, true);
        $this->s0 = $this->bytesToInt(substr($hash, 0, 8)) ?: 1;
        $this->s1 = $this->bytesToInt(substr($hash, 8, 8)) ?: 2;
    }

    private function bytesToInt(string $bytes): int
    {
        // Mask to 63 bits so the result is always a positive PHP int regardless of platform.
        return unpack('P', $bytes)[1] & PHP_INT_MAX;
    }

    private function nextUint63(): int
    {
        $x = $this->s0;
        $y = $this->s1;
        $this->s0 = $y;
        $x ^= ($x << 23) & PHP_INT_MAX;
        $x ^= ($x >> 17);
        $x ^= $y ^ ($y >> 26);
        $this->s1 = $x & PHP_INT_MAX;
        return ($this->s0 + $this->s1) & PHP_INT_MAX;
    }

    /** A float in [0, 1). */
    public function nextFloat(): float
    {
        return $this->nextUint63() / (PHP_INT_MAX + 1.0);
    }

    /** An integer in [$min, $max], inclusive. */
    public function nextInt(int $min, int $max): int
    {
        if ($max <= $min) return $min;
        return $min + (int) floor($this->nextFloat() * ($max - $min + 1));
    }

    /** Knuth's algorithm: a Poisson-distributed non-negative integer with the given mean. */
    public function poisson(float $lambda): int
    {
        if ($lambda <= 0) return 0;
        $limit = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= $this->nextFloat();
        } while ($p > $limit);
        return $k - 1;
    }

    /**
     * Picks one key from $weights (key => non-negative weight) proportionally to its weight.
     * Returns null if every weight is zero/the array is empty.
     */
    public function weightedPick(array $weights): int|string|null
    {
        $total = array_sum($weights);
        if ($total <= 0) return null;
        $roll = $this->nextFloat() * $total;
        $cumulative = 0.0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($roll < $cumulative) return $key;
        }
        return array_key_last($weights);
    }

    /** True with the given probability (0..1). */
    public function chance(float $probability): bool
    {
        return $this->nextFloat() < $probability;
    }
}
