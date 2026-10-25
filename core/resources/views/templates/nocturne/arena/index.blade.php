@extends('Template::layouts.frontend')
@section('content')
    <div class="nocturne-arena-page">
        <div class="nocturne-arena-crumb-row">
            <div class="nocturne-arena-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Arena')</span></div>
            <div class="nocturne-arena-crumb-tools">
                @auth
                    <div class="nocturne-arena-bell-wrap">
                        <button type="button" class="nocturne-arena-bell" id="arena-bell"><i class="ph-fill ph-bell"></i><span class="nocturne-arena-bell__count" id="arena-bell-count" hidden>0</span></button>
                        <div class="nocturne-arena-bell-panel" id="arena-bell-panel" hidden>
                            <div class="nocturne-arena-bell-panel__head"><strong>@lang('Notifications')</strong><button type="button" id="arena-bell-markread">@lang('Mark all read')</button></div>
                            <div class="nocturne-arena-bell-panel__list" id="arena-bell-list"><p class="nocturne-arena-notif-empty">@lang('Loading…')</p></div>
                        </div>
                    </div>
                @endauth
                <button type="button" class="nocturne-arena-bell" id="arena-rail-open" aria-label="@lang('Arena menu')"><i class="ph-fill ph-list"></i></button>
            </div>
        </div>

        <div class="nocturne-arena-layout">
            <div class="nocturne-arena-feed">
                @if($heroSlides->isNotEmpty())
                    <div class="nocturne-arena-carousel" id="arena-carousel" data-interval="7000">
                        <div class="nocturne-arena-carousel__track">
                            @foreach($heroSlides as $index => $slide)
                                <div class="nocturne-arena-announcement nocturne-arena-slide{{ $index === 0 ? ' is-active' : '' }}" data-slide="{{ $index }}">
                                    @if($slide->image)<img class="nocturne-arena-announcement__bg" src="{{ $slide->image }}" alt="" loading="{{ $index === 0 ? 'eager' : 'lazy' }}"><div class="nocturne-arena-announcement__shade"></div>@endif
                                    <div class="nocturne-arena-announcement__content">
                                        @if($slide->badge_label)<span class="nocturne-arena-announcement__badge nocturne-arena-announcement__badge--{{ $slide->type }}">{{ __($slide->badge_label) }}</span>@endif
                                        <h2>{{ __($slide->title) }}</h2>
                                        @if($slide->countdown_end)
                                            <div class="nocturne-arena-announcement__timer" data-countdown-end="{{ $slide->countdown_end * 1000 }}" data-countdown-label="{{ __($slide->countdown_label) }}">
                                                <div class="nocturne-arena-timer-unit"><strong data-unit="d">00</strong><small><i class="ph-bold ph-calendar"></i>@lang('Days')</small></div>
                                                <div class="nocturne-arena-timer-unit"><strong data-unit="h">00</strong><small><i class="ph-bold ph-clock"></i>@lang('Hrs')</small></div>
                                                <div class="nocturne-arena-timer-unit"><strong data-unit="m">00</strong><small><i class="ph-bold ph-hourglass"></i>@lang('Mins')</small></div>
                                                <div class="nocturne-arena-timer-unit"><strong data-unit="s">00</strong><small><i class="ph-bold ph-timer"></i>@lang('Sec')</small></div>
                                            </div>
                                        @endif
                                        <div class="nocturne-arena-announcement__foot">
                                            @if($slide->reward_label)<span class="nocturne-arena-announcement__reward"><i class="ph-fill ph-trophy"></i>{{ __($slide->reward_label) }}</span>@endif
                                            @if($slide->button_label && $slide->button_url)<a href="{{ $slide->button_url }}" class="nocturne-arena-announcement__btn">{{ __($slide->button_label) }}<i class="ph-bold ph-arrow-right"></i></a>@endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($heroSlides->count() > 1)
                            <button type="button" class="nocturne-arena-carousel__nav nocturne-arena-carousel__nav--prev" id="arena-carousel-prev" aria-label="@lang('Previous')"><i class="ph-bold ph-caret-left"></i></button>
                            <button type="button" class="nocturne-arena-carousel__nav nocturne-arena-carousel__nav--next" id="arena-carousel-next" aria-label="@lang('Next')"><i class="ph-bold ph-caret-right"></i></button>
                            <div class="nocturne-arena-carousel__dots" id="arena-carousel-dots">
                                @foreach($heroSlides as $index => $slide)
                                    <button type="button" class="nocturne-arena-carousel__dot{{ $index === 0 ? ' is-active' : '' }}" data-dot="{{ $index }}" aria-label="@lang('Go to slide :n', ['n' => $index + 1])"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @auth
                    @php
                        $composerCategories = ['general' => ['General','ph-chat-circle-text'],'win' => ['Win','ph-trophy'],'challenge' => ['Challenge','ph-sword'],'question' => ['Question','ph-question']];
                    @endphp
                    <form method="post" action="{{ route('user.arena.store') }}" enctype="multipart/form-data" class="pc-composer" id="arena-composer-form">
                        @csrf
                        <div class="pc-composer__top">
                            <span class="pc-composer__avatar">
                                @if(auth()->user()->avatarUrl())
                                    <img src="{{ auth()->user()->avatarUrl() }}" alt="" onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'ph-fill ph-user-circle'}))">
                                @else
                                    <i class="ph-fill ph-user-circle"></i>
                                @endif
                            </span>

                            <div class="pc-composer__field" id="pc-field">
                                <label class="sr-only" for="arena-composer-body">@lang('Share something')</label>
                                <textarea name="body" rows="1" maxlength="2000" placeholder="@lang('Share a win, ask for a competition, or say something...')" id="arena-composer-body">{{ old('body') }}</textarea>
                                <button type="button" class="pc-icon-btn" id="pc-emoji-toggle" aria-label="@lang('Add emoji')" aria-haspopup="true" aria-expanded="false">
                                    <i class="ph-bold ph-smiley"></i>
                                </button>
                            </div>
                        </div>

                        <div class="pc-emoji-pop" id="pc-emoji-pop" role="menu" aria-label="@lang('Emoji')">
                            @foreach(['😊','😂','😍','🔥','👏','🎉','👍','🙌','💡','🚀','❤️','🏆'] as $emoji)
                                <button type="button" role="menuitem">{{ $emoji }}</button>
                            @endforeach
                        </div>

                        <div class="pc-composer__preview" id="arena-image-preview" hidden><img src="" alt=""><button type="button" id="arena-image-remove" aria-label="@lang('Remove image')"><i class="ph-bold ph-x"></i></button></div>
                        <div class="pc-composer__preview" id="arena-video-preview" hidden><video src="" controls muted></video><button type="button" id="arena-video-remove" aria-label="@lang('Remove video')"><i class="ph-bold ph-x"></i></button></div>
                        <div class="pc-chips" id="pc-chips"></div>

                        <div class="pc-composer__bar">
                            <div class="pc-dd" id="pc-color-dd">
                                <button type="button" class="pc-round-btn" id="pc-color-btn" aria-label="@lang('Post color')" aria-haspopup="true" aria-expanded="false">
                                    <i class="ph-fill ph-palette pc-palette-icon"></i>
                                    <span class="pc-color-dot"></span>
                                </button>
                                @php
                                    $swatchColors = [
                                        'violet' => ['#7c3aed', '#4c1d95'],
                                        'sunset' => ['#f97316', '#be123c'],
                                        'forest' => ['#00e78a', '#065f46'],
                                        'ocean' => ['#0ea5e9', '#1e3a8a'],
                                        'rose' => ['#f472b6', '#9d174d'],
                                    ];
                                @endphp
                                <div class="pc-menu" id="pc-color-menu" role="radiogroup" aria-label="@lang('Post color')">
                                    <div class="pc-swatches">
                                        <button type="button" class="pc-swatch pc-swatch--none" role="radio" aria-checked="true" data-swatch="" aria-label="@lang('No color')"><i class="ph-bold ph-x"></i></button>
                                        @foreach($swatchColors as $swatch => [$c1, $c2])
                                            <button type="button" class="pc-swatch pc-swatch--{{ $swatch }}" role="radio" aria-checked="false" data-swatch="{{ $swatch }}" aria-label="{{ ucfirst($swatch) }}" style="--pc-c1:{{ $c1 }};--pc-c2:{{ $c2 }}"></button>
                                        @endforeach
                                    </div>
                                </div>
                                <input type="hidden" name="background_style" id="arena-background-input" value="{{ old('background_style') }}">
                            </div>

                            <div class="pc-dd" id="pc-cat-dd">
                                <button type="button" class="pc-cat-btn is-default" id="pc-cat-btn" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="pc-cat-icon" id="pc-cat-icon"><i class="ph-fill {{ $composerCategories['general'][1] }}"></i></span>
                                    <span id="pc-cat-label">{{ __($composerCategories['general'][0]) }}</span>
                                    <i class="ph-bold ph-caret-down pc-chev"></i>
                                </button>
                                <div class="pc-menu" id="pc-cat-menu">
                                    <ul class="pc-cat-list" role="listbox" aria-label="@lang('Post category')">
                                        @foreach($composerCategories as $key => $info)
                                            <li><button type="button" class="pc-cat-item" role="option" aria-selected="{{ $key === 'general' ? 'true' : 'false' }}" data-value="{{ $key }}" data-icon="{{ $info[1] }}">
                                                <i class="ph-fill {{ $info[1] }}"></i>{{ __($info[0]) }}
                                                <i class="ph-bold ph-check pc-tick"></i>
                                            </button></li>
                                        @endforeach
                                    </ul>
                                </div>
                                <input type="hidden" name="type" id="pc-cat-value" value="general">
                            </div>

                            <input class="sr-only" type="file" id="arena-image-input" name="image" accept=".png,.jpg,.jpeg,.webp" data-label="{{ __('image') }}">
                            <label class="pc-round-btn" for="arena-image-input" title="@lang('Add a photo')">
                                <span class="sr-only">@lang('Add a photo')</span>
                                <i class="ph-fill ph-image-square"></i>
                            </label>

                            <input class="sr-only" type="file" id="arena-video-input" name="video" accept=".mp4,.mov,.webm" data-label="{{ __('video') }}">
                            <label class="pc-round-btn" for="arena-video-input" title="@lang('Add a video')">
                                <span class="sr-only">@lang('Add a video')</span>
                                <i class="ph-fill ph-video-camera"></i>
                            </label>

                            <button type="submit" class="pc-send" id="pc-send" disabled>
                                <i class="ph-bold ph-paper-plane-tilt"></i><i class="ph-fill ph-sparkle pc-send__sparkle"></i>
                                @lang('Post')
                            </button>
                        </div>

                        @if(count($recentWins))
                            <div class="nocturne-arena-win-pick" id="arena-win-pick" hidden>
                                <label>@lang('Attach a win (optional)')</label>
                                <select name="linked_game_log_id">
                                    <option value="">@lang('None')</option>
                                    @foreach($recentWins as $log)
                                        <option value="{{ $log->id }}">{{ __($log->game->name ?? 'Game') }} &middot; {{ gs('coin_mode') ? coinAmountText($log->win_amo) : showAmount($log->win_amo) }} &middot; {{ showDateTime($log->created_at, 'M d') }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </form>
                @else
                    <div class="nocturne-arena-guest-cta">
                        <p>@lang('Sign in to post, comment, vote and follow other players.')</p>
                        <a href="{{ route('user.login') }}">@lang('Sign in')</a>
                    </div>
                @endauth

                <div class="nocturne-arena-list" id="arena-feed">
                    @if($posts->isEmpty())
                        <div class="nocturne-arena-empty"><i class="ph-fill ph-chats-circle"></i><h3>@lang('No posts yet')</h3><p>@lang('Be the first to post in the Arena.')</p></div>
                    @else
                        @include('Template::partials.arena_post_cards')
                    @endif
                </div>

                @if($posts->hasMorePages())
                    <div class="nocturne-arena-loadmore">
                        <button type="button" id="arena-load-more" data-next-page="{{ $posts->currentPage() + 1 }}" data-url="{{ route('arena.index') }}">@lang('Load More')</button>
                    </div>
                @endif
            </div>

            <div class="nocturne-arena-rail-backdrop" id="arena-rail-backdrop"></div>
            <aside class="nocturne-arena-rail" id="arena-rail">
                <div class="nocturne-arena-rail__head"><strong>@lang('Arena menu')</strong><button type="button" class="nocturne-arena-rail__close" id="arena-rail-close" aria-label="@lang('Close')"><i class="ph-bold ph-x"></i></button></div>
                @auth
                    <div class="nocturne-arena-widget nocturne-arena-widget--profile">
                        <a href="{{ route('arena.profile', auth()->user()->username ?? '') }}" class="nocturne-arena-widget__me">
                            <span class="nocturne-arena-post__avatar">@if(auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->username ?? '?', 0, 1)) }}@endif</span>
                            <div>
                                <strong>{{ auth()->user()->username ?? __('Complete your profile') }}</strong>
                                @if($currentTier)<small><i class="ph-fill ph-crown-simple"></i>{{ __($currentTier->name) }}</small>@endif
                            </div>
                        </a>
                        @if($nextTier)
                            <div class="nocturne-arena-tier-progress">
                                <div class="nocturne-arena-tier-progress__track"><span style="width:{{ $tierProgressPercent }}%"></span></div>
                                <small>{{ formatVipThreshold(max($nextTier->min_wagered - $wagered, 0)) }} @lang('to') {{ __($nextTier->name) }}</small>
                            </div>
                        @endif
                        <a href="{{ route('user.arena.my-posts') }}" class="nocturne-arena-my-posts-link"><i class="ph-bold ph-list-bullets"></i>@lang('My Posts')</a>
                    </div>

                    <div class="nocturne-arena-widget">
                        <h3><i class="ph-fill ph-chart-bar"></i>@lang('Your Stats')</h3>
                        <div class="nocturne-arena-stat-row"><span>@lang('Wagered')</span><strong>{{ gs('coin_mode') ? coinAmountText($userStats['total_invest']) : showAmount($userStats['total_invest']) }}</strong></div>
                        <div class="nocturne-arena-stat-row"><span>@lang('Won')</span><strong>{{ gs('coin_mode') ? coinAmountText($userStats['total_win']) : showAmount($userStats['total_win']) }}</strong></div>
                        <div class="nocturne-arena-stat-row"><span>@lang('Lost')</span><strong>{{ gs('coin_mode') ? coinAmountText($userStats['total_loss']) : showAmount($userStats['total_loss']) }}</strong></div>
                    </div>
                @endauth

                <div class="nocturne-arena-widget">
                    <h3><i class="ph-fill ph-sword nocturne-live-pulse"></i>@lang('Live Games')</h3>
                    @forelse($liveTables as $table)
                        <a href="{{ route('user.tables.show', $table['id']) }}" class="nocturne-arena-widget-row">
                            <span class="nocturne-arena-widget-row__icon"><i class="ph-fill ph-game-controller"></i></span>
                            <div>
                                <strong>{{ $catalog[$table['game']]['name'] ?? ucfirst($table['game']) }}</strong>
                                <small>{{ count($table['players']) }}/{{ $table['player_limit'] }} @lang('players') &middot; {{ ucfirst($table['status']) }}</small>
                            </div>
                        </a>
                    @empty
                        <p class="nocturne-arena-widget-empty">@lang('No live tables right now.')</p>
                    @endforelse
                </div>

                <div class="nocturne-arena-widget">
                    <h3><i class="ph-fill ph-trophy"></i>@lang('Recent Wins')</h3>
                    @if($recentSiteWins->isNotEmpty())
                        <div class="nocturne-arena-wins-ticker">
                            <div class="nocturne-arena-wins-ticker__track" style="--win-count:{{ $recentSiteWins->count() }}">
                                @for($pass = 0; $pass < 2; $pass++)
                                    @foreach($recentSiteWins as $log)
                                        <div class="nocturne-arena-widget-row" aria-hidden="{{ $pass === 1 ? 'true' : 'false' }}">
                                            <span class="nocturne-arena-widget-row__icon nocturne-arena-widget-row__icon--win"><i class="ph-fill ph-coin"></i></span>
                                            <div>
                                                <strong>{{ formatHistoricalAmountAsCoins($log->amount, $log->currency_id, $log->rate_to_usd) }}</strong>
                                                <small>{{ $log->username ?? 'Someone' }} &middot; {{ __($log->game_name) }}</small>
                                            </div>
                                        </div>
                                    @endforeach
                                @endfor
                            </div>
                        </div>
                    @else
                        <p class="nocturne-arena-widget-empty">@lang('No wins yet.')</p>
                    @endif
                </div>

                <div class="nocturne-arena-widget">
                    <h3><i class="ph-fill ph-flame"></i>@lang('Trending')</h3>
                    @forelse($trendingGames as $game)
                        <div class="nocturne-arena-widget-row">
                            <span class="nocturne-arena-widget-row__icon"><i class="ph-fill ph-dice-five"></i></span>
                            <strong>{{ __($game->name) }}</strong>
                        </div>
                    @empty
                        <p class="nocturne-arena-widget-empty">@lang('Nothing trending right now.')</p>
                    @endforelse
                </div>

                @if($trendingNews->isNotEmpty())
                    <div class="nocturne-arena-widget">
                        <h3><i class="ph-fill ph-newspaper"></i>@lang('News')</h3>
                        @foreach($trendingNews as $post)
                            <a href="{{ route('blog.details', $post->slug) }}" class="nocturne-arena-widget-row">
                                <span class="nocturne-arena-widget-row__icon"><i class="ph-fill ph-article"></i></span>
                                <strong>{{ __($post->title) }}</strong>
                            </a>
                        @endforeach
                    </div>
                @endif

                @auth
                    @if($suggestedFollows->isNotEmpty())
                        <div class="nocturne-arena-widget">
                            <h3><i class="ph-fill ph-users-three"></i>@lang('Suggested for you')</h3>
                            @foreach($suggestedFollows as $suggested)
                                <div class="nocturne-arena-widget-row nocturne-arena-widget-row--suggest">
                                    <a href="{{ route('arena.profile', $suggested->username ?? '') }}" class="nocturne-arena-post__avatar">@if($suggested->avatarUrl())<img src="{{ $suggested->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($suggested->username ?? '?', 0, 1)) }}@endif</a>
                                    <a href="{{ route('arena.profile', $suggested->username ?? '') }}"><strong>{{ $suggested->username }}</strong></a>
                                    <button type="button" class="nocturne-arena-mini-follow" data-user-id="{{ $suggested->id }}" data-following="0"><span class="label-follow">@lang('Follow')</span><span class="label-following">@lang('Following')</span></button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endauth
            </aside>
        </div>
    </div>
@endsection

@push('style')
<style>
:root{--arena-accent:#00e78a;--arena-bg:#10152a;--arena-panel:#151a31;--arena-raised:#1b2140;--arena-muted:#858eaf;--arena-line:rgba(133,142,175,.18);--arena-text:#f5f6ff}
.nocturne-arena-page{max-width:1240px;margin:0 auto;padding:24px 20px 60px}
.nocturne-arena-crumb-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
.nocturne-arena-crumb{display:flex;align-items:center;gap:7px;color:var(--arena-muted);font-size:13px;font-weight:700}
.nocturne-arena-crumb a{color:var(--arena-muted);text-decoration:none}
.nocturne-arena-crumb a:hover{color:var(--arena-text)}
.nocturne-arena-crumb span{color:var(--arena-text)}
.nocturne-arena-crumb i{font-size:11px;opacity:.6}
.nocturne-arena-crumb-tools{display:flex;align-items:center;gap:8px}

.nocturne-arena-bell-wrap{position:relative}
.nocturne-arena-bell{position:relative;display:grid;place-items:center;width:38px;height:38px;border:1px solid var(--arena-line);border-radius:11px;background:var(--arena-panel);color:var(--arena-muted);font-size:16px;cursor:pointer}
.nocturne-arena-bell:hover{color:var(--arena-accent);border-color:rgba(0,231,138,.4)}
.nocturne-arena-bell__count{position:absolute;top:-6px;right:-6px;display:grid;place-items:center;min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:#ff5c7a;color:#fff;font-size:9.5px;font-weight:800}
.nocturne-arena-bell-panel{position:absolute;top:calc(100% + 8px);right:0;z-index:30;width:320px;max-height:380px;overflow-y:auto;padding:14px;border:1px solid var(--arena-line);border-radius:16px;background:#11162b;box-shadow:0 24px 60px rgba(0,0,0,.45)}
.nocturne-arena-bell-panel__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.nocturne-arena-bell-panel__head strong{color:var(--arena-text);font-size:13.5px}
.nocturne-arena-bell-panel__head button{border:0;background:transparent;color:var(--arena-accent);font-size:11px;font-weight:800;cursor:pointer}
.nocturne-arena-notif{display:flex;gap:10px;padding:9px 4px;border-radius:10px}
.nocturne-arena-notif.is-unread{background:rgba(0,231,138,.06)}
.nocturne-arena-notif__avatar{display:grid;place-items:center;flex:0 0 30px;width:30px;height:30px;border-radius:50%;background:rgba(0,231,138,.12);color:var(--arena-accent);font-size:13px;overflow:hidden}
.nocturne-arena-notif__avatar img{width:100%;height:100%;object-fit:cover}
.nocturne-arena-notif p{margin:0;color:var(--arena-text);font-size:12.5px;line-height:1.4}
.nocturne-arena-notif small{color:var(--arena-muted);font-size:10.5px}
.nocturne-arena-notif-empty{margin:0;padding:16px 0;color:var(--arena-muted);font-size:12.5px;text-align:center}

@include('Template::partials.arena_post_card_styles')

/* Desktop keeps the sidebar inline, same as before. It only becomes an off-canvas
   drawer (opened by the list icon) below the 991.98px breakpoint, where it used to
   stack under the feed instead - see the @media block for that swap. */
.nocturne-arena-layout{display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start}
.nocturne-arena-feed{min-width:0}
.nocturne-arena-rail-backdrop{display:none}
#arena-rail-open{display:none}

/* ---------- Post composer (pc-*, namespaced to this page) ---------- */
.pc-composer{position:relative;margin-bottom:20px;padding:16px;border:1px solid var(--arena-line);border-radius:22px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.16)}
.pc-composer__top{display:flex;gap:10px;align-items:flex-start}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

.pc-composer__avatar{flex:none;width:40px;height:40px;margin-top:3px;border-radius:50%;overflow:hidden;display:grid;place-items:center;background:linear-gradient(135deg,var(--arena-accent),#384269);border:2px solid rgba(0,231,138,.35)}
.pc-composer__avatar img{width:100%;height:100%;object-fit:cover;display:block}
.pc-composer__avatar i{color:#fff;font-size:22px}

.pc-composer__field{--pc-tint-a:transparent;--pc-tint-b:transparent;flex:1;min-width:0;display:flex;align-items:flex-start;gap:8px;background:var(--arena-bg);border-radius:20px;padding:10px 14px;transition:background .25s ease}
.pc-composer__field[data-tint]{background:linear-gradient(135deg,color-mix(in srgb,var(--pc-tint-a) 26%,var(--arena-bg)),color-mix(in srgb,var(--pc-tint-b) 18%,var(--arena-bg)))}
.pc-composer__field:focus-within{background:#1b2140}
.pc-composer__field textarea{flex:1;min-width:0;min-height:28px;max-height:220px;padding:8px 0;border:0;background:transparent;color:var(--arena-text);font:inherit;font-size:14.5px;line-height:1.5;resize:none;overflow-y:auto;outline:0}
.pc-composer__field textarea::placeholder{color:#606986}

.pc-icon-btn{flex:none;width:30px;height:30px;margin-top:4px;border-radius:50%;border:0;cursor:pointer;background:transparent;color:var(--arena-muted);display:grid;place-items:center;padding:0;font-size:17px}
.pc-icon-btn:hover{color:var(--arena-text);background:rgba(245,246,255,.08)}

.pc-emoji-pop{position:absolute;right:16px;top:64px;z-index:30;background:var(--arena-raised);border:1px solid var(--arena-line);border-radius:14px;padding:8px;display:none;grid-template-columns:repeat(6,1fr);gap:2px;box-shadow:0 14px 34px rgba(0,0,0,.3)}
.pc-emoji-pop.is-open{display:grid}
.pc-emoji-pop button{border:0;background:none;font-size:19px;cursor:pointer;padding:5px;border-radius:8px;line-height:1}
.pc-emoji-pop button:hover{background:rgba(245,246,255,.08)}

.pc-composer__preview{position:relative;margin:12px 0 0 50px;border-radius:14px;overflow:hidden;max-width:320px}
.pc-composer__preview img{display:block;width:100%;max-height:220px;object-fit:cover}
.pc-composer__preview video{display:block;width:100%;max-height:260px;object-fit:cover;background:#000}
.pc-composer__preview button{position:absolute;top:8px;right:8px;display:grid;place-items:center;width:28px;height:28px;border:0;border-radius:50%;background:rgba(5,8,18,.7);color:#fff;cursor:pointer}

.pc-chips{display:flex;flex-wrap:wrap;gap:6px;padding:0 0 0 50px}
.pc-chips:empty{display:none}
.pc-chip{display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-size:12px;font-weight:700;color:var(--arena-text);background:var(--arena-bg);border:1px solid var(--arena-line);border-radius:999px;padding:5px 6px 5px 11px}
.pc-chip button{border:0;background:rgba(245,246,255,.1);color:var(--arena-muted);width:18px;height:18px;border-radius:50%;cursor:pointer;line-height:1;font-size:10px;padding:0}

.pc-composer__bar{display:flex;flex-wrap:wrap;align-items:center;gap:9px;margin:14px 0 0 50px}
.pc-dd{position:relative}

.pc-round-btn{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--arena-line);background:var(--arena-bg);color:var(--arena-muted);cursor:pointer;display:grid;place-items:center;padding:0;font-size:17px;transition:color .15s ease,border-color .15s ease,background .15s ease}
.pc-round-btn:hover,.pc-round-btn[aria-expanded="true"]{color:var(--arena-accent);border-color:rgba(0,231,138,.4);background:rgba(0,231,138,.08)}
.pc-round-btn:focus-visible,.pc-cat-btn:focus-visible,.pc-send:focus-visible,.pc-icon-btn:focus-visible{outline:2px solid var(--arena-accent);outline-offset:2px}

.pc-color-dot{width:20px;height:20px;border-radius:50%;display:none;background:linear-gradient(135deg,var(--pc-c1),var(--pc-c2));box-shadow:0 0 0 2px var(--arena-bg),0 0 0 3.5px var(--pc-c1)}
.pc-round-btn.has-color .pc-color-dot{display:block}
.pc-round-btn.has-color .pc-palette-icon{display:none}

.pc-menu{position:absolute;left:0;top:calc(100% + 8px);z-index:25;min-width:190px;background:var(--arena-raised);border:1px solid var(--arena-line);border-radius:16px;padding:8px;box-shadow:0 14px 34px rgba(0,0,0,.3);display:none}
.pc-menu.is-open{display:block;animation:pc-pop .14s ease-out}
.pc-menu.is-up{top:auto;bottom:calc(100% + 8px)}
@keyframes pc-pop{from{opacity:0;transform:translateY(-4px) scale(.98)}to{opacity:1;transform:none}}

.pc-swatches{display:flex;gap:10px;padding:4px}
.pc-swatch{width:28px;height:28px;border-radius:50%;border:0;cursor:pointer;padding:0;transition:transform .12s ease}
.pc-swatch:hover{transform:scale(1.12)}
.pc-swatch[aria-checked="true"]{box-shadow:0 0 0 2px var(--arena-raised),0 0 0 4px #fff}
.pc-swatch--none{background:transparent;border:1.5px dashed var(--arena-muted);color:var(--arena-muted);display:grid;place-items:center;font-size:12px}
.pc-swatch--violet{background:linear-gradient(135deg,#7c3aed,#4c1d95)}
.pc-swatch--sunset{background:linear-gradient(135deg,#f97316,#be123c)}
.pc-swatch--forest{background:linear-gradient(135deg,#00e78a,#065f46)}
.pc-swatch--ocean{background:linear-gradient(135deg,#0ea5e9,#1e3a8a)}
.pc-swatch--rose{background:linear-gradient(135deg,#f472b6,#9d174d)}

.pc-cat-btn{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 14px 0 13px;border-radius:999px;border:1.5px solid var(--arena-line);background:var(--arena-bg);cursor:pointer;font-size:12.5px;font-weight:750;color:var(--arena-text)}
.pc-cat-btn .pc-chev{font-size:12px;opacity:.7;transition:transform .15s ease}
.pc-cat-btn[aria-expanded="true"] .pc-chev{transform:rotate(180deg)}
.pc-cat-btn.is-default{border-color:rgba(0,231,138,.4);background:rgba(0,231,138,.1);color:var(--arena-accent);animation:pc-blink 2.2s ease-in-out infinite}
.pc-cat-btn.is-picked{border-color:rgba(0,231,138,.4);background:rgba(0,231,138,.1);color:var(--arena-accent)}
@keyframes pc-blink{0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(0,231,138,.45)}50%{opacity:.72;box-shadow:0 0 0 6px rgba(0,231,138,0)}}
@media (prefers-reduced-motion:reduce){.pc-cat-btn.is-default{animation:none}}

.pc-cat-list{list-style:none;margin:0;padding:0;min-width:180px}
.pc-cat-item{width:100%;display:flex;align-items:center;gap:9px;border:0;background:none;cursor:pointer;padding:9px 11px;border-radius:10px;font-size:12.5px;font-weight:650;color:var(--arena-text);text-align:left}
.pc-cat-item:hover,.pc-cat-item:focus-visible{background:rgba(245,246,255,.08);outline:0}
.pc-tick{margin-left:auto;font-size:13px;color:var(--arena-accent);opacity:0}
.pc-cat-item[aria-selected="true"] .pc-tick{opacity:1}

.pc-send{margin-left:auto;display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 20px;border:0;border-radius:999px;cursor:pointer;background:var(--arena-accent);color:#062a23;font-size:13px;font-weight:800;transition:opacity .15s ease,transform .12s ease}
.pc-send__sparkle{font-size:11px;margin-left:-3px}
.pc-send:active:not(:disabled){transform:scale(.97)}
.pc-send:disabled{opacity:.4;cursor:not-allowed}

.nocturne-arena-win-pick{margin:14px 0 0 50px}
.nocturne-arena-win-pick label{display:block;margin-bottom:6px;color:var(--arena-muted);font-size:11px;font-weight:800;text-transform:uppercase}
.nocturne-arena-win-pick select{width:100%;max-width:360px;padding:10px 12px;border:1px solid var(--arena-line);border-radius:12px;background:var(--arena-bg);color:var(--arena-text);font:inherit;font-size:13px}

@media (max-width:520px){
    .pc-composer__bar{margin-left:0}
    .pc-composer__preview,.pc-chips,.nocturne-arena-win-pick{margin-left:0}
    .pc-send{flex:1 0 100%;margin-left:0;justify-content:center}
}

.nocturne-arena-guest-cta{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;padding:16px 18px;border:1px solid var(--arena-line);border-radius:16px;background:var(--arena-panel);flex-wrap:wrap}
.nocturne-arena-guest-cta p{margin:0;color:var(--arena-muted);font-size:13px}
.nocturne-arena-guest-cta a{padding:9px 16px;border-radius:11px;background:var(--arena-accent);color:#062a23;font-size:12.5px;font-weight:800;text-decoration:none}

.nocturne-arena-list{display:grid;gap:14px}

.nocturne-arena-empty{display:grid;justify-items:center;gap:8px;padding:50px 20px;border:1px dashed var(--arena-line);border-radius:18px;color:var(--arena-muted);text-align:center}
.nocturne-arena-empty i{color:var(--arena-accent);font-size:28px}
.nocturne-arena-empty h3{margin:0;color:var(--arena-text);font-size:16px}

.nocturne-arena-loadmore{display:flex;justify-content:center;margin-top:20px}
#arena-load-more{padding:12px 26px;border:1px solid var(--arena-line);border-radius:14px;background:var(--arena-panel);color:var(--arena-text);font-size:13px;font-weight:800;cursor:pointer}
#arena-load-more:hover{border-color:rgba(0,231,138,.4);color:var(--arena-accent)}
#arena-load-more:disabled{opacity:.5;cursor:default}

.nocturne-arena-rail{display:grid;gap:16px}
.nocturne-arena-rail__head{display:none}
.nocturne-arena-widget{padding:18px;border:1px solid var(--arena-line);border-radius:18px;background:var(--arena-panel)}
.nocturne-arena-widget h3{display:flex;align-items:center;gap:8px;margin:0 0 8px;color:var(--arena-text);font-size:14.5px;font-weight:800}
.nocturne-arena-widget h3 i{color:var(--arena-accent)}
.nocturne-arena-wins-ticker{position:relative;height:150px;overflow:hidden;-webkit-mask-image:linear-gradient(180deg,transparent,#000 10%,#000 90%,transparent);mask-image:linear-gradient(180deg,transparent,#000 10%,#000 90%,transparent)}
.nocturne-arena-wins-ticker__track{animation:nocturneArenaWinsScroll calc(var(--win-count,6) * 2.6s) linear infinite}
.nocturne-arena-wins-ticker:hover .nocturne-arena-wins-ticker__track{animation-play-state:paused}
@keyframes nocturneArenaWinsScroll{from{transform:translateY(0)}to{transform:translateY(-50%)}}
@media(prefers-reduced-motion:reduce){.nocturne-arena-wins-ticker__track{animation:none}.nocturne-arena-wins-ticker{height:150px;overflow-y:auto;-webkit-mask-image:none;mask-image:none}}

/* Hero carousel (announcements + live/upcoming competitions) */
.nocturne-arena-carousel{position:relative;margin-bottom:20px;touch-action:pan-y}
/* The rounded border lives on the track (the thing that actually clips), not on each
   slide - a slide is a flat position:absolute;inset:0 layer with border-radius:0 (it relies
   on the track's overflow:hidden to get its curve), so giving it its own border used to draw
   square corners that the track's curve sliced straight through, reading as a "cut off"
   corner at the two corners not hidden behind the background art/glow. */
.nocturne-arena-carousel__track{position:relative;min-height:300px;border-radius:22px;border:1px solid var(--arena-line);overflow:hidden}
/* Nav arrows sit clear of the text column, stay invisible until a real mouse hovers the
   carousel, and never appear at all on touch/narrow screens - swipe takes over there. */
.nocturne-arena-carousel__nav{position:absolute;top:50%;z-index:3;display:grid;place-items:center;width:34px;height:34px;margin-top:-17px;border:1px solid rgba(255,255,255,.16);border-radius:50%;background:rgba(6,8,18,.6);backdrop-filter:blur(4px);color:#fff;font-size:14px;cursor:pointer;opacity:0;transition:opacity .2s ease,background .15s ease,border-color .15s ease;pointer-events:none}
.nocturne-arena-carousel__nav--prev{left:8px}
.nocturne-arena-carousel__nav--next{right:8px}
@media (hover:hover) and (pointer:fine){
    .nocturne-arena-carousel:hover .nocturne-arena-carousel__nav{opacity:1;pointer-events:auto}
    .nocturne-arena-carousel__nav:hover{background:rgba(6,8,18,.85);border-color:rgba(0,231,138,.45)}
}
.nocturne-arena-carousel__dots{position:absolute;left:50%;bottom:12px;z-index:3;display:flex;gap:6px;transform:translateX(-50%)}
.nocturne-arena-carousel__dot{width:7px;height:7px;padding:0;border:0;border-radius:50%;background:rgba(255,255,255,.28);cursor:pointer;transition:background .15s ease,transform .15s ease}
.nocturne-arena-carousel__dot.is-active{background:var(--arena-accent);transform:scale(1.3)}
@media(max-width:700px){.nocturne-arena-carousel__nav{display:none}}

/* Announcement/competition slide */
.nocturne-arena-slide{position:absolute;inset:0;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .6s ease}
.nocturne-arena-slide.is-active{opacity:1;visibility:visible;pointer-events:auto}
.nocturne-arena-announcement{position:relative;overflow:hidden;display:flex;align-items:center;min-height:300px;margin-bottom:20px;border-radius:22px;border:1px solid var(--arena-line);background:radial-gradient(ellipse at 85% 15%,rgba(0,231,138,.14),transparent 45%),radial-gradient(ellipse at 10% 100%,rgba(0,231,138,.18),transparent 45%),linear-gradient(120deg,#0b0f22,#171d37 65%,#0b0f22)}
.nocturne-arena-slide.nocturne-arena-announcement{position:absolute;inset:0;margin-bottom:0;border:0;border-radius:0;box-shadow:none}
.nocturne-arena-announcement::before{content:'';position:absolute;right:-8%;top:50%;width:46%;aspect-ratio:1;transform:translateY(-50%);background:conic-gradient(from 180deg,rgba(0,231,138,.0),rgba(0,231,138,.16),rgba(0,231,138,.0) 60%);border-radius:50%;filter:blur(2px);pointer-events:none}
.nocturne-arena-announcement:has(.nocturne-arena-announcement__bg)::before{display:none}
.nocturne-arena-announcement__badge--competition{color:#ffd166}
.nocturne-arena-announcement__badge--competition::before{background:#ffd166}
.nocturne-arena-announcement__bg{position:absolute;right:0;top:0;width:54%;height:100%;object-fit:cover;object-position:top center;-webkit-mask-image:linear-gradient(to right,transparent,#000 20%);mask-image:linear-gradient(to right,transparent,#000 20%)}
.nocturne-arena-announcement__shade{position:absolute;inset:0;background:linear-gradient(100deg,rgba(5,8,18,.94) 36%,rgba(5,8,18,.1))}
.nocturne-arena-announcement__content{position:relative;z-index:1;max-width:600px;padding:clamp(22px,3.6vw,34px)}
.nocturne-arena-announcement__badge{position:relative;display:inline-flex;align-items:center;gap:7px;margin:0 0 12px;padding:3px 0;color:var(--arena-accent);font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.nocturne-arena-announcement__badge::before{content:'';flex:0 0 auto;width:7px;height:7px;border-radius:50%;background:currentColor;animation:arenaBadgeBlink 1.4s ease-in-out infinite}
@keyframes arenaBadgeBlink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.7)}}
@media(prefers-reduced-motion:reduce){.nocturne-arena-announcement__badge::before{animation:none}}
.nocturne-arena-announcement__content h2{margin:0 0 8px;color:#fff;font-size:clamp(20px,3vw,28px);font-weight:950;letter-spacing:-.01em;line-height:1.1;text-transform:uppercase}
.nocturne-arena-announcement__timer{display:flex;gap:9px;margin-bottom:16px}
.nocturne-arena-timer-unit{display:grid;justify-items:center;gap:3px;min-width:58px;padding:10px 8px;border:1px solid rgba(0,231,138,.3);border-radius:13px;background:rgba(0,231,138,.06);box-shadow:0 0 18px rgba(0,231,138,.08) inset}
.nocturne-arena-timer-unit strong{color:#fff;font-size:21px;font-weight:950;font-variant-numeric:tabular-nums;line-height:1}
.nocturne-arena-timer-unit small{display:flex;align-items:center;gap:3px;color:var(--arena-muted);font-size:8.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.nocturne-arena-timer-unit small i{color:var(--arena-accent);font-size:9px}
.nocturne-arena-announcement__foot{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.nocturne-arena-announcement__reward{display:inline-flex;align-items:center;gap:6px;color:var(--arena-accent);font-size:13px;font-weight:800;white-space:nowrap}
.nocturne-arena-announcement__btn{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border:0;border-radius:12px;background:var(--arena-accent);color:#062a23;font-size:13px;font-weight:900;text-decoration:none;white-space:nowrap;transition:transform .15s ease,box-shadow .15s ease}
.nocturne-arena-announcement__btn:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,231,138,.3);background:#049b5f;color:#fff}
.nocturne-arena-announcement__btn i{font-size:15px}
/* Mobile keeps the exact same side-by-side arrangement as desktop (text left, art bleeding in
   on the right, timer row, footer row) - everything just scales down to fit, it never
   restructures into a stacked layout. */
@media(max-width:700px){
    /* 165px was shorter than the slide's own content (badge + 2-line title + timer row +
       footer), so the bottom of the card - the button, sometimes the dots too - was being
       sliced off by the track's overflow:hidden. Give it enough room for the worst case
       (long 2-line title) and clamp the title so a very long competition name can't blow
       past that again. */
    .nocturne-arena-carousel__track{min-height:224px}
    .nocturne-arena-announcement{min-height:224px}
    .nocturne-arena-announcement__bg{width:46%}
    .nocturne-arena-announcement__content{max-width:62%;padding:16px 16px 30px}
    .nocturne-arena-announcement__badge{margin:0 0 6px;font-size:9px;gap:5px}
    .nocturne-arena-announcement__badge::before{width:6px;height:6px}
    .nocturne-arena-announcement__content h2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0 0 10px;font-size:clamp(15px,5.2vw,20px);line-height:1.18}
    .nocturne-arena-announcement__timer{gap:5px;margin-bottom:12px}
    .nocturne-arena-timer-unit{min-width:0;flex:1;padding:6px 2px;border-radius:9px}
    .nocturne-arena-timer-unit strong{font-size:14px}
    .nocturne-arena-timer-unit small{font-size:6.5px;gap:2px;white-space:nowrap}
    .nocturne-arena-timer-unit small i{font-size:7px}
    /* Stacked, not side-by-side: at this width reward text + button never reliably fit on one
       line, and letting them wrap conditionally is what broke the button's own label text
       mid-word before. Always stacking keeps the layout (and its height) predictable. */
    .nocturne-arena-announcement__foot{flex-direction:column;align-items:flex-start;gap:8px}
    .nocturne-arena-announcement__reward{font-size:10px}
    .nocturne-arena-announcement__btn{padding:9px 14px;font-size:10.5px;gap:5px}
    .nocturne-arena-announcement__btn i{font-size:11px}
    .nocturne-arena-carousel__nav{width:28px;height:28px;font-size:13px}
    .nocturne-arena-carousel__dots{bottom:8px}
}
@media(max-width:380px){
    .nocturne-arena-announcement__content{max-width:58%}
    .nocturne-arena-announcement__content h2{font-size:clamp(13px,5vw,17px)}
}

/* Profile/level widget */
.nocturne-arena-widget--profile{display:grid;gap:12px}
.nocturne-arena-widget__me{display:flex;align-items:center;gap:11px;text-decoration:none}
.nocturne-arena-widget__me strong{display:block;color:var(--arena-text);font-size:14px}
.nocturne-arena-widget__me small{display:flex;align-items:center;gap:4px;color:var(--arena-accent);font-size:11px;font-weight:700}
.nocturne-arena-tier-progress__track{height:6px;border-radius:999px;background:var(--arena-bg);overflow:hidden}
.nocturne-arena-tier-progress__track span{display:block;height:100%;background:var(--arena-accent)}
.nocturne-arena-tier-progress small{display:block;margin-top:6px;color:var(--arena-muted);font-size:11px}
.nocturne-arena-my-posts-link{display:flex;align-items:center;gap:7px;padding-top:4px;color:var(--arena-muted);font-size:12px;font-weight:750;text-decoration:none}
.nocturne-arena-my-posts-link:hover{color:var(--arena-accent)}
.nocturne-arena-my-posts-link i{color:var(--arena-accent)}

.nocturne-arena-stat-row{display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--arena-line)}
.nocturne-arena-stat-row:first-of-type{border-top:0}
.nocturne-arena-stat-row span{color:var(--arena-muted);font-size:12px}
.nocturne-arena-stat-row strong{color:var(--arena-text);font-size:12.5px;font-weight:800}

.nocturne-arena-widget-row{display:flex;align-items:center;gap:10px;padding:8px 0;text-decoration:none}
.nocturne-arena-widget-row+.nocturne-arena-widget-row{border-top:1px solid var(--arena-line)}
.nocturne-arena-widget-row__icon{display:grid;place-items:center;flex:0 0 30px;width:30px;height:30px;border-radius:9px;background:rgba(0,231,138,.1);color:var(--arena-accent);font-size:14px}
.nocturne-arena-widget-row__icon--win{background:rgba(255,181,71,.14);color:#ffb547}
.nocturne-arena-widget-row strong{display:block;color:var(--arena-text);font-size:12.5px;font-weight:750}
.nocturne-arena-widget-row small{color:var(--arena-muted);font-size:10.5px}
.nocturne-arena-widget-row--suggest strong{font-size:12.5px}
.nocturne-arena-widget-row--suggest{gap:8px}
.nocturne-arena-widget-row--suggest .nocturne-arena-post__avatar{flex:0 0 30px;width:30px;height:30px;font-size:11px}
.nocturne-arena-widget-row--suggest>a:nth-child(2){flex:1;min-width:0;color:inherit;text-decoration:none}
.nocturne-arena-widget-empty{margin:0;padding:6px 0;color:var(--arena-muted);font-size:12px}

/* Below this width the sidebar used to stack directly under the feed (easy to miss,
   made the page feel endless). Instead it becomes an off-canvas drawer, opened with
   the list icon next to the bell, so it's reachable in one tap but out of the way. */
@media(max-width:991.98px){
    .nocturne-arena-layout{grid-template-columns:1fr}
    #arena-rail-open{display:grid;place-items:center}
    /* The site's own fixed header is z-index:1060 and the mobile bottom nav is z-index:1040 -
       the drawer previously sat at z-index:60, well BELOW both, so the header covered its
       close button and the bottom nav covered its lower content. It needs to outrank them. */
    .nocturne-arena-rail-backdrop{display:block;position:fixed;inset:0;z-index:1065;background:rgba(5,8,18,.6);backdrop-filter:blur(2px);opacity:0;visibility:hidden;transition:opacity .25s ease,visibility .25s ease}
    .nocturne-arena-rail-backdrop.is-open{opacity:1;visibility:visible}
    .nocturne-arena-rail{position:fixed;top:0;right:0;z-index:1070;display:flex;flex-direction:column;gap:16px;width:360px;max-width:88vw;height:100%;padding:calc(18px + env(safe-area-inset-top)) 16px calc(24px + env(safe-area-inset-bottom));overflow-y:auto;background:#0d1225;border-left:1px solid var(--arena-line);box-shadow:-24px 0 60px rgba(0,0,0,.45);transform:translateX(100%);transition:transform .28s cubic-bezier(.2,.8,.2,1)}
    .nocturne-arena-rail.is-open{transform:translateX(0)}
    .nocturne-arena-rail__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:2px}
    .nocturne-arena-rail__head strong{color:var(--arena-text);font-size:16px;font-weight:850}
    .nocturne-arena-rail__close{display:grid;place-items:center;width:34px;height:34px;border:1px solid var(--arena-line);border-radius:10px;background:var(--arena-panel);color:var(--arena-muted);font-size:15px;cursor:pointer}
    .nocturne-arena-rail__close:hover{color:var(--arena-accent);border-color:rgba(0,231,138,.4)}
}
@media(max-width:575.98px){
    .nocturne-arena-rail{width:100%;max-width:100vw}
    /* The shell (.nocturne-main) already adds 12px of its own side padding on phones - this
       used to add another 14px on top (26px total per side), which is what was squeezing
       the hero banner's own content area and triggering the button text-wrap/empty-space
       complaints. Down to a few px so the combined gutter lands around 16px, not ~26-32px. */
    .nocturne-arena-page{padding:16px 4px 40px}
    .nocturne-arena-composer{padding:14px}
    .nocturne-arena-composer__preview,.nocturne-arena-composer__swatches,.nocturne-arena-composer__row,.nocturne-arena-win-pick,.nocturne-arena-composer__foot,.nocturne-arena-composer__note{margin-left:0}
    .nocturne-arena-composer__foot{flex-wrap:wrap}
    .nocturne-arena-post{padding:14px}
    .nocturne-arena-crumb-row{flex-wrap:wrap}
    .nocturne-arena-bell-panel{right:-14px;width:290px}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    // ---------- Post composer ----------
    (function() {
        var form = document.getElementById('arena-composer-form');
        if (!form) return;
        var body = document.getElementById('arena-composer-body');
        var field = document.getElementById('pc-field');
        var send = document.getElementById('pc-send');
        var chips = document.getElementById('pc-chips');
        var imageInput = document.getElementById('arena-image-input');
        var videoInput = document.getElementById('arena-video-input');
        var imagePreview = document.getElementById('arena-image-preview');
        var videoPreview = document.getElementById('arena-video-preview');
        var fileInputs = [imageInput, videoInput];

        // Facebook/X-style auto-growing composer - no manual drag handle, it just grows with content.
        function autoGrow() { body.style.height = 'auto'; body.style.height = Math.min(body.scrollHeight, 220) + 'px'; }
        function hasContent() {
            if (body.value.trim()) return true;
            return fileInputs.some(function(input) { return input.files.length; });
        }
        function refreshSend() { send.disabled = !hasContent(); }
        body.addEventListener('input', function() { autoGrow(); refreshSend(); });
        autoGrow();

        function renderChips() {
            chips.innerHTML = '';
            fileInputs.forEach(function(input) {
                if (!input.files.length) return;
                var chip = document.createElement('span');
                chip.className = 'pc-chip';
                chip.textContent = input.files[0].name + ' ';
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', "{{ __('Remove') }} " + input.dataset.label);
                remove.innerHTML = '<i class="ph-bold ph-x"></i>';
                remove.addEventListener('click', function() {
                    input.value = '';
                    if (input === imageInput) { imagePreview.hidden = true; imagePreview.querySelector('img').src = ''; }
                    else { videoPreview.hidden = true; videoPreview.querySelector('video').src = ''; }
                    renderChips(); refreshSend();
                });
                chip.appendChild(remove);
                chips.appendChild(chip);
            });
        }

        imageInput.addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            videoInput.value = ''; videoPreview.hidden = true; videoPreview.querySelector('video').src = '';
            var reader = new FileReader();
            reader.onload = function(e) { imagePreview.querySelector('img').src = e.target.result; imagePreview.hidden = false; };
            reader.readAsDataURL(file);
            renderChips(); refreshSend();
        });
        videoInput.addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            imageInput.value = ''; imagePreview.hidden = true; imagePreview.querySelector('img').src = '';
            videoPreview.querySelector('video').src = URL.createObjectURL(file);
            videoPreview.hidden = false;
            renderChips(); refreshSend();
        });
        document.getElementById('arena-image-remove').addEventListener('click', function() {
            imageInput.value = ''; imagePreview.hidden = true; imagePreview.querySelector('img').src = '';
            renderChips(); refreshSend();
        });
        document.getElementById('arena-video-remove').addEventListener('click', function() {
            videoInput.value = ''; videoPreview.hidden = true; videoPreview.querySelector('video').src = '';
            renderChips(); refreshSend();
        });

        // ---------- Dropdown helper: outside click / Escape to close, flips upward if there's
        // no room below, same behaviour for the color menu, the category menu and the emoji pop. ----------
        var dropdowns = [];
        function makeDropdown(btn, menu) {
            var dd = {
                open: function() {
                    closeAll(dd);
                    menu.classList.remove('is-up');
                    menu.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                    var rect = menu.getBoundingClientRect();
                    if (rect.bottom > window.innerHeight - 8 && btn.getBoundingClientRect().top > rect.height + 16) menu.classList.add('is-up');
                },
                close: function() { menu.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); },
                isOpen: function() { return menu.classList.contains('is-open'); }
            };
            btn.addEventListener('click', function(e) { e.stopPropagation(); dd.isOpen() ? dd.close() : dd.open(); });
            dropdowns.push(dd);
            return dd;
        }
        function closeAll(except) { dropdowns.forEach(function(dd) { if (dd !== except) dd.close(); }); }
        document.addEventListener('click', function() { closeAll(); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeAll(); });

        // Emoji popover - inserts at the cursor, same dropdown open/close behaviour.
        var emojiBtn = document.getElementById('pc-emoji-toggle');
        var emojiPop = document.getElementById('pc-emoji-pop');
        makeDropdown(emojiBtn, emojiPop);
        emojiPop.addEventListener('click', function(e) {
            if (e.target.tagName !== 'BUTTON') return;
            var start = body.selectionStart != null ? body.selectionStart : body.value.length;
            var end = body.selectionEnd != null ? body.selectionEnd : start;
            body.value = body.value.slice(0, start) + e.target.textContent + body.value.slice(end);
            body.focus(); body.selectionStart = body.selectionEnd = start + e.target.textContent.length;
            autoGrow(); refreshSend();
        });

        // Color dropdown.
        var colorBtn = document.getElementById('pc-color-btn');
        var colorDd = makeDropdown(colorBtn, document.getElementById('pc-color-menu'));
        var colorValue = document.getElementById('arena-background-input');
        var swatches = document.querySelectorAll('#pc-color-menu .pc-swatch');
        swatches.forEach(function(swatch) {
            swatch.addEventListener('click', function() {
                swatches.forEach(function(other) { other.setAttribute('aria-checked', other === swatch ? 'true' : 'false'); });
                colorValue.value = swatch.dataset.swatch;
                if (swatch.dataset.swatch) {
                    var c1 = swatch.style.getPropertyValue('--pc-c1');
                    var c2 = swatch.style.getPropertyValue('--pc-c2');
                    colorBtn.classList.add('has-color');
                    colorBtn.style.setProperty('--pc-c1', c1);
                    colorBtn.style.setProperty('--pc-c2', c2);
                    field.style.setProperty('--pc-tint-a', c1);
                    field.style.setProperty('--pc-tint-b', c2);
                    field.setAttribute('data-tint', '');
                } else {
                    colorBtn.classList.remove('has-color');
                    field.removeAttribute('data-tint');
                }
                colorDd.close();
            });
        });

        // Category dropdown - "General" blinks gently until another category is chosen.
        var catBtn = document.getElementById('pc-cat-btn');
        var catDd = makeDropdown(catBtn, document.getElementById('pc-cat-menu'));
        var catValue = document.getElementById('pc-cat-value');
        var catIcon = document.getElementById('pc-cat-icon');
        var catLabel = document.getElementById('pc-cat-label');
        var catItems = document.querySelectorAll('#pc-cat-menu .pc-cat-item');
        catItems.forEach(function(item) {
            item.addEventListener('click', function() {
                catItems.forEach(function(other) { other.setAttribute('aria-selected', other === item ? 'true' : 'false'); });
                catValue.value = item.dataset.value;
                catIcon.innerHTML = '<i class="ph-fill ' + item.dataset.icon + '"></i>';
                catLabel.textContent = item.textContent.trim();
                var isGeneral = item.dataset.value === 'general';
                catBtn.classList.toggle('is-default', isGeneral);
                catBtn.classList.toggle('is-picked', !isGeneral);
                document.getElementById('arena-win-pick') && (document.getElementById('arena-win-pick').hidden = item.dataset.value !== 'win');
                catDd.close(); catBtn.focus();
            });
        });

        form.addEventListener('submit', function() { send.disabled = true; send.querySelector('.ph-paper-plane-tilt')?.classList.replace('ph-paper-plane-tilt', 'ph-spinner'); });
        refreshSend();
    })();

    $('#arena-load-more').on('click', function() {
        var btn = $(this);
        var page = btn.data('next-page');
        var url = btn.data('url');
        btn.prop('disabled', true).text("{{ __('Loading…') }}");
        $.ajax({ url: url, data: { page: page }, headers: { 'Accept': 'application/json' } })
            .done(function(res) {
                $('#arena-feed').append(res.html);
                setupSeeMore(document.getElementById('arena-feed'));
                window.arenaObserveFeedVideos && window.arenaObserveFeedVideos(document.getElementById('arena-feed'));
                if (res.hasMore) {
                    btn.data('next-page', page + 1).prop('disabled', false).text("{{ __('Load More') }}");
                } else {
                    btn.remove();
                }
            })
            .fail(function() { btn.prop('disabled', false).text("{{ __('Load More') }}"); });
    });

    @include('Template::partials.arena_post_card_scripts')

    @auth
    // Notification bell
    var notifUrl = "{{ route('user.arena.notifications') }}";
    var notifReadUrl = "{{ route('user.arena.notifications.read') }}";
    function loadNotifications() {
        $.ajax({ url: notifUrl, headers: { 'Accept': 'application/json' } }).done(function(res) {
            $('#arena-bell-list').html(res.html);
            if (res.unread > 0) {
                $('#arena-bell-count').text(res.unread > 9 ? '9+' : res.unread).prop('hidden', false);
            } else {
                $('#arena-bell-count').prop('hidden', true);
            }
        });
    }
    $('#arena-bell').on('click', function(e) {
        e.stopPropagation();
        var panel = $('#arena-bell-panel');
        var opening = panel.prop('hidden');
        panel.prop('hidden', !opening);
        if (opening) loadNotifications();
    });
    $('#arena-bell-markread').on('click', function() {
        $.post(notifReadUrl, { _token: csrfToken }).done(loadNotifications);
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.nocturne-arena-bell-wrap').length) $('#arena-bell-panel').prop('hidden', true);
    });
    loadNotifications();
    @endauth

    // Arena menu: the former always-on right sidebar, now an off-canvas drawer.
    (function() {
        var rail = document.getElementById('arena-rail');
        var backdrop = document.getElementById('arena-rail-backdrop');
        var openBtn = document.getElementById('arena-rail-open');
        var closeBtn = document.getElementById('arena-rail-close');
        if (!rail || !backdrop || !openBtn) return;
        // Drawer behavior (and the inert lock) only applies below the 991.98px
        // breakpoint - on desktop the rail stays inline and must stay interactive.
        var drawerMq = window.matchMedia('(max-width: 991.98px)');
        function openRail() {
            if (!drawerMq.matches) return;
            rail.classList.add('is-open');
            backdrop.classList.add('is-open');
            rail.removeAttribute('inert');
            document.body.style.overflow = 'hidden';
        }
        function closeRail() {
            rail.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            if (drawerMq.matches) rail.setAttribute('inert', '');
            document.body.style.overflow = '';
        }
        function syncRailMode() {
            if (drawerMq.matches) {
                if (!rail.classList.contains('is-open')) rail.setAttribute('inert', '');
            } else {
                rail.removeAttribute('inert');
                rail.classList.remove('is-open');
                backdrop.classList.remove('is-open');
                document.body.style.overflow = '';
            }
        }
        syncRailMode();
        drawerMq.addEventListener('change', syncRailMode);
        openBtn.addEventListener('click', openRail);
        closeBtn && closeBtn.addEventListener('click', closeRail);
        backdrop.addEventListener('click', closeRail);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeRail();
        });
    })();

    // Hero carousel: rotates through announcement/competition slides, with per-slide countdowns.
    var carousel = document.getElementById('arena-carousel');
    if (carousel) {
        var slides = Array.prototype.slice.call(carousel.querySelectorAll('.nocturne-arena-slide'));
        var dots = Array.prototype.slice.call(carousel.querySelectorAll('.nocturne-arena-carousel__dot'));
        var prevBtn = document.getElementById('arena-carousel-prev');
        var nextBtn = document.getElementById('arena-carousel-next');
        var current = 0;
        var autoTimer = null;
        var interval = parseInt(carousel.getAttribute('data-interval'), 10) || 7000;

        var goTo = function (index) {
            index = (index + slides.length) % slides.length;
            slides[current].classList.remove('is-active');
            if (dots[current]) dots[current].classList.remove('is-active');
            current = index;
            slides[current].classList.add('is-active');
            if (dots[current]) dots[current].classList.add('is-active');
        };
        var next = function () { goTo(current + 1); };
        var prev = function () { goTo(current - 1); };
        var restartAuto = function () {
            clearInterval(autoTimer);
            if (slides.length > 1) autoTimer = setInterval(next, interval);
        };
        if (nextBtn) nextBtn.addEventListener('click', function () { next(); restartAuto(); });
        if (prevBtn) prevBtn.addEventListener('click', function () { prev(); restartAuto(); });
        dots.forEach(function (dot, i) { dot.addEventListener('click', function () { goTo(i); restartAuto(); }); });
        restartAuto();
        carousel.addEventListener('mouseenter', function () { clearInterval(autoTimer); });
        carousel.addEventListener('mouseleave', restartAuto);

        // Touch swipe (mobile has no visible nav arrows, this replaces them).
        var touchStartX = 0, touchStartY = 0, touching = false;
        carousel.addEventListener('touchstart', function (e) {
            var t = e.touches[0];
            touchStartX = t.clientX; touchStartY = t.clientY; touching = true;
            clearInterval(autoTimer);
        }, { passive: true });
        carousel.addEventListener('touchend', function (e) {
            if (!touching) return;
            touching = false;
            var t = e.changedTouches[0];
            var dx = t.clientX - touchStartX, dy = t.clientY - touchStartY;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) { dx < 0 ? next() : prev(); }
            restartAuto();
        }, { passive: true });

        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        slides.forEach(function (slide) {
            var timerEl = slide.querySelector('.nocturne-arena-announcement__timer');
            if (!timerEl) return;
            var endAt = parseInt(timerEl.getAttribute('data-countdown-end'), 10);
            var unitD = timerEl.querySelector('[data-unit="d"]');
            var unitH = timerEl.querySelector('[data-unit="h"]');
            var unitM = timerEl.querySelector('[data-unit="m"]');
            var unitS = timerEl.querySelector('[data-unit="s"]');
            var tick = function () {
                var diff = endAt - Date.now();
                if (diff <= 0) {
                    unitD.textContent = unitH.textContent = unitM.textContent = unitS.textContent = '00';
                    clearInterval(slideTimer);
                    return;
                }
                unitD.textContent = pad(Math.floor(diff / 86400000));
                unitH.textContent = pad(Math.floor((diff % 86400000) / 3600000));
                unitM.textContent = pad(Math.floor((diff % 3600000) / 60000));
                unitS.textContent = pad(Math.floor((diff % 60000) / 1000));
            };
            tick();
            var slideTimer = setInterval(tick, 1000);
        });
    }
})(jQuery);
</script>
@endpush
