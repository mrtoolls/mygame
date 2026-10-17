@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <p class="mb-3">@lang('This applies any database changes shipped with the code (new tables/columns the theme update added, such as VIP tiers) directly on this server - no terminal or SSH access needed. It only ever adds what is missing; it will not touch or remove your existing data.')</p>
                    @if (count($pending))
                        <h6>@lang('Pending changes')</h6>
                        <ul class="list-group list-group-flush mb-3">
                            @foreach ($pending as $line)
                                <li class="list-group-item">{{ $line }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="alert alert--success">@lang('Nothing pending - the database is already up to date.')</div>
                    @endif

                    @if (session()->has('migrate_output'))
                        <h6 class="mt-4">@lang('Last run output')</h6>
                        <pre class="bg--dark text-white p-3 rounded" style="white-space: pre-wrap;">{{ session('migrate_output') }}</pre>
                    @endif
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn--primary w-100 h-45 confirmationBtn" data-question="@lang('Run the database update now?')" data-action="{{ route('admin.system.database.update.run') }}">@lang('Run Database Update')</button>
                </div>
            </div>
        </div>
    </div>
    <x-confirmation-modal />
@endsection
