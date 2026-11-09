@extends('layouts.app')
@section('title',\App\Support\StudioAppearance::text('browse','seo_title','Explore titles'))
@section('description',\App\Support\StudioAppearance::text('browse','seo_description','Browse Gomovie catalog'))
@section('content')
<section class="gm-browse-intro">
  <p class="gm-eyebrow">{{ \App\Support\StudioAppearance::text('browse','eyebrow','Discover Gomovie') }}</p>
  <h1>{{ \App\Support\StudioAppearance::text('browse','heading','Movies & Series') }}</h1>
  <p>{{ \App\Support\StudioAppearance::text('browse','description','Explore published catalog titles. Playback depends on approved distribution rights and availability.') }}</p>
</section>
<form method="GET" action="{{ route('browse') }}" class="gm-filter-bar" role="search">
  <div class="gm-search-field"><label for="browse-query">Search titles</label>
    <input id="browse-query" type="search" name="q" maxlength="100" value="{{ $q }}" placeholder="Search movies and series...">
  </div>
  <div class="gm-select-field"><label for="browse-type">Content</label><select id="browse-type" name="type">
    @foreach(['all'=>'All titles','movie'=>'Movies','series'=>'Series'] as $value=>$label)<option value="{{ $value }}" @selected($type===$value)>{{ $label }}</option>@endforeach
  </select></div>
  <div class="gm-select-field"><label for="browse-access">Access</label><select id="browse-access" name="access">
    @foreach(['all'=>'All access','free'=>'Free','premium'=>'Premium'] as $value=>$label)<option value="{{ $value }}" @selected($access===$value)>{{ $label }}</option>@endforeach
  </select></div>
  <button class="gm-button gm-button-primary" type="submit">Apply filters</button>
</form>
@if($type !== 'series')
<section class="gm-content-section">
  <div class="gm-section-heading"><h2><span aria-hidden="true" class="gm-section-marker"></span>Movies</h2><span class="gm-section-caption">Published movies</span></div>
  @if($movies->isEmpty())
    <p class="gm-empty-state">No matching movies are available yet.</p>
  @else
    <div class="gm-poster-grid">@foreach($movies as $item)@include('frontend.partials.card',['kind'=>'movie'])@endforeach</div>
    <div class="mt-8">{{ $movies->links() }}</div>
  @endif
</section>
@endif
@if($type !== 'movie')
<section class="gm-content-section">
  <div class="gm-section-heading"><h2><span aria-hidden="true" class="gm-section-marker"></span>TV Series</h2><span class="gm-section-caption">Published series</span></div>
  @if($series->isEmpty())
    <p class="gm-empty-state">No matching TV series are available yet.</p>
  @else
    <div class="gm-poster-grid">@foreach($series as $item)@include('frontend.partials.card',['kind'=>'series'])@endforeach</div>
  @endif
</section>
@endif
@endsection
