<?php

namespace App\Support;

use App\Models\AgentTransactionSpeedLog;
use App\Models\SupportAgent;
use Illuminate\Support\Collection;

/**
 * Computes the public "speed" score (0-100) shown on an agent's WinxPay card.
 *
 * Score = speed_override if admin set one, else max(ratingAverage, fastStreakBonus).
 *
 * ratingAverage is a Bayesian-shrunk lifetime average (not a plain average) so a
 * handful of raters can't max the bar out: (PRIOR_WEIGHT*PRIOR_MEAN + SUM(rating))
 * / (PRIOR_WEIGHT + COUNT(rating)). fastStreakBonus is 100 once an agent has
 * confirmed >=50 deposits within the current calendar month before 50% of each
 * deposit's SLA window elapsed, else 0.
 */
final class AgentSpeed
{
    public const PRIOR_MEAN = 50;
    public const PRIOR_WEIGHT = 20;
    public const FAST_STREAK_THRESHOLD = 50;

    public function scoreFor(SupportAgent $agent): int
    {
        if ($agent->speed_override !== null) {
            return (int) $agent->speed_override;
        }

        return max($this->ratingAverage($agent), $this->fastStreakBonus($agent));
    }

    public function ratingAverage(SupportAgent $agent): int
    {
        $logs = AgentTransactionSpeedLog::where('support_agent_id', $agent->id)->whereNotNull('user_rating');
        $count = (clone $logs)->count();
        $sum = (clone $logs)->sum('user_rating');

        $score = (self::PRIOR_WEIGHT * self::PRIOR_MEAN + $sum) / (self::PRIOR_WEIGHT + $count);

        return (int) round($score);
    }

    public function fastStreakCountThisMonth(SupportAgent $agent): int
    {
        return AgentTransactionSpeedLog::where('support_agent_id', $agent->id)
            ->where('met_fast_threshold', true)
            ->where('confirmed_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function fastStreakBonus(SupportAgent $agent): int
    {
        return $this->fastStreakCountThisMonth($agent) >= self::FAST_STREAK_THRESHOLD ? 100 : 0;
    }

    /**
     * Bulk-compute scores for a collection of agents in a small, fixed number of
     * queries (not one pair of queries per card) to avoid N+1 on a public listing.
     *
     * @param Collection<int, SupportAgent> $agents
     * @return array<int, int> agent id => score
     */
    public function scoresFor(Collection $agents): array
    {
        $ids = $agents->pluck('id')->all();
        if (!$ids) {
            return [];
        }

        $ratingRows = AgentTransactionSpeedLog::selectRaw('support_agent_id, SUM(user_rating) as rating_sum, COUNT(user_rating) as rating_count')
            ->whereIn('support_agent_id', $ids)
            ->whereNotNull('user_rating')
            ->groupBy('support_agent_id')
            ->get()
            ->keyBy('support_agent_id');

        $fastRows = AgentTransactionSpeedLog::selectRaw('support_agent_id, COUNT(*) as fast_count')
            ->whereIn('support_agent_id', $ids)
            ->where('met_fast_threshold', true)
            ->where('confirmed_at', '>=', now()->startOfMonth())
            ->groupBy('support_agent_id')
            ->get()
            ->keyBy('support_agent_id');

        $scores = [];
        foreach ($agents as $agent) {
            if ($agent->speed_override !== null) {
                $scores[$agent->id] = (int) $agent->speed_override;
                continue;
            }

            $ratingRow = $ratingRows->get($agent->id);
            $sum = (float) ($ratingRow->rating_sum ?? 0);
            $count = (int) ($ratingRow->rating_count ?? 0);
            $ratingScore = (int) round((self::PRIOR_WEIGHT * self::PRIOR_MEAN + $sum) / (self::PRIOR_WEIGHT + $count));

            $fastCount = (int) ($fastRows->get($agent->id)->fast_count ?? 0);
            $fastBonus = $fastCount >= self::FAST_STREAK_THRESHOLD ? 100 : 0;

            $scores[$agent->id] = max($ratingScore, $fastBonus);
        }

        return $scores;
    }
}
