@extends('admin.layouts.app')

@section('panel')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <select name="club_id" class="form-control" onchange="this.form.submit()">
                    <option value="">@lang('All clubs')</option>
                    @foreach ($clubs as $club)<option value="{{ $club->id }}" @selected(request('club_id') == $club->id)>{{ $club->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select name="position" class="form-control" onchange="this.form.submit()">
                    <option value="">@lang('All positions')</option>
                    @foreach (['GK', 'DEF', 'MID', 'FWD'] as $pos)<option value="{{ $pos }}" @selected(request('position') == $pos)>{{ $pos }}</option>@endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        @foreach ($players as $player)
            <form id="player-form-{{ $player->id }}" action="{{ route('admin.fantasy.players.update', $player->id) }}" method="POST" class="d-none">@csrf</form>
        @endforeach
        <table class="table">
            <thead><tr><th>@lang('Player')</th><th>@lang('Club')</th><th>@lang('Position')</th><th>@lang('Rating')</th><th>@lang('Price')</th><th>@lang('Status')</th><th></th></tr></thead>
            <tbody>
                @foreach ($players as $player)
                    @php($formId = 'player-form-' . $player->id)
                    <tr>
                        <td>{{ $player->name }}</td>
                        <td>{{ $player->club->short_code }}</td>
                        <td>{{ $player->position }}</td>
                        <td><input type="number" form="{{ $formId }}" name="rating" value="{{ $player->rating }}" class="form-control form-control-sm" style="width:80px" min="0" max="99"></td>
                        <td><input type="number" form="{{ $formId }}" name="price" value="{{ $player->price }}" step="0.5" class="form-control form-control-sm" style="width:80px" min="0"></td>
                        <td>
                            <select form="{{ $formId }}" name="status" class="form-control form-control-sm">
                                <option value="fit" @selected($player->status === 'fit')>@lang('Fit')</option>
                                <option value="injured" @selected($player->status === 'injured')>@lang('Injured')</option>
                                <option value="suspended" @selected($player->status === 'suspended')>@lang('Suspended')</option>
                            </select>
                        </td>
                        <td><button type="submit" form="{{ $formId }}" class="btn btn-sm btn--primary">@lang('Save')</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $players->links() }}
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.fantasy.index') }}" />
@endpush
