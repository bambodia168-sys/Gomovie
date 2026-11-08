@extends('layouts.app')
@section('title','About Gomovie')
@section('content')
<section class="gm-about-panel">
  <p class="gm-eyebrow">About the platform</p>
  <h1>Stories worth discovering.</h1>
  <p>Gomovie is a developing catalog and streaming platform for movies and TV series. Our public pages show published titles; playback remains restricted to content with approved rights, secure delivery and verified access.</p>
  <div class="gm-about-grid">
    <article><span aria-hidden="true">▣</span><h2>Explore</h2><p>Find movies, TV series and episode information in one catalog.</p></article>
    <article><span aria-hidden="true">♡</span><h2>Personal library</h2><p>Registered, verified viewers can save titles to their private lists.</p></article>
    <article><span aria-hidden="true">♧</span><h2>Responsible delivery</h2><p>Billing, private streaming and media processing are enabled only after independent acceptance.</p></article>
  </div>
  <a href="{{ route('browse') }}" class="gm-button gm-button-primary">Explore catalog</a>
</section>
@endsection
