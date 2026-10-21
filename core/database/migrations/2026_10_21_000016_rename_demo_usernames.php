<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'username')) {
            return;
        }

        $existing = User::query()->pluck('username', 'id')->map(fn ($username) => mb_strtolower((string) $username))->all();

        User::where('username', 'like', 'demo\_%')->orderBy('id')->each(function (User $user) use (&$existing) {
            $firstName = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $user->firstname));
            $lastInitial = mb_substr(preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $user->lastname)), 0, 1);
            $base = $firstName . $lastInitial;
            if (mb_strlen($base) < 3) {
                $base = str_pad($base !== '' ? $base : 'player', 3, '0');
            }
            $base = mb_substr($base, 0, 55);

            $candidate = $base;
            $suffix = 1;
            while (in_array(mb_strtolower($candidate), $existing, true)) {
                $suffix++;
                $candidate = $base . $suffix;
            }

            unset($existing[$user->id]);
            $existing[$user->id] = mb_strtolower($candidate);
            $user->forceFill(['username' => $candidate])->save();
        });
    }

    public function down(): void
    {
        // Intentionally not reversible - the original demo usernames are not worth restoring.
    }
};
