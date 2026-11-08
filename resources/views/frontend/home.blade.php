@extends('layouts.app')
@section('title','Home')
@section('content')
@if($heroTitles->isNotEmpty())
<section class="gm-hero" aria-label="Featured titles" x-data="{
  active:0,
  count:{{ $heroTitles->count() }},
  position(index) {
    let delta=(index-this.active+this.count)%this.count;
    if(delta>Math.floor(this.count/2)) delta-=this.count;
    if(delta===0) return 'is-center';
    if(delta===-1) return 'is-left';
    if(delta===1) return 'is-right';
    if(delta===-2) return 'is-far-left';
    if(delta===2) return 'is-far-right';
    return 'is-hidden';
  },
  previous(){this.active=(this.active+this.count-1)%this.count},
  next(){this.active=(this.active+1)%this.count}
}">
  <div class="gm-hero-backlight" aria-hidden="true"></div>
  <p class="gm-hero-kicker">CINEMATIC STORIES · CURATED DISCOVERY</p>
  <div class="gm-hero-stage">
    @foreach($heroTitles as $index=>$entry)
      @php
        $feature=$entry['item'];
        $image=\App\Support\CatalogArtwork::url($feature->poster_path ?? $feature->backdrop_path, 'w500');
      @endphp
      <button type="button" class="gm-hero-tile" :class="position({{ $index }})"
          @click="active={{ $index }}" :aria-current="active==={{ $index }} ? 'true' : 'false'"
          aria-label="Feature {{ $feature->title }}">
        @if($image)<img src="{{ $image }}" alt="" loading="{{ $index===0 ? 'eager' : 'lazy' }}" decoding="async">
        @else<div class="gm-hero-placeholder"><span aria-hidden="true">▶</span></div>@endif
        <span class="gm-hero-tile-overlay" aria-hidden="true"></span>
        <span class="gm-hero-badge">{{ $feature->is_premium ? '♛ Premium' : 'Free' }}</span>
        <span class="gm-hero-tile-caption"><strong>{{ $feature->title }}</strong><small>{{ $entry['kind']==='movie' ? 'Film' : 'TV Series' }}</small></span>
        <span class="gm-hero-play" aria-hidden="true">▶</span>
      </button>
    @endforeach
    @if($heroTitles->count()>1)
      <button class="gm-hero-arrow gm-hero-prev" type="button" @click="previous()" aria-label="Previous featured title">‹</button>
      <button class="gm-hero-arrow gm-hero-next" type="button" @click="next()" aria-label="Next featured title">›</button>
    @endif
  </div>
  <div class="gm-hero-pagination" aria-label="Select featured title">
    @foreach($heroTitles as $index=>$entry)
      <button type="button" @click="active={{ $index }}" :class="active==={{ $index }} ? 'is-current' : ''"
        :aria-current="active==={{ $index }} ? 'true' : 'false'"
        aria-label="Show featured title {{ $index+1 }}"></button>
    @endforeach
  </div>
  @foreach($heroTitles as $index=>$entry)
    @php $feature=$entry['item']; @endphp
    <div class="gm-hero-description" x-show="active==={{ $index }}" x-cloak x-transition.opacity>
      <div>
        <span class="gm-eyebrow">{{ $entry['kind']==='movie' ? 'Featured movie' : 'Featured series' }} <span aria-hidden="true">·</span> {{ $feature->is_premium ? 'Premium title' : 'Free title' }}</span>
        <h1>{{ $feature->title }}</h1>
        <p>{{ \Illuminate\Support\Str::limit($feature->overview ?: 'Explore the story, seasons, and publication details.',160) }}</p>
      </div>
      <div class="gm-hero-actions">
        <a class="gm-button gm-button-primary" href="{{ route('watch.show',[$entry['kind'],$feature->slug]) }}">▶ View title</a>
        <a class="gm-button gm-button-secondary" href="{{ route('browse') }}">Explore catalog</a>
      </div>
    </div>
  @endforeach
</section>
@else
<section class="gm-empty-hero">
  <div class="gm-empty-hero-art" aria-hidden="true"><span>▶</span></div>
  <div class="gm-empty-hero-content">
    <p class="gm-eyebrow">MOVIES · SERIES · MORE</p>
    <h1>Your next story <em>starts here.</em></h1>
    <p>Explore published films and series, save favorites and discover new stories as the Gomovie catalog grows.</p>
    <div class="gm-hero-actions">
      <a href="{{ route('browse') }}" class="gm-button gm-button-primary">Browse catalog</a>
      @guest<a href="{{ route('register') }}" class="gm-button gm-button-secondary">Create your account</a>@endguest
    </div>
  </div>
</section>
@endif

<section class="gm-promo-strip" aria-label="Gomovie viewing">
  <span class="gm-promo-icon" aria-hidden="true">✦</span>
  <div><strong>Gomovie <span>Stories</span></strong><p>Discover films · Explore series · Build your watchlist</p></div>
  <a href="{{ route('browse') }}" class="gm-button gm-button-gold">Explore titles <span aria-hidden="true">↗</span></a>
</section>

<section class="gm-content-section" aria-labelledby="gm-picks-title">
  <div class="gm-section-heading">
    <h2 id="gm-picks-title"><span class="gm-section-marker"></span>Top Picks for You</h2>
    <a href="{{ route('browse') }}" class="gm-view-all">View all <span aria-hidden="true">→</span></a>
  </div>
  @if($topPicks->isEmpty())
    <p class="gm-empty-state">Published movie and series picks will appear here once the catalog is ready.</p>
  @else
  <div class="gm-feature-grid">
    @foreach($topPicks->take(4) as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind'],'cardMode'=>'featured'])
    @endforeach
  </div>
  @endif
</section>

<section class="gm-content-section" aria-labelledby="gm-releases-title">
  <div class="gm-section-heading">
    <h2 id="gm-releases-title"><span class="gm-section-marker"></span>New Releases</h2>
    <a class="gm-view-all" href="{{ route('browse') }}">View all <span aria-hidden="true">→</span></a>
  </div>
  @if($newReleases->isEmpty())
    <p class="gm-empty-state">Newly published titles will appear here.</p>
  @else
  <div class="gm-content-rail">
    @foreach($newReleases as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind'],'cardMode'=>'rail'])
    @endforeach
  </div>
  @endif
</section>

@if($trending->isNotEmpty())
<section class="gm-content-section" aria-labelledby="gm-trending-title">
  <div class="gm-section-heading">
    <h2 id="gm-trending-title"><span class="gm-section-marker"></span>Trending Now <small>LAST 30 DAYS</small></h2>
    <a class="gm-view-all" href="{{ route('browse') }}">View all <span aria-hidden="true">→</span></a>
  </div>
  <div class="gm-content-rail">
    @foreach($trending as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind'],'cardMode'=>'rail','cardRank'=>$loop->iteration])
    @endforeach
  </div>
</section>
@endif

<section class="gm-content-section" aria-labelledby="gm-recent-title">
  <div class="gm-section-heading">
    <h2 id="gm-recent-title"><span class="gm-section-marker"></span>Recently Added</h2>
    <a class="gm-view-all" href="{{ route('browse') }}">View all <span aria-hidden="true">→</span></a>
  </div>
  @if($recentlyAdded->isEmpty())
    <p class="gm-empty-state">No published titles are available yet.</p>
  @else
  <div class="gm-poster-grid">
    @foreach($recentlyAdded as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind']])
    @endforeach
  </div>
  @endif
</section>
@endsection
