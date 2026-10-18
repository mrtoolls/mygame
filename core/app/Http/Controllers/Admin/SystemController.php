<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frontend;
use App\Models\Page;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    public function systemInfo()
    {
        $laravelVersion = app()->version();
        $timeZone       = config('app.timezone');
        $pageTitle      = 'Application Information';

        return view('admin.system.info', compact('pageTitle', 'laravelVersion', 'timeZone'));
    }

    public function optimize()
    {
        $pageTitle = 'Clear System Cache';

        return view('admin.system.optimize', compact('pageTitle'));
    }

    public function optimizeClear()
    {
        Artisan::call('optimize:clear');
        $notify[] = ['success', 'Cache cleared successfully'];

        return back()->withNotify($notify);
    }

    public function systemServerInfo()
    {
        $currentPHP    = phpversion();
        $pageTitle     = 'Server Information';
        $serverDetails = $_SERVER;

        return view('admin.system.server', compact('pageTitle', 'serverDetails', 'currentPHP'));
    }

    public function databaseUpdate()
    {
        $pageTitle = 'Database Update';
        $pending   = [];
        try {
            $pending = Artisan::call('migrate:status') === 0
                ? collect(explode("\n", Artisan::output()))
                    ->filter(fn($line) => str_contains($line, 'Pending'))
                    ->values()
                : [];
        } catch (\Throwable $e) {
            $pending = [];
        }
        return view('admin.system.database_update', compact('pageTitle', 'pending'));
    }

    public function databaseUpdateRun()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output   = Artisan::output();
            $notify[] = ['success', 'Database update finished'];
        } catch (\Throwable $e) {
            $output   = $e->getMessage();
            $notify[] = ['error', 'Database update failed, see the output below'];
        }
        session()->flash('migrate_output', $output);
        return back()->withNotify($notify);
    }

    public function seedNocturnePages()
    {
        $pages = [
            [
                'slug' => 'about-us', 'name' => 'About Us', 'secs' => ['about_hero', 'about_story', 'about_priority', 'about_team', 'about_awards', 'about_reviews', 'about_statistics', 'about_links', 'about_news', 'about_support'],
                'content' => ['about' => ['heading' => 'About Us', 'description' => 'We are building a modern, fair and secure gaming experience for every player.']],
            ],
            [
                'slug' => 'promotions', 'name' => 'Promotions', 'secs' => ['promotions'],
                'content' => ['promotions' => ['heading' => 'Promotions', 'subheading' => 'Grab the latest offers and boosts']],
            ],
            [
                'slug' => 'sponsorships', 'name' => 'Sponsorships', 'secs' => ['sponsorships'],
                'content' => ['sponsorships' => ['heading' => 'Sponsorships', 'subheading' => 'Proud partners across sport and esports']],
            ],
            [
                'slug' => 'licenses-security', 'name' => 'Licenses & Security', 'secs' => ['licenses'],
                'content' => ['licenses' => ['heading' => 'Licenses & Security', 'subheading' => 'Licensed, audited and built on provably-fair technology']],
            ],
            [
                'slug' => 'vip-club', 'name' => 'VIP-Club', 'secs' => ['vip_club'],
                'content' => ['vip_club' => ['heading' => 'VIP Club', 'subheading' => 'Climb the tiers, unlock real rakeback and privileges']],
            ],
            [
                'slug' => 'feedback-about-us', 'name' => 'Feedback About Us', 'secs' => ['testimonial'],
                'content' => ['testimonial' => ['heading' => 'What Players Say', 'description' => 'Real feedback from our community', 'button_name' => '', 'button_url' => '']],
            ],
        ];

        $created = [];
        $skipped = [];

        // Page.tempname and Frontend.tempname use two different conventions in this codebase:
        // PageBuilderController stores Page.tempname as activeTemplate() (dotted, e.g.
        // "templates.nocturne.") since that is what SiteController::pages()/index() query
        // against, while FrontendController/getContent() store and read Frontend.tempname as
        // the plain activeTemplateName() ("nocturne"). Using the wrong one for either model
        // means the row exists in the database but nothing can ever match it.
        $pageTempname = activeTemplate();
        $contentTempname = activeTemplateName();

        // Clean up rows an earlier, buggy version of this action created with the wrong
        // (plain, non-dotted) tempname - they can never be matched by any route, so they
        // were just dead clutter sitting in the pages table.
        Page::where('tempname', $contentTempname)->whereIn('slug', array_column($pages, 'slug'))->delete();

        foreach ($pages as $data) {
            if (Page::where('tempname', $pageTempname)->where('slug', $data['slug'])->exists()) {
                $skipped[] = $data['name'];
                continue;
            }

            $page            = new Page();
            $page->tempname  = $pageTempname;
            $page->name      = $data['name'];
            $page->slug      = $data['slug'];
            $page->secs      = json_encode($data['secs']);
            $page->save();

            foreach ($data['content'] as $sectionKey => $values) {
                $contentExists = Frontend::where('data_keys', $sectionKey . '.content')->where('tempname', $contentTempname)->exists();
                if ($contentExists) {
                    continue;
                }
                $content             = new Frontend();
                $content->data_keys  = $sectionKey . '.content';
                $content->tempname   = $contentTempname;
                $content->data_values = $values;
                $content->save();
            }

            $created[] = $data['name'];
        }

        // Seed the About Us page with a complete, editable Nocturne layout. Re-running
        // this action fills missing defaults without replacing any admin-edited content.
        $aboutSections = ['about_hero', 'about_story', 'about_priority', 'about_team', 'about_awards', 'about_reviews', 'about_statistics', 'about_links', 'about_news', 'about_support'];
        $aboutPage = Page::firstOrNew(['tempname' => $pageTempname, 'slug' => 'about-us']);
        if (!$aboutPage->exists) {
            $aboutPage->name = 'About Us';
            $aboutPage->secs = json_encode($aboutSections);
            $aboutPage->save();
        } else {
            $currentSections = json_decode($aboutPage->secs, true) ?: [];
            if (array_diff($aboutSections, $currentSections) || array_intersect(['about', 'why_choose_us'], $currentSections)) {
                $aboutPage->name = 'About Us';
                $aboutPage->secs = json_encode($aboutSections);
                $aboutPage->save();
            }
        }

        $aboutContent = [
            'about_hero' => [
                'heading' => 'About Us',
                'description' => 'Winxpot brings games, rewards and player support together in one clear, welcoming experience. Our goal is to make every visit enjoyable, transparent and easy to navigate.',
            ],
            'about_story' => [
                'heading' => 'About Winxpot',
                'subheading' => 'A Player-First Gaming Experience',
                'description' => "A great gaming experience starts with clear choices and a place that feels easy to use. Winxpot brings together a wide range of games, account tools, promotions and support in one destination.\n\nExplore at your own pace, find the games that suit you and keep the important account information close at hand.",
                'expanded_description' => "We continue to improve the way players discover games, manage their accounts and get help. Our team is focused on making the experience thoughtful at every step, from the first visit to the support that follows.\n\nPlease review the current terms, payment options and official license information on this site before using the platform.",
                'read_more_label' => 'Read More',
                'read_less_label' => 'Show Less',
            ],
            'about_priority' => [
                'heading' => 'Our Priority',
                'founder_name' => 'Winxpot Leadership Team',
                'founder_role' => 'A team focused on player experience',
                'founder_message' => 'Our work is guided by a simple principle: put players first. We aim to build a welcoming, clear and thoughtful gaming experience, and to make it easy to reach our team whenever you need help.',
                'license_label' => 'Official License Document',
                'license_number' => 'Add verified license details in the admin',
            ],
            'about_team' => [
                'heading' => 'Our Team',
                'subheading' => 'Here for Every Player',
                'description' => 'Winxpot brings together people across product, operations and customer care. Add your team photos and locations to introduce the people behind the experience.',
            ],
            'about_awards' => [
                'heading' => 'Awards',
                'subheading' => 'Every milestone matters. Explore the recognition Winxpot has earned and add the award details you would like players to see.',
            ],
            'about_reviews' => [
                'heading' => 'Reviews',
                'button_name' => 'View all',
                'button_url' => '/feedback-about-us',
            ],
            'about_statistics' => ['heading' => 'Winxpot Statistics'],
            'about_links' => ['heading' => 'Other Pages'],
            'about_news' => [
                'heading' => 'Latest News',
                'button_name' => 'View all',
                'button_url' => '/blog',
            ],
            'about_support' => [
                'heading' => 'Do you have a question?',
                'subheading' => 'Write us and our support team will help you.',
                'reply_time' => 'Winxpot Care is ready to help',
                'button_name' => 'Live Support',
                'button_url' => '#support',
            ],
        ];

        foreach ($aboutContent as $sectionKey => $values) {
            $contentExists = Frontend::where('data_keys', $sectionKey . '.content')->where('tempname', $contentTempname)->exists();
            if ($contentExists) continue;

            $content = new Frontend();
            $content->data_keys = $sectionKey . '.content';
            $content->tempname = $contentTempname;
            $content->data_values = $values;
            $content->save();
        }

        $aboutElements = [
            'about_story' => [
                ['title' => 'Get Your Earnings in Seconds', 'description' => 'Follow payment requests and check their latest status from your account.'],
                ['title' => 'Crypto and Bank Deposits', 'description' => 'Explore the payment methods available to you at checkout. Options may vary by location.'],
                ['title' => 'Promotions and VIP', 'description' => 'Discover current offers and VIP benefits from the Bonuses area.'],
                ['title' => 'Support When You Need It', 'description' => 'Contact Winxpot Care through live support or a written support ticket.'],
            ],
            'about_team' => [
                ['title' => 'Product & Design', 'description' => 'The people shaping the player experience.'],
                ['title' => 'Customer Care', 'description' => 'The team helping players find answers.'],
            ],
            'about_awards' => [
                ['title' => 'Award title here', 'description' => 'Year'],
                ['title' => 'Award title here', 'description' => 'Year'],
                ['title' => 'Award title here', 'description' => 'Year'],
                ['title' => 'Award title here', 'description' => 'Year'],
            ],
            'about_reviews' => [
                ['name' => 'Player Story', 'player_since' => 'Add membership year', 'quote' => 'Add an approved player review here. Update this sample with verified feedback in the admin before publishing.'],
                ['name' => 'Player Story', 'player_since' => 'Add membership year', 'quote' => 'Add an approved player review here. Update this sample with verified feedback in the admin before publishing.'],
                ['name' => 'Player Story', 'player_since' => 'Add membership year', 'quote' => 'Add an approved player review here. Update this sample with verified feedback in the admin before publishing.'],
            ],
            'about_statistics' => [
                ['title' => 'Total Players Online', 'amount' => '—'],
                ['title' => 'Total Registered Players', 'amount' => '—'],
                ['title' => 'Total Paid to Players', 'amount' => '—'],
            ],
            'about_links' => [
                ['title' => 'Feedback About Us', 'button_url' => '/feedback-about-us'],
                ['title' => 'Licenses & Security', 'button_url' => '/licenses-security'],
                ['title' => 'Sponsorships', 'button_url' => '/sponsorships'],
            ],
            'about_news' => [
                ['title' => 'A Fresh Look at Your Gaming Experience', 'description' => 'Explore updates, discover games and find helpful account information across Winxpot.', 'button_url' => '/blog'],
                ['title' => 'Discover Games for Every Mood', 'description' => 'Browse slots, table games and originals from the Games area.', 'button_url' => '/games'],
                ['title' => 'Stay Close to the Action', 'description' => 'Visit the news page for platform updates and announcements from the Winxpot team.', 'button_url' => '/blog'],
            ],
        ];

        foreach ($aboutElements as $sectionKey => $items) {
            $elementKey = $sectionKey . '.element';
            if (Frontend::where('data_keys', $elementKey)->where('tempname', $contentTempname)->exists()) continue;

            foreach ($items as $values) {
                $element = new Frontend();
                $element->data_keys = $elementKey;
                $element->tempname = $contentTempname;
                $element->data_values = $values;
                $element->save();
            }
        }

        if (!in_array('About Us', $created, true) && !in_array('About Us', $skipped, true)) {
            $skipped[] = 'About Us content already existed; missing defaults were added without replacing edits';
        }

        $notify = [];
        if ($created) {
            $notify[] = ['success', count($created) . ' page(s) created: ' . implode(', ', $created)];
        }
        if ($skipped) {
            $notify[] = ['info', 'Existing pages were preserved; missing About Us defaults were added without replacing edited copy or images. The About Us section order was refreshed if it still used the old layout. Existing pages: ' . implode(', ', $skipped)];
        }
        if (!$created && !$skipped) {
            $notify[] = ['info', 'Nothing to do'];
        }

        return back()->withNotify($notify);
    }
}
