<?php

use App\Models\User;
use App\Support\AvatarLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moves every user off the old assets/images/profilepics/ pool (demo-seeded users) and
     * fills in any user left with no avatar at all (a handful of real users registered while
     * the onboarding default-avatar assignment had a path bug and silently never set one) -
     * all re-rolled from the new assets/avatars/male|female/ library, matched to each user's
     * own gender.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'image')) return;

        User::whereNull('image')
            ->orWhere('image', 'like', 'assets/images/profilepics/%')
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $avatarPath = AvatarLibrary::randomPath(AvatarLibrary::normalizeGender($user->gender));
                    if ($avatarPath) {
                        $user->image = $avatarPath;
                        $user->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        // One-way: the old profilepics pool assignment isn't worth reconstructing, and users
        // that had no avatar before this ran are fine staying on their new one.
    }
};
