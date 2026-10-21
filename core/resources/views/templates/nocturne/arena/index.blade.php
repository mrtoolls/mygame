@extends('Template::layouts.frontend')
@section('content')
    <div class="nocturne-arena-page">
        <div class="nocturne-arena-crumb-row">
            <div class="nocturne-arena-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Arena')</span></div>
            @auth
                <div class="nocturne-arena-bell-wrap">
                    <button type="button" class="nocturne-arena-bell" id="arena-bell"><i class="ph-fill ph-bell"></i><span class="nocturne-arena-bell__count" id="arena-bell-count" hidden>0</span></button>
                    <div class="nocturne-arena-bell-panel" id="arena-bell-panel" hidden>
                        <div class="nocturne-arena-bell-panel__head"><strong>@lang('Notifications')</strong><button type="button" id="arena-bell-markread">@lang('Mark all read')</button></div>
                        <div class="nocturne-arena-bell-panel__list" id="arena-bell-list"><p class="nocturne-arena-notif-empty">@lang('Loading…')</p></div>
                    </div>
                </div>
            @endauth
        </div>

        <div class="nocturne-arena-layout">
            <div class="nocturne-arena-feed">
                @if($announcement)
                    <div class="nocturne-arena-announcement">
                        @if($announcement->imageUrl())<img class="nocturne-arena-announcement__bg" src="{{ $announcement->imageUrl() }}" alt=""><div class="nocturne-arena-announcement__shade"></div>@endif
                        <div class="nocturne-arena-announcement__content">
                            @if($announcement->badge_label)<span class="nocturne-arena-announcement__badge"><i class="ph-fill ph-megaphone"></i>{{ __($announcement->badge_label) }}</span>@endif
                            <h2>{{ __($announcement->title) }}</h2>
                            @if($announcement->description)<p>{{ __($announcement->description) }}</p>@endif
                            <div class="nocturne-arena-announcement__foot">
                                @if($announcement->reward_label)<span class="nocturne-arena-announcement__reward"><i class="ph-fill ph-trophy"></i>{{ __($announcement->reward_label) }}</span>@endif
                                @if($announcement->ends_at)<span class="nocturne-arena-announcement__countdown" id="arena-announcement-countdown" data-countdown-end="{{ $announcement->ends_at->timestamp * 1000 }}">--:--:--</span>@endif
                                @if($announcement->button_label && $announcement->button_url)<a href="{{ $announcement->button_url }}" class="nocturne-arena-announcement__btn">{{ __($announcement->button_label) }}</a>@endif
                            </div>
                        </div>
                    </div>
                @endif

                @auth
                    <div class="nocturne-arena-composer">
                        <form method="post" action="{{ route('user.arena.store') }}" enctype="multipart/form-data" id="arena-composer-form">
                            @csrf
                            <div class="nocturne-arena-composer__top">
                                <span class="nocturne-arena-post__avatar">@if(auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->username, 0, 1)) }}@endif</span>
                                <textarea name="body" rows="2" maxlength="2000" placeholder="@lang('Share a win, ask for a competition, or say something...')" id="arena-composer-body">{{ old('body') }}</textarea>
                            </div>

                            <div class="nocturne-arena-composer__preview" id="arena-image-preview" hidden><img src="" alt=""><button type="button" id="arena-image-remove"><i class="ph-bold ph-x"></i></button></div>

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
                                <label class="nocturne-arena-image-btn"><i class="ph-bold ph-image"></i>@lang('Photo')<input type="file" name="image" id="arena-image-input" accept=".png,.jpg,.jpeg,.webp" hidden></label>
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

            <aside class="nocturne-arena-rail">
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

.nocturne-arena-layout{display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start}
.nocturne-arena-feed{min-width:0}

.nocturne-arena-composer{margin-bottom:20px;padding:18px;border:1px solid var(--arena-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.16)}
.nocturne-arena-composer__top{display:flex;gap:12px;align-items:flex-start}
.nocturne-arena-composer textarea{flex:1;min-width:0;min-height:44px;padding:10px 0;border:0;background:transparent;color:var(--arena-text);font:inherit;font-size:14.5px;resize:vertical;outline:0}
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
.nocturne-arena-image-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;border:1px solid var(--arena-line);border-radius:11px;background:var(--arena-bg);color:var(--arena-muted);font-size:12.5px;font-weight:750;cursor:pointer}
.nocturne-arena-image-btn:hover{color:var(--arena-accent);border-color:rgba(0,231,138,.4)}
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
.nocturne-arena-post__body{margin:0 0 4px;color:#d7dafa;font-size:14px;line-height:1.65;white-space:pre-wrap;overflow-wrap:anywhere}
.nocturne-arena-post__image{margin-top:10px;border-radius:14px;overflow:hidden}
.nocturne-arena-post__image img{display:block;width:100%;max-height:420px;object-fit:cover}
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

.nocturne-arena-rail{display:grid;gap:16px}
.nocturne-arena-widget{padding:18px;border:1px solid var(--arena-line);border-radius:18px;background:var(--arena-panel)}
.nocturne-arena-widget h3{display:flex;align-items:center;gap:8px;margin:0 0 8px;color:var(--arena-text);font-size:14.5px;font-weight:800}
.nocturne-arena-widget h3 i{color:var(--arena-accent)}

/* Announcement card */
.nocturne-arena-announcement{position:relative;overflow:hidden;margin-bottom:20px;border-radius:22px;border:1px solid var(--arena-line);background:radial-gradient(ellipse at 80% 0,rgba(0,231,138,.2),transparent 36%),linear-gradient(120deg,#171d37,#11162b 70%);box-shadow:0 18px 50px rgba(0,0,0,.2)}
.nocturne-arena-announcement__bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.nocturne-arena-announcement__shade{position:absolute;inset:0;background:linear-gradient(100deg,rgba(5,8,18,.88),rgba(5,8,18,.4))}
.nocturne-arena-announcement__content{position:relative;z-index:1;padding:22px}
.nocturne-arena-announcement__badge{display:inline-flex;align-items:center;gap:6px;margin-bottom:10px;padding:6px 12px;border-radius:999px;background:rgba(0,231,138,.16);color:var(--arena-accent);font-size:11px;font-weight:800}
.nocturne-arena-announcement__content h2{margin:0 0 6px;color:#fff;font-size:clamp(18px,2.2vw,24px);font-weight:850}
.nocturne-arena-announcement__content p{margin:0 0 14px;color:#c7cbea;font-size:13.5px;max-width:560px}
.nocturne-arena-announcement__foot{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.nocturne-arena-announcement__reward{display:inline-flex;align-items:center;gap:6px;color:var(--arena-accent);font-size:13px;font-weight:800}
.nocturne-arena-announcement__countdown{padding:7px 13px;border-radius:10px;background:rgba(0,0,0,.35);color:#fff;font-size:13px;font-weight:800;font-variant-numeric:tabular-nums}
.nocturne-arena-announcement__btn{padding:9px 18px;border-radius:11px;background:var(--arena-accent);color:#062a23;font-size:13px;font-weight:800;text-decoration:none}

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

@media(max-width:991.98px){
    .nocturne-arena-layout{grid-template-columns:1fr}
    .nocturne-arena-rail{order:3}
}
@media(max-width:575.98px){
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
    $('#arena-image-input').on('change', function() {
        var file = this.files[0];
        if (!file) return;
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
                if (res.hasMore) {
                    btn.data('next-page', page + 1).prop('disabled', false).text("{{ __('Load More') }}");
                } else {
                    btn.remove();
                }
            })
            .fail(function() { btn.prop('disabled', false).text("{{ __('Load More') }}"); });
    });

    var csrfToken = "{{ csrf_token() }}";
    var voteUrlTpl = "{{ route('user.arena.vote', ['post' => '__ID__']) }}";
    var reactUrl = "{{ route('user.arena.react') }}";
    var commentsIndexUrlTpl = "{{ route('arena.comments.index', ['post' => '__ID__']) }}";
    var commentsStoreUrlTpl = "{{ route('user.arena.comments.store', ['post' => '__ID__']) }}";

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

    // Announcement countdown
    var countdownEl = document.getElementById('arena-announcement-countdown');
    if (countdownEl) {
        var endAt = parseInt(countdownEl.getAttribute('data-countdown-end'), 10);
        var tick = function() {
            var diff = endAt - Date.now();
            if (diff <= 0) { countdownEl.textContent = "{{ __('Ended') }}"; return; }
            var h = Math.floor(diff / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var s = Math.floor((diff % 60000) / 1000);
            var pad = function(n) { return (n < 10 ? '0' : '') + n; };
            countdownEl.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
        };
        tick();
        setInterval(tick, 1000);
    }
})(jQuery);
</script>
@endpush
