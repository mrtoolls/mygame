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

    @if (@$sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
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
