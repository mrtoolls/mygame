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
                'slug' => 'about-us', 'name' => 'About Us', 'secs' => ['about', 'why_choose_us', 'statistics', 'awards'],
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

        $notify = [];
        if ($created) {
            $notify[] = ['success', count($created) . ' page(s) created: ' . implode(', ', $created)];
        }
        if ($skipped) {
            $notify[] = ['info', count($skipped) . ' page(s) already existed and were left as-is: ' . implode(', ', $skipped)];
        }
        if (!$created && !$skipped) {
            $notify[] = ['info', 'Nothing to do'];
        }

        return back()->withNotify($notify);
    }
}
