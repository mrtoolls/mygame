<?php

namespace App\Console\Commands;

use App\Models\TableGame;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fans a NEW_LIVE_TABLE notification (in-app + push, no mass email - see the migration's own
 * comment) out to every active user, a bounded batch at a time, resuming from last_user_id on
 * each tick rather than doing it all inline on the request that created the table. See the
 * table_game_announcement_queue migration for why this is a scheduled command and not a real
 * queue job on this host.
 */
class AnnounceNewLiveTables extends Command
{
    protected $signature = 'table-games:announce-new-tables';
    protected $description = 'Notify active users (in-app + push) about recently created live tables, a batch at a time';

    private const BATCH_SIZE = 300;

    public function handle(): int
    {
        $rows = DB::table('table_game_announcement_queue')->whereNull('completed_at')->orderBy('id')->get();
        $notified = 0;

        foreach ($rows as $row) {
            $table = TableGame::find($row->table_game_id);
            if (!$table) {
                DB::table('table_game_announcement_queue')->where('id', $row->id)->update(['completed_at' => now(), 'updated_at' => now()]);
                continue;
            }

            $creator = User::find($table->creator_id);
            $gameLabel = ucfirst(str_replace('_', ' ', $table->game_type));
            $lastId = (int) $row->last_user_id;

            $users = User::active()
                ->where('id', '>', $lastId)
                ->where('id', '!=', $table->creator_id)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get(['id']);

            foreach ($users as $user) {
                notify($user, 'NEW_LIVE_TABLE', [
                    'creator'    => $creator?->username ?? 'A player',
                    'game'       => $gameLabel,
                    'notif_icon' => 'game-controller',
                    'notif_url'  => route('user.tables.show', $table->id),
                ], ['push', 'inapp'], false);
                $lastId = $user->id;
                $notified++;
            }

            $isDone = $users->count() < self::BATCH_SIZE;
            DB::table('table_game_announcement_queue')->where('id', $row->id)->update([
                'last_user_id' => $lastId,
                'completed_at' => $isDone ? now() : null,
                'updated_at'   => now(),
            ]);
        }

        $this->info("New-live-table announcements: notified {$notified} user(s) across " . $rows->count() . " pending table(s).");

        return self::SUCCESS;
    }
}
