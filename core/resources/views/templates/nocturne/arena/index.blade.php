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
                    <div class="nocturne-arena-composer">
                        <form method="post" action="{{ route('user.arena.store') }}" enctype="multipart/form-data" id="arena-composer-form">
                            @csrf
                            <div class="nocturne-arena-composer__top">
                                <span class="nocturne-arena-post__avatar">@if(auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->username, 0, 1)) }}@endif</span>
                                <textarea name="body" rows="1" maxlength="2000" placeholder="@lang('Share a win, ask for a competition, or say something...')" id="arena-composer-body">{{ old('body') }}</textarea>
                            </div>

                            <div class="nocturne-arena-composer__preview" id="arena-image-preview" hidden><img src="" alt=""><button type="button" id="arena-image-remove"><i class="ph-bold ph-x"></i></button></div>
                            <div class="nocturne-arena-composer__preview" id="arena-video-preview" hidden><video src="" controls muted></video><button type="button" id="arena-video-remove"><i class="ph-bold ph-x"></i></button></div>

                            <div class="nocturne-arena-composer__swatches" id="arena-swatches">
                                @foreach(\App\Models\ArenaPost::BACKGROUNDS as $swatch)
                                    <button type="button" class="nocturne-arena-swatch nocturne-arena-swatch--{{ $swatch }} {{ $loop->first ? 'is-active' : '' }}" data-swatch="{{ $swatch }}" aria-label="{{ $swatch }}"></button>
                                @endforeach
                                <input type="hidden" name="background_style" id="arena-background-input" value="{{ \App\Models\ArenaPost::BACKGROUNDS[0] }}">
                            </div>

                            <div class="nocturne-arena-composer__row">
                                <div class="nocturne-arena-type-pick">
                                    @foreach(['general' => ['General','ph-chat-circle-text'],'win' => ['Win','ph-trophy'],'challenge' => ['Challenge','ph-sword'],'question' => ['Question','ph-question']] as $key => $info)
                                        <label class="nocturne-arena-type-opt {{ $loop->first ? 'is-active' : '' }}">
                                            <input type="radio" name="type" value="{{ $key }}" {{ $loop->first ? 'checked' : '' }}>
                                            <i class="ph-fill {{ $info[1] }}"></i><span>{{ __($info[0]) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            @if(count($recentWins))
                                <div class="nocturne-arena-win-pick" id="arena-win-pick" hidden>
                                    <label>@lang('Attach a win (optional)')</label>
                                    <select name="linked_game_log_id">
                                        <option value="">@lang('None')</option>
                                        @foreach($recentWins as $log)
                                            <option value="{{ $log->id }}">{{ __($log->game->name ?? 'Game') }} &middot; {{ showAmount($log->win_amo) }} &middot; {{ showDateTime($log->created_at, 'M d') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="nocturne-arena-composer__foot">
                                <div class="nocturne-arena-attach-tools">
                                    <label class="nocturne-arena-attach-btn" title="@lang('Add a photo')"><i class="ph-fill ph-image-square"></i><input type="file" name="image" id="arena-image-input" accept=".png,.jpg,.jpeg,.webp" hidden></label>
                                    <label class="nocturne-arena-attach-btn" title="@lang('Add a video')"><i class="ph-fill ph-video-camera"></i><input type="file" name="video" id="arena-video-input" accept=".mp4,.mov,.webm" hidden></label>
                                </div>
                                <button type="submit" class="nocturne-arena-post-btn"><i class="ph-bold ph-paper-plane-tilt"></i>@lang('Post')</button>
                            </div>

                            @if(gs('arena_post_approval_required'))
                                <p class="nocturne-arena-composer__note"><i class="ph-fill ph-info"></i>@lang('Posts are reviewed by a moderator before they go live.')</p>
                            @endif
                        </form>
                    </div>
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
                        <a href="{{ route('arena.profile', auth()->user()->username) }}" class="nocturne-arena-widget__me">
                            <span class="nocturne-arena-post__avatar">@if(auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->username, 0, 1)) }}@endif</span>
                            <div>
                                <strong>{{ auth()->user()->username }}</strong>
                                @if($currentTier)<small><i class="ph-fill ph-crown-simple"></i>{{ __($currentTier->name) }}</small>@endif
                            </div>
                        </a>
                        @if($nextTier)
                            <div class="nocturne-arena-tier-progress">
                                <div class="nocturne-arena-tier-progress__track"><span style="width:{{ $tierProgressPercent }}%"></span></div>
                                <small>{{ showAmount(max($nextTier->min_wagered - $wagered, 0)) }} @lang('to') {{ __($nextTier->name) }}</small>
                            </div>
                        @endif
                        <a href="{{ route('user.arena.my-posts') }}" class="nocturne-arena-my-posts-link"><i class="ph-bold ph-list-bullets"></i>@lang('My Posts')</a>
                    </div>

                    <div class="nocturne-arena-widget">
                        <h3><i class="ph-fill ph-chart-bar"></i>@lang('Your Stats')</h3>
                        <div class="nocturne-arena-stat-row"><span>@lang('Wagered')</span><strong>{{ showAmount($userStats['total_invest']) }}</strong></div>
                        <div class="nocturne-arena-stat-row"><span>@lang('Won')</span><strong>{{ showAmount($userStats['total_win']) }}</strong></div>
                        <div class="nocturne-arena-stat-row"><span>@lang('Lost')</span><strong>{{ showAmount($userStats['total_loss']) }}</strong></div>
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
                    @forelse($recentSiteWins as $log)
                        <div class="nocturne-arena-widget-row">
                            <span class="nocturne-arena-widget-row__icon nocturne-arena-widget-row__icon--win"><i class="ph-fill ph-coin"></i></span>
                            <div>
                                <strong>{{ showAmount($log->win_amo) }}</strong>
                                <small>{{ $log->user->username ?? 'Someone' }} &middot; {{ __($log->game->name ?? 'a game') }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="nocturne-arena-widget-empty">@lang('No wins yet.')</p>
                    @endforelse
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
                                    <a href="{{ route('arena.profile', $suggested->username) }}" class="nocturne-arena-post__avatar">@if($suggested->avatarUrl())<img src="{{ $suggested->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($suggested->username, 0, 1)) }}@endif</a>
                                    <a href="{{ route('arena.profile', $suggested->username) }}"><strong>{{ $suggested->username }}</strong></a>
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

.nocturne-arena-post__identity a{color:inherit;text-decoration:none}
.nocturne-arena-post__identity strong:hover{color:var(--arena-accent)}
.nocturne-arena-mini-follow{flex:0 0 auto;padding:5px 11px;border:1px solid rgba(0,231,138,.4);border-radius:999px;background:rgba(0,231,138,.1);color:var(--arena-accent);font-size:10.5px;font-weight:800;cursor:pointer}
.nocturne-arena-mini-follow .label-following{display:none}
.nocturne-arena-mini-follow.is-following{border-color:var(--arena-line);background:transparent;color:var(--arena-muted)}
.nocturne-arena-mini-follow.is-following .label-follow{display:none}
.nocturne-arena-mini-follow.is-following .label-following{display:inline}

/* The feed is the whole page now - the right rail (profile/stats, live games, wins,
   trending, news, suggested follows) moved into an off-canvas menu opened by the list
   icon next to the bell, instead of permanently eating a column next to the feed. */
.nocturne-arena-layout{display:block;max-width:680px;margin:0 auto}
.nocturne-arena-feed{min-width:0}

.nocturne-arena-composer{margin-bottom:20px;padding:18px;border:1px solid var(--arena-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.16)}
.nocturne-arena-composer__top{display:flex;gap:12px;align-items:flex-start}
.nocturne-arena-composer textarea{flex:1;min-width:0;min-height:28px;max-height:260px;padding:10px 0;border:0;background:transparent;color:var(--arena-text);font:inherit;font-size:14.5px;resize:none;overflow-y:auto;outline:0}
.nocturne-arena-composer textarea::placeholder{color:#606986}
.nocturne-arena-composer__preview{position:relative;margin:12px 0 0 56px;border-radius:14px;overflow:hidden;max-width:320px}
.nocturne-arena-composer__preview img{display:block;width:100%;max-height:220px;object-fit:cover}
.nocturne-arena-composer__preview button{position:absolute;top:8px;right:8px;display:grid;place-items:center;width:28px;height:28px;border:0;border-radius:50%;background:rgba(5,8,18,.7);color:#fff;cursor:pointer}

.nocturne-arena-composer__swatches{display:flex;align-items:center;gap:8px;margin:12px 0 0 56px}
.nocturne-arena-swatch{width:22px;height:22px;border-radius:50%;border:2px solid transparent;cursor:pointer}
.nocturne-arena-swatch.is-active{border-color:#fff}
.nocturne-arena-swatch--violet{background:linear-gradient(135deg,#7c3aed,#4c1d95)}
.nocturne-arena-swatch--sunset{background:linear-gradient(135deg,#f97316,#be123c)}
.nocturne-arena-swatch--forest{background:linear-gradient(135deg,#00e78a,#065f46)}
.nocturne-arena-swatch--ocean{background:linear-gradient(135deg,#0ea5e9,#1e3a8a)}
.nocturne-arena-swatch--rose{background:linear-gradient(135deg,#f472b6,#9d174d)}

.nocturne-arena-composer__row{margin:14px 0 0 56px}
.nocturne-arena-type-pick{display:flex;flex-wrap:wrap;gap:8px}
.nocturne-arena-type-opt{position:relative}
.nocturne-arena-type-opt input{position:absolute;opacity:0;pointer-events:none}
.nocturne-arena-type-opt>i,.nocturne-arena-type-opt>span{pointer-events:none}
.nocturne-arena-type-opt{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border:1px solid var(--arena-line);border-radius:999px;background:var(--arena-bg);color:var(--arena-muted);font-size:12px;font-weight:750;cursor:pointer}
.nocturne-arena-type-opt i{font-size:14px}
.nocturne-arena-type-opt.is-active{border-color:rgba(0,231,138,.4);background:rgba(0,231,138,.1);color:var(--arena-accent)}

.nocturne-arena-win-pick{margin:14px 0 0 56px}
.nocturne-arena-win-pick label{display:block;margin-bottom:6px;color:var(--arena-muted);font-size:11px;font-weight:800;text-transform:uppercase}
.nocturne-arena-win-pick select{width:100%;max-width:360px;padding:10px 12px;border:1px solid var(--arena-line);border-radius:12px;background:var(--arena-bg);color:var(--arena-text);font:inherit;font-size:13px}

.nocturne-arena-composer__foot{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:16px 0 0 56px}
.nocturne-arena-attach-tools{display:flex;align-items:center;gap:8px}
.nocturne-arena-attach-btn{display:grid;place-items:center;width:40px;height:40px;border:1px solid var(--arena-line);border-radius:50%;background:var(--arena-bg);color:var(--arena-muted);font-size:19px;cursor:pointer;transition:color .15s ease,border-color .15s ease,background .15s ease}
.nocturne-arena-attach-btn:hover{color:var(--arena-accent);border-color:rgba(0,231,138,.4);background:rgba(0,231,138,.08)}
.nocturne-arena-composer__preview video{display:block;width:100%;max-height:260px;object-fit:cover;background:#000}
.nocturne-arena-post-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:0;border-radius:12px;background:var(--arena-accent);color:#062a23;font-size:13px;font-weight:800;cursor:pointer}
.nocturne-arena-composer__note{display:flex;align-items:center;gap:6px;margin:12px 0 0 56px;color:var(--arena-muted);font-size:11.5px}
.nocturne-arena-composer__note i{color:var(--arena-accent)}

.nocturne-arena-guest-cta{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;padding:16px 18px;border:1px solid var(--arena-line);border-radius:16px;background:var(--arena-panel);flex-wrap:wrap}
.nocturne-arena-guest-cta p{margin:0;color:var(--arena-muted);font-size:13px}
.nocturne-arena-guest-cta a{padding:9px 16px;border-radius:11px;background:var(--arena-accent);color:#062a23;font-size:12.5px;font-weight:800;text-decoration:none}

.nocturne-arena-list{display:grid;gap:14px}
.nocturne-arena-post{padding:18px;border:1px solid var(--arena-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.14)}
.nocturne-arena-post__head{display:flex;align-items:center;gap:11px;margin-bottom:12px}
.nocturne-arena-post__avatar{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:50%;background:rgba(0,231,138,.12);color:var(--arena-accent);font-size:15px;font-weight:800;overflow:hidden}
.nocturne-arena-post__avatar img{width:100%;height:100%;object-fit:cover}
.nocturne-arena-post__identity{flex:1;min-width:0;display:grid;gap:1px}
.nocturne-arena-post__identity strong{color:var(--arena-text);font-size:13.5px;font-weight:800}
.nocturne-arena-post__identity small{color:var(--arena-muted);font-size:11px}
.nocturne-arena-post__type{display:inline-flex;align-items:center;gap:5px;flex:none;padding:5px 11px;border-radius:999px;background:rgba(133,142,175,.12);color:var(--arena-muted);font-size:10.5px;font-weight:800}
.nocturne-arena-post__menu-wrap{position:relative;flex:0 0 auto}
.nocturne-arena-post__menu-btn{display:grid;place-items:center;width:30px;height:30px;border:0;border-radius:50%;background:transparent;color:var(--arena-muted);font-size:17px;cursor:pointer}
.nocturne-arena-post__menu-btn:hover{background:rgba(255,255,255,.06);color:var(--arena-text)}
.nocturne-arena-post__menu{position:absolute;top:34px;right:0;z-index:5;display:grid;min-width:150px;padding:6px;border:1px solid var(--arena-line);border-radius:13px;background:var(--arena-panel);box-shadow:0 14px 30px rgba(0,0,0,.35)}
.nocturne-arena-post__menu[hidden]{display:none}
.nocturne-arena-post__menu button{display:flex;align-items:center;gap:9px;padding:9px 10px;border:0;border-radius:9px;background:transparent;color:var(--arena-text);font-size:12.5px;font-weight:700;text-align:left;cursor:pointer}
.nocturne-arena-post__menu button:hover{background:rgba(255,255,255,.06)}
.nocturne-arena-post__menu .nocturne-arena-post__delete{color:#ff6b86}
.nocturne-arena-post__edit-form{display:grid;gap:8px;margin:0 0 10px}
.nocturne-arena-post__edit-form textarea{width:100%;padding:10px;border:1px solid var(--arena-line);border-radius:12px;background:var(--arena-bg);color:var(--arena-text);font:inherit;font-size:13.5px;resize:vertical}
.nocturne-arena-post__edit-actions{display:flex;justify-content:flex-end;gap:8px}
.nocturne-arena-post__edit-actions button{min-height:34px;padding:0 16px;border:0;border-radius:10px;font-size:12px;font-weight:800;cursor:pointer}
.nocturne-arena-post__edit-cancel{background:rgba(255,255,255,.06);color:var(--arena-muted)}
.nocturne-arena-post__edit-actions button[type=submit]{background:var(--arena-accent);color:#062a1d}
.nocturne-arena-post__body-wrap{margin:0 0 4px}
.nocturne-arena-post__body{margin:0;color:#d7dafa;font-size:14px;line-height:1.65;white-space:pre-wrap;overflow-wrap:anywhere}
.nocturne-arena-post__body.is-clamped{max-height:4.95em;overflow:hidden}
.nocturne-arena-seemore{display:inline-block;margin-top:4px;padding:0;border:0;background:none;color:var(--arena-accent);font-size:12.5px;font-weight:800;cursor:pointer}
.nocturne-arena-post__image{position:relative;margin-top:10px;aspect-ratio:16/10;border-radius:14px;overflow:hidden;cursor:zoom-in}
.nocturne-arena-post__image img{display:block;width:100%;height:100%;object-fit:cover}
.nocturne-arena-post__video{position:relative;margin-top:10px;aspect-ratio:16/10;border-radius:14px;overflow:hidden;background:#000}
.nocturne-arena-post__video video{display:block;width:100%;height:100%;object-fit:cover}
.nocturne-arena-post__mute-btn{position:absolute;right:10px;bottom:10px;z-index:1;display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:50%;background:rgba(6,8,18,.65);color:#fff;font-size:16px;cursor:pointer}
.nocturne-arena-lightbox{position:fixed;inset:0;z-index:1400;display:grid;place-items:center;padding:24px;background:rgba(4,6,16,.92);backdrop-filter:blur(3px)}
.nocturne-arena-lightbox[hidden]{display:none}
.nocturne-arena-lightbox img{max-width:100%;max-height:92vh;border-radius:10px;object-fit:contain}
.nocturne-arena-lightbox__close{position:absolute;top:18px;right:18px;display:grid;place-items:center;width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,.1);color:#fff;font-size:18px;cursor:pointer}
.nocturne-arena-post__colored{display:grid;place-items:center;min-height:140px;padding:24px;border-radius:16px;text-align:center}
.nocturne-arena-post__colored p{margin:0;color:#fff;font-size:17px;font-weight:800;line-height:1.5;overflow-wrap:anywhere}
.nocturne-arena-post__colored--violet{background:linear-gradient(135deg,#7c3aed,#4c1d95)}
.nocturne-arena-post__colored--sunset{background:linear-gradient(135deg,#f97316,#be123c)}
.nocturne-arena-post__colored--forest{background:linear-gradient(135deg,#00e78a,#065f46)}
.nocturne-arena-post__colored--ocean{background:linear-gradient(135deg,#0ea5e9,#1e3a8a)}
.nocturne-arena-post__colored--rose{background:linear-gradient(135deg,#f472b6,#9d174d)}
.nocturne-arena-post__win{display:flex;align-items:center;gap:12px;margin-top:12px;padding:13px 15px;border:1px solid rgba(0,231,138,.22);border-radius:14px;background:rgba(0,231,138,.07)}
.nocturne-arena-post__win i{color:var(--arena-accent);font-size:22px}
.nocturne-arena-post__win strong{display:block;color:var(--arena-text);font-size:13.5px}
.nocturne-arena-post__win small{color:var(--arena-muted);font-size:11.5px}

.nocturne-arena-empty{display:grid;justify-items:center;gap:8px;padding:50px 20px;border:1px dashed var(--arena-line);border-radius:18px;color:var(--arena-muted);text-align:center}
.nocturne-arena-empty i{color:var(--arena-accent);font-size:28px}
.nocturne-arena-empty h3{margin:0;color:var(--arena-text);font-size:16px}

.nocturne-arena-loadmore{display:flex;justify-content:center;margin-top:20px}
#arena-load-more{padding:12px 26px;border:1px solid var(--arena-line);border-radius:14px;background:var(--arena-panel);color:var(--arena-text);font-size:13px;font-weight:800;cursor:pointer}
#arena-load-more:hover{border-color:rgba(0,231,138,.4);color:var(--arena-accent)}
#arena-load-more:disabled{opacity:.5;cursor:default}

/* Off-canvas drawer: the rail slides in from the right over a backdrop instead of sitting
   permanently in the layout. Fixed to the viewport so it scrolls independently of the page. */
.nocturne-arena-rail-backdrop{position:fixed;inset:0;z-index:59;background:rgba(5,8,18,.6);backdrop-filter:blur(2px);opacity:0;visibility:hidden;transition:opacity .25s ease,visibility .25s ease}
.nocturne-arena-rail-backdrop.is-open{opacity:1;visibility:visible}
.nocturne-arena-rail{position:fixed;top:0;right:0;z-index:60;display:flex;flex-direction:column;gap:16px;width:360px;max-width:88vw;height:100%;padding:18px 16px 24px;overflow-y:auto;background:#0d1225;border-left:1px solid var(--arena-line);box-shadow:-24px 0 60px rgba(0,0,0,.45);transform:translateX(100%);transition:transform .28s cubic-bezier(.2,.8,.2,1)}
.nocturne-arena-rail.is-open{transform:translateX(0)}
.nocturne-arena-rail__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:2px}
.nocturne-arena-rail__head strong{color:var(--arena-text);font-size:16px;font-weight:850}
.nocturne-arena-rail__close{display:grid;place-items:center;width:34px;height:34px;border:1px solid var(--arena-line);border-radius:10px;background:var(--arena-panel);color:var(--arena-muted);font-size:15px;cursor:pointer}
.nocturne-arena-rail__close:hover{color:var(--arena-accent);border-color:rgba(0,231,138,.4)}
.nocturne-arena-widget{padding:18px;border:1px solid var(--arena-line);border-radius:18px;background:var(--arena-panel)}
.nocturne-arena-widget h3{display:flex;align-items:center;gap:8px;margin:0 0 8px;color:var(--arena-text);font-size:14.5px;font-weight:800}
.nocturne-arena-widget h3 i{color:var(--arena-accent)}

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
.nocturne-arena-announcement__content h2{margin:0 0 8px;color:#fff;font-size:clamp(24px,3.6vw,38px);font-weight:950;letter-spacing:-.01em;line-height:1.1;text-transform:uppercase}
.nocturne-arena-announcement__timer{display:flex;gap:9px;margin-bottom:16px}
.nocturne-arena-timer-unit{display:grid;justify-items:center;gap:3px;min-width:58px;padding:10px 8px;border:1px solid rgba(0,231,138,.3);border-radius:13px;background:rgba(0,231,138,.06);box-shadow:0 0 18px rgba(0,231,138,.08) inset}
.nocturne-arena-timer-unit strong{color:#fff;font-size:21px;font-weight:950;font-variant-numeric:tabular-nums;line-height:1}
.nocturne-arena-timer-unit small{display:flex;align-items:center;gap:3px;color:var(--arena-muted);font-size:8.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.nocturne-arena-timer-unit small i{color:var(--arena-accent);font-size:9px}
.nocturne-arena-announcement__foot{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.nocturne-arena-announcement__reward{display:inline-flex;align-items:center;gap:6px;color:var(--arena-accent);font-size:13px;font-weight:800}
.nocturne-arena-announcement__btn{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border:0;border-radius:12px;background:var(--arena-accent);color:#062a23;font-size:13px;font-weight:900;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease}
.nocturne-arena-announcement__btn:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,231,138,.3)}
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
    .nocturne-arena-announcement__foot{gap:8px}
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

/* Vote / reaction / comment bar */
.nocturne-arena-post__bar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--arena-line)}
.nocturne-arena-vote{display:flex;align-items:center;gap:8px}
.nocturne-arena-vote.is-disabled{opacity:.5}
.nocturne-arena-vote__btn{display:grid;place-items:center;width:30px;height:30px;border:1px solid var(--arena-line);border-radius:9px;background:var(--arena-bg);color:var(--arena-muted);font-size:13px;cursor:pointer}
.nocturne-arena-vote__btn:hover:not(:disabled){border-color:rgba(0,231,138,.4);color:var(--arena-accent)}
.nocturne-arena-vote__btn--up.is-active{background:rgba(0,231,138,.14);border-color:rgba(0,231,138,.5);color:var(--arena-accent)}
.nocturne-arena-vote__btn--down.is-active{background:rgba(255,92,122,.14);border-color:rgba(255,92,122,.5);color:#ff5c7a}
.nocturne-arena-vote__score{min-width:22px;text-align:center;color:var(--arena-text);font-size:13px;font-weight:800}

.nocturne-arena-reactions{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.nocturne-arena-reactions.is-disabled{opacity:.5}
.nocturne-arena-reaction{display:inline-flex;align-items:center;gap:4px;padding:5px 8px;border:1px solid transparent;border-radius:999px;background:transparent;font-size:14px;cursor:pointer}
.nocturne-arena-reaction:hover:not(:disabled){background:var(--arena-bg)}
.nocturne-arena-reaction.is-active{border-color:rgba(0,231,138,.4);background:rgba(0,231,138,.1)}
.nocturne-arena-reaction small{color:var(--arena-muted);font-size:10.5px;font-weight:800}
.nocturne-arena-reaction small.is-zero{display:none}

.nocturne-arena-comment-toggle{display:inline-flex;align-items:center;gap:6px;margin-left:auto;padding:6px 12px;border:1px solid var(--arena-line);border-radius:999px;background:transparent;color:var(--arena-muted);font-size:12.5px;font-weight:750;cursor:pointer}
.nocturne-arena-comment-toggle:hover{border-color:rgba(0,231,138,.4);color:var(--arena-accent)}

.nocturne-arena-comments-wrap{margin-top:14px;padding-top:14px;border-top:1px solid var(--arena-line)}
.nocturne-arena-comment-form{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.nocturne-arena-comment-form input,.nocturne-arena-reply-form input{flex:1;min-width:0;padding:9px 13px;border:1px solid var(--arena-line);border-radius:999px;background:var(--arena-bg);color:var(--arena-text);font:inherit;font-size:12.5px;outline:0}
.nocturne-arena-comment-form button,.nocturne-arena-reply-form button{flex:0 0 auto;display:grid;place-items:center;width:32px;height:32px;border:0;border-radius:50%;background:var(--arena-accent);color:#062a23;cursor:pointer}
.nocturne-arena-reply-form{display:flex;align-items:center;gap:8px;margin:8px 0 0 44px}

.nocturne-arena-comment-list{display:grid;gap:12px}
.nocturne-arena-comment-empty{margin:0;color:var(--arena-muted);font-size:12.5px;text-align:center;padding:10px 0}
.nocturne-arena-comment{display:flex;gap:10px}
.nocturne-arena-comment--reply{margin-top:8px}
.nocturne-arena-comment__avatar{display:grid;place-items:center;flex:0 0 32px;width:32px;height:32px;border-radius:50%;background:rgba(0,231,138,.12);color:var(--arena-accent);font-size:12px;font-weight:800;overflow:hidden}
.nocturne-arena-comment__avatar img{width:100%;height:100%;object-fit:cover}
.nocturne-arena-comment__body{flex:1;min-width:0}
.nocturne-arena-comment__bubble{display:inline-block;padding:9px 13px;border-radius:14px;background:var(--arena-bg)}
.nocturne-arena-comment__bubble strong{display:block;margin-bottom:2px;color:var(--arena-text);font-size:12.5px;font-weight:800}
.nocturne-arena-comment__bubble p{margin:0;color:#d7dafa;font-size:13px;line-height:1.5;overflow-wrap:anywhere}
.nocturne-arena-comment__meta{display:flex;align-items:center;gap:10px;margin:5px 0 0 13px}
.nocturne-arena-comment__meta span{color:var(--arena-muted);font-size:11px}
.nocturne-arena-comment__reply-btn{border:0;background:transparent;color:var(--arena-muted);font-size:11px;font-weight:800;cursor:pointer;padding:0}
.nocturne-arena-comment__reply-btn:hover{color:var(--arena-accent)}
.nocturne-arena-comment__replies{display:grid;gap:8px;margin-left:30px}

@media(max-width:575.98px){
    .nocturne-arena-rail{width:100%;max-width:100vw}
    .nocturne-arena-page{padding:16px 14px 40px}
    .nocturne-arena-composer{padding:14px}
    .nocturne-arena-composer__preview,.nocturne-arena-composer__swatches,.nocturne-arena-composer__row,.nocturne-arena-win-pick,.nocturne-arena-composer__foot,.nocturne-arena-composer__note{margin-left:0}
    .nocturne-arena-composer__foot{flex-wrap:wrap}
    .nocturne-arena-post{padding:14px}
    .nocturne-arena-crumb-row{flex-wrap:wrap}
    .nocturne-arena-announcement__content{padding:18px}
    .nocturne-arena-announcement__foot{gap:10px}
    .nocturne-arena-bell-panel{right:-14px;width:290px}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    // Facebook/X-style auto-growing composer - no manual drag handle, it just grows with content.
    var composerBody = document.getElementById('arena-composer-body');
    function autoGrow() { composerBody.style.height = 'auto'; composerBody.style.height = Math.min(composerBody.scrollHeight, 260) + 'px'; }
    if (composerBody) { composerBody.addEventListener('input', autoGrow); autoGrow(); }

    $('#arena-image-input').on('change', function() {
        var file = this.files[0];
        if (!file) return;
        $('#arena-video-input').val('');
        $('#arena-video-preview').prop('hidden', true).find('video').attr('src', '');
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#arena-image-preview img').attr('src', e.target.result);
            $('#arena-image-preview').prop('hidden', false);
            $('#arena-swatches').hide();
        };
        reader.readAsDataURL(file);
    });
    $('#arena-image-remove').on('click', function() {
        $('#arena-image-input').val('');
        $('#arena-image-preview').prop('hidden', true).find('img').attr('src', '');
        $('#arena-swatches').show();
    });
    $('#arena-video-input').on('change', function() {
        var file = this.files[0];
        if (!file) return;
        $('#arena-image-input').val('');
        $('#arena-image-preview').prop('hidden', true).find('img').attr('src', '');
        $('#arena-video-preview img').remove();
        var url = URL.createObjectURL(file);
        $('#arena-video-preview video').attr('src', url);
        $('#arena-video-preview').prop('hidden', false);
        $('#arena-swatches').hide();
    });
    $('#arena-video-remove').on('click', function() {
        $('#arena-video-input').val('');
        $('#arena-video-preview').prop('hidden', true).find('video').attr('src', '');
        $('#arena-swatches').show();
    });

    $('.nocturne-arena-swatch').on('click', function() {
        $('.nocturne-arena-swatch').removeClass('is-active');
        $(this).addClass('is-active');
        $('#arena-background-input').val($(this).data('swatch'));
    });

    $('.nocturne-arena-type-opt input').on('change', function() {
        $('.nocturne-arena-type-opt').removeClass('is-active');
        $(this).closest('.nocturne-arena-type-opt').addClass('is-active');
        $('#arena-win-pick').prop('hidden', $(this).val() !== 'win');
    });

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

    // Clamp long post text to ~3 lines with a "See more" toggle - only added when the text
    // actually overflows that height, never on short posts.
    function setupSeeMore(scope) {
        (scope || document).querySelectorAll('[data-seemore]:not([data-seemore-ready])').forEach(function(body) {
            body.setAttribute('data-seemore-ready', '1');
            body.classList.add('is-clamped');
            var button = body.nextElementSibling;
            if (!button || !button.classList.contains('nocturne-arena-seemore')) return;
            if (body.scrollHeight > body.clientHeight + 2) {
                button.hidden = false;
                button.addEventListener('click', function() {
                    var expanded = !body.classList.contains('is-clamped');
                    body.classList.toggle('is-clamped', expanded);
                    button.textContent = expanded ? "{{ __('See more') }}" : "{{ __('See less') }}";
                });
                button.textContent = "{{ __('See more') }}";
            } else {
                body.classList.remove('is-clamped');
            }
        });
    }
    setupSeeMore(document);

    var csrfToken = "{{ csrf_token() }}";
    var voteUrlTpl = "{{ route('user.arena.vote', ['post' => '__ID__']) }}";
    var reactUrl = "{{ route('user.arena.react') }}";
    var commentsIndexUrlTpl = "{{ route('arena.comments.index', ['post' => '__ID__']) }}";
    var commentsStoreUrlTpl = "{{ route('user.arena.comments.store', ['post' => '__ID__']) }}";

    // Post menu (Share / Edit / Delete) - event-delegated since posts load via AJAX.
    $(document).on('click', '.nocturne-arena-post__menu-btn', function(event) {
        event.stopPropagation();
        var menu = $(this).siblings('.nocturne-arena-post__menu');
        var isHidden = menu.prop('hidden');
        $('.nocturne-arena-post__menu').prop('hidden', true);
        menu.prop('hidden', !isHidden);
    });
    $(document).on('click', function() { $('.nocturne-arena-post__menu').prop('hidden', true); });
    $(document).on('click', '.nocturne-arena-post__menu', function(event) { event.stopPropagation(); });

    $(document).on('click', '.nocturne-arena-post__share', function() {
        var url = $(this).data('url');
        if (navigator.share) {
            navigator.share({ url: url }).catch(function() {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                window.notify ? notify('success', "{{ __('Link copied to clipboard') }}") : alert("{{ __('Link copied to clipboard') }}");
            });
        } else {
            window.prompt("{{ __('Copy this link:') }}", url);
        }
    });

    $(document).on('click', '.nocturne-arena-post__edit', function() {
        var article = $(this).closest('.nocturne-arena-post');
        article.find('.nocturne-arena-post__edit-form').prop('hidden', false);
        article.find('.nocturne-arena-post__body-wrap, .nocturne-arena-post__colored').hide();
        article.find('.nocturne-arena-post__edit-form textarea').trigger('focus');
    });
    $(document).on('click', '.nocturne-arena-post__edit-cancel', function() {
        var article = $(this).closest('.nocturne-arena-post');
        article.find('.nocturne-arena-post__edit-form').prop('hidden', true);
        article.find('.nocturne-arena-post__body-wrap, .nocturne-arena-post__colored').show();
    });
    $(document).on('submit', '.nocturne-arena-post__edit-form', function(event) {
        event.preventDefault();
        var form = $(this);
        $.post(form.attr('action'), form.serialize()).done(function() { location.reload(); });
    });

    $(document).on('click', '.nocturne-arena-post__delete', function() {
        if (!confirm("{{ __('Delete this post? This cannot be undone.') }}")) return;
        var url = $(this).data('url');
        var article = $(this).closest('.nocturne-arena-post');
        $.ajax({ url: url, method: 'DELETE', data: { _token: csrfToken } })
            .done(function() { article.fadeOut(200, function() { article.remove(); }); });
    });

    // Fixed-size media frame - click a post image to open it full size.
    var lightbox = $('<div class="nocturne-arena-lightbox" hidden><button type="button" class="nocturne-arena-lightbox__close"><i class="ph-bold ph-x"></i></button><img src="" alt=""></div>').appendTo('body');
    $(document).on('click', '[data-lightbox] img', function() {
        lightbox.find('img').attr('src', $(this).attr('src'));
        lightbox.prop('hidden', false);
    });
    lightbox.on('click', function(event) {
        if (event.target === this || $(event.target).closest('.nocturne-arena-lightbox__close').length) {
            lightbox.prop('hidden', true).find('img').attr('src', '');
        }
    });

    // Feed videos: muted + play only while actually visible (Facebook/Instagram pattern),
    // tap the speaker to unmute. Never plays with sound unless the viewer asks for it.
    if ('IntersectionObserver' in window) {
        var videoObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                var video = entry.target;
                if (entry.isIntersecting) { video.play().catch(function() {}); }
                else { video.pause(); }
            });
        }, { threshold: 0.6 });
        function observeFeedVideos(scope) {
            (scope || document).querySelectorAll('[data-feed-video]:not([data-observed])').forEach(function(video) {
                video.setAttribute('data-observed', '1');
                videoObserver.observe(video);
            });
        }
        observeFeedVideos(document);
        window.arenaObserveFeedVideos = observeFeedVideos;
    }
    $(document).on('click', '.nocturne-arena-post__mute-btn', function() {
        var video = $(this).siblings('[data-feed-video]')[0];
        if (!video) return;
        video.muted = !video.muted;
        $(this).find('i').attr('class', video.muted ? 'ph-fill ph-speaker-x' : 'ph-fill ph-speaker-high');
    });

    // Upvote / downvote
    $(document).on('click', '.nocturne-arena-vote__btn:not(:disabled)', function() {
        var btn = $(this);
        var article = btn.closest('.nocturne-arena-post');
        var postId = article.data('post-id');
        var value = btn.data('vote');
        $.post(voteUrlTpl.replace('__ID__', postId), { value: value, _token: csrfToken })
            .done(function(res) {
                article.find('.nocturne-arena-vote__score').text(res.upvotes - res.downvotes);
                article.find('.nocturne-arena-vote__btn').removeClass('is-active');
                if (res.mine === 1) article.find('.nocturne-arena-vote__btn--up').addClass('is-active');
                if (res.mine === -1) article.find('.nocturne-arena-vote__btn--down').addClass('is-active');
            });
    });

    // Emoji reactions
    $(document).on('click', '.nocturne-arena-reaction:not(:disabled)', function() {
        var btn = $(this);
        var wrap = btn.closest('.nocturne-arena-reactions');
        var emoji = btn.data('emoji');
        $.post(reactUrl, { reactable_type: wrap.data('reactable-type'), reactable_id: wrap.data('reactable-id'), emoji: emoji, _token: csrfToken })
            .done(function(res) {
                wrap.find('.nocturne-arena-reaction').each(function() {
                    var e = $(this).data('emoji');
                    var count = (res.counts && res.counts[e]) || 0;
                    $(this).toggleClass('is-active', res.mine === e);
                    $(this).find('small').text(count).toggleClass('is-zero', count === 0);
                });
            });
    });

    // Comment toggle: lazy-load on first open, then just show/hide
    $(document).on('click', '.nocturne-arena-comment-toggle', function() {
        var btn = $(this);
        var article = btn.closest('.nocturne-arena-post');
        var wrap = article.find('.nocturne-arena-comments-wrap');
        if (!wrap.data('loaded')) {
            $.ajax({ url: commentsIndexUrlTpl.replace('__ID__', btn.data('post-id')), headers: { 'Accept': 'application/json' } })
                .done(function(res) {
                    wrap.html(res.html).data('loaded', true).prop('hidden', false);
                });
        } else {
            wrap.prop('hidden', !wrap.prop('hidden'));
        }
    });

    // Top-level comment submit
    $(document).on('submit', '.nocturne-arena-comment-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var postId = form.data('post-id');
        var input = form.find('input[name=body]');
        var body = $.trim(input.val());
        if (!body) return;
        $.post(commentsStoreUrlTpl.replace('__ID__', postId), { body: body, _token: csrfToken })
            .done(function(res) {
                form.siblings('.nocturne-arena-comment-list').find('.nocturne-arena-comment-empty').remove();
                form.siblings('.nocturne-arena-comment-list').append(res.html);
                input.val('');
                form.closest('.nocturne-arena-post').find('.nocturne-arena-comment-count').text(res.comments_count);
            });
    });

    // Reply toggle
    $(document).on('click', '.nocturne-arena-comment__reply-btn', function() {
        var btn = $(this);
        var existing = btn.closest('.nocturne-arena-comment__body').children('.nocturne-arena-reply-form');
        if (existing.length) { existing.remove(); return; }
        var form = $('<form class="nocturne-arena-reply-form"><input type="text" name="body" maxlength="1000" placeholder="{{ __('Write a reply...') }}"><button type="submit"><i class="ph-bold ph-paper-plane-tilt"></i></button></form>');
        form.data('parent-id', btn.data('reply-to'));
        btn.closest('.nocturne-arena-comment__meta').after(form);
        form.find('input').trigger('focus');
    });

    // Reply submit
    $(document).on('submit', '.nocturne-arena-reply-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var input = form.find('input[name=body]');
        var body = $.trim(input.val());
        if (!body) return;
        var postId = form.closest('.nocturne-arena-post').data('post-id');
        var parentId = form.data('parent-id');
        $.post(commentsStoreUrlTpl.replace('__ID__', postId), { body: body, parent_id: parentId, _token: csrfToken })
            .done(function(res) {
                $('[data-replies-for="' + parentId + '"]').append(res.html);
                form.remove();
                $('.nocturne-arena-post[data-post-id="' + postId + '"]').find('.nocturne-arena-comment-count').text(res.comments_count);
            });
    });

    // Mini follow button on each post's author
    $(document).on('click', '.nocturne-arena-mini-follow', function() {
        var btn = $(this);
        var userId = btn.data('user-id');
        var following = btn.data('following') == '1';
        var url = "{{ route('user.arena.follow.store', ['user' => '__ID__']) }}".replace('__ID__', userId);
        $.ajax({ url: url, method: following ? 'DELETE' : 'POST', data: { _token: csrfToken } })
            .done(function(res) {
                $('.nocturne-arena-mini-follow[data-user-id="' + userId + '"]').toggleClass('is-following', res.following).data('following', res.following ? '1' : '0');
            });
    });

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
        function openRail() {
            rail.classList.add('is-open');
            backdrop.classList.add('is-open');
            rail.removeAttribute('inert');
            document.body.style.overflow = 'hidden';
        }
        function closeRail() {
            rail.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            rail.setAttribute('inert', '');
            document.body.style.overflow = '';
        }
        rail.setAttribute('inert', '');
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
