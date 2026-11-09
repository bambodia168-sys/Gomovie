@extends('layouts.admin')
@section('title','Player Settings')
@section('content')
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
  <div>
    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#F5C518]">Appearance / Playback</p>
    <h2 class="mt-2 text-3xl font-black tracking-tight">Player appearance</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-400">
      Set how the movie player looks and which controls are visible. Playback permissions, video files, and streaming security are not changed here.
    </p>
  </div>
  <a href="{{ route('browse') }}"
     class="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-semibold text-zinc-300 hover:border-rose-400 hover:text-white">Browse titles ↗</a>
</div>
<form method="POST" action="{{ route('admin.player-settings.update') }}" id="gm-player-settings-form" class="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(280px,.9fr)]">
  @csrf @method('PATCH')
  <div class="space-y-5">
    <section class="rounded-2xl border border-white/10 bg-zinc-900/75 p-6">
      <h3 class="text-lg font-bold">Look &amp; feel</h3>
      <p class="mt-1 text-xs leading-5 text-zinc-500">Use the existing Gomovie design with your preferred accent color and button spacing.</p>
      <fieldset class="mt-5">
        <legend class="text-sm font-semibold">Accent color</legend>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
          @foreach($accents as $name=>$hex)
            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-white/15 bg-zinc-950/80 px-3 py-3 text-xs font-semibold has-[:checked]:border-rose-400 has-[:checked]:bg-rose-500/10">
              <input type="radio" name="accent" value="{{ $name }}" class="accent-rose-400" @checked(old('accent',$preferences['accent'])===$name)>
              <span class="h-5 w-5 rounded-full border border-white/20" style="background-color:{{ $hex }}" aria-hidden="true"></span>
              <span>{{ ucfirst($name) }}</span>
            </label>
          @endforeach
        </div>
      </fieldset>
      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-semibold">Control size
          <select name="density" class="mt-2 w-full rounded-xl border border-white/15 bg-zinc-950 px-3 py-3 text-sm text-white">
            <option value="comfortable" @selected(old('density',$preferences['density'])==='comfortable')>Comfortable</option>
            <option value="compact" @selected(old('density',$preferences['density'])==='compact')>Compact</option>
          </select>
        </label>
        <label class="block text-sm font-semibold">Skip forward / back
          <select name="seek_seconds" class="mt-2 w-full rounded-xl border border-white/15 bg-zinc-950 px-3 py-3 text-sm text-white">
            @foreach([5,10,15,30] as $seconds)
              <option value="{{ $seconds }}" @selected((int) old('seek_seconds',$preferences['seek_seconds'])===$seconds)>{{ $seconds }} seconds</option>
            @endforeach
          </select>
        </label>
        <label class="block text-sm font-semibold">Hide controls after
          <select name="auto_hide_seconds" class="mt-2 w-full rounded-xl border border-white/15 bg-zinc-950 px-3 py-3 text-sm text-white">
            @foreach([0,1,2] as $seconds)
              <option value="{{ $seconds }}" @selected((int) old('auto_hide_seconds',$preferences['auto_hide_seconds'])===$seconds)>{{ $seconds ? $seconds.' seconds' : 'Never' }}</option>
            @endforeach
          </select>
        </label>
      </div>
    </section>
    <section class="rounded-2xl border border-white/10 bg-zinc-900/75 p-6">
      <h3 class="text-lg font-bold">Visible controls</h3>
      <p class="mt-1 text-xs leading-5 text-zinc-500">Main play/pause, time, timeline and seeking remain available. Optional controls only appear when supported by the browser and media.</p>
      <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach([
          'show_volume'=>'Volume / mute',
          'show_speed'=>'Playback speed',
          'show_captions'=>'Captions (if available)',
          'show_pip'=>'Picture-in-picture',
          'show_fullscreen'=>'Fullscreen',
        ] as $key=>$label)
          <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-white/10 bg-zinc-950/65 px-4 py-3">
            <span class="text-sm font-medium text-zinc-200">{{ $label }}</span>
            <span class="flex items-center">
              <input type="hidden" name="{{ $key }}" value="0">
              <input type="checkbox" name="{{ $key }}" value="1" @checked((bool) old($key,$preferences[$key])) class="h-4 w-4 accent-rose-500">
            </span>
          </label>
        @endforeach
      </div>
    </section>
    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" class="rounded-xl bg-[#e91b4b] px-6 py-3 text-sm font-bold text-white hover:bg-[#f32b5c]">Save player settings</button>
      <button type="button" id="gm-player-reset" class="rounded-xl border border-white/20 px-5 py-3 text-sm font-semibold text-zinc-300 hover:bg-white/5">Restore defaults</button>
    </div>
  </div>
  <aside class="h-fit rounded-2xl border border-white/10 bg-zinc-900/75 p-6">
    <h3 class="font-bold">What these settings affect</h3>
    <p class="mt-3 text-sm leading-6 text-zinc-400">The same modern control bar is used for private administrator previews and secure streams when available. These are interface preferences only.</p>
    <div class="mt-5 rounded-xl border border-white/10 bg-[#090c17] p-4">
      <div class="aspect-video rounded-lg border border-white/10 bg-gradient-to-br from-[#202237] to-[#070912] p-3">
        <div class="flex h-full flex-col justify-end">
          <div class="mb-3 h-1.5 w-full rounded-full bg-white/15"><span id="gm-player-settings-accent" class="block h-full w-[42%] rounded-full" style="background:{{ $accents[$preferences['accent']] ?? '#f33060' }}"></span></div>
          <div class="flex items-center justify-between gap-2 text-[11px] text-white">
            <span class="flex items-center gap-3"><strong>▶</strong><span>↶</span><span>↷</span><span>02:34 / 08:00</span></span>
            <span>◖) &nbsp; ⛶</span>
          </div>
        </div>
      </div>
      <p class="mt-3 text-[11px] leading-5 text-zinc-500">Appearance preview only. Open a watch page to test real video controls.</p>
    </div>
    <p class="mt-4 text-xs leading-5 text-zinc-500">Only administrators can change platform-wide player settings. Changes do not grant video rights, paid access or public playback.</p>
  </aside>
</form>
<script>
(() => {
  const form=document.getElementById('gm-player-settings-form');
  const colors=@json($accents);
  const preview=document.getElementById('gm-player-settings-accent');
  form?.querySelectorAll('input[name="accent"]').forEach(input=>input.addEventListener('change',()=>{
    if(input.checked && colors[input.value])preview.style.backgroundColor=colors[input.value];
  }));
  document.getElementById('gm-player-reset')?.addEventListener('click',()=>{
    const values={accent:'crimson',density:'comfortable',seek_seconds:'10',auto_hide_seconds:'1'};
    Object.entries(values).forEach(([name,value])=>{
      const input=form.querySelector('[name="'+name+'"][value="'+value+'"]') ||
        form.querySelector('select[name="'+name+'"]');
      if(input){if(input.type==='radio')input.checked=true;else input.value=value;}
    });
    for(const name of ['show_volume','show_speed','show_captions','show_pip','show_fullscreen']){
      const box=form.querySelector('input[type="checkbox"][name="'+name+'"]');
      if(box)box.checked=true;
    }
    preview.style.backgroundColor=colors.crimson;
  });
})();
</script>
@endsection
