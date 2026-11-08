@extends('layouts.app')
@section('title',$title->title)
@section('description',\Illuminate\Support\Str::limit($title->overview ?: $title->title,150))
@section('content')
@php
  $portrait=$displayMode==='portrait';
  $still=\App\Support\CatalogArtwork::url($selectedEpisode?->still_path ?: $title->backdrop_path ?: $title->poster_path,'original');
  $year=$type==='movie' ? ($title->release_year ?? null) : $title->first_air_date?->format('Y');
  $modeQuery=['type'=>$type,'slug'=>$title->slug];
  if($selectedEpisode) $modeQuery['episode']=$selectedEpisode->id;
@endphp
<div class="gm-watch-breadcrumb">
  <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span>
  <a href="{{ route('browse',['type'=>$type]) }}">{{ $type==='movie' ? 'Movies' : 'Series' }}</a><span aria-hidden="true">›</span>
  <span aria-current="page">{{ $title->title }}</span>
</div>
<div class="gm-watch-layout">
  <div class="gm-watch-main">
    <div class="gm-watch-toolbar">
      <div><span class="gm-eyebrow">Now exploring</span><h1>{{ $title->title }}</h1></div>
      <div class="gm-mode-switch" role="group" aria-label="Display aspect ratio">
        <a href="{{ route('watch.show',array_merge($modeQuery,['view'=>'widescreen'])) }}"
           @class(['is-selected'=>!$portrait]) @if(!$portrait) aria-current="page" @endif title="Widescreen 16 by 9">▭ <span>16:9</span></a>
        <a href="{{ route('watch.show',array_merge($modeQuery,['view'=>'portrait'])) }}"
           @class(['is-selected'=>$portrait]) @if($portrait) aria-current="page" @endif title="Portrait 9 by 16">▯ <span>9:16</span></a>
      </div>
    </div>

    <div class="gm-player-placement {{ $portrait ? 'is-portrait' : 'is-widescreen' }}">
      <section class="gm-player-stage" aria-label="{{ $portrait ? 'Portrait' : 'Widescreen' }} watch player">
        @if($still)<img class="gm-player-background" src="{{ $still }}" alt="" loading="eager" decoding="async">@endif
        <div class="gm-player-vignette" aria-hidden="true"></div>
        <span class="gm-player-aspect-label">{{ $portrait ? '▯ Portrait · 9:16' : '▭ Widescreen · 16:9' }}</span>
        @if($playerEnabled && $profileId)
        <div data-gomovie-player data-profile-id="{{ $profileId }}" class="gm-player-live">
          <video controls playsinline preload="none" aria-label="Gomovie secure player"></video>
          <div class="gm-player-controls">
            @if($type==='movie')
              <button type="button" data-play-type="movie" data-play-id="{{ $title->id }}" class="gm-button gm-button-primary">▶ Start playback</button>
            @elseif($selectedEpisode)
              <button type="button" data-play-type="episode" data-play-id="{{ $selectedEpisode->id }}" class="gm-button gm-button-primary">▶ Play selected episode</button>
            @endif
            <button type="button" data-stop-playback class="gm-button gm-button-secondary" hidden>Stop playback</button>
            <p data-playback-status role="status" aria-live="polite">Authorized playback requires approved media and access rights.</p>
          </div>
        </div>
        @else
          <div class="gm-player-lock-panel">
            <span class="gm-player-lock-icon" aria-hidden="true">♛</span>
            <p class="gm-eyebrow">{{ $title->is_premium ? 'Premium title' : 'Gomovie viewing' }}</p>
            <h2>{{ $viewerAuthorized ? 'Secure playback is being prepared' : 'Your next story starts here' }}</h2>
            <p>
              @if(!$viewerAuthorized)
                Sign in or create an account to save this title and follow new episodes. Playback opens when the licensed media service is approved.
              @else
                Secure streaming is not yet enabled for this installation. Media rights and delivery must be independently approved before playback.
              @endif
            </p>
            <div class="gm-watch-lock-actions">
              @guest
                <a href="{{ route('login') }}" class="gm-button gm-button-primary">Sign in</a>
                <a href="{{ route('register') }}" class="gm-button gm-button-secondary">Create account</a>
              @else
                @if(!$viewerAuthorized)<a href="{{ route('verification.notice') }}" class="gm-button gm-button-primary">Verify email</a>@endif
                <a href="{{ route('browse') }}" class="gm-button gm-button-secondary">Explore catalog</a>
              @endguest
            </div>
            @if($title->is_premium)<small>Paid access is not available until billing compliance and entitlements are verified.</small>@endif
          </div>
        @endif
      </section>
    </div>

    <div class="gm-watch-summary">
      <div class="gm-watch-title-block">
        <div class="gm-watch-tags"><span class="{{ $title->is_premium ? 'gm-tag-premium' : 'gm-tag-free' }}">{{ $title->is_premium ? '♛ Premium' : 'Free title' }}</span>
          <span>{{ $type==='movie' ? 'Film' : 'TV Series' }}</span>
          @if($year)<span>{{ $year }}</span>@endif
          @if($type==='movie' && $title->runtime_minutes)<span>{{ $title->runtime_minutes }} minutes</span>@endif
        </div>
        <h2>{{ $title->title }}</h2>
        @if($selectedEpisode)
          <p>Season {{ $selectedEpisode->season->season_number }} · Episode {{ $selectedEpisode->episode_number }}: {{ $selectedEpisode->title }}</p>
        @endif
      </div>
      <div class="gm-watch-actions">
        @if($profileId)
        <form action="{{ route('account.watchlist.toggle') }}" method="POST">
          @csrf
          <input type="hidden" name="profile_id" value="{{ $profileId }}">
          <input type="hidden" name="kind" value="{{ $type }}">
          <input type="hidden" name="title_id" value="{{ $title->id }}">
          <input type="hidden" name="action" value="{{ $isWatchlisted ? 'remove' : 'add' }}">
          <button class="gm-button gm-button-secondary" type="submit">{{ $isWatchlisted ? '♥ Remove from My List' : '♡ Add to My List' }}</button>
        </form>
        @elseif(!auth()->check())<a class="gm-button gm-button-secondary" href="{{ route('login') }}">♡ Save to list</a>@endif
        <button type="button" class="gm-button gm-button-secondary" data-share-url="{{ route('watch.show',[$type,$title->slug]) }}">↗ Share</button>
      </div>
    </div>
    @if($type==='series')
    <section class="gm-episode-section" aria-labelledby="episode-heading">
      <div class="gm-section-heading"><h2 id="episode-heading"><span class="gm-section-marker"></span>Episodes</h2><span class="gm-section-caption">{{ $seasons->sum(fn ($season)=>$season->episodes->count()) }} published</span></div>
      @forelse($seasons as $season)
        <div class="gm-season-group">
          <h3>Season {{ $season->season_number }} <span>{{ $season->title }}</span></h3>
          @if($season->episodes->isNotEmpty())
          <div class="gm-episode-strip">
            @foreach($season->episodes as $episode)
            <a href="{{ route('watch.show',[
                'type'=>$type,'slug'=>$title->slug,
                'episode'=>$episode->id,'view'=>$displayMode,
            ]) }}" @class(['gm-episode-chip','is-selected'=>$selectedEpisode?->id===$episode->id])
               @if($selectedEpisode?->id===$episode->id) aria-current="page" @endif
               title="{{ $episode->title }}">
              <span>EP {{ str_pad((string)$episode->episode_number,2,'0',STR_PAD_LEFT) }}</span>
            </a>
            @endforeach
          </div>
          @else<p class="gm-muted">No published episodes in this season.</p>@endif
        </div>
      @empty<p class="gm-empty-state">Episodes will appear when they are published.</p>@endforelse
    </section>
    @endif
    <section class="gm-watch-overview">
      <h2>Story details</h2>
      <p>{{ $selectedEpisode?->overview ?: $title->overview ?: 'A synopsis has not been published for this title.' }}</p>
      <p class="gm-muted">Playback requires approved content rights and a secure media-delivery configuration.</p>
    </section>
  </div>
  <aside class="gm-related-sidebar" aria-labelledby="gm-related-heading">
    <div class="gm-related-heading"><h2 id="gm-related-heading"><span aria-hidden="true">▣</span> Related Movies &amp; Series</h2><a href="{{ route('browse') }}">View all →</a></div>
    @forelse($related as $entry)
      @php
        $relatedItem=$entry['item']; $kind=$entry['kind'];
        $poster=\App\Support\CatalogArtwork::url($relatedItem->poster_path,'w342');
        $relatedYear=$kind==='movie' ? ($relatedItem->release_year ?? null) : $relatedItem->first_air_date?->format('Y');
      @endphp
      <a class="gm-related-item" href="{{ route('watch.show',[$kind,$relatedItem->slug]) }}">
        <span class="gm-related-thumbnail">
          @if($poster)<img src="{{ $poster }}" alt="" loading="lazy" decoding="async">@else<span aria-hidden="true">▶</span>@endif
        </span>
        <span class="gm-related-info"><strong>{{ $relatedItem->title }}</strong>
          <small>{{ $kind==='movie' ? 'Movie' : 'Series' }} @if($relatedYear) · {{ $relatedYear }} @endif</small>
          <em class="{{ $relatedItem->is_premium ? 'gm-tag-premium' : 'gm-tag-free' }}">{{ $relatedItem->is_premium ? 'Premium' : 'Free title' }}</em>
        </span>
      </a>
    @empty
      <p class="gm-related-empty">More published titles will appear here as the catalog grows.</p>
    @endforelse
  </aside>
</div>
@endsection
