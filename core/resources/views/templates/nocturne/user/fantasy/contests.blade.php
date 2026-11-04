@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Contests')</span></div>
        <div class="ff-header"><div><h1>@lang('Contests')</h1><p class="ff-sub">@lang('Gameweek') {{ $gameweek?->number ?? '-' }}</p></div></div>

        <div class="ff-contest-grid">
            @forelse ($contests as $contest)
                <div class="ff-contest-card ff-contest-card--{{ $contest->type }}">
                    <div class="ff-contest-card__head">
                        <strong>{{ $contest->name }}</strong>
                        <span class="ff-contest-tag">{{ $contest->type === 'h2h' ? __('Head-to-Head') : ($contest->type === 'sponsored' ? __('Sponsored') : __('Gameweek Pool')) }}</span>
                    </div>
                    <div class="ff-contest-card__body">
                        @if ($contest->type === 'sponsored')
                            <span>@lang('Prize pool'): <strong>{{ number_format($contest->sponsored_pool, 0) }}</strong></span>
                        @else
                            <span>@lang('Entry'): <strong>{{ number_format($contest->entry_fee, 0) }}</strong> @lang('coins')</span>
                        @endif
                        <span>@lang('Entrants'): {{ $entryCounts[$contest->id] ?? 0 }}{{ $contest->max_entrants ? '/' . $contest->max_entrants : '' }}</span>
                    </div>
                    @if (isset($myEntries[$contest->id]))
                        <span class="ff-contest-entered"><i class="ph-fill ph-check-circle"></i>@lang('Entered')</span>
                    @elseif ($contest->status === 'open')
                        <form action="{{ route('user.fantasy.contests.enter', $contest->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn--gradient w-100">@lang('Enter')</button>
                        </form>
                    @else
                        <span class="ff-contest-closed">@lang('Entries closed')</span>
                    @endif
                </div>
            @empty
                <p class="text-muted">@lang('No contests open for this gameweek yet.')</p>
            @endforelse
        </div>

        @if ($myRecentEntries->isNotEmpty())
            <h3 class="ff-section-title">@lang('Your Recent Entries')</h3>
            <div class="ff-standings">
                @foreach ($myRecentEntries as $entry)
                    <div class="ff-standing-row">
                        <span class="ff-standing-name">{{ $entry->contest->name }}</span>
                        <span>{{ ucfirst($entry->status) }}</span>
                        <span class="ff-standing-points">{{ $entry->payout !== null ? '+' . number_format($entry->payout, 0) : '-' }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('style')
<style>
.ff-contest-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 20px; }
.ff-contest-card { background: #0d1023; border: 1px solid #2c3453; border-radius: 14px; padding: 16px; display: grid; gap: 10px; }
.ff-contest-card__head { display: flex; justify-content: space-between; align-items: center; color: #fff; }
.ff-contest-tag { font-size: 10px; text-transform: uppercase; color: var(--nocturne-green, #00ef87); border: 1px solid var(--nocturne-green, #00ef87); border-radius: 6px; padding: 2px 6px; }
.ff-contest-card__body { display: flex; flex-direction: column; gap: 4px; font-size: 12.5px; color: var(--nocturne-muted, #929abc); }
.ff-contest-card__body strong { color: #fff; }
.ff-contest-entered { color: var(--nocturne-green, #00ef87); font-size: 12px; display: flex; align-items: center; gap: 6px; }
.ff-contest-closed { color: var(--nocturne-muted, #929abc); font-size: 12px; }
</style>
@endpush
