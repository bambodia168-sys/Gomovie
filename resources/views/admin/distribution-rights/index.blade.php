@extends('layouts.admin')
@section('title', 'Distribution Rights')
@section('content')
<div class="gm-page-heading">
  <div>
    <h2>Distribution Rights</h2>
    <p>Open a movie or episode to record or manage its worldwide playback rights.</p>
  </div>
  <div class="gm-page-actions">
    <a class="gm-studio-button" href="{{ route('admin.catalog.index', 'movies') }}">Movies</a>
    <a class="gm-studio-button" href="{{ route('admin.catalog.index', 'series') }}">TV Series</a>
  </div>
</div>
<section class="gm-studio-panel" aria-label="Rights records">
  <div class="gm-studio-table-scroll">
    <table class="gm-studio-table">
      <thead><tr><th scope="col">Title</th><th scope="col">Reference</th><th scope="col">Status</th><th scope="col">Expires (UTC)</th><th scope="col">Action</th></tr></thead>
      <tbody>
        @forelse($records as $record)
          <tr>
            <td><strong>{{ $record->movie_title ?? $record->episode_title }}</strong><small class="gm-studio-muted block">{{ ucfirst($record->licensable_type) }}</small></td>
            <td class="break-all">{{ $record->rights_reference ?? '—' }}</td>
            <td>{{ ucfirst($record->status) }}</td>
            <td>{{ $record->ends_at ?? '—' }}</td>
            <td><a class="gm-studio-button" href="{{ route('admin.distribution-rights.edit', [$record->licensable_type, $record->licensable_id, 'record' => $record->id]) }}">View</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="gm-studio-empty">Open an existing title to add its first rights record.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @include('partials.studio-pager', ['paginator' => $records, 'id' => 'distribution-rights-page'])
</section>
@endsection
