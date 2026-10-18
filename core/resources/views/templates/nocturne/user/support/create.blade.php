@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-support-page">
            <div class="nocturne-support-heading"><div><span class="nocturne-support-eyebrow"><i class="ph-bold ph-headset"></i> WINXPOT CARE</span><h1 class="nocturne-page-title">Open a support ticket</h1><p>Tell us what happened and our team will follow up in your account.</p></div><a class="nocturne-support-back-link" href="{{ route('ticket.index') }}"><i class="ph-bold ph-arrow-left"></i> All conversations</a></div>
            <div class="nocturne-support-form-card">
                <form action="{{ route('ticket.store') }}" method="post" enctype="multipart/form-data" class="disableSubmission">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8"><label class="nocturne-support-label">Subject</label><input type="text" name="subject" value="{{ old('subject') }}" class="form-control form--control" placeholder="A short summary" maxlength="255" required></div>
                        <div class="col-md-4"><label class="nocturne-support-label">Priority</label><select name="priority" class="form-select form--control" required><option value="1">Low</option><option value="2" selected>Normal</option><option value="3">High</option></select></div>
                        <div class="col-12"><label class="nocturne-support-label">How can we help?</label><textarea name="message" rows="7" class="form-control form--control" placeholder="Include the details our team needs to help…" required>{{ old('message') }}</textarea></div>
                        <div class="col-12"><button type="button" class="nocturne-support-attach addAttachment"><i class="ph-bold ph-paperclip"></i> Add attachment</button><p class="nocturne-support-note">Up to 5 files: JPG, PNG, PDF, DOC, or DOCX.</p><div class="row fileUploadsContainer g-2"></div></div>
                        <div class="col-12"><button class="nocturne-support-submit" type="submit"><i class="ph-bold ph-paper-plane-tilt"></i> Send to support</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@push('script')
    <script>
        (function ($) {
            let added = 0;
            $('.addAttachment').on('click', function () {
                if (added >= 5) return;
                added++;
                $('.fileUploadsContainer').append('<div class="col-md-6 removeFileInput"><div class="input-group"><input type="file" name="attachments[]" class="form-control form--control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required><button type="button" class="input-group-text removeFile" aria-label="Remove attachment"><i class="las la-times"></i></button></div></div>');
                if (added >= 5) $('.addAttachment').prop('disabled', true);
            });
            $(document).on('click', '.removeFile', function () { $(this).closest('.removeFileInput').remove(); added--; $('.addAttachment').prop('disabled', false); });
        })(jQuery);
    </script>
@endpush
