@extends('layouts.admin')
@section('title', 'Distribution Rights')
@section('content')
<div class="gm-page-heading">
  <div><h2>{{ $title->title }}</h2><p>Worldwide distribution rights for this {{ $kind }}.</p></div>
  <div class="gm-page-actions">
    <a class="gm-studio-button" href="{{ route('admin.distribution-rights.index') }}">All rights records</a>
    @if($kind === 'movie')
      <a class="gm-studio-button" href="{{ route('admin.catalog.edit', ['movies', $title]) }}">Edit movie</a>
    @else
      <a class="gm-studio-button" href="{{ route('admin.seasons.index', $title->season->series) }}">Seasons &amp; episodes</a>
    @endif
  </div>
</div>
<section class="gm-studio-panel" aria-label="Rights records">
  <div class="gm-studio-panel-head">
    <h3>Rights records</h3>
    @if($record && $canEdit)
      <a class="gm-studio-button" href="{{ route('admin.distribution-rights.edit', [$kind, $title]) }}#rights-form">Add record</a>
    @endif
  </div>
  <div class="gm-studio-table-scroll">
    <table class="gm-studio-table">
      <thead><tr><th scope="col">Reference</th><th scope="col">Status</th><th scope="col">Starts (UTC)</th><th scope="col">Expires (UTC)</th>@if($canEdit)<th scope="col">Action</th>@endif</tr></thead>
      <tbody>
        @forelse($records as $item)
          <tr>
            <td class="break-all">{{ $item->rights_reference ?? '—' }}</td>
            <td>{{ ucfirst($item->status) }}</td>
            <td>{{ $item->starts_at ?? '—' }}</td>
            <td>{{ $item->ends_at ?? '—' }}</td>
            @if($canEdit)
              <td><a class="gm-studio-button" href="{{ route('admin.distribution-rights.edit', [$kind, $title, 'record' => $item->id]) }}#rights-form">Edit</a></td>
            @endif
          </tr>
        @empty
          <tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="gm-studio-empty">No worldwide rights records for this title.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @include('partials.studio-pager', ['paginator' => $records, 'id' => 'title-rights-page'])
</section>
@if($canEdit)
  <section class="gm-studio-panel mt-4" id="rights-form" aria-labelledby="distribution-rights-form-heading">
    <div class="gm-studio-panel-head"><h3 id="distribution-rights-form-heading">{{ $record ? 'Edit rights record' : 'Add rights record' }}</h3></div>
    <form method="post" action="{{ $record ? route('admin.distribution-rights.update', [$kind, $title, $record->id]) : route('admin.distribution-rights.store', [$kind, $title]) }}">
      @csrf
      @if($record) @method('PATCH') @endif
      <div class="gm-studio-panel-body">
        @if($errors->any())
          <ul class="mb-4 text-sm" role="alert">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
          </ul>
        @endif
        <label class="gm-studio-field">
          <span>Agreement or rights reference</span>
          <input class="gm-studio-input" name="rights_reference" value="{{ old('rights_reference', $record?->rights_reference) }}" maxlength="255">
        </label>
        <div class="grid gap-x-4 sm:grid-cols-2">
          @foreach(['starts_at' => 'Starts (UTC)', 'ends_at' => 'Expires (UTC)'] as $key => $label)
            <label class="gm-studio-field min-w-0">
              <span>{{ $label }}</span>
              <input class="gm-studio-input" type="datetime-local" name="{{ $key }}"
                     value="{{ old($key, $record?->$key ? str_replace(' ', 'T', substr($record->$key, 0, 16)) : null) }}">
            </label>
          @endforeach
        </div>
        <div class="gm-studio-field">
          <label for="distribution-rights-status"><span>Status</span></label>
          <select class="gm-studio-input" id="distribution-rights-status" name="status">
            @foreach($statuses as $key => $label)
              <option value="{{ $key }}" @selected(old('status', $record?->status ?? 'pending') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <label class="flex items-start gap-2 text-sm">
          <input class="gm-studio-input mt-0.5 shrink-0" type="checkbox" name="rights_confirmed" value="1" @checked(old('rights_confirmed'))>
          <span>I confirm that this agreement grants worldwide streaming rights. Required for Active status.</span>
        </label>
      </div>
      <div class="gm-site-setting-actions"><button class="gm-studio-button is-primary" type="submit">Save rights record</button></div>
    </form>
  </section>
@else
  <p class="gm-studio-muted mt-4 text-sm">A super administrator can add, edit, or revoke rights records.</p>
@endif
@endsection
