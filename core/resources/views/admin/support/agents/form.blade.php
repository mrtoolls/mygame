@extends('admin.layouts.app')

@section('panel')
<div class="sa-admin">
    <header class="sa-hero">
        <a href="{{ route('admin.support.agents.index') }}" class="sa-back"><i class="las la-arrow-left"></i> Support Agents</a>
        <span class="sa-kicker">Support &amp; payments</span>
        <h1 class="sa-title">{{ $agent->exists ? 'Edit support agent' : 'Add a support agent' }}</h1>
        <p class="sa-intro">{{ $agent->exists ? 'Update this agent\'s profile, access, and payment-agent details.' : 'Create a login for a support agent - optionally also a payment agent who can be assigned to manual deposit and withdrawal methods.' }}</p>
    </header>

    @if($errors->has('payment_methods') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'payment_methods.')))
        <div class="sa-errors">
            <i class="las la-exclamation-triangle"></i>
            <div>
                <strong>Fix the payment methods below</strong>
                <ul>
                    @foreach($errors->keys() as $key)
                        @if($key === 'payment_methods' || str_starts_with($key, 'payment_methods.'))
                            <li>{{ $errors->first($key) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="post" enctype="multipart/form-data" action="{{ $agent->exists ? route('admin.support.agents.update', $agent->id) : route('admin.support.agents.store') }}">
        @csrf

        <section class="sa-panel">
            <div class="sa-panel-head"><span class="sa-panel-icon"><i class="las la-id-card"></i></span><h2 class="sa-panel-title">Profile</h2></div>
            <div class="sa-grid">
                <div class="sa-field"><label>Full name</label><input class="form-control" name="name" value="{{ old('name', $agent->name) }}" maxlength="120" required></div>
                <div class="sa-field"><label>Email address</label><input type="email" class="form-control" name="email" value="{{ old('email', $agent->email) }}" maxlength="190" required></div>
                <div class="sa-field"><label>Login username</label><input class="form-control" name="username" value="{{ old('username', $agent->username) }}" minlength="3" maxlength="60" required><small>Agents can sign in with this username or their email.</small></div>
                <div class="sa-field"><label>{{ $agent->exists ? 'New password (optional)' : 'Temporary password' }}</label><input type="password" class="form-control" name="password" minlength="10" autocomplete="new-password" {{ $agent->exists ? '' : 'required' }}><small>At least 10 characters. Share credentials with the agent securely.</small></div>
                <div class="sa-field"><label>Role title</label><input class="form-control" name="title" value="{{ old('title', $agent->title) }}" maxlength="120" placeholder="Customer care specialist"></div>
                <div class="sa-field">
                    <label>Profile photo</label>
                    <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG, or WebP up to 3 MB.</small>
                </div>
                <div class="sa-field sa-field--wide"><label>Short introduction</label><textarea class="form-control" name="bio" rows="3" maxlength="500">{{ old('bio', $agent->bio) }}</textarea></div>
                <div class="sa-field sa-field--wide">
                    <label>Linked player account (optional)</label>
                    <input class="form-control" name="linked_username" value="{{ old('linked_username', $agent->user?->username) }}" maxlength="60" placeholder="Exact username">
                    <small>Lets that player jump into this agent profile from their own account nav, with no separate agent login. Leave blank for staff with no player account; clear it to unlink.</small>
                </div>
            </div>
            @if($agent->exists && $agent->photo)
                <div class="sa-current-photo"><img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="Current profile photo"><span>Current photo</span></div>
            @endif
        </section>

        <section class="sa-panel">
            <div class="sa-panel-head"><span class="sa-panel-icon"><i class="las la-sliders-h"></i></span><h2 class="sa-panel-title">Access</h2></div>
            <label class="sa-toggle">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $agent->exists ? $agent->is_active : true))>
                <span class="sa-toggle__switch"></span>
                <span class="sa-toggle__text"><strong>Allow sign-in</strong><small>Agent can log in and appears in the support launcher.</small></span>
            </label>
            <label class="sa-toggle">
                <input type="checkbox" name="is_chat_agent" value="1" @checked(old('is_chat_agent', $agent->exists ? $agent->is_chat_agent : true))>
                <span class="sa-toggle__switch"></span>
                <span class="sa-toggle__text"><strong>Live chat</strong><small>Can claim and reply to live support chats.</small></span>
            </label>
            <label class="sa-toggle">
                <input type="checkbox" name="is_moderator" value="1" @checked(old('is_moderator', $agent->exists ? $agent->is_moderator : false))>
                <span class="sa-toggle__switch"></span>
                <span class="sa-toggle__text"><strong>Arena moderator</strong><small>Can approve or reject Arena community posts.</small></span>
            </label>
            <label class="sa-toggle">
                <input type="checkbox" name="is_news_editor" value="1" @checked(old('is_news_editor', $agent->exists ? $agent->is_news_editor : false))>
                <span class="sa-toggle__switch"></span>
                <span class="sa-toggle__text"><strong>News editor</strong><small>Can publish, edit, and remove site news posts.</small></span>
            </label>
            <label class="sa-toggle">
                <input type="checkbox" name="is_marketplace_supervisor" value="1" @checked(old('is_marketplace_supervisor', $agent->exists ? $agent->is_marketplace_supervisor : false))>
                <span class="sa-toggle__switch"></span>
                <span class="sa-toggle__text"><strong>Marketplace supervisor</strong><small>Can resolve coin-trade disputes and issue penalties.</small></span>
            </label>
        </section>

        <section class="sa-panel sa-panel--accent">
            <div class="sa-panel-head"><span class="sa-panel-icon sa-panel-icon--accent"><i class="las la-hand-holding-usd"></i></span><h2 class="sa-panel-title">Payment agent</h2></div>
            <label class="sa-toggle">
                <input type="checkbox" name="is_payment_agent" id="is-payment-agent" value="1" @checked(old('is_payment_agent', $agent->exists ? $agent->is_payment_agent : false))>
                <span class="sa-toggle__switch"></span>
                <span class="sa-toggle__text"><strong>Designate as a payment agent</strong><small>Only agents enabled here can be assigned to manual deposit and withdrawal methods.</small></span>
            </label>

            <div id="sa-payment-fields" class="sa-payment-fields">
                <div class="sa-field sa-field--wide">
                    <label>Assigned country and wallet currency</label>
                    <select class="form-control" name="country_id" id="agent-country">
                        <option value="">Select an allowed country</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected(old('country_id', $agent->country_id) == $country->id)>{{ $country->name }} · {{ $country->currency?->code }}</option>
                        @endforeach
                    </select>
                    <small>Payment agents must be assigned to the country and currency they serve.</small>
                </div>

                <div class="sa-methods-label">Payment methods</div>
                <div class="sa-methods" id="sa-methods">
                    @php($existingMethods = old('payment_methods', $agent->payment_details['methods'] ?? []))
                    @foreach($existingMethods as $i => $method)
                        @include('admin.support.agents.partials.payment_method_row', ['index' => $i, 'method' => (array) $method])
                    @endforeach
                </div>
                <button type="button" class="sa-add-method" id="sa-add-method"><i class="las la-plus"></i> Add payment method</button>

                <div class="sa-field sa-field--wide sa-instructions-field">
                    <label>Instructions for payers</label>
                    <textarea class="form-control" name="payment_instructions" rows="3" maxlength="2000" placeholder="e.g. Include your username as the transfer reference">{{ old('payment_instructions', $agent->payment_details['instructions'] ?? '') }}</textarea>
                    <small>Shown alongside the methods above - for anything that doesn't belong in a structured field.</small>
                </div>

                <div class="sa-grid sa-grid--verify">
                    <label class="sa-toggle">
                        <input type="checkbox" name="is_verified" value="1" @checked(old('is_verified', $agent->exists ? $agent->is_verified : false))>
                        <span class="sa-toggle__switch"></span>
                        <span class="sa-toggle__text"><strong>WinxPay Verified badge</strong><small>Shows the green "Verified" badge on this agent's public WinxPay card.</small></span>
                    </label>
                    <div class="sa-field">
                        <label>Speed override (0-100, optional)</label>
                        <input type="number" class="form-control" name="speed_override" min="0" max="100" value="{{ old('speed_override', $agent->speed_override) }}" placeholder="Leave blank to use computed score">
                        <small>Overrides the computed speed bar entirely when set.</small>
                    </div>
                </div>

                @if($agent->exists)
                    <div class="sa-computed">
                        <span><i class="las la-tachometer-alt"></i> Computed speed score: <strong>{{ $speedScore ?? 0 }}</strong>/100</span>
                        <span><i class="las la-bolt"></i> Fast confirms this month: <strong>{{ $fastStreakCount ?? 0 }}</strong>/50</span>
                        @if(!empty($agent->accepted_payment_types))
                            <span><i class="las la-tags"></i> Accepted types (set by agent): <strong>{{ implode(', ', $agent->accepted_payment_types) }}</strong></span>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        <div class="sa-actions">
            <button class="btn btn--primary" type="submit"><i class="las la-save"></i> Save agent</button>
            <a class="btn btn--dark" href="{{ route('admin.support.agents.index') }}">Cancel</a>
        </div>
    </form>
</div>

<template id="sa-method-template">@include('admin.support.agents.partials.payment_method_row', ['index' => '__INDEX__', 'method' => []])</template>

<style>
.sa-admin{--sa-bg:#101629;--sa-card:#151d33;--sa-card-2:#11192e;--sa-line:rgba(157,174,220,.16);--sa-muted:#8f9bbc;--sa-text:#f4f6ff;--sa-green:#00d987;--sa-danger:#ff718c;max-width:880px;margin:0 auto;color:var(--sa-text)}
.sa-admin *{box-sizing:border-box}
.sa-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;color:#b8c2df;font-size:12px;font-weight:700;text-decoration:none}
.sa-back:hover{color:var(--sa-green)}
.sa-hero{margin-bottom:16px;padding:22px 24px;border:1px solid var(--sa-line);border-radius:18px;background:linear-gradient(135deg,#18213a,#12192d)}
.sa-kicker{display:block;margin-bottom:6px;color:var(--sa-green);font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.sa-title{margin:0;color:var(--sa-text);font-size:clamp(20px,2.4vw,26px);font-weight:700;letter-spacing:-.02em}
.sa-intro{max-width:620px;margin:8px 0 0;color:var(--sa-muted);font-size:13px;line-height:1.55}
.sa-errors{display:flex;align-items:flex-start;gap:12px;margin-bottom:18px;padding:14px 16px;border:1px solid rgba(255,113,140,.3);border-radius:14px;background:rgba(255,113,140,.08);color:#ffd4dc;font-size:13px}
.sa-errors i{margin-top:2px;color:var(--sa-danger);font-size:17px}
.sa-errors strong{display:block;margin-bottom:4px;color:#fff}
.sa-errors ul{margin:0;padding:0 0 0 16px}
.sa-panel{min-width:0;margin-bottom:16px;padding:20px;border:1px solid var(--sa-line);border-radius:16px;background:linear-gradient(145deg,var(--sa-card),var(--sa-card-2));box-shadow:0 10px 28px rgba(0,0,0,.1)}
.sa-panel--accent{border-color:rgba(0,217,135,.25)}
.sa-panel-head{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.sa-panel-icon{display:grid;flex:0 0 32px;place-items:center;width:32px;height:32px;border-radius:10px;background:rgba(157,174,220,.12);color:var(--sa-muted);font-size:14px}
.sa-panel-icon--accent{background:rgba(0,217,135,.14);color:var(--sa-green)}
.sa-panel-title{margin:0;color:var(--sa-text);font-size:15.5px;font-weight:700;letter-spacing:-.01em}
.sa-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.sa-grid--verify{margin-top:14px}
.sa-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.sa-field--wide{grid-column:1/-1}
.sa-field label{color:#c6cde3;font-size:11px;font-weight:700}
.sa-field small{color:var(--sa-muted);font-size:11px}
.sa-field .form-control,.sa-admin select.form-control{min-height:42px;border:1px solid var(--sa-line);border-radius:10px;background:#0e1528;color:var(--sa-text)}
.sa-admin textarea.form-control{min-height:unset;border:1px solid var(--sa-line);border-radius:10px;background:#0e1528;color:var(--sa-text)}
.sa-current-photo{display:flex;align-items:center;gap:10px;margin-top:14px}
.sa-current-photo img{width:56px;height:56px;border-radius:50%;object-fit:cover;border:1px solid var(--sa-line)}
.sa-current-photo span{color:var(--sa-muted);font-size:11.5px}
.sa-toggle{display:flex;align-items:center;gap:12px;cursor:pointer}
.sa-toggle+.sa-toggle{margin-top:14px}
.sa-toggle input{position:absolute;opacity:0;width:0;height:0}
.sa-toggle__switch{position:relative;flex:0 0 42px;width:42px;height:24px;border-radius:999px;background:#242c48;border:1px solid var(--sa-line);transition:background .16s ease}
.sa-toggle__switch:before{content:'';position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;background:#aab3d4;transition:transform .16s ease,background .16s ease}
.sa-toggle input:checked+.sa-toggle__switch{background:rgba(0,217,135,.28);border-color:rgba(0,217,135,.5)}
.sa-toggle input:checked+.sa-toggle__switch:before{transform:translateX(18px);background:var(--sa-green)}
.sa-toggle__text strong{display:block;color:var(--sa-text);font-size:13px;font-weight:700}
.sa-toggle__text small{display:block;margin-top:2px;color:var(--sa-muted);font-size:11.5px;line-height:1.4}
.sa-payment-fields{margin-top:16px;padding-top:16px;border-top:1px solid var(--sa-line)}
.sa-methods-label{margin:16px 0 8px;color:#c6cde3;font-size:11px;font-weight:700}
.sa-methods{display:grid;gap:10px}
.sa-method{padding:14px;border:1px solid var(--sa-line);border-radius:13px;background:#0d1428}
.sa-method__head{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:10px}
.sa-method__type{flex:0 0 160px;min-height:40px!important}
.sa-method__label{flex:1 1 180px;min-height:40px!important}
.sa-method__remove{flex:0 0 auto;display:grid;place-items:center;width:38px;height:38px;border:1px solid var(--sa-line);border-radius:10px;background:rgba(255,113,140,.08);color:var(--sa-danger);cursor:pointer}
.sa-method__remove:hover{background:rgba(255,113,140,.16)}
.sa-method__fields{display:none;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding-top:10px;border-top:1px dashed var(--sa-line)}
.sa-method[data-type="bank"] .sa-method__fields[data-fields="bank"],
.sa-method[data-type="mobile_money"] .sa-method__fields[data-fields="mobile_money"],
.sa-method[data-type="crypto"] .sa-method__fields[data-fields="crypto"],
.sa-method[data-type="other"] .sa-method__fields[data-fields="other"]{display:grid}
.sa-add-method{display:inline-flex;align-items:center;gap:7px;margin-top:10px;padding:9px 14px;border:1px dashed rgba(0,217,135,.4);border-radius:10px;background:rgba(0,217,135,.06);color:var(--sa-green);font-size:12.5px;font-weight:700;cursor:pointer}
.sa-add-method:hover{background:rgba(0,217,135,.12)}
.sa-instructions-field{margin-top:16px}
.sa-computed{display:flex;flex-wrap:wrap;gap:16px;margin-top:16px;padding-top:14px;border-top:1px solid var(--sa-line);color:var(--sa-muted);font-size:11.5px}
.sa-computed i{margin-right:5px;color:var(--sa-green)}
.sa-computed strong{color:var(--sa-text)}
.sa-actions{display:flex;gap:10px;margin-top:4px}
@media(max-width:700px){.sa-grid,.sa-method__fields{grid-template-columns:1fr}.sa-hero,.sa-panel{padding:16px}.sa-method__type,.sa-method__label{flex:1 1 100%}}
</style>

<script>
(() => {
    const root = document.querySelector('.sa-admin');
    if (!root) return;

    const paymentFlag = document.getElementById('is-payment-agent');
    const paymentFields = document.getElementById('sa-payment-fields');
    const country = document.getElementById('agent-country');
    const syncPaymentVisibility = () => {
        const on = paymentFlag.checked;
        paymentFields.style.display = on ? '' : 'none';
        if (country) country.required = on;
    };
    if (paymentFlag && paymentFields) {
        paymentFlag.addEventListener('change', syncPaymentVisibility);
        syncPaymentVisibility();
    }

    const methodsBox = document.getElementById('sa-methods');
    const template = document.getElementById('sa-method-template');
    let nextIndex = methodsBox ? methodsBox.children.length : 0;

    const wireRow = (row) => {
        const typeSelect = row.querySelector('.sa-method__type');
        if (typeSelect) {
            typeSelect.addEventListener('change', () => { row.dataset.type = typeSelect.value; });
        }
        const removeBtn = row.querySelector('.sa-method__remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => row.remove());
        }
    };

    methodsBox?.querySelectorAll('.sa-method').forEach(wireRow);

    document.getElementById('sa-add-method')?.addEventListener('click', () => {
        const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        methodsBox.appendChild(row);
        wireRow(row);
    });
})();
</script>
@endsection
