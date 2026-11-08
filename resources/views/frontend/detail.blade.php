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
<div class="gm-watch-layout">
  <div class="gm-watch-main">
    <h1 class="sr-only">{{ $title->title }} — Watch page</h1>
    <div class="gm-watch-toolbar gm-watch-toolbar-compact">
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
        <div data-gomovie-player data-profile-id="{{ $profileId }}"
             data-next-url="{{ $autoNext['url'] ?? '' }}"
             data-next-title="{{ $autoNext['title'] ?? '' }}"
             data-auto-start="{{ request('autoplay') === '1' ? '1' : '0' }}"
             class="gm-player-live">
          <video controls playsinline preload="none" aria-label="Gomovie secure player"></video>
          <div class="gm-player-controls">
            @if($type==='movie')
              <button type="button" data-play-type="movie" data-play-id="{{ $title->id }}" class="gm-button gm-button-primary">▶ Start playback</button>
            @elseif($selectedEpisode)
              <button type="button" data-play-type="episode" data-play-id="{{ $selectedEpisode->id }}" class="gm-button gm-button-primary">▶ Play selected episode</button>
            @endif
            <button type="button" data-stop-playback class="gm-button gm-button-secondary" hidden>Stop playback</button>
            <button type="button" data-retry-playback class="gm-button gm-button-secondary" hidden>↻ Retry</button>
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

    @if($playerEnabled && $profileId)
      <section class="gm-watch-player-tools" aria-label="Playback options" data-gomovie-player-tools>
        <div class="gm-watch-player-tool-group">
          <button type="button" data-seek-offset="-10" title="Rewind 10 seconds" aria-label="Rewind 10 seconds">↶ 10s</button>
          <button type="button" data-seek-offset="10" title="Forward 10 seconds" aria-label="Forward 10 seconds">10s ↷</button>
          <button type="button" data-mute-toggle title="Mute or unmute" aria-label="Mute audio">♫</button>
          <label class="gm-watch-player-slider"><span>Volume</span><input type="range" data-playback-volume min="0" max="1" step="0.05" value="1" aria-label="Playback volume"></label>
        </div>
        <div class="gm-watch-player-tool-group">
          <label class="gm-watch-player-select"><span>Speed</span>
            <select data-playback-speed aria-label="Playback speed">
              <option value=".75">0.75×</option>
              <option value="1" selected>1×</option>
              <option value="1.25">1.25×</option>
              <option value="1.5">1.5×</option>
              <option value="2">2×</option>
            </select>
          </label>
          <label class="gm-watch-player-select" data-quality-container hidden><span>Quality</span>
            <select data-playback-quality aria-label="Video quality"><option value="-1">Auto</option></select>
          </label>
          <button type="button" data-captions-toggle aria-label="Turn captions on or off" title="Captions when available">CC</button>
          <button type="button" data-picture-in-picture title="Picture in picture">▣</button>
          <button type="button" data-fullscreen-player title="Fullscreen">⛶</button>
        </div>
        @if($autoNext)
          <label class="gm-watch-autoplay-choice">
            <input type="checkbox" data-auto-next-toggle checked>
            <span>Autoplay next</span>
          </label>
        @endif
      </section>
      @if($autoNext)
        <div class="gm-watch-next-panel" data-gomovie-next-prompt hidden role="status" aria-live="polite">
          <div><small>UP NEXT</small><strong>{{ $autoNext['title'] }}</strong>
            <span>Continuing in <span data-next-countdown>7</span>s</span>
          </div>
          <div class="gm-watch-next-actions">
            <button type="button" data-next-cancel>Cancel</button>
            <button type="button" data-next-now>Play next →</button>
          </div>
        </div>
      @endif
    @endif

    @if($type==='series')
      <section class="gm-episode-section gm-episode-section-near-player" aria-labelledby="episode-heading">
        <div class="gm-section-heading">
          <h2 id="episode-heading"><span class="gm-section-marker"></span>All Episodes</h2>
          <span class="gm-section-caption">{{ $seasons->sum(fn ($season) => $season->episodes->count()) }} published</span>
        </div>
        @if($selectedEpisode)
          <div class="gm-episode-navigation" aria-label="Episode navigation">
            @if($previousEpisode)
              <a href="{{ route('watch.show', ['type' => 'series', 'slug' => $title->slug, 'episode' => $previousEpisode->id, 'view' => $displayMode]) }}">← Previous episode</a>
            @else
              <span>Beginning of series</span>
            @endif
            @if($nextEpisode)
              <a href="{{ route('watch.show', ['type' => 'series', 'slug' => $title->slug, 'episode' => $nextEpisode->id, 'view' => $displayMode]) }}">Next episode →</a>
            @else
              <span>Latest published episode</span>
            @endif
          </div>
        @endif

        @forelse($seasons as $season)
          <div class="gm-season-group">
            <h3>Season {{ $season->season_number }} <span>{{ $season->title }}</span></h3>
            @if($season->episodes->isNotEmpty())
              <div class="gm-episode-strip">
                @foreach($season->episodes as $episode)
                  @php
                    $episodeImage = \App\Support\CatalogArtwork::url(
                        $episode->still_path ?: $season->poster_path ?: $title->poster_path,
                        'w342'
                    );
                  @endphp
                  <a href="{{ route('watch.show', [
                      'type' => 'series', 'slug' => $title->slug,
                      'episode' => $episode->id, 'view' => $displayMode,
                  ]) }}" @class(['gm-episode-chip', 'is-selected' => $selectedEpisode?->id === $episode->id])
                     @if($selectedEpisode?->id === $episode->id) aria-current="page" @endif
                     title="{{ $episode->title }}">
                    <span class="gm-episode-cover">
                      @if($episodeImage)<img src="{{ $episodeImage }}" loading="lazy" decoding="async" alt="" onerror="this.hidden=true">@endif
                      <span class="gm-episode-cover-number">{{ str_pad((string) $episode->episode_number, 2, '0', STR_PAD_LEFT) }}</span>
                    </span>
                    <span class="gm-episode-card-text">
                      <span>Episode {{ $episode->episode_number }}</span>
                      <strong>{{ $episode->title }}</strong>
                      @if($episode->runtime_minutes)<small>{{ $episode->runtime_minutes }} min</small>@endif
                    </span>
                  </a>
                @endforeach
              </div>
            @else
              <p class="gm-muted">No published episodes in this season yet.</p>
            @endif
          </div>
        @empty
          <p class="gm-empty-state">Episodes will appear here when they are published. Unpublished episodes are not shown to viewers.</p>
        @endforelse
      </section>
    @endif

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
