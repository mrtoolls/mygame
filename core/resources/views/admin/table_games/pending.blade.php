@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center"><div class="col-xl-8"><div class="card"><div class="card-body text-center"><h5>@lang('Table game database update required')</h5><p class="text-muted">@lang('Run the pending Database Update before configuring tables. This creates only the new table-game records and settings.')</p><a class="btn btn--primary" href="{{ route('admin.system.database.update') }}">@lang('Open Database Update')</a></div></div></div></div>
@endsection
