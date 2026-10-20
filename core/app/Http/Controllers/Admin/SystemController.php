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
                'content' => ['sponsorships' => ['heading' => 'Sponsorships', 'subheading' => 'Brands and partners supporting Winxpot events']],
            ],
            [
                'slug' => 'licenses-security', 'name' => 'Licenses & Security', 'secs' => ['licenses'],
                'content' => ['licenses' => ['heading' => 'Licenses & Security', 'subheading' => 'Explore the policies, organizations and safeguards that support a safer Winxpot experience.']],
            ],
            [
                'slug' => 'vip-club', 'name' => 'VIP-Club', 'secs' => ['vip_club', 'vip_member_privileges', 'vip_member_faq'],
                'content' => ['vip_club' => ['heading' => 'VIP Club', 'subheading' => 'Climb the tiers, unlock real rakeback and privileges']],
            ],
            [
                'slug' => 'feedback-about-us', 'name' => 'Feedback About Us', 'secs' => ['feedback_hero', 'feedback_form', 'feedback_reviews'],
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

        // Existing installations already have a VIP page, so merge new editable
        // sections into it instead of relying on the create-only page seed above.
        $vipSections = ['vip_club', 'vip_member_privileges', 'vip_member_faq'];
        $vipPage = Page::firstOrNew(['tempname' => $pageTempname, 'slug' => 'vip-club']);
        if (!$vipPage->exists) {
            $vipPage->name = 'VIP-Club';
            $vipPage->secs = json_encode($vipSections);
            $vipPage->save();
        } else {
            $currentSections = json_decode($vipPage->secs, true) ?: [];
            $mergedSections = array_values(array_unique(array_merge($currentSections, $vipSections)));
            if ($mergedSections !== $currentSections) {
                $vipPage->secs = json_encode($mergedSections);
                $vipPage->save();
            }
        }

        $aboutContent = [
            'licenses' => [
                'heading' => 'Licenses & Security',
                'subheading' => 'Explore the policies, organizations and safeguards that support a safer Winxpot experience.',
            ],
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
                'license_label' => 'Certificate of Cooperation',
                'license_number' => '',
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
            'vip_member_hero' => [
                'heading' => 'Welcome to VIP Club',
                'subheading' => 'A little extra for every step of your journey.',
                'description' => 'Progress through the tiers, explore the benefits available to you and see what is next.',
                'partner_label' => 'VIP PARTNER',
            ],
            'vip_club' => [
                'heading' => 'Unlock Exclusive VIP Rewards at Winxpot',
                'subheading' => 'Join the VIP Club to explore rewards, progression and exclusive monthly experiences. Benefits depend on your current tier and the terms shown in your account.',
            ],
            'vip_member_privileges' => ['heading' => 'VIP Club Privileges'],
            'vip_member_faq' => ['heading' => 'VIP Club FAQ'],
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

        // Ensure existing installations have the editable license hero fields,
        // while preserving any image and copy already entered by an administrator.
        $licensePage = Page::firstOrNew(['tempname' => $pageTempname, 'slug' => 'licenses-security']);
        if (!$licensePage->exists) {
            $licensePage->name = 'Licenses & Security';
            $licensePage->secs = json_encode(['licenses']);
            $licensePage->save();
        } else {
            $licenseSections = json_decode($licensePage->secs, true) ?: [];
            if (!in_array('licenses', $licenseSections, true)) {
                $licenseSections[] = 'licenses';
                $licensePage->secs = json_encode(array_values(array_unique($licenseSections)));
                $licensePage->save();
            }
        }

        $licenseContent = Frontend::firstOrNew(['data_keys' => 'licenses.content', 'tempname' => $contentTempname]);
        $licenseValues = (array) ($licenseContent->data_values ?? []);
        $licenseValues += [
            'heading' => 'Licenses & Security',
            'subheading' => 'Your privacy, account security and clear information are at the heart of the Winxpot experience.',
            'hero_image' => null,
        ];
        if (($licenseValues['subheading'] ?? null) === 'Licensed, audited and built on provably-fair technology') {
            $licenseValues['subheading'] = 'Your privacy, account security and clear information are at the heart of the Winxpot experience.';
        }
        $licenseContent->data_keys = 'licenses.content';
        $licenseContent->tempname = $contentTempname;
        $licenseContent->data_values = $licenseValues;
        $licenseContent->save();

        // Turn legacy sample claims into neutral, editable rows with empty logo
        // slots. Admins can add verified names, copy, and artwork before publishing.
        $licensePlaceholders = [
            'Regulatory information' => 'Add the verified operator, jurisdiction and license details here. Upload the regulator or license logo after confirming the details against the official register.',
            'Gaming authority' => 'Add the verified regulator name, jurisdiction and public register reference. Upload the official logo after checking its permitted use.',
            'AML and financial crime' => 'Describe the controls and responsible team that apply to Winxpot. Add only verified information and authorized logos.',
            'Platform security' => 'Describe the security measures actually used by Winxpot. Upload a supporting security mark only when its use is authorized and current.',
            'Privacy and data' => 'Summarize the privacy practices that apply to Winxpot. Upload a relevant organization logo only where the relationship is verified.',
            'Identity checks' => 'Describe the identity verification process and provider used by Winxpot. Upload its logo only after confirming the current relationship.',
            'Transaction security' => 'Describe the transaction security provider or controls used by Winxpot. Upload a logo only after confirming it is current and authorized.',
            'Connection security' => 'Describe the connection protection in use. Keep this information current and avoid implying certification without verified evidence.',
            'Responsible play' => 'Describe the responsible play tools available to players and add verified support details or resources.',
        ];
        $legacyLicenseTitles = [
            'crypto gambling' => 'Regulatory information', 'curacao gaming authority' => 'Gaming authority',
            'gcb' => 'Gaming authority', 'ukgc' => 'Gaming authority', 'fincen' => 'AML and financial crime',
            'gdpr' => 'Privacy and data', 'sumsub' => 'Identity checks', 'blockchain' => 'Transaction security',
            'ssl secured' => 'Connection security', 'ssl' => 'Connection security',
        ];
        $licenseRows = Frontend::where('data_keys', 'licenses.element')->where('tempname', $contentTempname)->get();
        foreach ($licenseRows as $item) {
            $values = (array) ($item->data_values ?? []);
            $oldTitle = strtolower(trim((string) ($values['title'] ?? '')));
            foreach ($legacyLicenseTitles as $legacy => $replacement) {
                if ($oldTitle !== $legacy) continue;
                $values['title'] = $replacement;
                $values['description'] = $licensePlaceholders[$replacement];
                $values['image'] = null;
                $item->data_values = $values;
                $item->save();
                break;
            }
        }
        $existingLicenseTitles = Frontend::where('data_keys', 'licenses.element')->where('tempname', $contentTempname)->get()
            ->map(fn (Frontend $item) => strtolower((string) ($item->data_values->title ?? '')))->all();
        foreach ($licensePlaceholders as $title => $description) {
            if (in_array(strtolower($title), $existingLicenseTitles, true)) continue;
            $element = new Frontend();
            $element->data_keys = 'licenses.element';
            $element->tempname = $contentTempname;
            $element->data_values = ['title' => $title, 'description' => $description, 'image' => null];
            $element->save();
        }

        $aboutElements = [
            'licenses' => [
                ['title' => 'Regulatory information', 'description' => 'Operator, jurisdiction and license details are maintained by Winxpot and published here only after they have been verified.'],
                ['title' => 'Platform security', 'description' => 'Use a unique password, keep your sign-in details private and contact support if you notice activity you do not recognize.'],
                ['title' => 'Privacy and data', 'description' => 'Read the Privacy Policy to learn how account information is handled and how to contact us with a privacy request.'],
                ['title' => 'Responsible play', 'description' => 'Play only if you meet the legal age where you live. Take breaks and contact support if you need help with your account.'],
            ],
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
                ['title' => 'Total Games Available', 'amount' => '—'],
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
            'vip_member_privileges' => [
                ['title' => 'Tier Rewards', 'description' => 'Unlock tier rewards as you progress through the VIP levels.'],
                ['title' => 'Daily Rakeback', 'description' => 'Earn rakeback based on the rate configured for your current tier.'],
                ['title' => 'Exclusive Promotions', 'description' => 'Discover promotions made available to VIP members.'],
                ['title' => 'Priority Support', 'description' => 'Get help from the support team when you need it.'],
                ['title' => 'Higher Limits', 'description' => 'Eligible tiers may include different betting and withdrawal limits.'],
                ['title' => 'VIP Events', 'description' => 'Look out for special events and experiences for VIP members.'],
            ],
            'vip_member_faq' => [
                ['question' => 'How do I progress to the next VIP tier?', 'answer' => 'Your eligible wagering activity counts toward the next tier. Your progress bar shows the amount remaining.'],
                ['question' => 'How is rakeback calculated?', 'answer' => 'Rakeback is calculated from eligible activity using the rate shown for your current tier. Terms and eligibility may apply.'],
                ['question' => 'When can I claim rakeback?', 'answer' => 'Available rakeback appears in your account when it is ready to claim. Open the Bonuses area to review your available rewards.'],
                ['question' => 'Do VIP tiers guarantee winnings?', 'answer' => 'No. VIP status provides the listed benefits only; it does not change game outcomes or guarantee winnings.'],
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

        // Seed complete, editable footer policy pages for this template. These drafts
        // avoid inventing a regulator, operator, or company registration details.
        $policyDefaults = [
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'details' => '<p><strong>Draft for Winxpot review.</strong> Before publishing, confirm the data controller, retention periods, service providers, and privacy contact for the jurisdictions where Winxpot operates.</p>
<h2>1. About this policy</h2><p>This Privacy Policy explains how Winxpot (“Winxpot”, “we”, “us”) collects, uses, stores, and shares personal information when you visit our website, create an account, use games or payment features, contact support, or otherwise interact with our services. The final published version should identify the legal entity that operates the service and the applicable local privacy rules.</p>
<h2>2. Information we may collect</h2><ul><li><strong>Account details:</strong> username, email address, date of birth, country, and account preferences.</li><li><strong>Verification information:</strong> identity, age, address, source-of-funds, or other documents when required for account security, payment processing, or applicable legal obligations.</li><li><strong>Transactions:</strong> deposits, withdrawals, payment method references, and related fraud-prevention records. We do not intend to collect full payment-card credentials where these are handled by a payment provider.</li><li><strong>Service activity:</strong> games used, settings, support requests, device and browser information, log data, and security events.</li><li><strong>Communications:</strong> messages sent to support, newsletter preferences, and feedback you choose to provide.</li></ul>
<h2>3. How we use information</h2><p>We use information to provide and maintain accounts, process requested transactions, personalize service settings, respond to support requests, protect the platform and users, detect fraud and abuse, meet applicable obligations, and send service or marketing messages where permitted. You can unsubscribe from marketing messages using the link in the message or by contacting support.</p>
<h2>4. Legal grounds and choices</h2><p>Depending on your location, processing may be based on providing the service you request, meeting a legal obligation, protecting users and the service, your consent, or another ground permitted by law. You may have rights to request access, correction, deletion, restriction, portability, or to object to certain uses. These rights and exceptions vary by location.</p>
<h2>5. Cookies and similar technologies</h2><p>We may use essential cookies to maintain sessions, protect accounts, and remember necessary settings. Optional analytics or marketing technologies should be used only in accordance with the consent requirements that apply to the visitor. Browser settings can control some cookies, though disabling essential cookies may affect site features.</p>
<h2>6. Sharing and international transfers</h2><p>Information may be shared with service providers that support hosting, security, identity checks, payments, communications, analytics, or customer care; with professional advisers; and with authorities where disclosure is legally required or necessary to protect people and the service. Providers should be limited to the information needed for their work and bound by suitable safeguards. If information is transferred across borders, Winxpot should apply the safeguards required by applicable law.</p>
<h2>7. Security and retention</h2><p>We use administrative, technical, and organizational measures designed to protect information. No online service can promise absolute security. Information is kept only as long as needed for the purposes described here, including account support, dispute handling, security, and legal recordkeeping, and is then deleted or de-identified where appropriate.</p>
<h2>8. Children and restricted users</h2><p>Winxpot is intended only for people who meet the minimum legal age for online gaming in their location and is not directed to children. If you believe a child has provided personal information, contact us so we can review and take appropriate steps.</p>
<h2>9. Contact and updates</h2><p>For privacy questions or requests, contact <a href="mailto:privacy@winxpot.com">privacy@winxpot.com</a>. If that mailbox is not active, use <a href="mailto:support@winxpot.com">support@winxpot.com</a>. We may update this policy and will display the revised version here with its effective date.</p>',
            ],
            [
                'slug' => 'terms-of-service',
                'title' => 'Terms of Service',
                'details' => '<p><strong>Draft for Winxpot review.</strong> Confirm the operating entity, governing law, dispute process, permitted territories, and product-specific rules before publishing. This page should be reviewed by qualified counsel.</p>
<h2>1. Acceptance and eligibility</h2><p>These Terms govern access to Winxpot’s website and services. By creating an account or using the service, you agree to the current Terms and linked policies. You must be at least 18 years old and meet the higher minimum age, if any, required where you live. You must not use Winxpot from a location where online gaming or the relevant service is prohibited.</p>
<h2>2. Account information and security</h2><p>Provide accurate, current information and keep your sign-in credentials private. You are responsible for activity on your account unless it resulted from a security failure for which Winxpot is responsible under applicable law. Notify support promptly if you suspect unauthorized access. One account per person may be required; do not sell, share, or transfer your account.</p>
<h2>3. Lawful and fair use</h2><p>Use the service only for lawful personal entertainment. Do not interfere with the site, exploit errors, use bots or collusion, manipulate games or promotions, impersonate another person, or attempt to access another user’s account. We may investigate suspected misuse and take proportionate action consistent with applicable law and published rules.</p>
<h2>4. Games, balances, and transactions</h2><p>Game rules and any game-specific information shown in the product apply to that game. Review the rules before playing. Payment methods, processing times, limits, fees, currencies, and verification requirements may vary by location and provider and are displayed in the account flow where available. A displayed balance is an account record and is not a bank deposit. Do not submit a payment for an account you do not control.</p>
<h2>5. Promotions</h2><p>Each promotion may have its own eligibility, expiry, wagering, game, and withdrawal conditions. Those conditions should be shown before you opt in. If a promotion appears to have been applied incorrectly, contact support before continuing to use it. Promotions are not a guarantee of winnings.</p>
<h2>6. Responsible play</h2><p>Play only for entertainment and only with money you can afford to lose. Set personal time and spending limits. Do not play while impaired or to recover losses. If play stops being enjoyable, take a break and contact support to ask about available account limits or exclusion tools. Help resources differ by country; seek local professional support if you are concerned about gambling.</p>
<h2>7. Verification, suspension, and closure</h2><p>We may request information reasonably needed to verify eligibility, protect an account, investigate suspicious activity, or comply with applicable requirements. We may restrict access while a concern is reviewed. You may ask to close your account by contacting support. Any remaining balance or pending transaction will be handled under the applicable rules and law.</p>
<h2>8. Service availability and intellectual property</h2><p>We aim to keep the service available, but maintenance, technical issues, or third-party outages may cause interruptions. Winxpot names, marks, software, and original content are protected by applicable intellectual-property rights. Do not copy or commercially use them without permission.</p>
<h2>9. Disclaimers, liability, and changes</h2><p>To the extent permitted by law, the service is provided subject to the warranties and limits permitted in your location. Nothing in these Terms removes a right or remedy that cannot legally be excluded. We may revise the Terms by publishing an updated version. Material changes should be communicated as required by law. Questions: <a href="mailto:support@winxpot.com">support@winxpot.com</a>.</p>',
            ],
            [
                'slug' => 'licenses-security',
                'title' => 'Licenses & Security',
                'details' => '<p><strong>Admin verification required before publication.</strong> Add only current, independently verifiable operator, regulator, and license details. Do not describe Winxpot as licensed or certified unless the named entity, jurisdiction, and license status have been checked against the regulator’s official register.</p>
<h2>Regulatory information</h2><p>Online gaming rules depend on the player’s location and the legal entity providing the service. Winxpot’s operator name, registration details, authorized territories, regulator, and license number must be supplied and verified by the site administrator before this page makes any regulatory claim. Where a service is not available, it must not be offered in that location.</p>
<h2>Platform security</h2><p>Winxpot uses security practices intended to protect accounts and service data. Keep your password private, use a unique password, sign out on shared devices, and contact support if you notice activity you do not recognize. We may ask for identity or transaction information when needed to protect an account or review a payment.</p>
<h2>Game integrity</h2><p>Game providers and game-specific rules should be identified on the relevant game pages. Any claim about independent testing, audited algorithms, certifications, or provably fair results must include the actual provider or auditor and a working verification reference before it is published.</p>
<h2>Report a security issue</h2><p>If you believe you have found a security vulnerability, report it privately to <a href="mailto:security@winxpot.com">security@winxpot.com</a> or <a href="mailto:support@winxpot.com">support@winxpot.com</a>. Include enough detail to reproduce the issue, but do not access other users’ data, disrupt the service, or publicly disclose the issue before we have had a reasonable opportunity to investigate.</p>
<h2>Documents and updates</h2><p>Upload current license documents and security certifications through the admin content manager only after confirming that they are authentic, current, and approved for public display. Outdated or unverified documents should be removed. For questions, contact <a href="mailto:support@winxpot.com">support@winxpot.com</a>.</p>',
            ],
            [
                'slug' => 'aml-policy',
                'title' => 'AML Policy',
                'details' => '<p><strong>Draft for compliance review.</strong> This overview must be adapted to the actual operator, products, payment flows, and legal obligations in every permitted jurisdiction. It does not itself establish regulatory compliance.</p>
<h2>1. Purpose</h2><p>Winxpot aims to prevent its services from being misused for money laundering, terrorist financing, fraud, sanctions evasion, or other financial crime. Controls must be proportionate to the risks of the products and locations actually served and must follow applicable law.</p>
<h2>2. Customer checks</h2><p>Where required, Winxpot or its service providers may verify a customer’s identity, age, address, payment method, or source of funds. Checks may take place when an account is opened, before a transaction, when activity changes, or when a legal or security concern arises. Information should be requested securely and used only for legitimate verification purposes.</p>
<h2>3. Monitoring and review</h2><p>Transactions and account activity may be reviewed to identify unusual patterns, attempted use of third-party payment methods, inconsistent account information, or other indicators that require review. A review may result in a request for information or a temporary restriction while the matter is assessed.</p>
<h2>4. Restricted conduct</h2><p>Do not use an account to conceal the source or ownership of funds, use payment methods without authorization, move funds for another person, evade verification, or structure activity to avoid controls. Do not create multiple accounts to bypass limits or restrictions.</p>
<h2>5. Escalation and records</h2><p>Where required by applicable law, relevant concerns may be escalated to the appropriate internal contact or authority, and records may be retained for the required period. We may be unable to disclose certain review or reporting details where the law prohibits disclosure.</p>
<h2>6. Customer cooperation and contact</h2><p>Customers should provide complete and accurate information when a legitimate verification request is made. If you have questions about a request, contact <a href="mailto:support@winxpot.com">support@winxpot.com</a> using the contact details shown on this site. Never send passwords or full card security codes by email.</p>',
            ],
        ];

        foreach ($policyDefaults as $policy) {
            $exists = Frontend::where('data_keys', 'policy_pages.element')
                ->where('tempname', $contentTempname)
                ->where('slug', $policy['slug'])
                ->exists();
            if ($exists) continue;

            $content = new Frontend();
            $content->data_keys = 'policy_pages.element';
            $content->tempname = $contentTempname;
            $content->slug = $policy['slug'];
            $content->data_values = ['title' => $policy['title'], 'details' => $policy['details']];
            $content->save();
            $created[] = $policy['title'];
        }

        // Create editable partner-logo slots if the footer gallery is empty. Each item
        // exposes an image uploader in Admin > Frontend > Footer Partner Logos.
        if (!Frontend::where('data_keys', 'footer_partners.element')->where('tempname', $contentTempname)->exists()) {
            for ($slot = 1; $slot <= 8; $slot++) {
                $partner = new Frontend();
                $partner->data_keys = 'footer_partners.element';
                $partner->tempname = $contentTempname;
                $partner->data_values = ['partner_name' => 'Partner logo ' . $slot];
                $partner->save();
            }
        }

        if (!in_array('About Us', $created, true) && !in_array('About Us', $skipped, true)) {
            $skipped[] = 'About Us content already existed; missing defaults were added without replacing edits';
        }

        $notify = [];
        if ($created) {
            $notify[] = ['success', count($created) . ' page/content item(s) created: ' . implode(', ', $created)];
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
