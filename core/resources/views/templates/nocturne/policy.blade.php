@extends('Template::layouts.frontend')
@section('content')
    <section class="py-100">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <article class="nocturne-policy-content">
                        {!! $policy->data_values->details !!}
                    </article>
                </div>
            </div>
        </div>
    </section>
@endsection
