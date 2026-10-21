@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two custom-data-table">
                            <thead>
                                <tr>
                                    <th>@lang('Cover')</th>
                                    <th>@lang('Title')</th>
                                    <th>@lang('Posted By')</th>
                                    <th>@lang('Views')</th>
                                    <th>@lang('Date')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($posts as $post)
                                    <tr>
                                        <td>
                                            @if($post->image)
                                                <img src="{{ $post->imageUrl() }}" alt="" style="width:64px;height:40px;border-radius:8px;object-fit:cover">
                                            @endif
                                        </td>
                                        <td class="fw-bold">
                                            {{ __($post->title) }}
                                            @if($post->badge_label)<br><span class="badge badge--dark">{{ __($post->badge_label) }}</span>@endif
                                        </td>
                                        <td>{{ $post->posted_by_name ?: '-' }} <span class="badge badge--{{ $post->posted_by_type === 'agent' ? 'info' : 'dark' }}">{{ ucfirst($post->posted_by_type) }}</span></td>
                                        <td>{{ $post->views }}</td>
                                        <td>{{ showDateTime($post->created_at) }}</td>
                                        <td>@php echo $post->statusBadge @endphp</td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.news.edit', $post->id) }}" class="btn btn-sm btn-outline--primary ms-1"><i class="las la-pen"></i>@lang('Edit')</a>
                                                @if ($post->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to unpublish this post?')" data-action="{{ route('admin.news.status', $post->id) }}">
                                                        <i class="la la-eye-slash"></i>@lang('Unpublish')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success ms-1 confirmationBtn" data-question="@lang('Are you sure to publish this post?')" data-action="{{ route('admin.news.status', $post->id) }}">
                                                        <i class="la la-eye"></i>@lang('Publish')
                                                    </button>
                                                @endif
                                                <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to delete this post?')" data-action="{{ route('admin.news.destroy', $post->id) }}">
                                                    <i class="la la-trash"></i>@lang('Delete')
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No news posts added yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{ paginateLinks($posts) }}
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn-outline--primary" href="{{ route('admin.news.create') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
