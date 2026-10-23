@extends('Template::layouts.app')
@section('app')
<section class="wx-landing" id="hero">
    <header class="wx-landing__topbar">
        <a class="wx-landing__logo" href="{{ route('landing') }}" aria-label="{{ gs('site_name') }} home">
            <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
        </a>

        <nav class="wx-landing__nav" aria-label="Main navigation">
            <a href="{{ route('landing') }}"><i class="ph-fill ph-house"></i><span>@lang('Home')</span></a>
            <a href="{{ route('pages', 'about-us') }}"><i class="ph-fill ph-info"></i><span>@lang('About Us')</span></a>
            <a href="{{ route('arena.index') }}"><i class="ph-fill ph-fire"></i><span>@lang('Arena')</span></a>
            <a href="{{ route('blog') }}"><i class="ph-fill ph-newspaper-clipping"></i><span>@lang('Blogs')</span></a>

            <div class="wx-landing__account">
                <button type="button" class="wx-landing__account-btn" id="wx-account-toggle" aria-haspopup="true" aria-expanded="false" aria-label="@lang('Account menu')">
                    <i class="ph-fill ph-user"></i>
                </button>
                <div class="wx-landing__account-menu" id="wx-account-menu" hidden>
                    <a href="{{ route('user.login') }}"><i class="ph-bold ph-sign-in"></i>@lang('Sign In')</a>
                    <a href="{{ route('user.register') }}"><i class="ph-bold ph-user-plus"></i>@lang('Sign Up')</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="wx-landing__copy">
        <h1>@lang('Play, Compete and Connect')<br class="wx-landing__hl-break"> @lang('with Gamers Worldwide')</h1>
        <div class="wx-landing__cta">
            <a class="wx-landing__btn wx-landing__btn--primary" href="{{ route('user.login') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                @lang('Join Us')
            </a>
            <button type="button" id="hero-install-btn" class="wx-landing__btn wx-landing__btn--ghost">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3v12"/>
                    <path d="M7 10l5 5 5-5"/>
                    <path d="M4 21h16"/>
                </svg>
                @lang('Install App')
            </button>
        </div>
    </div>

    {{-- Same live endpoint/format as the "Winxpot Statistics" section on the About Us page
         (resources/views/templates/nocturne/sections/about_statistics.blade.php -> StatsController@live),
         so the numbers shown here always agree with it. --}}
    <div class="wx-landing__stats" id="wx-stats" aria-hidden="true">
        <div class="wx-landing__stat">
            <strong data-stat="online">0</strong>
            <span>@lang('Total Players Online')</span>
        </div>
        <span class="wx-landing__stat-sep"></span>
        <div class="wx-landing__stat">
            <strong data-stat="registered">0</strong>
            <span>@lang('Total Registered Players')</span>
        </div>
        <span class="wx-landing__stat-sep"></span>
        <div class="wx-landing__stat">
            <strong data-stat="games">0</strong>
            <span>@lang('Total Games Available')</span>
        </div>
    </div>

    <div class="wx-landing__map" id="wx-map" aria-hidden="true">
        <canvas id="wx-map-canvas"></canvas>
    </div>

    <footer class="wx-landing__footer">
        <div class="wx-landing__footer-links">
            <a href="{{ route('policy.pages', 'privacy-policy') }}"><i class="ph-fill ph-lock-key"></i>@lang('Privacy Policy')</a>
            <a href="{{ route('policy.pages', 'terms-of-service') }}"><i class="ph-fill ph-file-text"></i>@lang('Terms of Service')</a>
            <a href="{{ route('pages', 'licenses-security') }}"><i class="ph-fill ph-shield-check"></i>@lang('Licenses & Security')</a>
            <a href="{{ route('policy.pages', 'aml-policy') }}"><i class="ph-fill ph-eye"></i>@lang('AML Policy')</a>
        </div>
        <div class="wx-landing__footer-actions">
            <button type="button" class="wx-landing__hamburger" id="wx-menu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="@lang('Menu')">
                <span class="wx-landing__hamburger-bar"></span>
                <span class="wx-landing__hamburger-bar"></span>
            </button>
            @include('Template::partials.official_button')
        </div>
        <p class="wx-landing__copyright">&copy; {{ date('Y') }} Winxpot. @lang('All rights reserved.')</p>
    </footer>

    <div class="wx-landing__sheet" id="wx-menu-sheet" hidden>
        <button type="button" class="wx-landing__sheet-backdrop" data-sheet-close="wx-menu-sheet" aria-label="@lang('Close menu')"></button>
        <div class="wx-landing__sheet-panel" role="dialog" aria-modal="true" aria-label="@lang('Menu')">
            <button type="button" class="wx-landing__sheet-close" data-sheet-close="wx-menu-sheet" aria-label="@lang('Close')">&times;</button>
            <a href="{{ route('landing') }}"><i class="ph-fill ph-house"></i><span>@lang('Home')</span></a>
            <a href="{{ route('pages', 'about-us') }}"><i class="ph-fill ph-info"></i><span>@lang('About Us')</span></a>
            <a href="{{ route('arena.index') }}"><i class="ph-fill ph-fire"></i><span>@lang('Arena')</span></a>
            <a href="{{ route('blog') }}"><i class="ph-fill ph-newspaper-clipping"></i><span>@lang('Blogs')</span></a>
            <a href="{{ route('user.login') }}"><i class="ph-bold ph-sign-in"></i><span>@lang('Sign In')</span></a>
        </div>
    </div>
</section>

@push('style')
<style>
:root{--wxl-bg:#070a18;--wxl-ink:#f2f5ff;--wxl-mute:#8d95bd;--wxl-green:#00ee88;--wxl-green-d:#04301f}
/* html/body themselves have no height/overflow constraint sitewide (other pages need to scroll
   normally), so scope a hard lock to just this page via :has(#hero) rather than touching the
   shared stylesheet. This matters specifically on mobile Safari/Chrome: 100dvh on .wx-landing
   alone stops ITS OWN content from overflowing, but real mobile browsers can still show a tiny
   residual "rubber-band" bounce on the BODY itself (a dvh-unit/address-bar-collapse quirk) even
   when nothing is actually overflowing - locking html/body too, plus overscroll-behavior:none,
   removes that bounce entirely instead of relying on .wx-landing's own clip alone. */
html:has(#hero){height:100%;overflow:hidden}
body:has(#hero){height:100%;overflow:hidden;overscroll-behavior:none}
/* Hard-capped to exactly one viewport height (not min-height) on every breakpoint, not just
   mobile - a short/zoomed desktop or tablet window must fit in one screen too. overflow is
   hidden on both axes - a hard clip, not an auto-scrolling safety net - because an auto
   scrollbar on this element turns ANY descendant that happens to compute past its edge (the
   decorative ring was one) into a scrollable page; a hard clip guarantees it never scrolls,
   full stop. Top-anchored (flex-start), not centered - centering a variable-height block inside
   a fixed-height viewport looks fine on one aspect ratio and produces either a cramped top gap
   or a huge empty band on another, which is exactly the inconsistent spacing this replaces.
   Every gap below is now a small fixed value instead of a vh-based clamp, so the rhythm stays
   the same regardless of viewport shape; the footer alone keeps margin-top:auto so it - and only
   it - anchors to the bottom edge and absorbs whatever space is left over. */
.wx-landing{position:relative;height:100vh;height:100dvh;display:flex;flex-direction:column;justify-content:flex-start;overflow:hidden;overscroll-behavior:none;text-align:center;background:var(--wxl-bg) radial-gradient(70% 50% at 50% 100%,rgba(0,238,136,.1),transparent 70%),radial-gradient(60% 40% at 50% 0%,rgba(70,60,200,.16),transparent 70%);padding:6rem 0 1.5rem;margin:calc(-1 * clamp(0px,0px,0px))}
.wx-landing::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(0,238,136,.03) 0%,transparent 50%),radial-gradient(circle at 80% 70%,rgba(70,60,200,.05) 0%,transparent 60%);pointer-events:none;z-index:0}

.wx-landing__topbar{position:absolute;top:0;left:0;right:0;display:flex;justify-content:center;align-items:center;gap:2.5rem;padding:1.2rem 2rem;z-index:20;background:linear-gradient(to bottom,rgba(7,10,24,.85) 0%,rgba(7,10,24,.4) 70%,transparent 100%);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.wx-landing__logo{display:flex;align-items:center;text-decoration:none;transition:opacity .2s;flex-shrink:0}
.wx-landing__logo:hover{opacity:.85}
.wx-landing__logo img{display:block;max-width:168px;max-height:44px;object-fit:contain}

.wx-landing__nav{display:flex;align-items:center;gap:2rem}
.wx-landing__nav>a{display:inline-flex;align-items:center;gap:7px;color:var(--wxl-ink);text-decoration:none;font-size:1rem;font-weight:600;letter-spacing:.01em;transition:color .2s,text-shadow .2s;position:relative;padding:.3rem 0}
.wx-landing__nav>a i{color:var(--wxl-green);font-size:17px}
.wx-landing__nav>a::after{content:'';position:absolute;bottom:-2px;left:0;width:0;height:2px;background:var(--wxl-green);border-radius:2px;transition:width .25s ease}
.wx-landing__nav>a:hover{color:var(--wxl-green);text-shadow:0 0 20px rgba(0,238,136,.3)}
.wx-landing__nav>a:hover::after{width:100%}

.wx-landing__account{position:relative}
.wx-landing__account-btn{display:grid;place-items:center;width:40px;height:40px;border:1px solid rgba(255,255,255,.18);border-radius:50%;background:linear-gradient(145deg,rgba(0,238,136,.18),rgba(255,255,255,.04));color:var(--wxl-ink);font-size:19px;cursor:pointer;transition:border-color .2s,background .2s,color .2s,box-shadow .2s}
.wx-landing__account-btn:hover,.wx-landing__account-btn[aria-expanded="true"]{border-color:rgba(0,238,136,.6);color:var(--wxl-green);background:linear-gradient(145deg,rgba(0,238,136,.3),rgba(255,255,255,.06));box-shadow:0 0 0 4px rgba(0,238,136,.12)}
.wx-landing__account-menu{position:absolute;top:calc(100% + 10px);right:0;z-index:30;display:grid;gap:2px;width:170px;padding:7px;border:1px solid rgba(255,255,255,.14);border-radius:16px;background:#10142a;box-shadow:0 20px 50px rgba(0,0,0,.5)}
.wx-landing__account-menu a{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:11px;color:var(--wxl-ink);text-decoration:none;font-size:13.5px;font-weight:700}
.wx-landing__account-menu a i{color:var(--wxl-green);font-size:16px}
.wx-landing__account-menu a:hover{background:rgba(0,238,136,.1);color:var(--wxl-green)}

/* The circular clip lives on this inner wrapper, not the avatar element itself - a status dot
   positioned on the OUTER element can then sit right on (or just outside) the circle's edge
   without being clipped away, which a percentage inset inside an overflow:hidden circle can't
   do (a square corner inset still falls outside the inscribed circle and gets cut off). */
.wx-landing__avatar-clip{display:block;width:100%;height:100%;border-radius:50%;overflow:hidden;border:4px solid var(--wxl-bg);box-shadow:0 0 0 1px rgba(255,255,255,.22),0 0 28px rgba(0,238,136,.25)}
.wx-landing__avatar-clip img{display:block;width:100%;height:100%;object-fit:cover}

/* Small "online" status dot on each avatar (ring + map clusters) - sits on the top-right edge,
   outside the circular clip, alternating green/white per instance and gently blinking. */
.wx-landing__dot{position:absolute;top:-2px;right:-2px;width:13px;height:13px;border-radius:50%;border:2px solid var(--wxl-bg);z-index:7;animation:wxlDotBlink 1.8s ease-in-out infinite}
.wx-landing__cl .wx-landing__dot{width:8px;height:8px;top:-1.5px;right:-1.5px;border-width:1.5px}
.wx-landing__dot--green{background:var(--wxl-green);box-shadow:0 0 6px rgba(0,238,136,.7)}
.wx-landing__dot--white{background:#fff;box-shadow:0 0 6px rgba(255,255,255,.7)}
@keyframes wxlDotBlink{0%,100%{opacity:1}50%{opacity:.3}}

.wx-landing__copy{position:relative;z-index:10;flex:none;padding:0 20px;margin-top:.75rem}
.wx-landing__copy h1{margin:0 auto;max-width:19em;text-wrap:balance;font-size:clamp(22px,min(4.2vw,6.2vh),58px);line-height:1.12;font-weight:700;letter-spacing:-.025em;background:linear-gradient(135deg,#f2f5ff 0%,#c8d0f0 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-shadow:0 2px 30px rgba(0,238,136,.08)}

.wx-landing__cta{margin-top:1.4rem;display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.wx-landing__btn{display:inline-flex;align-items:center;gap:9px;height:44px;padding:0 22px 0 20px;border:0;border-radius:99px;text-decoration:none;font:700 13.5px/1 "Montserrat",system-ui,sans-serif;letter-spacing:.01em;cursor:pointer;transition:transform .18s ease,box-shadow .25s ease,background .25s ease;position:relative;overflow:hidden}
.wx-landing__btn svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none;transition:transform .2s}
.wx-landing__btn:hover svg{transform:scale(1.08)}
.wx-landing__btn:focus-visible{outline:3px solid #fff;outline-offset:3px}
.wx-landing__btn--primary{background:var(--wxl-green);color:var(--wxl-green-d);box-shadow:0 10px 30px rgba(0,238,136,.28),0 0 0 1px rgba(0,238,136,.15)}
.wx-landing__btn--primary:hover{transform:translateY(-3px) scale(1.02);box-shadow:0 0 0 8px rgba(0,238,136,.12),0 18px 44px rgba(0,238,136,.45)}
.wx-landing__btn--ghost{background:rgba(255,255,255,.06);color:var(--wxl-ink);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.wx-landing__btn--ghost svg{color:var(--wxl-green)}
.wx-landing__btn--ghost:hover{background:rgba(255,255,255,.12);transform:translateY(-3px) scale(1.02);border-color:rgba(0,238,136,.35);box-shadow:0 0 0 5px rgba(0,238,136,.08),0 14px 36px rgba(0,0,0,.4)}

/* Live counters, same endpoint/format as the About Us "Winxpot Statistics" section (see the
   script block below) - plain inline numbers, no card/background, sized to its own content
   (align-self:center overrides the section's default flex stretch, which was otherwise pulling
   this to the full section width edge-to-edge). */
.wx-landing__stats{position:relative;z-index:10;display:flex;align-self:center;align-items:center;justify-content:center;flex-wrap:nowrap;gap:28px;margin-top:1.5rem;max-width:100%}
.wx-landing__stat{display:flex;flex-direction:column;align-items:center;gap:2px;min-width:0}
.wx-landing__stat strong{font-size:clamp(16px,2vw,22px);font-weight:800;letter-spacing:-.01em;line-height:1.15;background:linear-gradient(135deg,#eafff5,var(--wxl-green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap}
.wx-landing__stat span{font-size:10.5px;font-weight:700;letter-spacing:.02em;line-height:1.3;color:var(--wxl-mute);white-space:nowrap}
.wx-landing__stat-sep{width:1px;height:26px;align-self:center;background:rgba(255,255,255,.16);flex:none}

.wx-landing__map{position:relative;flex:none;left:50%;transform:translateX(-50%);margin-top:1.1rem;margin-bottom:-2vh;z-index:2}
.wx-landing__map canvas{display:block;-webkit-mask-image:radial-gradient(ellipse at 50% 58%,#000 52%,transparent 100%);mask-image:radial-gradient(ellipse at 50% 58%,#000 52%,transparent 100%)}
.wx-landing__cl{position:absolute;display:flex;transform:translate(-50%,-50%);--a:clamp(24px,2.7vw,42px);z-index:6}
.wx-landing__cl i{position:relative;display:block;width:var(--a);height:var(--a);margin-left:calc(var(--a) * -0.3);transition:transform .2s;cursor:pointer}
.wx-landing__cl i .wx-landing__avatar-clip{border-width:2px;box-shadow:0 0 0 1px rgba(255,255,255,.18);transition:box-shadow .2s}
.wx-landing__cl i:hover{transform:scale(1.15);z-index:10}
.wx-landing__cl i:hover .wx-landing__avatar-clip{box-shadow:0 0 0 2px var(--wxl-green),0 0 24px rgba(0,238,136,.4)}
.wx-landing__cl i:first-child{margin-left:0}
.wx-landing__tag{position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);padding:5px 11px;border-radius:999px;background:#10142a;border:1px solid rgba(0,238,136,.4);color:var(--wxl-green);font-size:11px;font-weight:800;white-space:nowrap;box-shadow:0 10px 24px rgba(0,0,0,.4);pointer-events:none;opacity:0;transition:opacity .15s ease}
.wx-landing__tag.is-visible{opacity:1}

/* The section is top-anchored (flex-start) now, not centered, so margin-top:auto here does
   exactly one predictable thing: pushes the footer down to the bottom edge and soaks up
   whatever space is left, while everything above it stays tightly packed near the top with its
   own small fixed gaps. */
.wx-landing__footer{position:relative;z-index:20;padding:0 1rem .4rem;margin-top:auto}
.wx-landing__footer-links{display:flex;justify-content:center;gap:1.5rem;flex-wrap:wrap;margin-bottom:8px}
.wx-landing__footer-links a{display:inline-flex;align-items:center;gap:5px;color:var(--wxl-mute);text-decoration:none;font-size:.75rem;font-weight:600;letter-spacing:.02em;transition:color .2s,text-shadow .2s}
.wx-landing__footer-links a i{font-size:11.5px;color:rgba(141,149,189,.7);transition:color .2s}
.wx-landing__footer-links a:hover{color:var(--wxl-green);text-shadow:0 0 20px rgba(0,238,136,.35)}
.wx-landing__footer-links a:hover i{color:var(--wxl-green)}
.wx-landing__copyright{margin:8px 0 0;color:rgba(141,149,189,.6);font-size:.65rem;font-weight:600;letter-spacing:.03em}

/* Forces exactly two lines on mobile (a real device's width is too variable for natural
   wrapping to reliably land on 2 lines on its own) while leaving the desktop headline flowing
   as one continuous phrase - the <br> is just not rendered there. */
.wx-landing__hl-break{display:none}

/* Mobile-only: nav moves out of the topbar entirely (just the logo stays up top) and into a
   hamburger + Official pairing in the footer, opening a bottom sheet instead. Desktop keeps
   the ordinary top nav, so this row stays hidden there. */
.wx-landing__footer-actions{display:none;align-items:center;justify-content:center;gap:12px;margin-bottom:12px}
/* The current modern take on a hamburger: two short, asymmetric bars (not three stacked full-
   width lines) that resolve into an X when the sheet opens. */
.wx-landing__hamburger{display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:5px;width:46px;height:46px;padding:0 0 0 14px;border:1px solid rgba(255,255,255,.18);border-radius:50%;background:rgba(255,255,255,.06);cursor:pointer;transition:border-color .2s,background .2s}
.wx-landing__hamburger:hover,.wx-landing__hamburger[aria-expanded="true"]{border-color:rgba(0,238,136,.5);background:rgba(0,238,136,.1)}
.wx-landing__hamburger-bar{display:block;height:2px;border-radius:2px;background:var(--wxl-ink);transition:transform .25s ease,width .25s ease,opacity .2s ease}
.wx-landing__hamburger-bar:first-child{width:18px}
.wx-landing__hamburger-bar:last-child{width:12px}
.wx-landing__hamburger[aria-expanded="true"] .wx-landing__hamburger-bar{width:16px}
.wx-landing__hamburger[aria-expanded="true"] .wx-landing__hamburger-bar:first-child{transform:translateY(3.5px) rotate(45deg)}
.wx-landing__hamburger[aria-expanded="true"] .wx-landing__hamburger-bar:last-child{transform:translateY(-3.5px) rotate(-45deg)}

.wx-landing__sheet{position:fixed;inset:0;z-index:1300;display:flex;align-items:flex-end;justify-content:center}
.wx-landing__sheet-backdrop{position:absolute;inset:0;border:0;background:rgba(5,8,18,.78);backdrop-filter:blur(6px);cursor:pointer}
.wx-landing__sheet-panel{position:relative;z-index:1;width:100%;max-width:440px;padding:24px 20px;border-radius:26px 26px 0 0;background:linear-gradient(165deg,#171d37,#0e1226);border:1px solid rgba(255,255,255,.1);box-shadow:0 -20px 60px rgba(0,0,0,.5);display:grid;gap:4px}
.wx-landing__sheet-close{position:absolute;top:12px;right:14px;display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:50%;background:rgba(255,255,255,.08);color:var(--wxl-ink);font-size:18px;cursor:pointer}
.wx-landing__sheet-panel a{display:flex;align-items:center;gap:12px;padding:14px 12px;border-radius:14px;color:var(--wxl-ink);text-decoration:none;font-size:15px;font-weight:700}
.wx-landing__sheet-panel a:hover,.wx-landing__sheet-panel a:active{background:rgba(0,238,136,.1);color:var(--wxl-green)}
.wx-landing__sheet-panel a i{color:var(--wxl-green);font-size:19px;width:22px;text-align:center}

@media(max-width:640px){
    /* Height/overflow are inherited from the base rule above. The topbar stays absolutely
       pinned to the very top so the logo and account icon sit fixed at the corners; this
       padding-top just clears room below it. Unlike desktop, the content group itself
       (.wx-landing__copy down to .wx-landing__map) gets margin-top:auto AND .wx-landing__map
       gets margin-bottom:auto - two equal auto-margin slots, so whatever vertical space is left
       over splits evenly between "gap above the content" and "gap below it, before the footer"
       instead of all piling into just one of those gaps. The footer itself switches to a small
       fixed margin-top here (not the base rule's auto) so it isn't a third competing auto slot. */
    .wx-landing{padding-top:4.3rem;padding-bottom:.6rem}
    /* Logo pinned top-left, account icon (Sign In/Up dropdown) pinned top-right - the ordinary
       nav links (Home/Arena/Blogs) move to the footer hamburger sheet instead so the topbar
       doesn't compete with the headline for room right below it. */
    .wx-landing__topbar{justify-content:space-between;padding:.9rem 1rem}
    .wx-landing__logo{position:relative;padding:4px 10px}
    .wx-landing__logo::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at center,rgba(0,238,136,.16),transparent 72%);border-radius:999px;z-index:-1}
    .wx-landing__logo img{max-width:106px;max-height:28px}
    .wx-landing__nav{gap:0}
    .wx-landing__nav>a{display:none}
    .wx-landing__account-btn{width:36px;height:36px;font-size:17px}
    .wx-landing__footer-actions{display:flex;margin-bottom:8px}
    .wx-landing__copy{margin-top:auto;padding:0 16px}
    .wx-landing__hl-break{display:block}
    /* Sized so "Play, Compete and Connect" (the longer of the two forced lines) reliably fits
       on one line down to ~360px-wide phones, rather than wrapping a third time on its own. */
    .wx-landing__copy h1{font-size:clamp(18px,5.3vw,22px);line-height:1.22;max-width:none}
    /* Side by side, native-app style, not stacked full-width - each button shrinks to fit
       rather than forcing a tall vertical stack of two giant pills. */
    .wx-landing__cta{flex-direction:row;flex-wrap:nowrap;align-items:stretch;gap:10px;margin-top:1rem;width:100%;padding:0 16px}
    .wx-landing__btn{flex:1;min-width:0;width:auto;max-width:none;justify-content:center;height:40px;padding:0 8px;font-size:11px;gap:5px}
    .wx-landing__btn svg{width:15px;height:15px;flex-shrink:0}
    /* Stays one inline row even on narrow phones - labels are allowed to wrap to two short
       lines rather than forcing the three columns themselves to stack. */
    .wx-landing__stats{gap:16px;margin-top:.85rem}
    .wx-landing__stat strong{font-size:13.5px}
    .wx-landing__stat span{font-size:7.6px;white-space:normal;line-height:1.15;max-width:72px}
    .wx-landing__stat-sep{height:20px}
    .wx-landing__map{margin-top:1.2rem;margin-bottom:auto}
    .wx-landing__cl{--a:24px}
    .wx-landing__cl i .wx-landing__avatar-clip{border-width:1.5px}
    /* Per request: footer navigation links are desktop/tablet only - just the copyright shows
       on mobile, so the already-vertical hero/CTA/map stack doesn't need yet another row. A
       small fixed margin-top (not the base rule's auto) - .wx-landing__copy/.wx-landing__map
       already claim the auto-margin slots above, so the footer sits right after that bottom gap
       instead of adding a third competing auto slot. */
    .wx-landing__footer-links{display:none}
    .wx-landing__footer{padding:0 1rem;margin-top:.8rem}
    .wx-landing__copyright{font-size:.65rem;margin-top:6px}
}
@media(min-width:641px) and (max-width:1024px){
    .wx-landing__topbar{gap:1.8rem;padding:1rem 1.5rem}
    .wx-landing__nav{gap:1.5rem}
}
@media(max-height:720px) and (min-width:641px){
    /* Still top-anchored (flex-start, inherited) - just a touch less top padding on a short
       desktop/tablet window, kept no lower than the topbar's own height so nothing overlaps it. */
    .wx-landing{padding-top:5.2rem;padding-bottom:1rem}
}
@media(prefers-reduced-motion:reduce){
    .wx-landing__btn,.wx-landing__btn:hover,.wx-landing__cl i:hover{transition:none;transform:none}
}
</style>
@endpush

@push('script')
<script>
(() => {
    'use strict';
    var COMMUNITY = @json($communityUsers->values()->map(fn($u) => ['username' => $u->username, 'avatar' => $u->avatarUrl()]));
    if (!COMMUNITY.length) COMMUNITY = [{username: 'winxpot', avatar: null}];

    // Every user the controller sends has a real image (query filters on it) - this initial-
    // letter fallback only matters in the near-impossible case of zero qualifying users at all.
    function avatarLetter(u) {
        return ((u && u.username ? u.username : 'W').charAt(0) || 'W').toUpperCase();
    }
    function letterFallbackHtml(letter) {
        return '<span style="display:grid;place-items:center;width:100%;height:100%;background:#1b2340;color:#00ee88;font-weight:800;font-size:60%">' + letter + '</span>';
    }
    // If an avatar URL 404s or otherwise fails to load (stale path, deleted file), swap that one
    // tile to the same initial-letter badge instead of leaving a broken-image icon on screen.
    // A named global (not inline-HTML-string injection into onerror) avoids any quote-escaping
    // fragility between this attribute and the fallback markup's own attributes.
    window.__wxAvatarFallback = function (img, letter) { img.outerHTML = letterFallbackHtml(letter); };
    // A sibling dot, not a child of the <img> - the outerHTML swap above only touches the <img>
    // tag itself, so this is untouched either way.
    function statusDotHtml() {
        var color = Math.random() < 0.5 ? 'green' : 'white';
        return '<i class="wx-landing__dot wx-landing__dot--' + color + '" aria-hidden="true"></i>';
    }
    function avatarMarkup(i) {
        var u = COMMUNITY[i % COMMUNITY.length];
        var dot = statusDotHtml();
        var inner = (u && u.avatar)
            ? '<img src="' + u.avatar + '" alt="" loading="eager" onerror="window.__wxAvatarFallback(this,\'' + avatarLetter(u) + '\')">'
            : letterFallbackHtml(avatarLetter(u));
        // The dot is a sibling of the clip span, not inside it, so the circular overflow:hidden
        // clip on the span can't cut it off.
        return '<span class="wx-landing__avatar-clip">' + inner + '</span>' + dot;
    }
    function usernameFor(i) {
        var u = COMMUNITY[i % COMMUNITY.length];
        return u ? u.username : null;
    }

    var tag = document.createElement('span');
    tag.className = 'wx-landing__tag';
    document.body.appendChild(tag);
    var tagTimer = null;
    function revealUsername(target, username) {
        if (!username) return;
        var rect = target.getBoundingClientRect();
        tag.textContent = '@' + username;
        tag.style.position = 'fixed';
        tag.style.left = (rect.left + rect.width / 2) + 'px';
        tag.style.top = rect.top + 'px';
        tag.style.transform = 'translate(-50%, calc(-100% - 8px))';
        tag.classList.add('is-visible');
        clearTimeout(tagTimer);
        tagTimer = setTimeout(function () { tag.classList.remove('is-visible'); }, 2200);
    }
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.wx-landing__cl i')) tag.classList.remove('is-visible');
    });

    // ── dotted world map (continent outlines rasterized to a dot grid) ──
    var P = [
        [[-168,66],[-141,70],[-110,72],[-95,72],[-82,66],[-94,59],[-80,52],[-78,60],[-65,60],[-56,52],[-66,45],[-76,36],[-81,30],[-80,25],[-90,29],[-97,26],[-97,19],[-88,21],[-88,15],[-83,10],[-78,8],[-86,12],[-96,16],[-106,22],[-112,29],[-117,33],[-124,40],[-124,48],[-135,57],[-150,60],[-160,56],[-165,62]],
        [[-78,8],[-62,10],[-50,0],[-35,-6],[-40,-20],[-48,-26],[-58,-38],[-66,-46],[-69,-54],[-75,-48],[-72,-30],[-71,-18],[-81,-6],[-80,0]],
        [[-55,60],[-44,60],[-20,70],[-20,80],[-45,83],[-65,80],[-58,70]],
        [[-9,37],[-9,43],[-2,44],[-4,48],[3,51],[8,54],[10,58],[5,62],[14,68],[26,71],[44,68],[60,69],[73,73],[105,78],[130,72],[160,70],[180,68],[180,65],[163,60],[156,51],[142,59],[136,54],[140,48],[130,42],[129,37],[126,35],[122,40],[119,36],[122,30],[117,23],[108,21],[109,12],[105,9],[100,13],[100,7],[103,1],[98,8],[97,17],[91,22],[85,19],[80,10],[77,8],[73,17],[69,22],[62,25],[57,26],[50,30],[51,24],[56,26],[59,22],[52,16],[43,13],[35,28],[34,31],[36,36],[28,37],[26,40],[23,38],[19,42],[13,45],[16,38],[9,44],[3,43],[0,39],[-5,36]],
        [[-17,21],[-10,30],[-6,36],[10,37],[11,33],[20,31],[32,31],[34,28],[43,12],[51,12],[40,-3],[40,-15],[35,-25],[27,-34],[18,-34],[12,-17],[9,-1],[9,4],[-8,4],[-17,14]],
        [[114,-22],[122,-18],[130,-12],[137,-12],[142,-11],[146,-19],[153,-26],[150,-37],[141,-38],[135,-34],[115,-34]],
        [[109,1],[117,7],[119,1],[116,-4],[110,-3]],
        [[95,5],[98,4],[106,-6],[104,-5]],
        [[130,31],[140,35],[142,40],[145,44],[141,45],[139,40],[135,35]],
        [[-5,50],[2,51],[0,54],[-3,58],[-6,57],[-3,54]],
        [[44,-25],[47,-25],[50,-15],[49,-12],[44,-17]]
    ];
    var LT = 78, LB = -56, ST = 3, m = document.createElement('canvas');
    m.width = 360; m.height = 180;
    var g = m.getContext('2d');
    P.forEach(function (p) {
        g.beginPath();
        p.forEach(function (q, i) {
            var x = q[0] + 180, y = 90 - q[1];
            i ? g.lineTo(x, y) : g.moveTo(x, y);
        });
        g.closePath(); g.fill();
    });
    var D = g.getImageData(0, 0, 360, 180).data, dots = [];
    for (var lat = LT, r = 0; lat >= LB; lat -= ST, r++) {
        for (var lon = -180 + (r % 2 ? ST / 2 : 0); lon < 180; lon += ST) {
            var x = Math.min(359, Math.floor(lon + 180)), y = Math.min(179, Math.floor(90 - lat));
            if (D[(y * 360 + x) * 4 + 3] > 128) dots.push([lon, lat]);
        }
    }

    // ── community clusters: [lon, lat, [community-avatar indices]] ──
    var CL = [
        [-100, 46, [0, 2]],
        [18, 6, [3, 5]],
        [75, 57, [4, 7, 8]],
        [-58, -12, [9, 1]],
        [134, -25, [6]],
        [10, 50, [2, 0]]
    ];

    var map = document.getElementById('wx-map'), cv = document.getElementById('wx-map-canvas'),
        cx = cv.getContext('2d'), els = [];

    CL.forEach(function (c) {
        var e = document.createElement('div');
        e.className = 'wx-landing__cl';
        c[2].forEach(function (i) {
            var avatarEl = document.createElement('i');
            avatarEl.innerHTML = avatarMarkup(i);
            var name = usernameFor(i);
            avatarEl.addEventListener('click', function (ev) { ev.stopPropagation(); revealUsername(avatarEl, name); });
            e.appendChild(avatarEl);
        });
        map.appendChild(e);
        els.push(e);
    });

    function pj(lon, lat, W, H) {
        var u = (lon + 180) / 360, v = (LT - lat) / (LT - LB), s = 0.78 + 0.22 * v;
        return [W * (0.5 + (u - 0.5) * s), H * (0.03 + 0.94 * v), s];
    }

    function draw() {
        var vw = innerWidth, vh = innerHeight, mobile = vw <= 640,
            // Mobile still needs a smaller share of the screen than desktop so the whole page
            // (logo, headline, buttons, map, footer) fits in one view without scrolling, but a
            // little bigger than before now that the gap above it has been tightened up.
            H = mobile
                ? Math.max(Math.min(vh * 0.24, vw / 2.3), vh * 0.18)
                : Math.max(Math.min(vh * 0.46, vw / 2.9), vh * 0.3),
            W = H * 2.9,
            k = Math.min(devicePixelRatio || 1, 2);
        map.style.width = W + 'px'; map.style.height = H + 'px';
        cv.width = W * k; cv.height = H * k;
        cv.style.width = W + 'px'; cv.style.height = H + 'px';
        cx.setTransform(k, 0, 0, k, 0, 0);
        cx.clearRect(0, 0, W, H);
        cx.shadowColor = 'rgba(0, 238, 136, 0.15)';
        cx.shadowBlur = 6;
        cx.fillStyle = 'rgba(165, 175, 225, 0.6)';
        dots.forEach(function (d) {
            var p = pj(d[0], d[1], W, H);
            cx.beginPath();
            cx.arc(p[0], p[1], W / 360 * ST * 0.33 * p[2], 0, 6.283);
            cx.fill();
        });
        cx.shadowBlur = 0; cx.shadowColor = 'transparent';
        CL.forEach(function (c, i) {
            var p = pj(c[0], c[1], W, H);
            els[i].style.left = p[0] + 'px';
            els[i].style.top = p[1] + 'px';
        });
    }
    draw();
    addEventListener('resize', draw);

    // ── live stats strip (same endpoint/format as the About Us "Winxpot Statistics" section,
    //    resources/views/templates/nocturne/sections/about_statistics.blade.php) ──
    (function () {
        var nodes = document.querySelectorAll('[data-stat]');
        if (!nodes.length) return;
        function trimZeros(str) {
            return str.indexOf('.') === -1 ? str : str.replace(/0+$/, '').replace(/\.$/, '');
        }
        function compact(value) {
            value = Number(value);
            if (value >= 1e6) return trimZeros((value / 1e6).toFixed(1)) + 'M';
            if (value >= 1e3) return trimZeros((value / 1e3).toFixed(1)) + 'K';
            return value.toLocaleString('en-US');
        }
        function formatStat(key, value) {
            var text = key === 'online' ? Math.round(value).toLocaleString('en-US') : compact(value);
            return text + (key !== 'games' ? '+' : '');
        }
        // Fast eased count-up (not a slow 1-by-1 tick) from 0 to the real fetched value.
        function countUp(node, key, target) {
            var duration = 900, start = null;
            function tick(now) {
                if (start === null) start = now;
                var p = Math.min((now - start) / duration, 1), eased = 1 - Math.pow(1 - p, 3);
                node.textContent = formatStat(key, target * eased);
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }
        fetch('{{ route('stats.live') }}', { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data) return;
                nodes.forEach(function (node) {
                    var key = node.dataset.stat;
                    if (data[key] === undefined) return;
                    countUp(node, key, Number(data[key]));
                });
            })
            .catch(function () {});
    })();

    // ── account dropdown (Sign In / Sign Up) ──
    var toggle = document.getElementById('wx-account-toggle'), menu = document.getElementById('wx-account-menu');
    toggle?.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = !menu.hidden;
        menu.hidden = open;
        toggle.setAttribute('aria-expanded', String(!open));
    });
    document.addEventListener('click', function (e) {
        if (!menu.hidden && !e.target.closest('.wx-landing__account')) {
            menu.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    // ── mobile footer hamburger menu sheet ──
    var menuToggle = document.getElementById('wx-menu-toggle'), menuSheet = document.getElementById('wx-menu-sheet');
    function closeMenuSheet() {
        if (!menuSheet) return;
        menuSheet.hidden = true;
        menuToggle?.setAttribute('aria-expanded', 'false');
    }
    menuToggle?.addEventListener('click', function () {
        if (!menuSheet) return;
        var open = !menuSheet.hidden;
        menuSheet.hidden = open;
        menuToggle.setAttribute('aria-expanded', String(!open));
    });
    menuSheet?.querySelectorAll('[data-sheet-close]').forEach(function (btn) {
        btn.addEventListener('click', closeMenuSheet);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenuSheet();
    });
})();
</script>
@endpush
@endsection
