@extends('Template::layouts.master')
@section('content')
    <div class="pai-gow-page">
    <header class="pai-gow-heading">
        <div class="pai-gow-heading__mark"><i class="ph-fill ph-cards"></i></div>
        <div><span>@lang('TABLE GAME')</span><h1>@lang('Pai Gow Poker')</h1><p>@lang('Build a five-card high hand and a two-card low hand to challenge the dealer.')</p></div>
        <button type="button" class="pai-gow-heading__rules" data-bs-toggle="modal" data-bs-target="#exampleModalCenter"><i class="ph ph-book-open-text"></i><span>@lang('How to play')</span></button>
    </header>
    <div class="row gy-4 pai-gow-layout">
        <!-- Game Table Section -->
        <div class="col-xl-9" id="game--card">
            <div class="game--card">
                <div class="pai-gow-table">
                    <!-- Dealer Section -->
                    <div class="dealer-section mb-4">
                        <h5 class="dealer-section__text">@lang('Dealer Hand')</h5>
                        <div class="dealer-section__top">
                            <h5 class="dealer-section__title">@lang('HIGHEST')</h5>
                            <div class="d-md-block d-none">
                                <span class="dealer-section__title">@lang('2ND HIGHEST')</span>
                            </div>
                        </div>
                        <div class="dealer-section__inner">
                            <div class="hand high-hand">
                                <div class="cards d-flex" id="dealer-high-hand">
                                    @for ($i = 0; $i < 5; $i++)
                                        <div class="card-thumb">
                                            <div class="card-inner">
                                                <img class="card-face back"
                                                     src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}"
                                                     alt="card back">
                                                <img class="card-face front"
                                                     src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}"
                                                     alt="card front">
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                            <div class="hand low-hand ">
                                <div class="d-md-none d-block">
                                    <span class="dealer-section__title mb-2">@lang('2ND HIGHEST')</span>
                                </div>
                                <div class="cards d-flex" id="dealer-low-hand">
                                    @for ($i = 0; $i < 2; $i++)
                                        <div class="card-thumb">
                                            <div class="card-inner">
                                                <img class="card-face back"
                                                     src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}"
                                                     alt="card back">
                                                <img class="card-face front"
                                                     src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}"
                                                     alt="card front">
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Player Section -->
                    <div class="player-section">
                        <h5 class="player-section__title">@lang('Your Hand')</h5>
                        <div class="hand low-hand">
                            <div class="cards d-flex justify-content-between" id="player-low-hand">
                                <div class="low-slot">
                                    <span class="low-slot__text">@lang('2ND HIGHEST')</span>
                                    <div class="player-low-hand-shape"></div>
                                </div>
                                <div class="low-slot">
                                    <span class="low-slot__text">@lang('2ND HIGHEST')</span>
                                    <div class="player-low-hand-shape"></div>
                                </div>
                            </div>
                        </div>
                        <div class="hand high-hand mb-2">
                            <span class="high-hand__title">@lang('HIGHEST')</span>
                            <div class="cards d-flex justify-content-center" id="player-high-hand">
                                @for ($i = 0; $i < 7; $i++)
                                    <div class="card-thumb" data-card-index="{{ $i }}">
                                        <div class="card-inner">
                                            <img class="card-face back"
                                                 src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}"
                                                 alt="card back">
                                            <img class="card-face front"
                                                 src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}"
                                                 alt="card front">
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <button type="button" class="btn btn--gradient standBtn d-none">@lang('Stand')</button>
                        <button type="button" class="btn btn--gradient resetBtn d-none">@lang('Refresh')</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Betting & Info Section -->
        <div class="col-xl-3" id="form--area">
            <div class="game--card">
                <h4 class="game-contet-title">
                    {{ $isDemo ? trans('Demo Balance:') : trans('Current Balance:') }}
                    @if (!$isDemo && gs('coin_mode'))
                        <span class="text balance">{!! showUserBalance() !!}</span>
                    @else
                        <span class="text balance">{{ showAmount($balance, currencyFormat: false) }}</span>
                        {{ __(gs('cur_text')) }}
                    @endif
                </h4>
                <form id="game" method="post">
                    @csrf
                    <div class="form-group">
                        <div class="input-group">
                            <input class="form-control form--control" name="invest" type="number" step="any"
                                   value="{{ old('invest') }}" placeholder="@lang('Enter amount')" autocomplete="off">
                            <span class="input-group-text">{{ gs('coin_mode') ? __('Coins') : __(gs('cur_text')) }}</span>
                        </div>
                        <small class="fw-light mt-3 d-inline-block input-inner-note">
                            <i class="fas fa-info-circle mr-2"></i> @lang('Invest Limit'):
                            @if (gs('coin_mode')) {{ formatCoinOrUsd($game->min_limit) }} - {{ formatCoinOrUsd($game->max_limit) }} @else {{ showAmount($game->min_limit) }} - {{ showAmount($game->max_limit) }} @endif
                        </small>
                        <small class="fw-light mt-1 d-inline-block input-inner-note">
                            <i class="fas fa-info-circle mr-2"></i> @lang('Select 2 cards from your High Hand for the Low Hand')
                        </small>
                    </div>
                    <div class="form-submit game-playbtn">
                        <button type="submit" class="btn btn--gradient w-100 investBtn">@lang('Play Now')</button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3 w-100">
                        <button type="button" class="d-block text-white text-center mx-auto" data-bs-toggle="modal"
                                data-bs-target="#exampleModalCenter"><i class="fas fa-info-circle mr-2"></i>
                            @lang('Game Instruction')
                        </button>
                        <button type="button" class="sound--btn audioBtn">
                            <i class="fas fa-volume-up"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <!-- Game Instruction Modal -->
    <div class="modal custom--modal fade" id="exampleModalCenter">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content section--bg">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Game Rule')</h5>
                    <span class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </span>
                </div>
                <div class="modal-body">
                    @php echo $game->instruction @endphp
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-lib')
    <script src="{{ asset('assets/global/js/soundControl.js') }}"></script>
    <script src="{{ asset('assets/global/js/game/pai-gow-poker.js') }}"></script>
@endpush


@push('style')
    <style>
        .pai-gow-page { --pg-line:rgba(143,158,207,.18); --pg-muted:#9ca9ca; }
        .pai-gow-heading { display:flex; align-items:center; gap:14px; margin:0 0 18px; padding:18px 20px; border:1px solid var(--pg-line); border-radius:18px; background:radial-gradient(ellipse at 90% 0%,rgba(0,239,135,.1),transparent 42%),linear-gradient(135deg,#171d36,#12182b); }
        .pai-gow-heading__mark { display:grid; width:48px; height:48px; flex:0 0 48px; place-items:center; border:1px solid rgba(0,239,135,.24); border-radius:15px; background:rgba(0,239,135,.1); color:#00ef87; font-size:24px; }
        .pai-gow-heading>div:nth-child(2) { min-width:0; }
        .pai-gow-heading>div:nth-child(2)>span { color:#00e994; font-size:9px; font-weight:800; letter-spacing:.14em; }
        .pai-gow-heading h1 { margin:3px 0 2px; color:#f4f6ff; font-size:clamp(21px,2.1vw,29px); font-weight:750; letter-spacing:-.035em; line-height:1.15; }
        .pai-gow-heading p { margin:0; color:var(--pg-muted); font-size:13px; line-height:1.45; }
        .pai-gow-heading__rules { display:inline-flex; min-height:40px; flex:0 0 auto; align-items:center; justify-content:center; gap:8px; margin-inline-start:auto; padding:0 13px; border:1px solid var(--pg-line); border-radius:12px; background:rgba(255,255,255,.045); color:#e7ebfa; font-size:12px; font-weight:700; }
        .pai-gow-layout > [id="game--card"] { min-width:0; }
        .pai-gow-table { overflow:hidden; border:1px solid var(--pg-line); border-radius:20px; background:radial-gradient(ellipse at 50% 50%,rgba(0,183,116,.09),transparent 68%),linear-gradient(145deg,#11192b,#101626); }
        .pai-gow-table .dealer-section,.pai-gow-table .player-section { border:1px solid rgba(143,158,207,.12); border-radius:16px; background:rgba(21,29,49,.72); }
        .pai-gow-table .dealer-section { margin:16px 16px 12px!important; }
        .pai-gow-table .player-section { margin:0 16px 16px; }
        .pai-gow-table .dealer-section__text,.pai-gow-table .player-section__title { margin-bottom:14px; color:#eef2ff; font-size:16px; font-weight:750; }
        .pai-gow-table .dealer-section__title,.pai-gow-table .high-hand__title { color:#aab7d6; font-size:10px; font-weight:800; letter-spacing:.11em; }
        .pai-gow-table .card-thumb { flex:0 0 80px; border-radius:9px; filter:drop-shadow(0 9px 12px rgba(0,0,0,.25)); }
        .pai-gow-table .card-face,.pai-gow-table .card-inner { border-radius:9px; }
        .pai-gow-page #form--area .game--card { height:100%; padding:20px; border:1px solid var(--pg-line); border-radius:20px; background:linear-gradient(155deg,#191f38,#12182c); }
        .pai-gow-page .game-contet-title { color:#aab7d6; font-size:12px; font-weight:650; }
        .pai-gow-page .game-contet-title .balance { display:block; margin-top:7px; color:#f5f7ff; font-size:24px; font-weight:780; }
        .pai-gow-page .input-inner-note { color:#9da9c9; font-size:11px; line-height:1.5; }
        .pai-gow-page .investBtn,.pai-gow-table .standBtn,.pai-gow-table .resetBtn { min-height:46px; border-radius:13px; font-size:14px!important; font-weight:800; }
        .pai-gow-page .sound--btn { display:grid; width:40px; height:40px; place-items:center; border:1px solid var(--pg-line); border-radius:12px; background:rgba(255,255,255,.05); }
        /* --- Layout --- */
        .pai-gow-table .hand {
            margin-bottom: 15px;
        }

        .pai-gow-table .cards {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* card container */
        .card-thumb {
            width: 80px;
            height: 120px;
            perspective: 900px;
            cursor: pointer;
            position: relative;
        }

        .card-inner {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.6s ease;
            border-radius: 6px;
        }

        .card-face {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 6px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.2);
            display: block;
            object-fit: cover;
        }

        .card-face.back {
            transform: rotateY(0deg);
        }

        .card-face.front {
            transform: rotateY(180deg);
        }

        /* flipped state (show front) */
        .card-thumb.flipped .card-inner {
            transform: rotateY(180deg);
        }

        /* entry animation (slide + fade) */
        .card-thumb.enter {
            transform: translateY(10px);
            opacity: 0;
        }

        .card-thumb.enter.show {
            transform: translateY(0);
            opacity: 1;
            transition: transform 0.35s ease, opacity 0.35s ease;
        }

        /* selectable hover */
        .card-thumb.selectable:hover {
            transform: translateY(-6px) scale(1.06);
            transition: transform 0.18s ease;
            z-index: 5;
        }

        /* selected to low-hand bounce */
        .card-thumb.moving {
            transition: transform 0.45s cubic-bezier(.2, .9, .2, 1), opacity 0.45s ease;
        }

        /* keep original card-item styles for bonus table */
        .card-item::before,
        .card-item::after {
            background-image: unset;
        }

        .card-item {
            border: none;
        }

        .btn--gradient.callBtn,
        .btn--gradient.foldBtn,
        .btn--gradient.dealBtn,
        .btn--gradient.refreshBtn {
            padding: 12px 20px !important;
            font-size: 20px !important;
            border-radius: 8px;
        }

        /* make sure front image looks correct when rotated */
        .card-face.front {
            transform: rotateY(180deg);
        }

        .player-section {
            background: #ffffff0a;
            padding: 32px 48px;
            border-radius: 8px;
        }

        .player-section__title,
        .dealer-section__text {
            text-align: center;
            margin-bottom: 20px;
        }

        .high-hand__title {
            display: flex;
            justify-content: center;
            margin-bottom: 5px;
            color: #fff;
            letter-spacing: 2px;
            font-size: 20px;
            font-weight: 400;
        }

        .player-section .btn.btn--gradient {
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 250px;
            margin: 0 auto;
            margin-top: 20px;
        }

        .dealer-section {
            background: #ffffff0a;
            padding: 32px 48px;
            border-radius: 8px;
        }

        .dealer-section__inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }


        .dealer-section__top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }



        .dealer-section__title {
            display: flex;
            justify-content: center;
            color: #fff;
            letter-spacing: 2px;
            font-size: 20px;
            font-weight: 400;
            margin-bottom: 0;
            line-height: 1;
            font-family: var(--body-font);
        }

        .player-low-hand-shape {
            width: 80px;
            height: 120px;
            /* background: #fff; */
            border-radius: 6px;
            border: 1px dashed rgba(255, 255, 255, 0.5);
        }

        .low-slot {
            width: 80px;
            height: 120px;
            position: relative;
        }

        .low-slot:first-child .low-slot__text {
            position: absolute;
            left: -30px;
            top: 0;
            transform: rotate(-90deg);
            color: #fff;
            letter-spacing: 2px;
            font-size: 14px;
            font-weight: 400;
            width: 120px;
            height: 100%;
            white-space: nowrap;
        }

        .low-slot:last-child .low-slot__text {
            position: absolute;
            right: -30px;
            top: 0;
            transform: rotate(-90deg);
            color: #fff;
            letter-spacing: 2px;
            font-size: 14px;
            font-weight: 400;
            width: 120px;
            white-space: nowrap;
            height: 100%;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
        }

        .low-slot .card-thumb {
            position: absolute;
            inset: 0;
        }

        @media (max-width:767px) {
            .pai-gow-heading { gap:11px; margin-bottom:12px; padding:13px; border-radius:15px; }
            .pai-gow-heading__mark { width:42px; height:42px; flex-basis:42px; border-radius:13px; font-size:21px; }
            .pai-gow-heading h1 { font-size:21px; }
            .pai-gow-heading p { font-size:11px; }
            .pai-gow-heading__rules { min-height:38px; padding:0 10px; font-size:11px; }
            .dealer-section__inner {
                flex-direction: column;
            }

            .dealer-section__top {
                justify-content: center
            }

            .pai-gow-table .cards {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width:575px) {

            .pai-gow-heading { align-items:flex-start; }
            .pai-gow-heading__rules { width:38px; min-width:38px; height:38px; padding:0; }
            .pai-gow-heading__rules span { display:none; }
            .pai-gow-table .dealer-section { margin:10px 10px 8px!important; }
            .pai-gow-table .player-section { margin:0 10px 10px; }
            .pai-gow-page #form--area .game--card { padding:15px; border-radius:17px; }
            .pai-gow-table .cards { gap:clamp(3px,1.2vw,7px); }
            .pai-gow-table .card-thumb { width:clamp(38px,12vw,52px); height:clamp(57px,18vw,78px); flex:0 0 clamp(38px,12vw,52px); }
            .pai-gow-table .player-section { padding:14px 8px; }
            .pai-gow-table .dealer-section { padding:14px 8px; }
            .pai-gow-table .player-section__title,.pai-gow-table .dealer-section__text { margin-bottom:10px; font-size:14px; }
            .pai-gow-table .dealer-section__inner { gap:10px; }
            .pai-gow-table .low-slot { width:clamp(48px,15vw,64px); height:clamp(72px,22.5vw,96px); }
            .pai-gow-table .player-low-hand-shape { width:100%; height:100%; }
            .pai-gow-table .low-slot:first-child .low-slot__text,.pai-gow-table .low-slot:last-child .low-slot__text { width:clamp(72px,22.5vw,96px); font-size:9px; letter-spacing:.07em; }
            .pai-gow-table .player-section .hand.low-hand { padding:0 clamp(28px,9vw,42px); }
            .pai-gow-table .dealer-section__title,.pai-gow-table .high-hand__title { font-size:9px; }
            .pai-gow-table .card-face,.pai-gow-table .card-inner { border-radius:6px; }

            .dealer-section,
            .player-section {
                padding: 14px;
            }

            .player-section .hand.low-hand {
                padding: 0 32px;
            }

            .card-thumb {
                width: 70px;
                height: 100px;
            }

            .low-slot {
                width: 70px;
                height: 100px;
            }

            .player-low-hand-shape {
                width: 70px;
                height: 100px;
            }

            .low-slot:first-child .low-slot__text,
            .low-slot:last-child .low-slot__text {
                width: 100px;
                font-size: 12px;
            }
        }
        @media (max-width:360px) {
            .pai-gow-heading { gap:8px; padding:11px; }
            .pai-gow-heading__mark { width:36px; height:36px; flex-basis:36px; }
            .pai-gow-heading h1 { font-size:19px; }
            .pai-gow-table .card-thumb { width:38px; height:57px; flex-basis:38px; }
            .pai-gow-table .cards { gap:3px; }
        }
    </style>
@endpush


@push('script')
    <script>
        let userBalance = Number("@if (!$isDemo && gs('coin_mode')){{ app(\App\Services\CoinLedger::class)->wallet('user', auth()->id())->available_balance }}@else{{ $balance }}@endif");
        let minLimit = Number("@if (gs('coin_mode')){{ bcdiv((string) $game->min_limit, (string) (gs('coin_peg_usd') ?: '0.01'), 0) }}@else{{ $game->min_limit }}@endif");
        let maxLimit = Number("@if (gs('coin_mode')){{ bcdiv((string) $game->max_limit, (string) (gs('coin_peg_usd') ?: '0.01'), 0) }}@else{{ $game->max_limit }}@endif");
        let currency = "@if (gs('coin_mode')){{ __('Coins') }}@else{{ gs('cur_text') }}@endif";
        let investField = $("[name=invest]");
        let investBtn = $(".investBtn");
        let balance = $(".balance");
        let dealerHighHand = $('#dealer-high-hand');
        let dealerLowHand = $('#dealer-low-hand');
        let playerHighHand = $('#player-high-hand');
        let playerLowHand = $('#player-low-hand');
        audioAssetPath = `{{ asset('assets/audio') }}`;
        let gameLogId = null;
        const cardBack = "{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}"
        const cardPath = "{{ asset(activeTemplate(true) . 'images/cards') }}";


        $(document).ready(function() {
            $('#player-high-hand').on('click', '.selectable', function() {
                safePlaySound("click.mp3");

                const $emptySlot = $('#player-low-hand .low-slot')
                    .filter(function() {
                        return $(this).find('.card-thumb').length === 0;
                    })
                    .first();

                if (!$emptySlot.length) {
                    return notify('error', 'You can select maximum 2 cards');
                }

                const $card = $(this);
                const $clone = $card.clone()
                    .removeClass('selectable')
                    .addClass('moving');

                $emptySlot.append($clone);
                $card.remove();

                setTimeout(() => $clone.removeClass('moving'), 400);
            });
        });


        $('#game').on('submit', async function(e) {
            e.preventDefault();
            safePlaySound("click.mp3");

            const invest = investField.val();

            if (!invest) return notify('error', 'Invest field is required');
            if (invest < minLimit) return notify('error', `Minimum invest is ${minLimit}`);
            if (invest > maxLimit) return notify('error', `Maximum invest is ${maxLimit}`);
            if (invest > userBalance) return notify('error', 'Insufficient balance');

            investBtn.addClass("d-none");
            investField.prop('readonly', true);


            const url = "{{ route('user.play.invest', [$game->alias, @$isDemo]) }}";

            let response = await $.post(url, {
                _token: "{{ csrf_token() }}",
                invest: invest
            });

            if (response.error) {
                investBtn.removeClass("d-none");
                investField.prop('readonly', false);
                return notify("error", response.error);
            }

            investField.val("");

            $('.standBtn').removeClass("d-none");

            await revealPlayerCards(response.result);

            gameLogId = response.game_log_id;
            balance.text(response.balance);
            notify("success", response.message);
        });


        $('.standBtn').on('click', async function() {

            let lowHandCards = [];
            $('#player-low-hand .card-thumb').each(function() {
                lowHandCards.push($(this).data('card'));
            });

            if (lowHandCards.length < 2) {
                return notify('error', 'Select 2 cards for Low Hand');
            }

            let highHandCards = [];
            $('#player-high-hand .card-thumb').each(function() {
                highHandCards.push($(this).data('card'));
            });

            const url = "{{ route('user.play.end', [$game->alias, @$isDemo]) }}";
         
            let response = await $.post(url, {
                _token: "{{ csrf_token() }}",
                game_log_id: gameLogId,
                high_hand: highHandCards,
                low_hand: lowHandCards
            });

            await revealDealerCards(response.dealerCards.high, response.dealerCards.low);
            balance.text(response.bal);

            $('.standBtn').addClass("d-none");
            $('.resetBtn').removeClass("d-none");
            response.win_status = response.win_status ?? (response.type === 'success' ? 1 : response.type === 'push' ? 2 : 0);
            response.result = response.type === 'success' ? 'Player Win' : response.type === 'push' ? 'Draw' : 'Dealer Win';
            setPopup(response);

        });
    </script>
@endpush
