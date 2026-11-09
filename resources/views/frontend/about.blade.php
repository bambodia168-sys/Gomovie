@extends('layouts.app')
@section('title',\App\Support\StudioAppearance::text('about','seo_title','About Gomovie'))
@section('description',\App\Support\StudioAppearance::text('about','seo_description','About Gomovie'))
@section('content')
<section class="gm-about-panel">
  <p class="gm-eyebrow">{{ \App\Support\StudioAppearance::text('about','eyebrow','About the platform') }}</p>
  <h1>{{ \App\Support\StudioAppearance::text('about','heading','Stories worth discovering.') }}</h1>
  <p>{{ \App\Support\StudioAppearance::text('about','description','Gomovie is a developing catalog and streaming platform for movies and TV series. Our public pages show published titles; playback remains restricted to content with approved rights, secure delivery and verified access.') }}</p>
  <div class="gm-about-grid">
    <article><span aria-hidden="true">▣</span><h2>{{ \App\Support\StudioAppearance::slot('about-explore-heading') }}</h2><p>{{ \App\Support\StudioAppearance::slot('about-explore-description') }}</p></article>
    <article><span aria-hidden="true">♡</span><h2>{{ \App\Support\StudioAppearance::slot('about-library-heading') }}</h2><p>{{ \App\Support\StudioAppearance::slot('about-library-description') }}</p></article>
    <article><span aria-hidden="true">♧</span><h2>{{ \App\Support\StudioAppearance::slot('about-delivery-heading') }}</h2><p>{{ \App\Support\StudioAppearance::slot('about-delivery-description') }}</p></article>
  </div>
  <a href="{{ route('browse') }}" class="gm-button gm-button-primary">Explore catalog</a>
</section>
@endsection
