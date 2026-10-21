@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Bot identity</h5></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" action="{{ route('admin.support.bot.settings') }}">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label">Chatbot name</label>
                                <input class="form-control" name="chatbot_name" value="{{ old('chatbot_name', gs('chatbot_name')) }}" maxlength="60" required>
                                <small class="text-muted">Shown next to every bot message and on the chat launcher.</small>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Chatbot photo</label>
                                <input type="file" class="form-control" name="chatbot_photo" accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted">JPG, PNG, or WebP up to 3 MB.</small>
                            </div>
                            <div class="col-md-2 d-flex align-items-center gap-2">
                                @if(gs('chatbot_photo'))
                                    <img src="{{ asset('assets/images/chatbot/' . gs('chatbot_photo')) }}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:50%">
                                @else
                                    <span class="rounded-circle d-grid place-items-center bg--primary text-white" style="width:56px;height:56px">{{ strtoupper(substr(gs('chatbot_name') ?: 'L', 0, 1)) }}</span>
                                @endif
                            </div>
                        </div>
                        <button class="btn btn--primary mt-3" type="submit"><i class="las la-save"></i> Save identity</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1">Trained responses</h4>
                        <p class="text-muted mb-0">Add custom triggers and answers. These are checked before the bot's built-in replies, so they can sharpen or override the default behavior.</p>
                    </div>
                    <a href="{{ route('admin.support.bot.trainings.create') }}" class="btn btn--primary"><i class="las la-plus"></i> Add trained response</a>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead>
                                <tr><th>Triggers</th><th>Response</th><th>Order</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                @forelse($trainings as $training)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Str::limit($training->trigger_phrases, 80) }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($training->response, 90) }}</td>
                                        <td>{{ $training->sort_order }}</td>
                                        <td>@if($training->is_active)<span class="badge badge--success">Active</span>@else<span class="badge badge--secondary">Disabled</span>@endif</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('admin.support.bot.trainings.edit', $training->id) }}" class="btn btn-sm btn-outline--primary"><i class="las la-pen"></i> Edit</a>
                                            <form action="{{ route('admin.support.bot.trainings.status', $training->id) }}" method="post" class="d-inline">@csrf<button class="btn btn-sm btn-outline--warning" type="submit">{{ $training->is_active ? 'Disable' : 'Enable' }}</button></form>
                                            <button type="button" class="btn btn-sm btn-outline--danger confirmationBtn" data-question="Remove this trained response?" data-action="{{ route('admin.support.bot.trainings.delete', $training->id) }}"><i class="las la-trash"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center py-5 text-muted">No trained responses yet. The bot is still using its built-in replies.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($trainings->hasPages())<div class="card-footer">{{ paginateLinks($trainings) }}</div>@endif
            </div>
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn-sm btn-outline--primary" href="{{ route('admin.support.agents.index') }}"><i class="las la-headset"></i> Support agents</a>
@endpush
