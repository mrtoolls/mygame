@extends('admin.layouts.app')

@section('panel')
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ $training->exists ? 'Edit trained response' : 'Add a trained response' }}</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ $training->exists ? route('admin.support.bot.trainings.update', $training->id) : route('admin.support.bot.trainings.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Trigger phrases</label>
                                <input class="form-control" name="trigger_phrases" value="{{ old('trigger_phrases', $training->trigger_phrases) }}" maxlength="1000" required placeholder="rakeback, cashback, loyalty rewards">
                                <small class="text-muted">Comma-separated. The bot replies with the response below if a visitor's message contains any one of these phrases.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Response</label>
                                <textarea class="form-control" name="response" rows="4" maxlength="2000" required>{{ old('response', $training->response) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority order</label>
                                <input type="number" class="form-control" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $training->sort_order ?? 0) }}">
                                <small class="text-muted">Lower numbers are checked first when more than one trained response could match.</small>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $training->exists ? $training->is_active : true))><span class="form-check-label">Active</span></label>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn--primary" type="submit"><i class="las la-save"></i> Save</button><a class="btn btn--dark" href="{{ route('admin.support.bot.index') }}">Cancel</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
