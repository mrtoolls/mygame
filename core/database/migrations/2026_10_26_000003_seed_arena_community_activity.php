<?php

use App\Models\ArenaComment;
use App\Models\ArenaPost;
use App\Models\ArenaPostVote;
use App\Models\ArenaReaction;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed a launch community feed for the active accounts in the supplied
     * onboarding export. Usernames are used instead of IDs because imports may
     * assign different primary keys in each environment.
     */
    private const USERNAMES = [
        'aliza', 'emersonwhiteee', 'ayguitar', 'mrleegan', 'jamesw', 'jennifern', 'davidk',
        'elizabethj', 'roberto', 'jessicad', 'danielm', 'karenm', 'thomaso', 'lisao',
        'kwameb', 'amarat', 'emekaa', 'funmip', 'femid', 'amaa', 'kojom', 'thandiwew',
        'thabom', 'gracem', 'kevinb', 'joys', 'marke', 'patiencek', 'ibrahimj', 'maryw',
        'michaeln', 'lindak', 'johnj', 'susano', 'williamd', 'sarahm', 'josephm', 'nancyo',
        'christophero', 'ngozib', 'chidit', 'chiamakaa', 'tundep', 'adaezed', 'kofia',
        'abenam', 'siphow', 'nomvulam', 'brianm', 'faithb', 'peters', 'mercye', 'stevenk',
        'blessingj', 'msdammy', 'vicky', 'ademi',
    ];

    private const GAMES = ['Ludo', 'Whot', 'Chess', 'Dice', 'Spades', 'Draughts'];

    private const COMMENT_STARTS = [
        'I’m in for a friendly match.',
        'That’s a good point. I’m still learning that part of the game.',
        'Count me in next time you open a table.',
        'A close game is always the most fun.',
        'I usually go with patience and one move at a time.',
        'Which game are you planning to play next?',
        'I had the same thought after my last round.',
        'Nice idea. Let’s keep it friendly.',
        'I’m practicing that too. The more I play, the more I notice.',
        'Good question for the community. What does everyone else think?',
        'I’d join a rematch. Send me an invite when you’re ready.',
        'That sounds like a fun way to spend a few minutes.',
        'The best rounds are the ones that stay close until the end.',
        'I’m choosing this game for my next break.',
        'Well said. Enjoy your next match!',
        'Let’s see who else wants to join in.',
    ];

    public function up(): void
    {
        foreach (['arena_posts', 'arena_comments', 'arena_post_votes', 'arena_reactions', 'arena_follows'] as $table) {
            if (!Schema::hasTable($table)) return;
        }

        $users = User::query()
            ->whereIn('username', self::USERNAMES)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'username'])
            ->values();

        if ($users->count() < 2) return;

        DB::transaction(function () use ($users) {
            $posts = collect();
            $postPrompts = [
                'A close %s game always comes down to the next move. What’s your favourite strategy?',
                'I’m getting more into %s lately. What tip helped you improve?',
                'Quick challenge: who wants a friendly %s match this week?',
                'A few rounds of %s are a great way to reset. What are you playing today?',
                'Still learning new ways to approach %s. Do you prefer bold moves or a patient game?',
                'The best %s matches are the ones that stay close. Who’s up for a rematch?',
                'Community question: which part of %s do you enjoy most?',
                'I’m building a little more practice into my week. Anyone else playing %s today?',
                'Friendly games are a good way to learn. Who would you challenge to %s?',
                'Small decisions can change a whole %s round. What’s your go-to move?',
                'Taking a break with a game sounds good right now. I’m leaning toward %s.',
                'What makes a great %s opponent for you: strategy, good sportsmanship, or both?',
            ];

            foreach ($users as $index => $user) {
                $body = sprintf($postPrompts[$index % count($postPrompts)], self::GAMES[$index % count(self::GAMES)]);
                $createdAt = Carbon::now()->subDays(($index * 11 + 2) % 28)->subHours(($index * 5) % 24);
                $post = ArenaPost::firstOrCreate(
                    ['user_id' => $user->id, 'body' => $body],
                    [
                        'type' => ['general', 'question', 'challenge'][$index % 3],
                        'background_style' => ArenaPost::BACKGROUNDS[$index % count(ArenaPost::BACKGROUNDS)],
                        'status' => 'approved',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]
                );
                $posts->push($post);
            }

            // Give every seeded account a voice in the feed, and have the
            // community respond to each author without self-comments.
            foreach ($posts as $postIndex => $post) {
                $topLevelComments = collect();
                $commentCount = 3 + ($postIndex % 3);
                for ($offset = 1; $offset <= $commentCount; $offset++) {
                    $commenter = $users[($postIndex + $offset) % $users->count()];
                    $body = self::COMMENT_STARTS[($postIndex * 3 + $offset) % count(self::COMMENT_STARTS)];
                    $comment = ArenaComment::firstOrCreate(
                        ['post_id' => $post->id, 'user_id' => $commenter->id, 'parent_id' => null, 'body' => $body],
                        ['created_at' => $post->created_at->copy()->addMinutes(12 + $offset * 19), 'updated_at' => $post->created_at->copy()->addMinutes(12 + $offset * 19)]
                    );
                    $topLevelComments->push($comment);
                }

                // Add one realistic reply on alternating posts. Replies are
                // kept to the single nesting level supported by Arena.
                if ($postIndex % 2 === 0 && $topLevelComments->isNotEmpty()) {
                    $parent = $topLevelComments->first();
                    $replier = $users[($postIndex + 7) % $users->count()];
                    if ((int) $replier->id === (int) $parent->user_id) $replier = $users[($postIndex + 8) % $users->count()];
                    $body = 'I agree. A friendly rematch would be fun.';
                    ArenaComment::firstOrCreate(
                        ['post_id' => $post->id, 'user_id' => $replier->id, 'parent_id' => $parent->id, 'body' => $body],
                        ['created_at' => $parent->created_at->copy()->addMinutes(8), 'updated_at' => $parent->created_at->copy()->addMinutes(8)]
                    );
                }

                $post->comments_count = ArenaComment::where('post_id', $post->id)->count();
                $post->save();
            }

            $emojis = ArenaReaction::EMOJIS;
            foreach ($users as $userIndex => $user) {
                // Seed a modest amount of two-way engagement across other
                // members' posts, using the same unique constraints as live UI.
                for ($offset = 1; $offset <= 4; $offset++) {
                    $post = $posts[($userIndex + $offset) % $posts->count()];
                    if ((int) $post->user_id === (int) $user->id) continue;

                    ArenaReaction::firstOrCreate(
                        ['reactable_type' => 'post', 'reactable_id' => $post->id, 'user_id' => $user->id],
                        ['emoji' => $emojis[($userIndex + $offset) % count($emojis)]]
                    );

                    ArenaPostVote::firstOrCreate(
                        ['post_id' => $post->id, 'user_id' => $user->id],
                        ['value' => 1]
                    );
                }

                for ($offset = 1; $offset <= 5; $offset++) {
                    $followed = $users[($userIndex + $offset) % $users->count()];
                    if ((int) $followed->id === (int) $user->id) continue;
                    DB::table('arena_follows')->updateOrInsert(
                        ['follower_id' => $user->id, 'followed_id' => $followed->id],
                        ['created_at' => now()->subDays(($userIndex + $offset) % 21), 'updated_at' => now()->subDays(($userIndex + $offset) % 21)]
                    );
                }
            }

            // Derive stored counters from the actual rows so the feed's totals
            // stay accurate if this migration is safely retried.
            foreach ($posts as $post) {
                $post->upvotes_count = ArenaPostVote::where('post_id', $post->id)->where('value', 1)->count();
                $post->downvotes_count = ArenaPostVote::where('post_id', $post->id)->where('value', -1)->count();
                $post->comments_count = ArenaComment::where('post_id', $post->id)->count();
                $post->save();
            }
        });
    }

    public function down(): void
    {
        // Keep these launch feed entries if a later migration is rolled back:
        // members may already have replied, reacted, or voted on them.
    }
};
