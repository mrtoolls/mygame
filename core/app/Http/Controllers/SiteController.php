<?php

namespace App\Http\Controllers;

use App\Constants\Status;
use App\Models\AdminNotification;
use App\Models\Frontend;
use App\Models\Game;
use App\Models\Language;
use App\Models\Page;
use App\Models\Subscriber;
use App\Models\UserFeedback;
use Illuminate\Support\Facades\Schema;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;

class SiteController extends Controller {
    public function index() {
        if (isset($_GET['reference'])) {
            $reference = $_GET['reference'];
            session()->put('reference', $reference);
        }

        $pageTitle   = 'Home';
        $sections    = Page::where('tempname', activeTemplate())->where('slug', '/')->first();
        $seoContents = $sections?->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::home', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
    }

    public function pages($slug) {
        $page        = Page::where('tempname', activeTemplate())->where('slug', $slug)->firstOrFail();
        $pageTitle   = $page->name;
        $sections    = $page->secs;
        if ($slug === 'feedback-about-us') {
            $sections = json_encode(['feedback_hero', 'feedback_form', 'feedback_reviews']);
            $feedbacks = Schema::hasTable('user_feedback') ? UserFeedback::approved()->with('user:id,username,image')->latest('approved_at')->paginate(9) : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 9);
            $feedbackStats = Schema::hasTable('user_feedback') ? ['count' => UserFeedback::approved()->count(), 'average' => round((float) UserFeedback::approved()->avg('rating'), 2)] : ['count' => 0, 'average' => 0];
        }
        $seoContents = $page->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::pages', compact('pageTitle', 'sections', 'seoContents', 'seoImage'), isset($feedbacks) ? compact('feedbacks', 'feedbackStats') : []);
    }

    public function games() {
        $pageTitle = "Games";
        $sections  = Page::where('tempname', activeTemplate())->where('slug', 'games')->first();
        $games     = Game::active()->get();
        return view('Template::games', compact('pageTitle', 'sections', 'games'));
    }

    public function contact() {
        $pageTitle   = "Contact Us";
        $user        = auth()->user();
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'contact')->first();
        $seoContents = @$sections->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::contact', compact('pageTitle', 'user', 'sections', 'seoContents', 'seoImage'));
    }

    public function contactSubmit(Request $request) {
        $request->validate([
            'name'    => 'required',
            'email'   => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        $request->session()->regenerateToken();

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $random = getNumber();

        $ticket           = new SupportTicket();
        $ticket->user_id  = auth()->id() ?? 0;
        $ticket->name     = $request->name;
        $ticket->email    = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;

        $ticket->ticket     = $random;
        $ticket->subject    = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status     = Status::TICKET_OPEN;
        $ticket->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = auth()->user() ? auth()->user()->id : 0;
        $adminNotification->title     = 'A new contact message has been submitted';
        $adminNotification->click_url = urlPath('admin.ticket.view', $ticket->id);
        $adminNotification->save();

        $message                    = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message           = $request->message;
        $message->save();

        $notify[] = ['success', 'Ticket created successfully!'];

        return to_route('ticket.view', [$ticket->ticket])->withNotify($notify);
    }

    public function policyPages($slug) {
        $policy      = Frontend::where('tempname', activeTemplateName())->where('slug', $slug)->where('data_keys', 'policy_pages.element')->firstOrFail();
        $pageTitle   = $policy->data_values->title;
        $seoContents = $policy->seo_content;
        $seoImage    = @$seoContents->image ? frontendImage('policy_pages', $seoContents->image, getFileSize('seo'), true) : null;
        return view('Template::policy', compact('policy', 'pageTitle', 'seoContents', 'seoImage'));
    }

    public function changeLanguage($lang = null) {
        $language = Language::where('code', $lang)->first();
        if (!$language) {
            $lang = 'en';
        }

        session()->put('lang', $lang);
        return back();
    }

    public function blog(Request $request) {
        $pageTitle = 'News';

        // The hero (most recent post) and the 3 compact items beside it come from
        // their own small query so the grid below never re-shows them, regardless
        // of how many "Load more" pages get fetched.
        $featuredAndList = \App\Models\NewsPost::published()->orderBy('id', 'desc')->limit(4)->get();
        $featured        = $featuredAndList->first();
        $recentList      = $featuredAndList->slice(1)->values();

        $gridQuery = \App\Models\NewsPost::published()->whereNotIn('id', $featuredAndList->pluck('id'))->orderBy('id', 'desc');
        $blogs     = (clone $gridQuery)->paginate(9);

        if ($request->wantsJson()) {
            return response()->json([
                'html'    => view('Template::partials.news_grid_cards', ['blogs' => $blogs])->render(),
                'hasMore' => $blogs->hasMorePages(),
            ]);
        }

        $page     = Page::where('tempname', activeTemplate())->where('slug', 'blog')->first();
        $sections = @$page->secs;
        return view('Template::blog', compact('blogs', 'featured', 'recentList', 'pageTitle', 'sections'));
    }

    public function blogDetails($slug) {
        $blog = \App\Models\NewsPost::published()->where('slug', $slug)->firstOrFail();
        $blog->increment('views');

        $pageTitle   = $blog->title;
        $latestBlogs = \App\Models\NewsPost::published()->where('id', '!=', $blog->id)->orderBy('id', 'desc')->limit(5)->get();
        $comments    = $blog->comments()->with('user')->get();

        return view('Template::blog_details', compact('blog', 'pageTitle', 'latestBlogs', 'comments'));
    }

    public function blogCommentStore(\Illuminate\Http\Request $request, $slug) {
        $blog = \App\Models\NewsPost::published()->where('slug', $slug)->firstOrFail();
        $request->validate(['body' => 'required|string|max:1000']);

        \App\Models\NewsComment::create([
            'news_post_id' => $blog->id,
            'user_id'      => auth()->id(),
            'body'         => $request->body,
        ]);

        return back()->with('status', 'Comment posted.')->withFragment('comments');
    }

    public function cookieAccept() {
        Cookie::queue('gdpr_cookie', gs('site_name'), 43200);
    }

    public function cookiePolicy() {
        $cookieContent = Frontend::where('data_keys', 'cookie.data')->first();
        abort_if($cookieContent->data_values->status != Status::ENABLE, 404);
        $pageTitle = 'Cookie Policy';
        $cookie    = Frontend::where('data_keys', 'cookie.data')->first();
        return view('Template::cookie', compact('pageTitle', 'cookie'));
    }

    public function placeholderImage($size = null) {
        $imgWidth  = explode('x', $size)[0];
        $imgHeight = explode('x', $size)[1];
        $text      = $imgWidth . '×' . $imgHeight;
        $fontFile  = realpath('assets/font/solaimanLipi_bold.ttf');
        $fontSize  = round(($imgWidth - 50) / 8);
        if ($fontSize <= 9) {
            $fontSize = 9;
        }
        if ($imgHeight < 100 && $fontSize > 30) {
            $fontSize = 30;
        }

        $image     = imagecreatetruecolor($imgWidth, $imgHeight);
        $colorFill = imagecolorallocate($image, 100, 100, 100);
        $bgFill    = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgFill);
        $textBox    = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth  = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX      = ($imgWidth - $textWidth) / 2;
        $textY      = ($imgHeight + $textHeight) / 2;
        header('Content-Type: image/jpeg');
        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);
        imagejpeg($image);
        imagedestroy($image);
    }

    public function maintenance() {
        $pageTitle = 'Maintenance Mode';
        if (gs('maintenance_mode') == Status::DISABLE) {
            return to_route('home');
        }
        $maintenance = Frontend::where('data_keys', 'maintenance.data')->first();
        return view('Template::maintenance', compact('pageTitle', 'maintenance'));
    }

    public function subscribe(Request $request) {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        $emailExist = Subscriber::where('email', $request->email)->first();
        if (!$emailExist) {
            $subscribe        = new Subscriber();
            $subscribe->email = $request->email;
            $subscribe->save();
            return response()->json(['success' => 'Subscribed successfully']);
        } else {
            return response()->json(['error' => 'Already subscribed']);
        }
    }

    public function pwaConfiguration() {
        $gs   = gs();
        $json = [
            "name"             => $gs->site_name,
            "sign"             => $gs->site_name,
            "start_url"        => route('home'),
            "display"          => "standalone",
            "background_color" => "#0b0d1e",
            "theme_color"      => "#111429",
            "description"      => $gs->site_name . " PWA",
            "icons"            => [
                [
                    "src"   => getImage(getFilePath('logoIcon') . '/pwa_favicon.png'),
                    "sizes" => "192x192",
                    "type"  => "image/png",
                ],
                [
                    "src"   => getImage(getFilePath('logoIcon') . '/pwa_thumb.png'),
                    "sizes" => "512x512",
                    "type"  => "image/png",
                ],
            ],
        ];
        return response()->json($json);
    }
}
