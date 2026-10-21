<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\NewsPost;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('news_posts')) {
            return;
        }

        // Clear out the placeholder/demo articles created while the News feature was being
        // built - news_comments cascades automatically via its FK, so this is safe to just
        // delete outright rather than trying to cherry-pick which rows were "demo" ones.
        NewsPost::query()->delete();

        $articles = [
            [
                'badge_label' => 'Tech',
                'title' => 'How Ray Tracing Is Reshaping Game Visuals',
                'excerpt' => 'Real-time ray tracing has gone from a tech demo curiosity to a mainstream feature, and it is changing how studios think about lighting, reflections, and atmosphere.',
                'body' => "<p>A few years ago, ray tracing was mostly a buzzword reserved for flashy tech demos. Today it has quietly become a standard feature in modern game engines, and the difference shows up in the small details: the way sunlight bounces off wet pavement, the soft shadows under a canopy of trees, the realistic reflections in a shop window.</p><p>What makes this shift interesting isn't just the visual fidelity - it's how it's changing the workflow for artists and lighting designers. Instead of faking light behavior with pre-baked tricks, teams can light a scene once and trust that it will look believable from any angle.</p><p>Of course, all that realism comes with a performance cost, which is why hybrid approaches - mixing ray-traced elements with traditional rendering - remain the practical choice for most studios balancing visual ambition against smooth frame rates.</p>",
            ],
            [
                'badge_label' => 'Game Design',
                'title' => 'AI NPCs: The Next Frontier in Game Design',
                'excerpt' => 'Non-player characters have historically followed rigid scripts. A new generation of AI-driven NPCs is starting to change that, for better and occasionally for worse.',
                'body' => "<p>For decades, talking to a non-player character meant picking from a short list of dialogue options that led to the same handful of responses no matter how you asked. That's starting to change as studios experiment with more dynamic, context-aware NPC behavior.</p><p>The appeal is obvious: a world that reacts naturally feels more alive. A shopkeeper who remembers your last visit, a guard who comments on the weather, companions who react differently depending on how you've played - these small touches add up to a much stronger sense of immersion.</p><p>The challenge studios are wrestling with is consistency. Players expect NPCs to stay in character and the world's lore, not wander off into nonsense. Expect this to remain one of the most actively debated areas of game design in the coming years.</p>",
            ],
            [
                'badge_label' => 'Esports',
                'title' => "Esports Prize Pools Keep Climbing - Here's Why",
                'excerpt' => 'Competitive gaming has moved well past its "niche hobby" reputation, with tournament prize pools now rivaling traditional sports.',
                'body' => "<p>It's easy to forget how recently esports was considered a fringe activity. These days, major tournaments fill arenas, draw millions of concurrent viewers online, and hand out prize pools that would have seemed unthinkable a decade ago.</p><p>Sponsorship money is a big part of the story, but so is the maturing broadcast product - better production values, dedicated commentary talent, and story-driven tournament formats that make competitive play genuinely watchable for newcomers, not just die-hard fans.</p><p>As more publishers build dedicated competitive ecosystems around their games from day one, rather than letting them emerge organically, expect the gap between esports and traditional spectator sports to keep narrowing.</p>",
            ],
            [
                'badge_label' => 'Tech',
                'title' => 'Cloud Gaming Finally Delivers on Its Promise',
                'excerpt' => 'Early cloud gaming services were held back by latency and unreliable connections. Years of infrastructure investment are finally paying off.',
                'body' => "<p>The pitch behind cloud gaming has always been simple: stream a game the way you'd stream a movie, no powerful hardware required. The execution, for a long time, didn't live up to the promise - laggy inputs and compressed visuals made it a hard sell for anything beyond casual play.</p><p>That gap has narrowed considerably. Better edge-server coverage, smarter compression, and more forgiving network conditions have made cloud play genuinely viable for a much wider range of games, including fast-paced competitive titles that used to be a non-starter.</p><p>It's not a total replacement for local hardware yet, but for players who just want to jump into a game on whatever device is nearest to them, cloud gaming has quietly become a legitimate option rather than a compromise.</p>",
            ],
            [
                'badge_label' => 'Culture',
                'title' => 'Retro Revival: Why Classic Games Are Making a Comeback',
                'excerpt' => 'Remasters, re-releases, and a thriving preservation community are keeping decades-old games relevant to a new generation of players.',
                'body' => "<p>Walk through any modern game storefront and you'll find remastered classics sitting comfortably alongside brand-new releases. It's a sign of just how much appetite there is for games that, in some cases, are older than the players discovering them for the first time.</p><p>Part of the draw is nostalgia, but it's not the whole story. Many of these older games have tight, focused designs that hold up well against the sprawling, feature-heavy titles common today. There's something refreshing about a game that respects your time.</p><p>Preservation communities deserve real credit here too - the unglamorous, often thankless work of documenting, archiving, and keeping old titles playable is a big part of why this generation of players gets to experience gaming history firsthand.</p>",
            ],
            [
                'badge_label' => 'Mobile',
                'title' => 'The Mobile Gaming Boom Shows No Signs of Slowing',
                'excerpt' => 'Smartphones have quietly become the most popular gaming platform on the planet, and the games themselves keep getting more ambitious.',
                'body' => "<p>It's easy to underestimate mobile gaming if you grew up thinking of consoles and PCs as the \"real\" platforms. But by sheer number of players, mobile has been the dominant gaming platform for years now, and the gap keeps widening.</p><p>What's changed recently is ambition. Early mobile games were mostly simple time-killers, but today's mobile titles increasingly borrow design ideas from console and PC gaming - deeper progression systems, richer visuals, and genuinely social features that keep players coming back.</p><p>As phone hardware keeps improving, expect the line between \"mobile game\" and \"real game\" to keep blurring, to the point where the distinction barely matters to most players.</p>",
            ],
            [
                'badge_label' => 'Tech',
                'title' => 'Game Engines: Unreal, Unity, and the New Challengers',
                'excerpt' => 'The engine landscape that once felt like a two-horse race is getting more interesting, with new tools lowering the barrier to entry.',
                'body' => "<p>For a long time, choosing a game engine mostly meant picking between Unreal and Unity, each with its own strengths and trade-offs. That landscape is slowly becoming more competitive.</p><p>Smaller, more specialized engines are carving out loyal followings by focusing on specific strengths - 2D games, rapid prototyping, or particular visual styles - rather than trying to be everything to everyone. That focus can be a real advantage for teams who know exactly what kind of game they're building.</p><p>Meanwhile, the big engines keep pushing toward making high-end features more accessible to smaller teams, which is good news for independent developers who want console-quality visuals without a console-sized budget.</p>",
            ],
            [
                'badge_label' => 'Community',
                'title' => 'How Modding Communities Keep Old Games Alive',
                'excerpt' => 'Long after official support ends, dedicated modding communities keep games playable, relevant, and sometimes unrecognizable from their original release.',
                'body' => "<p>Plenty of games have a longer second life in the hands of their modding community than they ever had under official support. Bug fixes, quality-of-life improvements, entirely new content - modders fill in the gaps studios leave behind.</p><p>Some of the most beloved features in modern games started life as fan-made mods that proved popular enough to influence - or get absorbed into - official development. It's one of the clearest examples of players shaping the direction of the hobby from the outside in.</p><p>Studios have increasingly caught on to this value, with more games shipping official modding tools from day one instead of leaving the community to reverse-engineer everything themselves.</p>",
            ],
            [
                'badge_label' => 'Multiplayer',
                'title' => 'The Rise of Cross-Platform Play',
                'excerpt' => 'Playing with friends used to mean everyone owning the same console. Cross-platform play is quietly erasing that barrier for good.',
                'body' => "<p>There was a time when choosing a gaming platform also meant choosing your friend group - if you weren't on the same console, you simply couldn't play together. Cross-platform play has steadily dismantled that limitation.</p><p>What started as an occasional feature in a handful of titles has become close to an expectation for any game with a meaningful multiplayer component. Players simply don't want to be locked out of playing with friends over a hardware choice.</p><p>The technical and competitive-balance challenges are real - different control schemes create different advantages - but the player demand for staying connected across platforms has made cross-play a feature few major releases can afford to skip.</p>",
            ],
            [
                'badge_label' => 'Safety',
                'title' => 'Voice Chat and Safety: How Platforms Are Protecting Players',
                'excerpt' => 'As voice and video chat become standard in online games, platforms are investing heavily in moderation tools to keep communities welcoming.',
                'body' => "<p>Real-time voice chat adds a lot to multiplayer games - coordinating a strategy feels completely different when you can just talk it through. But it also introduces real moderation challenges that text chat never had to deal with in quite the same way.</p><p>In response, platforms have been investing in a layered approach: reporting tools that are easy to find in the moment, smarter automated detection for clearly abusive behavior, and human review teams for the judgment calls that automation can't handle well.</p><p>None of this is a solved problem, but the direction is encouraging - treating player safety as a core feature to design around, not an afterthought bolted on after launch.</p>",
            ],
            [
                'badge_label' => 'Culture',
                'title' => 'From LAN Parties to Livestreams: The Evolution of Multiplayer',
                'excerpt' => 'The way people play together has transformed completely over the past two decades, even if the appeal - playing with friends - has stayed the same.',
                'body' => "<p>There's something fitting about how multiplayer gaming has evolved: the technology keeps changing, but the core appeal - doing something fun together with other people - hasn't moved an inch.</p><p>LAN parties, with their tangle of ethernet cables and borrowed monitors, gave way to online matchmaking, which gave way to persistent social hubs where you can jump between games with the same group of friends without ever really \"logging off\" of each other.</p><p>Livestreaming added another layer entirely, turning multiplayer sessions into something an audience can watch and participate in from the sidelines. The party just keeps getting bigger.</p>",
            ],
            [
                'badge_label' => 'Industry',
                'title' => 'Indie Developers Are Having a Moment',
                'excerpt' => 'Smaller studios are punching well above their weight, with some of the most talked-about releases in recent memory coming from teams of a handful of people.',
                'body' => "<p>It used to be assumed that only big studios with big budgets could make games that broke through into mainstream conversation. That assumption hasn't held up well lately.</p><p>Lower-cost, accessible development tools have leveled the playing field considerably, letting small teams - sometimes a single person - build polished, distinctive games without needing a publisher-sized budget behind them.</p><p>What indie games often lack in production scale, they make up for in focus and creative risk-taking. Without a committee second-guessing every decision, small teams can build genuinely weird, memorable experiences that bigger studios would rarely greenlight.</p>",
            ],
            [
                'badge_label' => 'Economy',
                'title' => 'The Future of In-Game Economies',
                'excerpt' => 'From cosmetic items to player-driven marketplaces, in-game economies have become sophisticated systems in their own right.',
                'body' => "<p>Virtual items used to be a novelty. Now, in-game economies are carefully designed systems with their own supply, demand, and player-driven marketplaces - sometimes rivaling the complexity of real economic systems.</p><p>Done well, this adds a genuine layer of strategy and self-expression: trading, crafting, and collecting become part of the appeal rather than a distraction from it. Done poorly, it can feel like the game is working against the player rather than for them.</p><p>Expect design teams to keep refining this balance, since a healthy in-game economy is now treated as a core pillar of long-term player engagement rather than a side feature.</p>",
            ],
            [
                'badge_label' => 'Tech',
                'title' => 'Why Server Stability Matters More Than Flashy Graphics',
                'excerpt' => 'Players will forgive modest visuals far more readily than they will forgive dropped connections and laggy servers.',
                'body' => "<p>It's a lesson the industry has learned and re-learned: players are remarkably forgiving of a game that looks a little rough around the edges, but they have almost no patience for one that doesn't run reliably.</p><p>A smooth, stable connection with low latency does more for how a game feels than another generation of graphical fidelity ever could. Nothing breaks immersion faster than a lag spike in the middle of an important moment.</p><p>That's why so much unglamorous engineering effort goes into server infrastructure behind the scenes - it rarely shows up in a trailer, but it's often the single biggest factor in whether players stick around.</p>",
            ],
            [
                'badge_label' => 'Accessibility',
                'title' => 'Accessibility in Gaming: Designing for Everyone',
                'excerpt' => 'Colorblind modes, remappable controls, and adjustable difficulty are no longer afterthoughts - they are becoming standard design practice.',
                'body' => "<p>Accessibility in gaming has gone from a niche concern to a genuine design discipline in its own right. Features like colorblind-friendly palettes, fully remappable controls, and adjustable difficulty aren't just nice extras anymore - players expect them.</p><p>The shift makes sense once you consider the numbers: a huge range of players have some kind of visual, auditory, or motor difference that affects how they experience a game. Designing with that in mind from the start, rather than patching it in later, tends to produce a better experience for everyone.</p><p>It's a trend worth celebrating - more players getting to enjoy the games they're excited about, without unnecessary barriers standing in the way.</p>",
            ],
        ];

        $now = now();
        foreach ($articles as $article) {
            NewsPost::create([
                'title' => $article['title'],
                'slug' => NewsPost::uniqueSlug($article['title']),
                'badge_label' => $article['badge_label'],
                'excerpt' => $article['excerpt'],
                'body' => $article['body'],
                'image' => null,
                'views' => random_int(40, 900),
                'status' => 1,
                'posted_by_type' => 'admin',
                'posted_by_id' => null,
                'posted_by_name' => 'Winxpot Team',
                'created_at' => $now->copy()->subDays(random_int(0, 30)),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally not reversible - this is a one-time content seed, not a schema change.
    }
};
