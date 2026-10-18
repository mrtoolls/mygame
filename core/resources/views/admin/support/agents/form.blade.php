@extends('admin.layouts.app')

@section('panel')
    <div class="row justify-content-center">
        <div class="col-xl-9 col-lg-10">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ $agent->exists ? 'Agent profile and access' : 'Create an agent account' }}</h5></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" action="{{ $agent->exists ? route('admin.support.agents.update', $agent->id) : route('admin.support.agents.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="name" value="{{ old('name', $agent->name) }}" maxlength="120" required></div>
                            <div class="col-md-6"><label class="form-label">Email address</label><input type="email" class="form-control" name="email" value="{{ old('email', $agent->email) }}" maxlength="190" required></div>
                            <div class="col-md-6"><label class="form-label">Login username</label><input class="form-control" name="username" value="{{ old('username', $agent->username) }}" minlength="3" maxlength="60" required><small class="text-muted">Agents can sign in with this username or their email.</small></div>
                            <div class="col-md-6"><label class="form-label">{{ $agent->exists ? 'New password (optional)' : 'Temporary password' }}</label><input type="password" class="form-control" name="password" minlength="10" autocomplete="new-password" {{ $agent->exists ? '' : 'required' }}><small class="text-muted">Use at least 10 characters. Share credentials with the agent securely.</small></div>
                            <div class="col-md-6"><label class="form-label">Role title</label><input class="form-control" name="title" value="{{ old('title', $agent->title) }}" maxlength="120" placeholder="Customer care specialist"></div>
                            <div class="col-md-6"><label class="form-label">Profile photo</label><input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp"><small class="text-muted">JPG, PNG, or WebP up to 3 MB.</small></div>
                            <div class="col-12"><label class="form-label">Short introduction</label><textarea class="form-control" name="bio" rows="3" maxlength="500">{{ old('bio', $agent->bio) }}</textarea></div>
                            @if($agent->exists && $agent->photo)<div class="col-12"><img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="Current profile" style="width:72px;height:72px;object-fit:cover;border-radius:50%"></div>@endif
                            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $agent->exists ? $agent->is_active : true))><span class="form-check-label">Allow this agent to sign in and appear in the support launcher</span></label></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn--primary" type="submit"><i class="las la-save"></i> Save agent</button><a class="btn btn--dark" href="{{ route('admin.support.agents.index') }}">Cancel</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
