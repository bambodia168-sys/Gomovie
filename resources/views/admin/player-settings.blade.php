@extends('layouts.admin')
@section('title','Player Settings')
@section('content')
<div class="gm-page-heading"><div><p class="text-xs uppercase tracking-widest text-amber-300">Player / configuration</p><h2>Player appearance</h2><p>Customize real player controls without changing private media access or streaming rights.</p></div>
<div class="gm-page-actions"><a class="gm-studio-button" href="{{ route('browse') }}">@include('partials.studio-icon',['name'=>'external']) Browse titles</a>
<button type="button" class="gm-studio-button is-primary" data-studio-open="gm-player-config-dialog">@include('partials.studio-icon',['name'=>'settings']) Configure player</button></div></div>
<div class="gm-studio-metrics">
  <div class="gm-studio-metric"><small>Accent</small><strong class="text-lg!">{{ ucfirst($preferences['accent']) }}</strong></div>
  <div class="gm-studio-metric"><small>Control density</small><strong class="text-lg! capitalize">{{ $preferences['density'] }}</strong></div>
  <div class="gm-studio-metric"><small>Skip distance</small><strong class="text-lg!">{{ $preferences['seek_seconds'] }} seconds</strong></div>
  <div class="gm-studio-metric"><small>Auto-hide</small><strong class="text-lg!">{{ $preferences['auto_hide_seconds'] ? $preferences['auto_hide_seconds'].' seconds' : 'Off' }}</strong></div>
</div>
<section class="gm-studio-panel mt-6"><div class="gm-studio-panel-head"><h3>Visible controls</h3><button type="button" class="gm-studio-button" data-studio-open="gm-player-config-dialog">Edit</button></div>
<div class="gm-studio-panel-body flex flex-wrap gap-3">
@foreach(['show_volume'=>'Volume','show_speed'=>'Playback speed','show_captions'=>'Captions','show_pip'=>'Picture-in-picture','show_fullscreen'=>'Fullscreen'] as $key=>$name)
<span class="gm-studio-pill {{ $preferences[$key]?'is-ok':'is-warn' }}">{{ $name }} · {{ $preferences[$key]?'On':'Off' }}</span>
@endforeach
</div></section>
<dialog class="gm-studio-modal" id="gm-player-config-dialog" aria-labelledby="gm-player-config-heading">
<form method="POST" action="{{ route('admin.player-settings.update') }}" id="gm-player-settings-form">@csrf @method('PATCH')
<div class="gm-studio-modal-heading"><h3 id="gm-player-config-heading">Configure player</h3><button type="button" class="gm-studio-button" data-studio-close aria-label="Close">×</button></div>
<div class="gm-studio-modal-body">
<fieldset class="gm-studio-field"><legend class="mb-2 font-bold text-sm">Accent color</legend><div class="grid grid-cols-2 gap-2">
@foreach($accents as $name=>$hex)
<label class="flex items-center gap-2 rounded-lg border border-white/15 p-3 text-sm"><input type="radio" name="accent" value="{{ $name }}" @checked(old('accent',$preferences['accent'])===$name)><span class="inline-block h-4 w-4 rounded-full" style="background:{{ $hex }}"></span>{{ ucfirst($name) }}</label>
@endforeach</div></fieldset>
<label class="gm-studio-field"><span>Control density</span><select class="gm-studio-input" name="density"><option value="comfortable" @selected($preferences['density']==='comfortable')>Comfortable</option><option value="compact" @selected($preferences['density']==='compact')>Compact</option></select></label>
<label class="gm-studio-field"><span>Seek forward / back</span><select class="gm-studio-input" name="seek_seconds">@foreach([5,10,15,30] as $seconds)<option value="{{ $seconds }}" @selected((int)$preferences['seek_seconds']===$seconds)>{{ $seconds }} seconds</option>@endforeach</select></label>
<label class="gm-studio-field"><span>Hide controls after</span><select class="gm-studio-input" name="auto_hide_seconds">@foreach([0,1,2] as $seconds)<option value="{{ $seconds }}" @selected((int)$preferences['auto_hide_seconds']===$seconds)>{{ $seconds?$seconds.' seconds':'Never' }}</option>@endforeach</select></label>
<fieldset class="gm-studio-field"><legend class="mb-2 font-bold text-sm">Visible controls</legend>
@foreach(['show_volume'=>'Volume / mute','show_speed'=>'Playback speed','show_captions'=>'Captions when available','show_pip'=>'Picture-in-picture','show_fullscreen'=>'Fullscreen'] as $key=>$label)
<label class="flex items-center gap-3 mt-3 text-sm"><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked((bool)$preferences[$key]) class="accent-rose-500">{{ $label }}</label>
@endforeach
</fieldset>
<p class="gm-studio-muted text-xs mt-4">Only supported controls appear when the browser and the actual video provide them. These settings do not grant public playback.</p>
</div><div class="gm-studio-modal-actions"><button type="button" class="gm-studio-button" id="gm-player-reset">Restore defaults</button><button type="button" class="gm-studio-button" data-studio-close>Cancel</button><button type="submit" class="gm-studio-button is-primary">Save player settings</button></div></form>
</dialog>
<script>
(() => {
 const form=document.getElementById('gm-player-settings-form');
 document.getElementById('gm-player-reset')?.addEventListener('click',()=>{
  for(const [name,value] of Object.entries({density:'comfortable',seek_seconds:'10',auto_hide_seconds:'1'})){
    const field=form.querySelector('[name="'+name+'"]');if(field)field.value=value;
  }
  const radio=form.querySelector('input[name="accent"][value="crimson"]');if(radio)radio.checked=true;
  for(const name of ['show_volume','show_speed','show_captions','show_pip','show_fullscreen']){
    const box=form.querySelector('input[type="checkbox"][name="'+name+'"]');if(box)box.checked=true;
  }
 });
})();
</script>
@endsection
