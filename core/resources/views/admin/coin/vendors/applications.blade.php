@extends('admin.layouts.app')
@section('panel')
@include('admin.coin.vendors.partials.styles')
<div class="cv-admin">
    <header class="cv-hero">
        <div><span class="cv-kicker">Coin economy / trust operations</span><h1 class="cv-title">Vendor applications</h1><p class="cv-intro">Review applicant details, identity checks, and decision history from one private workspace.</p></div>
        <div class="cv-hero-actions"><a class="btn btn--outline btn--primary" href="{{ route('admin.coin.vendors.index') }}">Manage vendors</a><a class="btn btn--primary" href="{{ route('admin.coin.vendors.settings') }}">Vendor settings</a></div>
    </header>
    <div class="cv-stats">
        @foreach([['Submitted','submitted'],['In review','in_review'],['Needs information','more_information'],['Approved','approved']] as [$label,$key])
            <div class="cv-stat"><span>{{ $label }}</span><strong>{{ $counts[$key] ?? 0 }}</strong><small>Applications</small></div>
        @endforeach
    </div>
    <section class="cv-panel">
        <div class="cv-panel-head"><div><h2 class="cv-panel-title">Application queue</h2><p class="cv-panel-copy">Private identity documents are only available from an audited review.</p></div></div>
        <nav class="cv-filters" aria-label="Application status">
            @foreach(['submitted'=>'Submitted','in_review'=>'In review','more_information'=>'Needs info','approved'=>'Approved','rejected'=>'Rejected','all'=>'All applications'] as $filter=>$label)
                <a class="cv-filter {{ $status === $filter ? 'is-active' : '' }}" href="{{ route('admin.coin.vendors.applications',['status'=>$filter,'search'=>request('search')]) }}">{{ $label }}<b>{{ $filter==='all' ? $counts->sum() : ($counts[$filter] ?? 0) }}</b></a>
            @endforeach
        </nav>
        <form class="cv-toolbar" method="get"><input type="hidden" name="status" value="{{ $status }}"><input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search applicant name or email"><button class="btn btn--primary" type="submit">Search applicants</button>@if(request('search'))<a class="btn btn--outline btn--dark" href="{{ route('admin.coin.vendors.applications',['status'=>$status]) }}">Clear</a>@endif</form>
        <div class="cv-table-wrap"><table class="cv-table"><thead><tr><th>Applicant</th><th>Submitted</th><th>Status</th><th>Review flags</th><th></th></tr></thead><tbody>
            @forelse($applications as $application)
                <tr><td><span class="cv-primary">{{ $application->user?->username ?? 'Account unavailable' }}</span><span class="cv-secondary">{{ $application->user?->email }}</span></td><td>{{ $application->submitted_at?->format('M j, Y · H:i') ?? 'Draft' }}</td><td><span class="cv-status cv-status--{{ \Illuminate\Support\Str::slug($application->status,'_') }}">{{ ucfirst(str_replace('_',' ',$application->status)) }}</span></td><td>@if($application->duplicate_flag)<span class="cv-status cv-status--more_information">Manual match review</span>@else<span class="cv-secondary">No duplicate flag</span>@endif</td><td class="text-end"><a class="btn btn--primary btn-sm" href="{{ route('admin.coin.vendors.applications.show',$application->id) }}">Open review</a></td></tr>
            @empty
                <tr><td colspan="5"><div class="cv-empty">No applications match these filters.</div></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="cv-pagination">{{ $applications->links() }}</div>
    </section>
</div>
@endsection