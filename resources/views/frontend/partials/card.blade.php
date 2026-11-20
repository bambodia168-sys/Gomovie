@php
  $poster = \App\Support\CatalogArtwork::url($item->poster_path ?? null, 'w500');
  $cardMode = $cardMode ?? 'standard';
  $cardRank = $cardRank ?? null;
  $kindLabel = $kind === 'movie' ? ($item->is_standalone_video ? 'Video' : 'Movie') : 'Series';
  $resumeCard = $resumeCard ?? null;
  $cardAvailability = $availability[$kind.':'.$item->id] ?? null;
  $badge = $cardAvailability ? \App\Services\Streaming\PlaybackAvailability::LABELS[$cardAvailability['state']] : null;
  $destination = $resumeCard['url'] ?? route('watch.show', array_filter([
    'type' => $kind, 'slug' => $item->slug,
    'episode' => $cardAvailability['episode'] ?? null, 'profile_id' => $profile?->id ?? null,
  ]));
  $year = $kind==='movie' ? ($item->release_year ?? null) : $item->first_air_date?->format('Y');
@endphp
<a href="{{ $destination }}"
   class="gm-poster-card gm-poster-card-{{ $cardMode }} group"
   aria-label="{{ $resumeCard ? 'Resume' : 'View' }} {{ $item->title }} — {{ strtolower($kindLabel) }}{{ $resumeCard ? ', '.$resumeCard['percent'].'% watched' : ($badge ? ', '.$badge : '') }}">
  <span class="gm-poster-frame">
    @if($poster)
      <img src="{{ $poster }}" alt="" loading="lazy" decoding="async" class="gm-poster-image">
    @else
      <span class="gm-poster-placeholder"><span aria-hidden="true">▶</span><strong>{{ $item->title }}</strong></span>
    @endif
    <span class="gm-poster-scrim" aria-hidden="true"></span>
    @if($cardRank)<span class="gm-rank-badge">{{ $cardRank }}</span>@endif
    <span class="gm-access-badge {{ $item->is_premium ? 'is-premium' : 'is-free' }}">{{ $item->is_premium ? 'Premium' : 'Free' }}</span>
    <span class="gm-poster-play" aria-hidden="true">▶</span>
  </span>
  <span class="gm-poster-details">
    <strong class="gm-poster-title">{{ $item->title }}</strong>
    @if($resumeCard)
      @if($resumeCard['episode_label'])<span class="gm-poster-meta">{{ $resumeCard['episode_label'] }}</span>@endif
      <progress class="gm-card-progress" aria-label="Watched" value="{{ $resumeCard['percent'] }}" max="100"></progress>
      <span class="gm-poster-meta">{{ $resumeCard['percent'] }}% watched</span>
    @else
      <span class="gm-poster-meta">@if($year){{ $year }} <span aria-hidden="true">·</span> @endif{{ $kindLabel }}</span>
      @if($badge)<span class="gm-card-availability" data-playback-state="{{ $cardAvailability['state'] }}">{{ $badge }}</span>@endif
    @endif
  </span>
</a>
