<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SupportBot::respond() only had generic gambling-site categories (login, deposit,
     * withdraw, bonus, kyc, games, security) baked into the code, and the admin-trained table
     * it checks first (support_bot_trainings) had zero rows - so any question about a feature
     * specific to this platform (table games, Arena, VIP/rakeback, currency switching,
     * competitions, gifts, the PWA install flow...) always fell through to the generic "I'm
     * still learning about that" fallback. This seeds a broad first pass of Winxpot-specific
     * trigger phrases and answers, each written to describe how the feature actually behaves
     * (not a hardcoded number like a commission rate, which is admin-configurable and would go
     * stale). Matching is plain substring search (SupportBot::trainedResponse()), so phrases
     * are short and literal, not full sentences.
     */
    public function up(): void
    {
        if (!Schema::hasTable('support_bot_trainings')) return;
        if (DB::table('support_bot_trainings')->exists()) return;

        $now = now();
        $rows = [
            ['table games, live games, live tables, board games', 'Live Tables lets you play real board/card games - Ludo, Chess, Draughts, Whot, Spades, Hearts, Backgammon, Dominoes, Sea Battle, Blackjack, Texas Hold\'em, Rummy, Crazy Eights, Oware, Snakes and Ladders, Four in a Row, and X and O - against other real players, with every move confirmed by the game server. Open "Live Tables" from the menu to see what\'s live or create your own table.'],
            ['create a table, make a table, start a table, host a table, set up a table', 'To create a table: open Live Tables, pick a game from the picker, choose Friendly (free, just for fun) or Staked (real money), set the number of seats and your stake if staked, then tap Create table. It starts once all seats fill, or earlier if you start it yourself with at least 2 players seated.'],
            ['join a table, join a live game', 'Open Live Tables to see tables currently waiting for players. Tap Join on any waiting table with an open seat. You can also Watch an in-progress table if you just want to spectate.'],
            ['watch a table, watch a game, spectate, spectator, watch without joining', 'You can watch any in-progress live table without joining it - open Live Tables and tap Watch on a table that\'s already playing. Spectating doesn\'t use your balance.'],
            ['friendly mode, friendly table, free table, play for fun', 'Friendly mode lets you play any live table game for free, with no stake and no money involved - pick "Friendly" instead of "Staked" when creating a table. Great for trying a game out first.'],
            ['staked table, real money table, stake table', 'A staked table uses real money: each seated player puts in the stake you set when creating the table, and when the game finishes the winner receives the pot minus a commission, shown on the create-table screen before you confirm.'],
            ['commission, table commission, house fee', 'Live table games take a small commission out of the pot when a staked game finishes - you can see the exact pot, commission, and what the winner receives on the create-table screen before you confirm, based on the stake and seats you choose.'],
            ['rematch, play again, request rematch', 'After a staked table finishes, the players can request a rematch with the same game and stake from the table\'s result screen. It needs to be accepted (or everyone can decline, which cancels it) and expires if it isn\'t actioned.'],
            ['leave a table, quit a table, forfeit, missing turns, missed turns, kicked from a table, kicked out', 'Leaving a live table mid-game, or missing a couple of turns in a row, puts you out of that game and your stake stays in the pot for the remaining players - so only leave if you mean to forfeit.'],
            ['invite to table, invite a friend to play, table invitation', 'From a table you\'ve created you can invite another player directly; they\'ll see the invitation and can accept it to take an open seat. You can also check pending invitations from the Live Tables page.'],
            ['hall of fame, leaderboard, top winners, weekly winners', 'Hall of Fame shows the top players for each live table game, both all-time and this week\'s top winners by wins. Open it from the trophy button on the Live Tables page and pick a game to see its leaderboard.'],
            ['recent wins, who won, big wins', 'The Live Tables and Arena pages show a live feed of recent wins from real games across the site, mixing normal games and live table games, so you can see what\'s been won recently.'],
            ['ludo rules, ludo safe square, ludo star', 'In Ludo, the four starting squares and the four star squares further round the board are safe - an opponent landing there won\'t send your token home. Anywhere else on the board, landing on an opponent\'s token sends it back to their yard.'],
            ['draughts rules, draughts capture, draughts king', 'In Draughts, capturing is optional, not forced - you can make any legal move even when a capture is available, but once you start a multi-jump capture with a piece you must finish that sequence with the same piece. A king can slide any number of empty squares diagonally and fly-capture over an opponent to land on any empty square beyond it.'],
            ['chess rules, how to play chess', 'Chess on Winxpot follows standard chess rules - each side moves pieces in turn, checkmating the opponent\'s king wins the game. Standard castling, en passant, and promotion rules apply.'],
            ['whot rules, how to play whot', 'Whot is played with a special 54-card deck - match the shape or number of the top card, or play a matching action card (like Pick Two or Pick Three) to stack penalties on the next player. The first to clear their hand wins.'],
            ['spades rules, hearts rules, partnership game', 'Spades and Hearts are 4-player partnership games - you\'re paired with the player across from you. If a partnership game breaks early because players drop out, the remaining stakes are refunded rather than settled.'],
            ['chat in table games, voice note, table chat', 'Each live table has its own chat where seated players and spectators can send text messages, quick reactions, and short voice notes while the game is in progress.'],
            ['gift in table games, send a gift, table gift', 'During a live table game you can send other players small gifts as a friendly gesture. Open the gift option from the table screen to see what\'s available and send one.'],
            ['arena, winxpot arena, social feed', 'Arena is Winxpot\'s community feed - post updates, share a recent win, react to and comment on other players\' posts, and follow players you like. Open Arena from the main menu.'],
            ['post on arena, share a win on arena', 'From Arena, use the composer at the top of the feed to write a post - you can optionally attach a photo, a short video, or one of your own recent wins to show off what you landed.'],
            ['follow on arena, arena profile', 'Tap a player\'s name or avatar anywhere on Arena to view their public profile, where you can follow them and see their posts.'],
            ['vip club, vip tier, vip rewards', 'VIP Club rewards you for how much you wager over time - the more you play, the higher tier you reach, unlocking better rakeback and perks. Open VIP Club from the menu to see your current tier and progress to the next one.'],
            ['rakeback, claim rakeback', 'Rakeback is a percentage of your wagering given back to you based on your VIP tier. Check VIP Club to see what you\'ve earned and claim it from there once it\'s available.'],
            ['competition, tournament, competition ticket', 'Competitions are time-limited events you enter with tickets, with prizes drawn at the end. Check the Competitions section for what\'s currently running and how to get a ticket.'],
            ['currency, change currency, switch currency, wallet currency', 'You can set your preferred wallet currency in your profile settings - your real balance is converted to it, and amounts across the site display in your chosen currency afterward.'],
            ['demo balance, demo mode, practice mode, practice balance', 'Demo mode lets you play with a separate demo balance that resets and never uses real money - it\'s there to practice a game before playing for real. Toggle Demo when starting a game to use it.'],
            ['install app, install the app, how to install, add to home screen, add to home, download app, pwa', 'Winxpot works as an installable app right from your browser - look for the Install App button (on the homepage or landing page) to add it to your home screen for a native-app-like experience, no app store needed.'],
            ['two factor, 2fa, enable 2fa, authenticator', 'Two-factor authentication adds an extra security code at login. You can turn it on from your account security settings - once enabled, you\'ll need a one-time code alongside your password to sign in.'],
            ['change password, update password, reset password', 'You can change your password any time from your profile settings while signed in. If you\'re locked out, use "Forgot password?" on the login page instead to reset it by email.'],
            ['change email, update email', 'You can update your email address from your profile settings while signed in. Some changes may need re-verification for your account\'s security.'],
            ['news, blog, winxpot news', 'The News page has the latest updates, announcements, and articles from Winxpot - open "News" from the menu to browse recent posts.'],
            ['promotions, current promotions, active promos', 'The Promotions page lists current offers and campaigns - open it from the menu to see what\'s active right now and how to take part.'],
            ['sponsorship, sponsorships, sponsor an event', 'Winxpot sponsors community events - see current and past sponsorships, or submit your own event for consideration, from the Sponsorships page.'],
            ['feedback, leave feedback, review winxpot', 'You can leave a rating and a written review of your experience from the Feedback page - approved feedback is shown publicly to other players.'],
            ['referral, refer a friend, invite friends, referral reward', 'Invite friends to Winxpot with your referral link or code from your account - you earn a referral reward when they join and play, shown in your referral details.'],
            ['official social media, official page, social media, facebook page, discord server, discord, instagram, tiktok, twitter, your social, your facebook, your instagram, your tiktok', 'Our official pages are listed under the "Official" button in the menu and the app footer - it shows our verified X, Facebook, Instagram, Discord, and TikTok links so you can find us safely and avoid impersonators.'],
            ['minimum deposit, maximum withdrawal, deposit limit, withdrawal limit', 'Minimum and maximum amounts vary by payment method - open Deposit or Withdraw to see the current limits for each method before you submit.'],
            ['support ticket, open a ticket, contact support team', 'For anything that needs a written record or isn\'t urgent, open a support ticket from the Support/Ticket page - our team replies there, and you can track the conversation any time.'],
            ['delete account, close my account, self exclude, responsible gambling', 'If you want to close your account or set responsible-play limits, talk to a live agent - they can walk you through the account options safely rather than this being done automatically in chat.'],
            ['fair games, provably fair, game fair, games fair, is it fair, fair play, random number, rng', 'Game outcomes are generated server-side using a random process designed to be fair to every player - the server, not your device, determines and confirms every result.'],
            ['app not working, site not loading, page not loading, site down, wont load, won\'t load, not working, cant load, can\'t load', 'First try refreshing the page or reopening the app, and check your internet connection. If it still won\'t load, let a live agent know what page and device you\'re using so it can be looked into.'],
            ['mobile app, native app, app store', 'Winxpot doesn\'t need an app-store download - install it straight from your browser as a Progressive Web App (look for Install App) for the same fast, native-app feel on your phone.'],
        ];

        $sort = 0;
        foreach ($rows as [$triggers, $response]) {
            DB::table('support_bot_trainings')->insert([
                'trigger_phrases' => $triggers,
                'response' => $response,
                'is_active' => true,
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally not reversible - an admin may have since edited these rows through the
        // Support Bot admin page, and a blind delete here could wipe out real edits/additions.
    }
};
