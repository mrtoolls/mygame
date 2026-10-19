@extends('Template::layouts.master')
@section('content')
    <section style="max-width:720px;margin:40px auto;padding:clamp(24px,5vw,44px);border:1px solid var(--nocturne-line);border-radius:22px;background:var(--nocturne-panel);text-align:center;color:var(--nocturne-text)">
        <span style="display:grid;width:58px;height:58px;margin:0 auto 15px;place-items:center;border-radius:18px;background:rgba(0,239,135,.12);color:var(--nocturne-green);font-size:28px"><i class="ph-bold ph-dice-five"></i></span>
        <h1 style="font-size:24px;font-weight:950">@lang('Live Games are getting ready')</h1>
        <p style="margin:8px 0 0;color:var(--nocturne-muted);font-size:13px;line-height:1.7">@lang('The table game feature is being prepared. Please check back soon.')</p>
    </section>
@endsection
