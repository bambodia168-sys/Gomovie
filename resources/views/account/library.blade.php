@extends('layouts.app')
@section('title',$section==='watchlist'?'My List':'Watch history')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
  <div><p class="text-xs uppercase tracking-widest text-[#F5C518]">Personal library</p>
    <h1 class="mt-2 text-4xl font-black">{{ $section==='watchlist' ? 'My List' : 'Watch history' }}</h1>
    <p class="mt-2 text-sm text-zinc-400">Your private saved titles and viewing progress.</p>
  </div>
  <div class="flex gap-2 text-sm"><a class="rounded-xl border border-white/20 px-4 py-3 {{ $section==='watchlist' ? 'text-[#F5C518]' : '' }}" href="{{ route('account.library',['section'=>'watchlist','profile_id'=>$profile->id]) }}">My List</a>
  <a class="rounded-xl border border-white/20 px-4 py-3 {{ $section==='history' ? 'text-[#F5C518]' : '' }}" href="{{ route('account.library',['section'=>'history','profile_id'=>$profile->id]) }}">History</a></div>
</div>
<nav class="mt-8 flex flex-wrap gap-2" aria-label="Viewer profiles">
  @foreach($profiles as $p)
  <a href="{{ route('account.library',['section'=>$section,'profile_id'=>$p->id]) }}" class="rounded-full border px-4 py-2 text-sm {{ $profile->id===$p->id ? 'border-[#E50914] bg-[#E50914]/15 text-white' : 'border-white/15 text-zinc-400 hover:text-white' }}">{{ $p->name }}</a>
  @endforeach
</nav>
@if($entries->isEmpty())
<p class="mt-9 rounded-2xl border border-dashed border-white/15 p-10 text-center text-zinc-400">Nothing to display for this profile yet. <a class="text-[#F5C518]" href="{{ route('browse') }}">Explore the catalog</a>.</p>
@else
  <div class="mt-8 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
    @foreach($entries as $entry)
      @php
        $item = $entry['item'];
        $kind = $entry['kind'];
        $row = $entry['row'];
        $parent = $kind==='episode' ? $item->season?->series : null;
        $link = $parent
            ? route('watch.show',['series',$parent->slug])
            : route('watch.show',[$kind,$item->slug]);
      @endphp
      <div class="overflow-hidden rounded-2xl border border-white/10 bg-zinc-900">
        @if($kind==='movie' || $kind==='series')
          @include('frontend.partials.card',['item'=>$item,'kind'=>$kind])
        @else
          <a class="block p-5" href="{{ $link }}"><span class="text-xs uppercase text-[#F5C518]">Episode</span><p class="mt-3 font-semibold">{{ $item->title }}</p><p class="mt-2 text-xs text-zinc-400">{{ $parent?->title }}</p></a>
        @endif
        @if($section==='history')
          @php $progress = $row->duration_seconds > 0 ? min(100, (int) round(100*$row->progress_seconds/$row->duration_seconds)) : 0; @endphp
          <div class="px-4 pb-4 pt-2"><p class="text-xs text-zinc-400">{{ $progress }}% watched</p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-700"><div class="h-full rounded-full bg-[#E50914]" style="width:{{ $progress }}%"></div></div>
          </div>
        @else
          <form method="POST" action="{{ route('account.watchlist.toggle') }}" class="p-4">@csrf
            <input type="hidden" name="profile_id" value="{{ $profile->id }}"><input type="hidden" name="kind" value="{{ $kind }}">
            <input type="hidden" name="title_id" value="{{ $item->id }}"><input type="hidden" name="action" value="remove">
            <button class="w-full rounded-lg border border-white/15 px-3 py-2 text-xs text-zinc-300 hover:border-red-500 hover:text-red-300">Remove from My List</button>
          </form>
        @endif
      </div>
    @endforeach
  </div>
@endif
<div class="mt-8">{{ $records->links() }}</div>
@endsection
