@extends('layouts.admin')
@section('title', 'Site Settings')
@section('content')
<div class="gm-site-settings">
  <header class="gm-page-heading">
    <div>
      <h2>Site Settings</h2>
      <p>Manage video processing, publishing, and service connections.</p>
    </div>
    <div class="gm-page-actions">
      <a class="gm-studio-button" href="{{ route('admin.player-settings.edit') }}">Player Settings</a>
      <a class="gm-studio-button" href="{{ route('admin.appearance.index') }}">Appearance</a>
    </div>
  </header>

  <nav class="gm-studio-subnav gm-site-settings-nav" aria-label="Settings sections">
    <a href="#uploads">Upload settings</a>
    <a href="#media">Video processing</a>
    <a href="#storage">Cloud storage</a>
    <a href="#tmdb">TMDB</a>
    <a href="#edge">Private playback</a>
    <a href="#monitoring">Queue alerts</a>
  </nav>

  @if($errors->any())
    <ul class="gm-studio-panel gm-studio-panel-body mb-4" role="alert">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  @endif

  <section class="gm-studio-panel gm-site-settings-media" id="uploads" aria-labelledby="gm-site-uploads">
    <div class="gm-studio-panel-head"><h3 id="gm-site-uploads">Upload settings</h3></div>
    <form method="post" action="{{ route('admin.site-settings.update', 'uploads') }}">
      @csrf @method('PATCH')
      <div class="gm-studio-panel-body gm-site-setting-fields">
        <label class="gm-studio-field">
          <span>Maximum video size (MB)</span>
          <input class="gm-studio-input" type="number" name="video_upload_max_mb" required min="1"
                 max="{{ \App\Support\VideoUploadLimit::MAX_MB }}" step="1"
                 value="{{ old('video_upload_max_mb', $uploadLimit) }}">
        </label>
        <p class="gm-studio-muted text-sm">This limit applies to each movie, TV episode, and normal video. Upload Studio can currently accept up to {{ number_format($effectiveUploadMb, 2) }} MB under the PHP limits. Your web server may also limit the request size.</p>
      </div>
      <div class="gm-site-setting-actions">
        <button class="gm-studio-button is-primary" type="submit">Save upload settings</button>
      </div>
    </form>
  </section>

  <section class="gm-studio-panel gm-site-settings-media" id="media" aria-labelledby="gm-site-media">
    <div class="gm-studio-panel-head">
      <h3 id="gm-site-media">Video processing</h3>
      <form method="post" action="{{ route('admin.site-settings.test-ffmpeg') }}">
        @csrf
        <button type="submit" class="gm-studio-button">Test FFmpeg</button>
      </form>
    </div>
    @if(session('ffmpeg_test'))
      <div class="gm-site-setting-result" role="status">
        <strong>FFmpeg test</strong>
        @foreach(session('ffmpeg_test') as $result)
          <span class="gm-studio-pill {{ $result['version'] ? 'is-ok' : 'is-warn' }}">
            {{ $result['label'] }} · {{ $result['version'] ? 'Passed ('.$result['version'].')' : 'Failed to start' }}
          </span>
        @endforeach
        <small>Checks both programs from the dashboard web process.</small>
      </div>
    @endif
    <form method="post" action="{{ route('admin.site-settings.update', 'media') }}">
      @csrf @method('PATCH')
      <div class="gm-site-setting-list">
        @include('admin.site-settings.switch', [
          'key' => 'transcoding_enabled', 'label' => 'FFmpeg transcoding',
          'description' => 'Convert eligible video uploads to HLS using the media worker.',
        ])
        @include('admin.site-settings.switch', [
          'key' => 'high_res_transcoding_enabled', 'label' => '1440p and 4K renditions',
          'description' => 'Create high-resolution streams when the source supports them.',
        ])
        @include('admin.site-settings.switch', [
          'key' => 'public_hls_enabled', 'label' => 'HLS playback for free titles',
          'description' => 'Serve processed streams for published free titles with active distribution rights.',
        ])
        @include('admin.site-settings.switch', [
          'key' => 'automatic_publication_enabled', 'label' => 'Publish eligible uploads automatically',
          'description' => 'Requires transcoding, HLS playback, active rights, and a successful malware scan.',
        ])
        @include('admin.site-settings.switch', [
          'key' => 'media_staging_enabled', 'label' => 'Private media staging',
          'description' => 'Allow reviewed packages to be copied to the private S3 staging bucket configured on the host.',
        ])
      </div>
      <div class="gm-site-setting-actions">
        <button class="gm-studio-button is-primary" type="submit">Save video processing</button>
      </div>
    </form>
  </section>

  <div class="gm-site-settings-grid">
    <section class="gm-studio-panel" id="storage" aria-labelledby="gm-site-storage">
      <div class="gm-studio-panel-head"><h3 id="gm-site-storage">Cloud storage</h3></div>
      <form method="post" action="{{ route('admin.site-settings.update', 'storage') }}">
        @csrf @method('PATCH')
        @include('admin.site-settings.switch', [
          'key' => 'r2_enabled', 'label' => 'Cloudflare R2 backup',
          'description' => 'Copy new source uploads to a private R2 bucket.',
        ])
        <div class="gm-studio-panel-body gm-site-setting-fields">
          <label class="gm-studio-field">
            <span>R2 S3 endpoint</span>
            <input class="gm-studio-input" type="url" name="r2_endpoint" value="{{ old('r2_endpoint', $values['r2_endpoint']) }}"
                   placeholder="https://…r2.cloudflarestorage.com" maxlength="1024">
          </label>
          <label class="gm-studio-field">
            <span>R2 bucket</span>
            <input class="gm-studio-input" name="r2_bucket" value="{{ old('r2_bucket', $values['r2_bucket']) }}" maxlength="63">
          </label>
          @include('admin.site-settings.secret', ['key' => 'r2_key', 'label' => 'R2 access key ID'])
          @include('admin.site-settings.secret', ['key' => 'r2_secret', 'label' => 'R2 secret access key'])
        </div>
        <div class="gm-site-setting-actions">
          <button class="gm-studio-button is-primary" type="submit">Save cloud storage</button>
        </div>
      </form>
    </section>

    <section class="gm-studio-panel" id="edge" aria-labelledby="gm-site-edge">
      <div class="gm-studio-panel-head"><h3 id="gm-site-edge">Private playback</h3></div>
      <form method="post" action="{{ route('admin.site-settings.update', 'edge') }}">
        @csrf @method('PATCH')
        @include('admin.site-settings.switch', [
          'key' => 'edge_enabled', 'label' => 'Private playback edge',
          'description' => 'Deliver authorized streams through your deployed playback edge.',
        ])
        <div class="gm-studio-panel-body gm-site-setting-fields">
          <label class="gm-studio-field">
            <span>Playback edge URL</span>
            <input class="gm-studio-input" type="url" name="media_edge_url" value="{{ old('media_edge_url', $values['media_edge_url']) }}"
                   placeholder="https://" maxlength="1024">
          </label>
          @include('admin.site-settings.secret', ['key' => 'media_signing_key', 'label' => 'Playback signing key'])
          @include('admin.site-settings.secret', ['key' => 'edge_shared_key', 'label' => 'Shared edge authorization key'])
        </div>
        <div class="gm-site-setting-actions">
          <button class="gm-studio-button is-primary" type="submit">Save private playback</button>
        </div>
      </form>
    </section>

    <section class="gm-studio-panel" id="tmdb" aria-labelledby="gm-site-tmdb">
      <div class="gm-studio-panel-head"><h3 id="gm-site-tmdb">TMDB</h3></div>
      <form method="post" action="{{ route('admin.site-settings.update', 'tmdb') }}">
        @csrf @method('PATCH')
        @include('admin.site-settings.switch', [
          'key' => 'tmdb_enabled', 'label' => 'TMDB metadata import',
          'description' => 'Import movie and series information into Media Library.',
        ])
        <div class="gm-studio-panel-body gm-site-setting-fields">
          @include('admin.site-settings.secret', ['key' => 'tmdb_token', 'label' => 'TMDB read access token'])
        </div>
        <div class="gm-site-setting-actions">
          <a class="gm-studio-button" href="{{ route('admin.media.index') }}">Media Library</a>
          <button class="gm-studio-button is-primary" type="submit">Save TMDB settings</button>
        </div>
      </form>
    </section>

    <section class="gm-studio-panel" id="monitoring" aria-labelledby="gm-site-monitoring">
      <div class="gm-studio-panel-head"><h3 id="gm-site-monitoring">Queue alerts</h3></div>
      <form method="post" action="{{ route('admin.site-settings.update', 'monitoring') }}">
        @csrf @method('PATCH')
        @include('admin.site-settings.switch', [
          'key' => 'queue_probes_enabled', 'label' => 'Warn when queue checks are overdue',
          'description' => 'Schedule worker checks and show a System Health alert if a worker misses them.',
        ])
        <div class="gm-site-setting-actions">
          <a class="gm-studio-button" href="{{ route('admin.operations.index') }}">System Health</a>
          <button class="gm-studio-button is-primary" type="submit">Save queue alerts</button>
        </div>
      </form>
    </section>
  </div>
</div>
@endsection
