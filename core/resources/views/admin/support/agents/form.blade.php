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
                            <div class="col-md-6"><label class="form-label">Assigned country and wallet currency</label><select class="form-control" name="country_id" id="agent-country"><option value="">Select an allowed country</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected(old('country_id', $agent->country_id) == $country->id)>{{ $country->name }} · {{ $country->currency?->code }}</option>@endforeach</select><small class="text-muted">Payment agents must be assigned to the country and currency they serve.</small></div>
                            @if($agent->exists && $agent->photo)<div class="col-12"><img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="Current profile" style="width:72px;height:72px;object-fit:cover;border-radius:50%"></div>@endif
                            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $agent->exists ? $agent->is_active : true))><span class="form-check-label">Allow this agent to sign in and appear in the support launcher</span></label></div>
                            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_moderator" value="1" @checked(old('is_moderator', $agent->exists ? $agent->is_moderator : false))><span class="form-check-label">Designate as an Arena moderator (can approve/reject Arena posts)</span></label></div>
                            <div class="col-12"><div class="p-3 rounded border"><label class="form-check mb-1"><input type="checkbox" class="form-check-input" name="is_payment_agent" id="is-payment-agent" value="1" @checked(old('is_payment_agent', $agent->exists ? $agent->is_payment_agent : false))><span class="form-check-label fw-bold">Designate as a payment agent</span></label><small class="text-muted d-block ms-4">Only agents explicitly enabled here can be assigned to manual deposit and withdrawal methods. New agents are support-only by default.</small><div class="mt-3"><label class="form-label">Payment instructions and receiving details</label><textarea class="form-control" name="payment_details" rows="4" maxlength="5000" placeholder="Account holder, bank or wallet details, and payment instructions">{{ old('payment_details', $agent->payment_details) }}</textarea><small class="text-muted">These details are shown to users only when this agent is selected for a manual deposit method.</small></div>

                                <div class="row g-3 mt-2">
                                    <div class="col-md-6"><label class="form-check mt-2"><input type="checkbox" class="form-check-input" name="is_verified" value="1" @checked(old('is_verified', $agent->exists ? $agent->is_verified : false))><span class="form-check-label fw-bold">WinxPay Verified badge</span></label><small class="text-muted d-block ms-4">Shows the green "Verified" badge on this agent's public WinxPay card.</small></div>
                                    <div class="col-md-6">
                                        <label class="form-label">Speed override (0-100, optional)</label>
                                        <input type="number" class="form-control" name="speed_override" min="0" max="100" value="{{ old('speed_override', $agent->speed_override) }}" placeholder="Leave blank to use computed score">
                                        <small class="text-muted">Overrides the computed speed bar entirely when set.</small>
                                    </div>
                                    @if($agent->exists)
                                        <div class="col-12">
                                            <small class="text-muted">Computed speed score: <strong>{{ $speedScore ?? 0 }}</strong>/100 · Fast confirms this month: <strong>{{ $fastStreakCount ?? 0 }}</strong>/50</small>
                                            @if(!empty($agent->accepted_payment_types))
                                                <div class="mt-1"><small class="text-muted">Accepted payment types (set by the agent): <strong>{{ implode(', ', $agent->accepted_payment_types) }}</strong></small></div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn--primary" type="submit"><i class="las la-save"></i> Save agent</button><a class="btn btn--dark" href="{{ route('admin.support.agents.index') }}">Cancel</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        (() => { const flag=document.getElementById('is-payment-agent'),country=document.getElementById('agent-country'); if(flag&&country){const sync=()=>country.required=flag.checked;flag.addEventListener('change',sync);sync();} })();
    </script>
@endsection
