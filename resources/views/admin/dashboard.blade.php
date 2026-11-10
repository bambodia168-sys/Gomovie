@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
@php
    $totalMovies = (int) $metrics['movies'];
    $totalSeries = (int) $metrics['series'];
    $publishedMovies = (int) $metrics['published_movies'];
    $publishedSeries = (int) $metrics['published_series'];
    $moviePublicationRate = $totalMovies > 0 ? (int) round($publishedMovies / $totalMovies * 100) : 0;
    $seriesPublicationRate = $totalSeries > 0 ? (int) round($publishedSeries / $totalSeries * 100) : 0;
    $canManageUsers = \App\Support\StudioNavigation::administrator(auth()->user());
@endphp

<div class="gm-dashboard-overview">
    <div class="gm-page-heading gm-dashboard-heading">
        <div>
            <p class="gm-dashboard-eyebrow">Gomovie Studio <span aria-hidden="true">·</span> Overview</p>
            <h2>Content overview</h2>
            <p>Live catalog statistics and recent activity, updated from your records.</p>
        </div>
        <div class="gm-page-actions">
            <a class="gm-studio-button" href="{{ route('admin.media.index') }}">
                @include('partials.studio-icon',['name'=>'media']) Media Library
            </a>
            <a class="gm-studio-button is-primary" href="{{ route('admin.catalog.create','movies') }}">
                @include('partials.studio-icon',['name'=>'plus']) Add a movie
            </a>
        </div>
    </div>

    <section class="gm-dashboard-section" aria-labelledby="gm-dashboard-catalog-heading">
        <div class="gm-dashboard-section-heading">
            <div>
                <h3 id="gm-dashboard-catalog-heading">Catalog at a glance</h3>
                <p>Key totals across your movie and series library</p>
            </div>
            <span class="gm-dashboard-live-indicator"><span aria-hidden="true"></span> Current totals</span>
        </div>
        <div class="gm-dashboard-metric-grid gm-dashboard-metric-grid--four">
            @include('partials.studio-visual-metric', [
                'key'=>'movies', 'label'=>'Movies', 'value'=>$totalMovies,
                'description'=>'Titles in your movie catalog', 'icon'=>'film',
                'tone'=>'violet', 'href'=>route('admin.catalog.index','movies')
            ])
            @include('partials.studio-visual-metric', [
                'key'=>'series', 'label'=>'TV Series', 'value'=>$totalSeries,
                'description'=>'Series in your library', 'icon'=>'series',
                'tone'=>'blue', 'href'=>route('admin.catalog.index','series')
            ])
            @include('partials.studio-visual-metric', [
                'key'=>'episodes', 'label'=>'Episodes', 'value'=>$metrics['episodes'],
                'description'=>'Episodes across all seasons', 'icon'=>'history',
                'tone'=>'indigo', 'href'=>route('admin.catalog.index','series')
            ])
            @include('partials.studio-visual-metric', [
                'key'=>'members', 'label'=>'Members', 'value'=>$metrics['members'],
                'description'=>'Registered platform accounts', 'icon'=>'users',
                'tone'=>'rose', 'href'=>$canManageUsers ? route('admin.users.index') : null
            ])
        </div>
    </section>

    <section class="gm-dashboard-section gm-dashboard-publication" aria-labelledby="gm-dashboard-publishing-heading">
        <div class="gm-dashboard-section-heading">
            <div>
                <h3 id="gm-dashboard-publishing-heading">Publishing &amp; uploads</h3>
                <p>Published content and media requiring attention</p>
            </div>
        </div>
        <div class="gm-dashboard-metric-grid gm-dashboard-metric-grid--three">
            @include('partials.studio-visual-metric', [
                'key'=>'published-movies', 'label'=>'Published movies', 'value'=>$publishedMovies,
                'description'=>$moviePublicationRate.'% of all movies published', 'icon'=>'check',
                'tone'=>'teal', 'href'=>route('admin.catalog.index','movies'),
                'percentage'=>$moviePublicationRate
            ])
            @include('partials.studio-visual-metric', [
                'key'=>'published-series', 'label'=>'Published series', 'value'=>$publishedSeries,
                'description'=>$seriesPublicationRate.'% of all series published', 'icon'=>'series',
                'tone'=>'amber', 'href'=>route('admin.catalog.index','series'),
                'percentage'=>$seriesPublicationRate
            ])
            @include('partials.studio-visual-metric', [
                'key'=>'ingests-pending', 'label'=>'Quarantined uploads', 'value'=>$metrics['ingests_pending'],
                'description'=>'Video uploads awaiting media review', 'icon'=>'upload',
                'tone'=>'slate', 'href'=>route('admin.media.index')
            ])
        </div>
    </section>

    <section class="gm-studio-panel gm-dashboard-table-panel" aria-labelledby="gm-dashboard-recent-heading">
        <div class="gm-studio-panel-head gm-dashboard-table-header">
            <div class="gm-dashboard-table-heading">
                <span class="gm-dashboard-table-heading-icon" aria-hidden="true">
                    @include('partials.studio-icon',['name'=>'film'])
                </span>
                <div>
                    <h3 id="gm-dashboard-recent-heading">Recently updated movies</h3>
                    <small>Latest catalog records · 10 items per page</small>
                </div>
            </div>
            <div class="gm-dashboard-table-controls">
                <span class="gm-dashboard-record-count">{{ number_format($recentMovies->total()) }} records</span>
                <a class="gm-studio-button" href="{{ route('admin.catalog.index','movies') }}">
                    Manage all @include('partials.studio-icon',['name'=>'external'])
                </a>
            </div>
        </div>
        <div class="gm-studio-table-scroll">
            <table class="gm-studio-table gm-dashboard-table">
                <thead>
                    <tr>
                        <th scope="col">No.</th>
                        <th scope="col">Movie</th>
                        <th scope="col">Status</th>
                        <th scope="col">Access</th>
                        <th scope="col">Last updated</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMovies as $movie)
                        <tr>
                            <td class="gm-dashboard-row-number">{{ ($recentMovies->firstItem() ?? 1) + $loop->index }}</td>
                            <td>
                                <div class="gm-dashboard-movie">
                                    <span class="gm-dashboard-movie-icon" aria-hidden="true">
                                        @include('partials.studio-icon',['name'=>'film'])
                                    </span>
                                    <span class="gm-dashboard-movie-copy">
                                        <a href="{{ route('admin.catalog.edit',['movies',$movie->id]) }}" class="gm-dashboard-movie-title">{{ $movie->title }}</a>
                                        <small>{{ $movie->release_year ? 'Released '.$movie->release_year : 'Movie catalog' }}</small>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="gm-studio-pill {{ $movie->status === 'published' ? 'is-ok' : 'is-warn' }}">
                                    <span class="gm-dashboard-pill-dot" aria-hidden="true"></span>
                                    {{ ucfirst($movie->status) }}
                                </span>
                            </td>
                            <td>
                                <span class="gm-dashboard-access {{ $movie->is_premium ? 'is-premium' : 'is-free' }}">
                                    {{ $movie->is_premium ? 'Premium' : 'Free' }}
                                </span>
                            </td>
                            <td class="gm-dashboard-update-date">{{ $movie->updated_at?->format('d M Y') ?? '—' }}</td>
                            <td class="text-right">
                                <button type="button" class="gm-studio-dots"
                                        data-studio-menu="recent-{{ $movie->id }}"
                                        aria-label="Actions for {{ $movie->title }}"
                                        aria-expanded="false">
                                    @include('partials.studio-icon',['name'=>'dots'])
                                </button>
                                <template id="recent-{{ $movie->id }}">
                                    <a role="menuitem" href="{{ route('admin.catalog.edit',['movies',$movie->id]) }}">
                                        @include('partials.studio-icon',['name'=>'edit']) Edit movie
                                    </a>
                                    <a role="menuitem" href="{{ route('admin.catalog.preview',['movies',$movie->id]) }}">
                                        @include('partials.studio-icon',['name'=>'eye']) Preview
                                    </a>
                                </template>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="gm-studio-empty">No movies have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.studio-pager',['paginator'=>$recentMovies,'id'=>'dashboard-go'])
    </section>
</div>
@endsection
