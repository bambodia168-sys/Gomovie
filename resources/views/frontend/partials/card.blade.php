@php
 $path = (string) ($item->poster_path ?? '');
 $image = '';
 if (preg_match('#^/[A-Za-z0-9_.\/-]+\.(?:jpg|jpeg|png|webp)$#i', $path)) $image = 'https://image.tmdb.org/t/p/w500'.$path;
 elseif (preg_match('#^artwork/[A-Za-z0-9_.\/-]+$#', $path)) $image = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
@endphp
<a href="{{ route('watch.show',[$kind,$item->slug]) }}" class="group block overflow-hidden rounded-2xl border border-white/10 bg-zinc-900 transition hover:-translate-y-1 hover:border-[#E50914]/60 hover:shadow-xl hover:shadow-black/50">
  <div class="relative aspect-[2/3] overflow-hidden bg-gradient-to-br from-zinc-800 via-zinc-900 to-black">
   @if($image)
     <img src="{{ $image }}" alt="Poster for {{ $item->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.05]">
   @else
     <div class="flex h-full flex-col items-center justify-center gap-4 px-4 text-center"><span aria-hidden="true" class="text-6xl text-[#E50914]/75">▶</span><span class="text-xl font-bold text-zinc-300">{{ $item->title }}</span></div>
   @endif
   <span class="absolute right-3 top-3 rounded-lg bg-black/75 px-2.5 py-1 text-[11px] font-semibold text-white">{{ $item->is_premium ? 'PREMIUM' : 'FREE' }}</span>
  </div>
  <div class="p-4"><p class="truncate font-semibold">{{ $item->title }}</p><p class="mt-2 flex items-center justify-between text-xs text-zinc-400"><span>{{ $kind==='movie' ? ($item->release_year ?: 'Movie') : 'Series' }}</span><span class="text-[#F5C518]">View details →</span></p></div>
</a>
