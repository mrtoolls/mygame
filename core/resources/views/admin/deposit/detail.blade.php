@extends('admin.layouts.app')
@section('panel')
    @php
        $statusMeta = match ((int) $deposit->status) {
            \App\Constants\Status::PAYMENT_PENDING => ['label' => 'Pending', 'cls' => 'pending', 'icon' => 'la-hourglass-half'],
            \App\Constants\Status::PAYMENT_SUCCESS => ['label' => 'Approved', 'cls' => 'success', 'icon' => 'la-check-circle'],
            \App\Constants\Status::PAYMENT_REJECT => ['label' => 'Rejected', 'cls' => 'reject', 'icon' => 'la-times-circle'],
            default => ['label' => 'Initiated', 'cls' => 'initiate', 'icon' => 'la-circle-notch'],
        };
        $methodName = $deposit->method_code < 5000 ? (@$deposit->gateway->name ?: '—') : 'Google Pay';
        $decodedDetails = $details ? json_decode($details) : null;
        $isImage = fn ($name) => $name && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    @endphp

    <div class="nocturne-admin-wrap">
        <div class="nocturne-admin-hero">
            <a href="{{ route('admin.deposit.pending') }}" class="nocturne-admin-back"><i class="las la-arrow-left"></i> @lang('Back to deposits')</a>
            <div class="nocturne-admin-hero__row">
                <div class="nocturne-admin-hero__identity">
                    <span class="nocturne-admin-avatar">{{ mb_strtoupper(mb_substr(@$deposit->user->username ?? '?', 0, 1)) }}</span>
                    <div>
                        <span class="nocturne-admin-hero__eyebrow">{{ @$deposit->user->username }} @lang('requested')</span>
                        <h1>{{ showAmount($deposit->amount) }} <small>{{ __(gs('cur_text')) }}</small></h1>
                        <p>
                            <a href="{{ route('admin.users.detail', $deposit->user_id) }}">&commat;{{ @$deposit->user->username }}</a>
                            <span class="dot">&middot;</span> {{ $methodName }}
                            <span class="dot">&middot;</span> {{ $deposit->trx }}
                        </p>
                    </div>
                </div>
                <div class="nocturne-admin-hero__status">
                    <span class="nocturne-admin-chip nocturne-admin-chip--{{ $statusMeta['cls'] }}"><i class="las {{ $statusMeta['icon'] }}"></i> {{ __($statusMeta['label']) }}</span>
                    <span class="nocturne-admin-hero__date"><i class="las la-calendar"></i> {{ showDateTime($deposit->created_at) }}</span>
                </div>
            </div>
        </div>

        <div class="nocturne-admin-grid">
            <div class="nocturne-admin-col">
                <div class="nocturne-admin-card">
                    <h3 class="nocturne-admin-card__title"><i class="las la-receipt"></i> @lang('Transaction breakdown')</h3>
                    <div class="nocturne-admin-stats">
                        <div class="nocturne-admin-stat"><span>@lang('Amount')</span><strong>{{ showAmount($deposit->amount) }}</strong></div>
                        <div class="nocturne-admin-stat"><span>@lang('Charge')</span><strong>{{ showAmount($deposit->charge) }}</strong></div>
                        <div class="nocturne-admin-stat"><span>@lang('After charge')</span><strong>{{ showAmount($deposit->amount + $deposit->charge) }}</strong></div>
                        <div class="nocturne-admin-stat"><span>@lang('Rate')</span><strong>1 {{ __(gs('cur_text')) }} = {{ showAmount($deposit->rate, currencyFormat: false) }} {{ __($deposit->baseCurrency()) }}</strong></div>
                        <div class="nocturne-admin-stat nocturne-admin-stat--wide"><span>@lang('After rate conversion')</span><strong>{{ showAmount($deposit->final_amount, currencyFormat: false) }} {{ __($deposit->method_currency) }}</strong></div>
                    </div>

                    @if ($deposit->admin_feedback)
                        <div class="nocturne-admin-note">
                            <i class="las la-comment-dots"></i>
                            <div><span>@lang('Admin note')</span><p>{{ __($deposit->admin_feedback) }}</p></div>
                        </div>
                    @endif
                </div>

                @if ($deposit->payment_agent_snapshot)
                    <div class="nocturne-admin-card">
                        <h3 class="nocturne-admin-card__title"><i class="las la-user-tie"></i> @lang('Handled by agent')</h3>
                        <div class="nocturne-admin-agent">
                            <span class="nocturne-admin-avatar nocturne-admin-avatar--sm">{{ mb_strtoupper(mb_substr($deposit->payment_agent_snapshot->name ?? '?', 0, 1)) }}</span>
                            <div>
                                <strong>{{ $deposit->payment_agent_snapshot->name }}</strong>
                                <small>{{ $deposit->payment_agent_snapshot->email }}</small>
                            </div>
                        </div>
                        @if (!empty($deposit->payment_agent_snapshot->payment_details))
                            <p class="nocturne-admin-agent__details">{!! nl2br(e($deposit->payment_agent_snapshot->payment_details)) !!}</p>
                        @endif
                    </div>
                @endif

                @if ($deposit->payment_proof || $deposit->payer_wallet_address)
                    <div class="nocturne-admin-card">
                        <h3 class="nocturne-admin-card__title"><i class="las la-shield-alt"></i> @lang('Crypto deposit confirmation')</h3>

                        @if ($deposit->disputed_at)
                            <div class="nocturne-admin-alert nocturne-admin-alert--danger">
                                <i class="las la-exclamation-triangle"></i>
                                <div>
                                    <strong>@lang('Disputed by user')</strong> &middot; {{ showDateTime($deposit->disputed_at) }}
                                    @if ($deposit->dispute_reason)<p>{{ $deposit->dispute_reason }}</p>@endif
                                </div>
                            </div>
                        @endif

                        <div class="nocturne-admin-stats">
                            @if ($deposit->payer_wallet_address)
                                <div class="nocturne-admin-stat nocturne-admin-stat--wide"><span>@lang('Paid from address')</span><strong class="is-code">{{ $deposit->payer_wallet_address }}</strong></div>
                            @endif
                            @if ($deposit->confirmation_deadline_at)
                                <div class="nocturne-admin-stat"><span>@lang('Confirm by')</span><strong>{{ showDateTime($deposit->confirmation_deadline_at) }}</strong></div>
                            @endif
                        </div>

                        @if ($deposit->payment_proof)
                            @php $proofUrl = route('admin.preview.attachment', encrypt(getFilePath('verify') . '/' . $deposit->payment_proof)); @endphp
                            <div class="nocturne-admin-receipt">
                                <span class="nocturne-admin-receipt__label">@lang('Payment receipt')</span>
                                @if ($isImage($deposit->payment_proof))
                                    <button type="button" class="nocturne-admin-receipt__frame" data-lightbox="{{ $proofUrl }}">
                                        <img src="{{ $proofUrl }}" alt="@lang('Payment receipt')" loading="lazy">
                                        <span class="nocturne-admin-receipt__zoom"><i class="las la-expand"></i> @lang('View full size')</span>
                                    </button>
                                @else
                                    <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="nocturne-admin-filelink"><i class="las la-file"></i> @lang('Open receipt')</a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                @if ($decodedDetails)
                    @php $hasFormFields = $deposit->method_code >= 1000 && collect($decodedDetails)->isNotEmpty(); @endphp
                    @if ($hasFormFields)
                        <div class="nocturne-admin-card">
                            <h3 class="nocturne-admin-card__title"><i class="las la-list-alt"></i> @lang('Submitted details')</h3>
                            <div class="nocturne-admin-fields">
                                @foreach ($decodedDetails as $val)
                                    <div class="nocturne-admin-field">
                                        <span>{{ __($val->name) }}</span>
                                        @if ($val->type == 'checkbox')
                                            <strong>{{ implode(', ', $val->value ?? []) }}</strong>
                                        @elseif($val->type == 'file')
                                            @if ($val->value)
                                                @php $fileUrl = route('admin.preview.attachment', encrypt(getFilePath('verify') . '/' . $val->value)); @endphp
                                                @if ($isImage($val->value))
                                                    <button type="button" class="nocturne-admin-receipt__frame nocturne-admin-receipt__frame--inline" data-lightbox="{{ $fileUrl }}">
                                                        <img src="{{ $fileUrl }}" alt="{{ __($val->name) }}" loading="lazy">
                                                        <span class="nocturne-admin-receipt__zoom"><i class="las la-expand"></i></span>
                                                    </button>
                                                @else
                                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="nocturne-admin-filelink"><i class="las la-file"></i> @lang('Open file')</a>
                                                @endif
                                            @else
                                                <strong class="is-muted">@lang('No file')</strong>
                                            @endif
                                        @else
                                            <strong>{{ __($val->value) }}</strong>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($deposit->method_code < 1000)
                        <div class="nocturne-admin-card">
                            <h3 class="nocturne-admin-card__title"><i class="las la-globe"></i> @lang('Gateway response')</h3>
                            <div class="nocturne-admin-fields">
                                @include('admin.deposit.gateway_data', ['details' => $decodedDetails])
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        @if ($deposit->status == \App\Constants\Status::PAYMENT_PENDING)
            <div class="nocturne-admin-actionbar">
                <span class="nocturne-admin-actionbar__hint"><i class="las la-info-circle"></i> @lang('Review the details above before you decide.')</span>
                <div class="nocturne-admin-actionbar__btns">
                    <button type="button" class="nocturne-admin-btn nocturne-admin-btn--danger" data-open-modal="rejectModal"><i class="las la-ban"></i> @lang('Reject')</button>
                    <button type="button" class="nocturne-admin-btn nocturne-admin-btn--success" data-open-modal="approveModal"><i class="las la-check"></i> @lang('Approve')</button>
                </div>
            </div>
        @endif
    </div>

    {{-- APPROVE MODAL --}}
    <div class="nocturne-admin-modal" id="approveModal" hidden>
        <div class="nocturne-admin-modal__backdrop" data-close-modal></div>
        <div class="nocturne-admin-modal__sheet">
            <form action="{{ route('admin.deposit.approve', $deposit->id) }}" method="post">
                @csrf
                <div class="nocturne-admin-modal__head">
                    <span class="nocturne-admin-modal__icon nocturne-admin-modal__icon--success"><i class="las la-check-circle"></i></span>
                    <h3>@lang('Approve this deposit?')</h3>
                    <button type="button" class="nocturne-admin-modal__close" data-close-modal><i class="las la-times"></i></button>
                </div>
                <div class="nocturne-admin-modal__body">
                    <p>@lang('This credits') <strong>{{ showAmount($deposit->amount) }}</strong> @lang('to') <strong>&commat;{{ @$deposit->user->username }}</strong>'s @lang('wallet immediately.')</p>
                    <label>@lang('Note to user (optional)')</label>
                    <textarea name="message" maxlength="255" rows="3" placeholder="@lang('e.g. Confirmed, thanks for the quick payment!')"></textarea>
                </div>
                <div class="nocturne-admin-modal__foot">
                    <button type="button" class="nocturne-admin-btn nocturne-admin-btn--ghost" data-close-modal>@lang('Cancel')</button>
                    <button type="submit" class="nocturne-admin-btn nocturne-admin-btn--success"><i class="las la-check"></i> @lang('Confirm approval')</button>
                </div>
            </form>
        </div>
    </div>

    {{-- REJECT MODAL --}}
    <div class="nocturne-admin-modal" id="rejectModal" hidden>
        <div class="nocturne-admin-modal__backdrop" data-close-modal></div>
        <div class="nocturne-admin-modal__sheet">
            <form action="{{ route('admin.deposit.reject') }}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{ $deposit->id }}">
                <div class="nocturne-admin-modal__head">
                    <span class="nocturne-admin-modal__icon nocturne-admin-modal__icon--danger"><i class="las la-ban"></i></span>
                    <h3>@lang('Reject this deposit?')</h3>
                    <button type="button" class="nocturne-admin-modal__close" data-close-modal><i class="las la-times"></i></button>
                </div>
                <div class="nocturne-admin-modal__body">
                    <p>@lang('Reject') <strong>{{ showAmount($deposit->amount) }}</strong> @lang('from') <strong>&commat;{{ @$deposit->user->username }}</strong>?</p>
                    <label>@lang('Reason for rejection')</label>
                    <textarea name="message" maxlength="255" rows="3" required placeholder="@lang('Explain why this deposit is being rejected...')">{{ old('message') }}</textarea>
                </div>
                <div class="nocturne-admin-modal__foot">
                    <button type="button" class="nocturne-admin-btn nocturne-admin-btn--ghost" data-close-modal>@lang('Cancel')</button>
                    <button type="submit" class="nocturne-admin-btn nocturne-admin-btn--danger"><i class="las la-ban"></i> @lang('Confirm rejection')</button>
                </div>
            </form>
        </div>
    </div>

    {{-- LIGHTBOX --}}
    <div class="nocturne-admin-lightbox" id="receiptLightbox" hidden>
        <div class="nocturne-admin-lightbox__backdrop" data-close-lightbox></div>
        <button type="button" class="nocturne-admin-lightbox__close" data-close-lightbox><i class="las la-times"></i></button>
        <img src="" alt="@lang('Receipt preview')">
    </div>
@endsection

@push('style')
<style>
:root{--na-accent:#00e78a;--na-bg:#0d1225;--na-panel:#151a31;--na-raised:#1b2140;--na-muted:#858eaf;--na-line:rgba(133,142,175,.18);--na-text:#f5f6ff;--na-danger:#ff5c7a;--na-warning:#ffb547}
.page-breadcrumb{display:none}
.nocturne-admin-wrap{max-width:1180px;margin:0 auto 90px}
.nocturne-admin-back{display:inline-flex;align-items:center;gap:7px;margin-bottom:14px;color:var(--na-muted);font-size:13px;font-weight:700;text-decoration:none}
.nocturne-admin-back:hover{color:var(--na-text)}

.nocturne-admin-hero{position:relative;overflow:hidden;margin-bottom:20px;padding:24px 28px;border:1px solid var(--na-line);border-radius:24px;background:radial-gradient(ellipse at 85% 0,rgba(0,231,138,.18),transparent 36%),linear-gradient(120deg,#171d37,#11162b 72%);box-shadow:0 18px 50px rgba(0,0,0,.22)}
.nocturne-admin-hero__row{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap}
.nocturne-admin-hero__identity{display:flex;align-items:center;gap:16px}
.nocturne-admin-avatar{display:grid;place-items:center;flex:0 0 54px;width:54px;height:54px;border-radius:16px;background:linear-gradient(145deg,rgba(0,231,138,.25),rgba(0,231,138,.08));color:var(--na-accent);font-size:22px;font-weight:850}
.nocturne-admin-avatar--sm{flex:0 0 38px;width:38px;height:38px;border-radius:12px;font-size:15px}
.nocturne-admin-hero__identity .nocturne-admin-hero__eyebrow{display:block;margin-bottom:5px;color:var(--na-accent);font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.nocturne-admin-hero__identity h1{margin:0;color:var(--na-text);font-size:clamp(22px,3vw,30px);font-weight:850;letter-spacing:-.03em}
.nocturne-admin-hero__identity h1 small{color:var(--na-muted);font-size:14px;font-weight:700}
.nocturne-admin-hero__identity p{margin:5px 0 0;color:var(--na-muted);font-size:13px}
.nocturne-admin-hero__identity p a{color:var(--na-accent);font-weight:750;text-decoration:none}
.nocturne-admin-hero__identity .dot{margin:0 6px;opacity:.5}
.nocturne-admin-hero__status{display:flex;flex-direction:column;align-items:flex-end;gap:8px}
.nocturne-admin-hero__date{display:flex;align-items:center;gap:6px;color:var(--na-muted);font-size:12px}

.nocturne-admin-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.02em}
.nocturne-admin-chip--pending{background:rgba(255,181,71,.14);color:var(--na-warning)}
.nocturne-admin-chip--success{background:rgba(0,231,138,.14);color:var(--na-accent)}
.nocturne-admin-chip--reject{background:rgba(255,92,122,.14);color:var(--na-danger)}
.nocturne-admin-chip--initiate{background:rgba(133,142,175,.16);color:var(--na-muted)}

.nocturne-admin-grid{display:flex;gap:20px}
.nocturne-admin-col{flex:1;min-width:0;display:grid;gap:18px}

.nocturne-admin-card{padding:22px 24px;border:1px solid var(--na-line);border-radius:22px;background:linear-gradient(160deg,#151a31,#10152a 78%);box-shadow:0 14px 38px rgba(0,0,0,.16)}
.nocturne-admin-card__title{display:flex;align-items:center;gap:9px;margin:0 0 16px;color:var(--na-text);font-size:15px;font-weight:800}
.nocturne-admin-card__title i{color:var(--na-accent);font-size:17px}

.nocturne-admin-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.nocturne-admin-stat{padding:13px 15px;border-radius:14px;background:rgba(255,255,255,.03)}
.nocturne-admin-stat--wide{grid-column:1/-1}
.nocturne-admin-stat span{display:block;margin-bottom:4px;color:var(--na-muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}
.nocturne-admin-stat strong{display:block;color:var(--na-text);font-size:14.5px;font-weight:750;overflow-wrap:anywhere}
.nocturne-admin-stat strong.is-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px}

.nocturne-admin-note{display:flex;gap:11px;margin-top:16px;padding:13px 15px;border:1px solid rgba(0,231,138,.18);border-radius:14px;background:rgba(0,231,138,.06)}
.nocturne-admin-note i{color:var(--na-accent);font-size:17px;margin-top:2px}
.nocturne-admin-note span{display:block;margin-bottom:3px;color:var(--na-accent);font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.nocturne-admin-note p{margin:0;color:var(--na-text);font-size:13.5px}

.nocturne-admin-agent{display:flex;align-items:center;gap:12px}
.nocturne-admin-agent strong{display:block;color:var(--na-text);font-size:14.5px}
.nocturne-admin-agent small{color:var(--na-muted);font-size:12px}
.nocturne-admin-agent__details{margin:14px 0 0;padding-top:14px;border-top:1px dashed var(--na-line);color:var(--na-muted);font-size:13px;line-height:1.6}

.nocturne-admin-alert{display:flex;gap:11px;margin-bottom:16px;padding:13px 15px;border-radius:14px}
.nocturne-admin-alert--danger{border:1px solid rgba(255,92,122,.25);background:rgba(255,92,122,.08)}
.nocturne-admin-alert i{color:var(--na-danger);font-size:18px;margin-top:2px}
.nocturne-admin-alert strong{color:var(--na-text);font-size:13.5px}
.nocturne-admin-alert p{margin:4px 0 0;color:var(--na-muted);font-size:13px}

.nocturne-admin-receipt{margin-top:16px}
.nocturne-admin-receipt__label{display:block;margin-bottom:9px;color:var(--na-muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}
.nocturne-admin-receipt__frame{position:relative;display:block;width:100%;max-width:320px;padding:0;border:1px solid var(--na-line);border-radius:16px;overflow:hidden;background:#fff;cursor:zoom-in}
.nocturne-admin-receipt__frame--inline{max-width:220px}
.nocturne-admin-receipt__frame img{display:block;width:100%;max-height:260px;object-fit:contain;background:#fff}
.nocturne-admin-receipt__frame--inline img{max-height:150px}
.nocturne-admin-receipt__zoom{position:absolute;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:linear-gradient(0deg,rgba(0,0,0,.72),transparent);color:#fff;font-size:11.5px;font-weight:700;opacity:0;transition:opacity .15s}
.nocturne-admin-receipt__frame:hover .nocturne-admin-receipt__zoom{opacity:1}
.nocturne-admin-filelink{display:inline-flex;align-items:center;gap:7px;padding:10px 15px;border:1px solid var(--na-line);border-radius:12px;background:var(--na-bg);color:var(--na-text);font-size:13px;font-weight:750;text-decoration:none}
.nocturne-admin-filelink:hover{border-color:rgba(0,231,138,.4);color:var(--na-accent)}

.nocturne-admin-fields{display:grid;gap:10px}
.nocturne-admin-field{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 14px;border-radius:13px;background:rgba(255,255,255,.03);flex-wrap:wrap}
.nocturne-admin-field>span{color:var(--na-muted);font-size:12.5px;font-weight:700}
.nocturne-admin-field>strong{color:var(--na-text);font-size:13.5px;font-weight:750;text-align:right;overflow-wrap:anywhere}
.nocturne-admin-field>strong.is-muted{color:var(--na-muted);font-weight:600}
.nocturne-admin-fields h6{margin:0 0 6px;color:var(--na-muted);font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.nocturne-admin-fields p{margin:0 0 14px;color:var(--na-text);font-size:13.5px;overflow-wrap:anywhere}
.nocturne-admin-fields hr{margin:0 0 12px;border-color:var(--na-line);opacity:1}
.nocturne-admin-fields .row{margin:0}
.nocturne-admin-fields .ms-3{margin-left:0!important;padding-left:14px;border-left:2px solid var(--na-line)}

.nocturne-admin-actionbar{position:sticky;bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:22px;padding:16px 20px;border:1px solid var(--na-line);border-radius:18px;background:rgba(13,18,37,.92);backdrop-filter:blur(10px);box-shadow:0 20px 50px rgba(0,0,0,.35);flex-wrap:wrap}
.nocturne-admin-actionbar__hint{display:flex;align-items:center;gap:7px;color:var(--na-muted);font-size:12.5px}
.nocturne-admin-actionbar__hint i{color:var(--na-accent)}
.nocturne-admin-actionbar__btns{display:flex;gap:10px}

.nocturne-admin-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 20px;border:0;border-radius:12px;font-size:13.5px;font-weight:800;cursor:pointer;transition:transform .12s,opacity .12s}
.nocturne-admin-btn:hover{transform:translateY(-1px)}
.nocturne-admin-btn--success{background:var(--na-accent);color:#062a23}
.nocturne-admin-btn--danger{background:var(--na-danger);color:#2a0712}
.nocturne-admin-btn--ghost{background:rgba(255,255,255,.06);color:var(--na-text)}

.nocturne-admin-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:flex-end;justify-content:center}
.nocturne-admin-modal__backdrop{position:absolute;inset:0;background:rgba(5,8,18,.72);backdrop-filter:blur(3px)}
.nocturne-admin-modal__sheet{position:relative;z-index:1;width:100%;max-width:460px;margin:0 16px 16px;border:1px solid var(--na-line);border-radius:22px;background:linear-gradient(165deg,#171d36,#11152a 80%);box-shadow:0 30px 70px rgba(0,0,0,.5);animation:na-modal-up .22s ease}
@keyframes na-modal-up{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}
.nocturne-admin-modal__head{display:flex;align-items:center;gap:12px;padding:20px 22px 6px}
.nocturne-admin-modal__icon{display:grid;place-items:center;flex:0 0 40px;width:40px;height:40px;border-radius:12px;font-size:19px}
.nocturne-admin-modal__icon--success{background:rgba(0,231,138,.14);color:var(--na-accent)}
.nocturne-admin-modal__icon--danger{background:rgba(255,92,122,.14);color:var(--na-danger)}
.nocturne-admin-modal__head h3{flex:1;margin:0;color:var(--na-text);font-size:16px;font-weight:800}
.nocturne-admin-modal__close{flex:0 0 auto;border:0;background:transparent;color:var(--na-muted);font-size:18px;cursor:pointer}
.nocturne-admin-modal__body{padding:12px 22px}
.nocturne-admin-modal__body p{margin:0 0 14px;color:var(--na-muted);font-size:13.5px;line-height:1.6}
.nocturne-admin-modal__body p strong{color:var(--na-text)}
.nocturne-admin-modal__body label{display:block;margin-bottom:7px;color:var(--na-muted);font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.nocturne-admin-modal__body textarea{width:100%;padding:11px 13px;border:1px solid var(--na-line);border-radius:12px;background:var(--na-bg);color:var(--na-text);font:inherit;font-size:13.5px;resize:vertical;outline:0}
.nocturne-admin-modal__body textarea:focus{border-color:rgba(0,231,138,.45)}
.nocturne-admin-modal__foot{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px 22px}

.nocturne-admin-lightbox{position:fixed;inset:0;z-index:1300;display:flex;align-items:center;justify-content:center;padding:30px}
.nocturne-admin-lightbox__backdrop{position:absolute;inset:0;background:rgba(5,8,18,.88)}
.nocturne-admin-lightbox img{position:relative;z-index:1;max-width:100%;max-height:100%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.6)}
.nocturne-admin-lightbox__close{position:absolute;top:22px;right:22px;z-index:2;display:grid;place-items:center;width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,.1);color:#fff;font-size:18px;cursor:pointer}

@media(max-width:991.98px){
    .nocturne-admin-grid{flex-direction:column}
}
@media(max-width:575.98px){
    .nocturne-admin-wrap{margin-bottom:84px}
    .nocturne-admin-hero{padding:18px}
    .nocturne-admin-hero__row{flex-direction:column;align-items:stretch}
    .nocturne-admin-hero__status{align-items:flex-start}
    .nocturne-admin-card{padding:17px}
    .nocturne-admin-stats{grid-template-columns:1fr}
    .nocturne-admin-field{flex-direction:column;align-items:flex-start}
    .nocturne-admin-field>strong{text-align:left}
    .nocturne-admin-actionbar{position:fixed;left:12px;right:12px;bottom:12px;flex-direction:column;align-items:stretch}
    .nocturne-admin-actionbar__btns{width:100%}
    .nocturne-admin-actionbar__btns .nocturne-admin-btn{flex:1}
    .nocturne-admin-modal__sheet{max-width:none}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    $('[data-open-modal]').on('click', function() {
        $('#' + $(this).data('open-modal')).prop('hidden', false);
        $('body').css('overflow', 'hidden');
    });
    $('[data-close-modal]').on('click', function() {
        $(this).closest('.nocturne-admin-modal').prop('hidden', true);
        $('body').css('overflow', '');
    });
    $('[data-lightbox]').on('click', function() {
        $('#receiptLightbox img').attr('src', $(this).data('lightbox'));
        $('#receiptLightbox').prop('hidden', false);
        $('body').css('overflow', 'hidden');
    });
    $('[data-close-lightbox]').on('click', function() {
        $('#receiptLightbox').prop('hidden', true);
        $('#receiptLightbox img').attr('src', '');
        $('body').css('overflow', '');
    });
    $(document).on('keydown', function(e) {
        if (e.key !== 'Escape') return;
        $('.nocturne-admin-modal, .nocturne-admin-lightbox').prop('hidden', true);
        $('body').css('overflow', '');
    });
})(jQuery);
</script>
@endpush
