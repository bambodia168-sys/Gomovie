@extends('layouts.app')
@section('title', $page->seo_title ?: $page->heading)
@section('description',$page->seo_description ?: $page->description)
@section('content')
<section class="gm-about-panel">
  @if($page->eyebrow)<p class="gm-eyebrow">{{ $page->eyebrow }}</p>@endif
  <h1>{{ $page->heading }}</h1>
  @if($page->description)<p>{{ $page->description }}</p>@endif
  @if($page->body)<div class="mt-8 text-sm leading-7 whitespace-pre-line">{{ $page->body }}</div>@endif
</section>
@endsection
