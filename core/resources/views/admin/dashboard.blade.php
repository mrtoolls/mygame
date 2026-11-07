@extends('admin.layouts.app')

@section('panel')
    <div class="admin-dashboard">
        <header class="admin-dashboard__hero">
            <div>
                <span class="admin-dashboard__eyebrow"><i class="las la-chart-line"></i> Winxpot control center</span>
                <h1>Dashboard</h1>
                <p>Your platform at a glance. Monitor users, transactions, and activity from one place.</p>
            </div>
            <div class="admin-dashboard__hero-meta">
                <span class="admin-dashboard__live"><i></i> Live overview</span>
                <span class="admin-dashboard__date"><i class="las la-calendar-alt"></i> {{ now()->format('l, M j, Y') }}</span>
            </div>
        </header>

    <div class="row gy-4">

        @if($widget['coin_mode'] ?? false)
            <div class="col-12">
                <div class="alert alert--{{ $widget['coin_reconciliation']['ok'] ? 'success' : 'danger' }} d-flex flex-wrap gap-3 justify-content-between align-items-center mb-0">
                    <div>
                        <strong>Coin mode is ON.</strong> Game totals below are in coins.
                        Circulating: {{ $widget['coin_reconciliation']['circulating'] }} coins
                        &middot; Coins purchased via gateway: {{ $widget['total_coins_purchased'] }}
                        &middot; Reconciliation: {{ $widget['coin_reconciliation']['ok'] ? 'OK' : 'MISMATCH' }}
                    </div>
                    <a href="{{ route('admin.coin.settings') }}" class="btn btn-sm btn--primary">Coin settings</a>
                </div>
            </div>
        @endif

        <div class="col-xxl-3 col-sm-6">

            <x-widget
                      style="6"
                      link="{{ route('admin.users.all') }}"
                      icon="las la-users"
                      title="Total Users"
                      value="{{ $widget['total_users'] }}"
                      bg="primary" />
        </div><!-- dashboard-w1 end -->
        <div class="col-xxl-3 col-sm-6">
            <x-widget
                      style="6"
                      link="{{ route('admin.users.active') }}"
                      icon="las la-user-check"
                      title="Active Users"
                      value="{{ $widget['verified_users'] }}"
                      bg="success" />
        </div><!-- dashboard-w1 end -->
        <div class="col-xxl-3 col-sm-6">
            <x-widget
                      style="6"
                      link="{{ route('admin.users.email.unverified') }}"
                      icon="lar la-envelope"
                      title="Email Unverified Users"
                      value="{{ $widget['email_unverified_users'] }}"
                      bg="danger" />
        </div><!-- dashboard-w1 end -->
        <div class="col-xxl-3 col-sm-6">
            <x-widget
                      style="6"
                      link="{{ route('admin.users.mobile.unverified') }}"
                      icon="las la-comment-slash"
                      title="Mobile Unverified Users"
                      value="{{ $widget['mobile_unverified_users'] }}"
                      bg="warning" />
        </div><!-- dashboard-w1 end -->
    </div><!-- row end-->

    <div class="row mt-2 gy-4">
        <div class="col-xxl-6">
            <div class="card box-shadow3 h-100">
                <div class="card-body">
                    <h5 class="card-title">@lang('Deposits')</h5>
                    <div class="widget-card-wrapper">

                        <div class="widget-card bg--success">
                            <a href="{{ route('admin.deposit.list') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ showAmount($deposit['total_deposit_amount']) }}</h6>
                                    <p class="widget-card-title">@lang('Total Deposited')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--warning">
                            <a href="{{ route('admin.deposit.pending') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-spinner"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $deposit['total_deposit_pending'] }}</h6>
                                    <p class="widget-card-title">@lang('Pending Deposits')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--danger">
                            <a href="{{ route('admin.deposit.rejected') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-ban"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $deposit['total_deposit_rejected'] }}</h6>
                                    <p class="widget-card-title">@lang('Rejected Deposits')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--primary">
                            <a href="{{ route('admin.deposit.list') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ showAmount($deposit['total_deposit_charge']) }}</h6>
                                    <p class="widget-card-title">@lang('Deposited Charge')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-6">
            <div class="card box-shadow3 h-100">
                <div class="card-body">
                    <h5 class="card-title">@lang('Withdrawals')</h5>
                    <div class="widget-card-wrapper">
                        <div class="widget-card bg--success">
                            <a href="{{ route('admin.withdraw.data.all') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="lar la-credit-card"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ showAmount($withdrawals['total_withdraw_amount']) }}</h6>
                                    <p class="widget-card-title">@lang('Total Withdrawn')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--warning">
                            <a href="{{ route('admin.withdraw.data.pending') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-spinner"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $withdrawals['total_withdraw_pending'] }}</h6>
                                    <p class="widget-card-title">@lang('Pending Withdrawals')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--danger">
                            <a href="{{ route('admin.withdraw.data.rejected') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="las la-times-circle"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $withdrawals['total_withdraw_rejected'] }}</h6>
                                    <p class="widget-card-title">@lang('Rejected Withdrawals')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--primary">
                            <a href="{{ route('admin.withdraw.data.all') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="las la-percent"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ showAmount($withdrawals['total_withdraw_charge']) }}</h6>
                                    <p class="widget-card-title">@lang('Withdrawal Charge')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="row gy-4 mt-2">
        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="{{ route('admin.game.log') }}" title="Total Played" icon="las la-list" value="{{ gs('coin_mode') ? coinAmountText($widget['total_played']) : showAmount($widget['total_played']) }}" bg="primary" outline="true" />
        </div>
        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="{{ route('admin.game.log') }}?win_status=1" title="Total Win" icon="las la-money-bill-wave-alt" value="{{ gs('coin_mode') ? coinAmountText($widget['total_win_amount']) : showAmount($widget['total_win_amount']) }}" bg="success" outline="true" />
        </div>
        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="{{ route('admin.game.log') }}?win_status=2" title="Total Loss" icon="las la-money-bill" value="{{ gs('coin_mode') ? coinAmountText($widget['total_loss_amount']) : showAmount($widget['total_loss_amount']) }}" bg="14" outline="true" />
        </div>
        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="javascript:void(0)" title="Total Profit" icon="las la-percentage" value="{{ gs('coin_mode') ? coinAmountText($widget['total_profit']) : showAmount($widget['total_profit']) }}" bg="19" outline="true" />
        </div>
    </div>


    <div class="row mb-none-30 mt-30">
        <div class="col-xl-6 mb-30">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between">
                        <h5 class="card-title">@lang('Deposit & Withdraw Report')</h5>

                        <div id="dwDatePicker" class="border p-1 cursor-pointer rounded">
                            <i class="la la-calendar"></i>&nbsp;
                            <span></span> <i class="la la-caret-down"></i>
                        </div>
                    </div>
                    <div id="dwChartArea"> </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mb-30">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between">
                        <h5 class="card-title">@lang('Transactions Report')</h5>

                        <div id="trxDatePicker" class="border p-1 cursor-pointer rounded">
                            <i class="la la-calendar"></i>&nbsp;
                            <span></span> <i class="la la-caret-down"></i>
                        </div>
                    </div>

                    <div id="transactionChartArea"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-none-30 mt-5">
        <div class="col-xl-4 col-lg-6 mb-30">
            <div class="card overflow-hidden">
                <div class="card-body">
                    <h5 class="card-title">@lang('Login By Browser') (@lang('Last 30 days'))</h5>
                    <canvas id="userBrowserChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6 mb-30">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">@lang('Login By OS') (@lang('Last 30 days'))</h5>
                    <canvas id="userOsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6 mb-30">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">@lang('Login By Country') (@lang('Last 30 days'))</h5>
                    <canvas id="userCountryChart"></canvas>
                </div>
            </div>
        </div>
    </div>



    @include('admin.partials.cron_modal')
    </div>
@endsection
@push('breadcrumb-plugins')
    <button class="btn btn-outline--primary btn-sm" data-bs-toggle="modal" data-bs-target="#cronModal">
        <i class="las la-server"></i>@lang('Cron Setup')
    </button>
@endpush


@push('script-lib')
    <script src="{{ asset('assets/admin/js/vendor/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/chart.js.2.8.0.js') }}"></script>
    <script src="{{ asset('assets/admin/js/moment.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/daterangepicker.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/charts.js') }}"></script>
@endpush

@push('style-lib')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/daterangepicker.css') }}">
@endpush

@push('script')
    <script>
        "use strict";

        const start = moment().subtract(14, 'days');
        const end = moment();

        const dateRangeOptions = {
            startDate: start,
            endDate: end,
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 15 Days': [moment().subtract(14, 'days'), moment()],
                'Last 30 Days': [moment().subtract(30, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Last 6 Months': [moment().subtract(6, 'months').startOf('month'), moment().endOf('month')],
                'This Year': [moment().startOf('year'), moment().endOf('year')],
            },
            maxDate: moment()
        }

        const changeDatePickerText = (element, startDate, endDate) => {
            $(element).html(startDate.format('MMMM D, YYYY') + ' - ' + endDate.format('MMMM D, YYYY'));
        }

        let dwChart = barChart(
            document.querySelector("#dwChartArea"),
            @json(__(gs('cur_text'))),
            [{
                    name: 'Deposited',
                    data: []
                },
                {
                    name: 'Withdrawn',
                    data: []
                }
            ],
            [],
        );

        let trxChart = lineChart(
            document.querySelector("#transactionChartArea"),
            [{
                    name: "Plus Transactions",
                    data: []
                },
                {
                    name: "Minus Transactions",
                    data: []
                }
            ],
            []
        );


        const depositWithdrawChart = (startDate, endDate) => {

            const data = {
                start_date: startDate.format('YYYY-MM-DD'),
                end_date: endDate.format('YYYY-MM-DD')
            }

            const url = @json(route('admin.chart.deposit.withdraw'));

            $.get(url, data,
                function(data, status) {
                    if (status == 'success') {
                        dwChart.updateSeries(data.data);
                        dwChart.updateOptions({
                            xaxis: {
                                categories: data.created_on,
                            }
                        });
                    }
                }
            );
        }

        const transactionChart = (startDate, endDate) => {

            const data = {
                start_date: startDate.format('YYYY-MM-DD'),
                end_date: endDate.format('YYYY-MM-DD')
            }

            const url = @json(route('admin.chart.transaction'));


            $.get(url, data,
                function(data, status) {
                    if (status == 'success') {


                        trxChart.updateSeries(data.data);
                        trxChart.updateOptions({
                            xaxis: {
                                categories: data.created_on,
                            }
                        });
                    }
                }
            );
        }



        $('#dwDatePicker').daterangepicker(dateRangeOptions, (start, end) => changeDatePickerText('#dwDatePicker span', start, end));
        $('#trxDatePicker').daterangepicker(dateRangeOptions, (start, end) => changeDatePickerText('#trxDatePicker span', start, end));

        changeDatePickerText('#dwDatePicker span', start, end);
        changeDatePickerText('#trxDatePicker span', start, end);

        depositWithdrawChart(start, end);
        transactionChart(start, end);

        $('#dwDatePicker').on('apply.daterangepicker', (event, picker) => depositWithdrawChart(picker.startDate, picker.endDate));
        $('#trxDatePicker').on('apply.daterangepicker', (event, picker) => transactionChart(picker.startDate, picker.endDate));

        piChart(
            document.getElementById('userBrowserChart'),
            @json(@$chart['user_browser_counter']->keys()),
            @json(@$chart['user_browser_counter']->flatten())
        );

        piChart(
            document.getElementById('userOsChart'),
            @json(@$chart['user_os_counter']->keys()),
            @json(@$chart['user_os_counter']->flatten())
        );

        piChart(
            document.getElementById('userCountryChart'),
            @json(@$chart['user_country_counter']->keys()),
            @json(@$chart['user_country_counter']->flatten())
        );
    </script>
@endpush
@push('style')
    <style>
        .apexcharts-menu {
            min-width: 120px !important;
        }

        .admin-dashboard {
            --dash-ink: #f4f7ff;
            --dash-muted: #909bbd;
            --dash-line: rgba(143, 157, 202, .16);
            --dash-panel: linear-gradient(145deg, rgba(24, 31, 57, .96), rgba(15, 20, 39, .98));
            color: var(--dash-ink);
        }
        .admin-dashboard__hero {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            overflow: hidden;
            margin-bottom: 24px;
            padding: clamp(22px, 3vw, 34px);
            border: 1px solid rgba(0, 231, 138, .2);
            border-radius: 22px;
            background: radial-gradient(ellipse at 86% 0%, rgba(0, 231, 138, .16), transparent 36%), var(--dash-panel);
            box-shadow: 0 18px 45px rgba(2, 5, 17, .18);
        }
        .admin-dashboard__hero::after {
            position: absolute;
            right: 10%;
            bottom: -100px;
            width: 220px;
            height: 220px;
            border: 1px solid rgba(0, 231, 138, .12);
            border-radius: 50%;
            content: "";
            pointer-events: none;
        }
        .admin-dashboard__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: #00e78a;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .admin-dashboard__eyebrow i { font-size: 16px; }
        .admin-dashboard__hero h1 {
            margin: 0;
            color: var(--dash-ink);
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 600;
            letter-spacing: -.04em;
        }
        .admin-dashboard__hero p {
            max-width: 560px;
            margin: 8px 0 0;
            color: var(--dash-muted);
            font-size: 14px;
            line-height: 1.65;
        }
        .admin-dashboard__hero-meta {
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            flex: 0 0 auto;
        }
        .admin-dashboard__live, .admin-dashboard__date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid var(--dash-line);
            border-radius: 999px;
            background: rgba(10, 15, 31, .48);
            color: #c0c9e2;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .admin-dashboard__live i {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #00e78a;
            box-shadow: 0 0 12px rgba(0, 231, 138, .8);
        }
        .admin-dashboard .card {
            height: 100%;
            overflow: hidden;
            border: 1px solid var(--dash-line);
            border-radius: 18px;
            background: var(--dash-panel);
            box-shadow: 0 12px 30px rgba(2, 5, 17, .13);
        }
        .admin-dashboard .card-body { padding: clamp(18px, 2vw, 24px); }
        .admin-dashboard .card-title {
            margin-bottom: 20px;
            color: var(--dash-ink);
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -.01em;
        }
        .admin-dashboard .widget-seven {
            min-height: 118px;
            border: 1px solid var(--dash-line);
            border-radius: 17px;
            box-shadow: 0 10px 24px rgba(2, 5, 17, .12);
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .admin-dashboard .widget-seven:hover {
            transform: translateY(-3px);
            border-color: rgba(0, 231, 138, .38);
            box-shadow: 0 16px 30px rgba(2, 5, 17, .2);
        }
        .admin-dashboard .widget-seven__content-title {
            color: rgba(242, 246, 255, .8);
            font-weight: 500;
        }
        .admin-dashboard .widget-seven__content-amount { font-weight: 600; }
        .admin-dashboard .widget-card-wrapper { gap: 10px; }
        .admin-dashboard .widget-card {
            min-height: 76px;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 14px;
            transition: transform .18s ease, filter .18s ease;
        }
        .admin-dashboard .widget-card:hover { transform: translateX(3px); filter: brightness(1.08); }
        .admin-dashboard #dwDatePicker, .admin-dashboard #trxDatePicker {
            border-color: var(--dash-line) !important;
            border-radius: 10px !important;
            background: rgba(9, 14, 29, .48);
            color: #c6cde1;
            font-size: 12px;
        }
        .admin-dashboard #dwChartArea, .admin-dashboard #transactionChartArea { min-height: 300px; }
        .admin-dashboard canvas { max-height: 280px; }
        @media (max-width: 767.98px) {
            .admin-dashboard__hero { align-items: flex-start; flex-direction: column; border-radius: 17px; }
            .admin-dashboard__hero-meta { align-items: flex-start; flex-direction: row; flex-wrap: wrap; }
            .admin-dashboard__date { white-space: normal; }
            .admin-dashboard .widget-seven { min-height: 100px; }
            .admin-dashboard #dwChartArea, .admin-dashboard #transactionChartArea { min-height: 250px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .admin-dashboard .widget-seven, .admin-dashboard .widget-card { transition: none; }
        }
    </style>
@endpush
