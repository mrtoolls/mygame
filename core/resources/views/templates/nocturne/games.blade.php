@extends('Template::layouts.frontend')
@section('content')

    @include('Template::partials.promo_banner_row')
    @include('Template::sections.live_table_games')

    <div class="nocturne-game-search">
        <i class="ph-bold ph-magnifying-glass"></i>
        <input type="text" id="nocturneGameSearch" placeholder="@lang('Search Games')" autocomplete="off">
    </div>

    <section class="nocturne-game-row">
        <div class="nocturne-game-row__head">
            <h2><i class="ph-bold ph-spade"></i> {{ gs('site_name') }} @lang('Originals')</h2>
        </div>
        <div class="games-section-wrapper" id="nocturneGameGrid">
            @include('Template::partials.game', ['games' => $games])
        </div>
        <p class="nocturne-game-search__empty d-none">@lang('No games match your search.')</p>
    </section>

    @include('Template::sections.recent_big_wins')
    @include('Template::sections.winxpot_partners')
    @include('Template::sections.last_games')
    @include('Template::sections.vip_club')
    @include('Template::sections.about_reviews')

    @if (@$sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @unless(in_array($sec, ['live_table_games', 'recent_big_wins', 'winxpot_partners', 'last_games', 'vip_club', 'about_reviews']))
                @include('Template::sections.' . $sec)
            @endunless
        @endforeach
    @endif
@endsection

@push('script')
    <script>
        (function($) {
            "use strict";
            $('#nocturneGameSearch').on('input', function() {
                var term = $(this).val().toLowerCase().trim();
                var visible = 0;
                $('#nocturneGameGrid .game-item').each(function() {
                    var name = $(this).find('.game-item__title').text().toLowerCase();
                    var match = name.indexOf(term) !== -1;
                    $(this).toggle(match);
                    if (match) visible++;
                });
                $('.nocturne-game-search__empty').toggleClass('d-none', visible !== 0);
            });
        })(jQuery);
    </script>
@endpush
