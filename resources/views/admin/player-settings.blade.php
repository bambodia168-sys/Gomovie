@extends('layouts.admin')
@section('title', 'Player Settings')
@section('content')
<div class="gm-page-heading">
  <div><h2>Player appearance</h2><p>Choose the default player and customize playback controls for watch pages.</p></div>
  <div class="gm-page-actions">
    <a class="gm-studio-button" href="{{ route('browse') }}">@include('partials.studio-icon', ['name' => 'external']) Browse titles</a>
    <button type="button" class="gm-studio-button is-primary" data-studio-open="gm-player-config-dialog">
      @include('partials.studio-icon', ['name' => 'settings']) Configure player
    </button>
  </div>
</div>

<div class="gm-studio-metrics">
  <div class="gm-studio-metric"><small>Default player</small><strong class="text-lg!">{{ $engines[$preferences['engine']] }}</strong></div>
  <div class="gm-studio-metric"><small>Accent color</small><strong class="text-lg!">{{ ucfirst($preferences['accent']) }}</strong></div>
  <div class="gm-studio-metric"><small>Control density</small><strong class="text-lg! capitalize">{{ $preferences['density'] }}</strong></div>
  <div class="gm-studio-metric"><small>Seek distance</small><strong class="text-lg!">{{ $preferences['seek_seconds'] }} seconds</strong></div>
</div>

<section class="gm-studio-panel mt-6">
  <div class="gm-studio-panel-head">
    <h3>Playback controls</h3>
    <button type="button" class="gm-studio-button" data-studio-open="gm-player-config-dialog">Edit</button>
  </div>
  <div class="gm-studio-panel-body flex flex-wrap gap-3">
    @foreach($controls as $key => $name)
      <span class="gm-studio-pill {{ $preferences[$key] ? 'is-ok' : 'is-warn' }}">{{ $name }} · {{ $preferences[$key] ? 'On' : 'Off' }}</span>
    @endforeach
    <span class="gm-studio-pill">Auto-hide · {{ $preferences['auto_hide_seconds'] ? $preferences['auto_hide_seconds'].' '.\Illuminate\Support\Pluralizer::plural('second', $preferences['auto_hide_seconds']) : 'Off' }}</span>
    <span class="gm-studio-pill">Video ads · {{ $preferences['ad_tag_url'] ? 'Configured' : 'Off' }}</span>
  </div>
</section>

<p class="mt-4 text-sm">
  <a class="underline underline-offset-4" href="{{ route('admin.site-settings.edit') }}#media">Manage FFmpeg and video processing</a>
</p>

<dialog class="gm-studio-modal gm-player-settings-dialog" id="gm-player-config-dialog" aria-labelledby="gm-player-config-heading">
  <form method="POST" action="{{ route('admin.player-settings.update') }}" id="gm-player-settings-form">
    @csrf @method('PATCH')
    <div class="gm-studio-modal-heading">
      <h3 id="gm-player-config-heading">Configure player</h3>
      <button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button>
    </div>

    <div class="gm-studio-modal-body">
      @if($errors->any())
        <ul class="mb-4 rounded-lg border border-rose-400/40 p-3 text-sm text-rose-200" role="alert">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      @endif

      <label class="gm-studio-field">
        <span>Default player</span>
        <select class="gm-studio-input" name="engine">
          @foreach($engines as $key => $name)
            <option value="{{ $key }}" @selected(old('engine', $preferences['engine']) === $key)>{{ $name }}</option>
          @endforeach
        </select>
      </label>

      <fieldset class="gm-studio-field">
        <legend class="mb-2 text-sm font-bold">Accent color</legend>
        <div class="grid grid-cols-2 gap-2">
          @foreach($accents as $name => $hex)
            <label class="flex items-center gap-2 rounded-lg border border-white/15 p-3 text-sm">
              <input type="radio" name="accent" value="{{ $name }}" @checked(old('accent', $preferences['accent']) === $name)>
              <i class="inline-block h-4 w-4 rounded-full" style="background:{{ $hex }}" aria-hidden="true"></i>
              {{ ucfirst($name) }}
            </label>
          @endforeach
        </div>
      </fieldset>

      <div class="gm-player-settings-fields">
        <label class="gm-studio-field">
          <span>Control density</span>
          <select class="gm-studio-input" name="density">
            @foreach(['comfortable' => 'Comfortable', 'compact' => 'Compact'] as $value => $name)
              <option value="{{ $value }}" @selected(old('density', $preferences['density']) === $value)>{{ $name }}</option>
            @endforeach
          </select>
        </label>
        <label class="gm-studio-field">
          <span>Seek forward / back</span>
          <select class="gm-studio-input" name="seek_seconds">
            @foreach($seekIntervals as $seconds)
              <option value="{{ $seconds }}" @selected((int) old('seek_seconds', $preferences['seek_seconds']) === $seconds)>{{ $seconds }} seconds</option>
            @endforeach
          </select>
        </label>
        <label class="gm-studio-field">
          <span>Hide controls after</span>
          <select class="gm-studio-input" name="auto_hide_seconds">
            @foreach($autoHideDelays as $seconds)
              <option value="{{ $seconds }}" @selected((int) old('auto_hide_seconds', $preferences['auto_hide_seconds']) === $seconds)>{{ $seconds ? $seconds.' '.\Illuminate\Support\Pluralizer::plural('second', $seconds) : 'Never' }}</option>
            @endforeach
          </select>
        </label>
      </div>

      <fieldset class="gm-studio-field">
        <legend class="mb-2 text-sm font-bold">Visible controls</legend>
        <div class="grid gap-2 sm:grid-cols-2">
          @foreach($controls as $key => $name)
            <label class="flex items-center gap-3 rounded-lg border border-white/15 p-3 text-sm">
              <input type="hidden" name="{{ $key }}" value="0">
              <input type="checkbox" name="{{ $key }}" value="1" @checked((bool) old($key, $preferences[$key])) class="accent-rose-500">
              {{ $name }}
            </label>
          @endforeach
        </div>
        <p class="gm-studio-muted mt-2 text-xs">Control availability depends on the browser and media.</p>
      </fieldset>

      <label class="gm-studio-field">
        <span>Video ad tag URL</span>
        <input class="gm-studio-input" type="url" name="ad_tag_url" value="{{ old('ad_tag_url', $preferences['ad_tag_url']) }}"
               placeholder="https://" maxlength="2048" aria-describedby="gm-ad-tag-help">
        <small class="gm-studio-muted" id="gm-ad-tag-help">Optional HTTPS VAST or VMAP tag for free titles. Use VMAP to schedule mid-rolls and post-rolls.</small>
      </label>
    </div>

    <div class="gm-studio-modal-actions">
      <button type="button" class="gm-studio-button" id="gm-player-reset">Restore defaults</button>
      <button type="button" class="gm-studio-button" data-studio-close>Cancel</button>
      <button type="submit" class="gm-studio-button is-primary">Save player settings</button>
    </div>
  </form>
</dialog>

<script>
(() => {
    const form = document.getElementById('gm-player-settings-form');
    const defaults = {{ \Illuminate\Support\Js::from($defaults) }};

    document.getElementById('gm-player-reset').addEventListener('click', () => {
        for (const [name, value] of Object.entries(defaults)) {
            const fields = form.querySelectorAll('[name="' + name + '"]:not([type="hidden"])');
            for (const field of fields) {
                if (field.type === 'checkbox') field.checked = value;
                else if (field.type === 'radio') field.checked = field.value === value;
                else field.value = value ?? '';
            }
        }
    });

    @if($errors->any())
        form.closest('dialog').showModal();
    @endif
})();
</script>
@endsection
