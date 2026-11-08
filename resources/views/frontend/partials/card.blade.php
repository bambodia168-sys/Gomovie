@php
  $poster = \App\Support\CatalogArtwork::url($item->poster_path ?? null, 'w500');
  $cardMode = $cardMode ?? 'standard';
  $cardRank = $cardRank ?? null;
  $year = $kind==='movie' ? ($item->release_year ?? null) : $item->first_air_date?->format('Y');
@endphp
<a href="{{ route('watch.show',[$kind,$item->slug]) }}"
   class="gm-poster-card gm-poster-card-{{ $cardMode }} group"
   aria-label="View {{ $item->title }} — {{ $kind === 'movie' ? 'movie' : 'series' }}">
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
    <span class="gm-poster-meta">{{ $year ?: ($kind === 'movie' ? 'Movie' : 'Series') }} <span aria-hidden="true">·</span> {{ $kind === 'movie' ? 'Movie' : 'Series' }}</span>
  </span>
</a>
