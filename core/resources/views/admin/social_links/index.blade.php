@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">@lang('Official Social Links')</h5>
                    <p class="mb-0 text-muted">@lang('Shown through the "Official" button on the landing page and the sidebar menu. Add a URL and turn a platform on to display it; leave it off to hide it.')</p>
                </div>
                <form method="post" action="{{ route('admin.social.links.update') }}">
                    @csrf
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @foreach ($links as $link)
                                <li class="list-group-item">
                                    <div class="row align-items-center g-2">
                                        <div class="col-md-3">
                                            <strong>{{ $link->label }}</strong>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="url" class="form-control" name="url[{{ $link->id }}]" value="{{ old('url.' . $link->id, $link->url) }}" placeholder="https://...">
                                        </div>
                                        <div class="col-md-2 text-md-end">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox" role="switch" name="active[{{ $link->id }}]" value="1" id="social-active-{{ $link->id }}" @checked(old('active.' . $link->id, $link->is_active))>
                                                <label class="form-check-label" for="social-active-{{ $link->id }}">@lang('Active')</label>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn--primary w-100 h-45">@lang('Save Changes')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
