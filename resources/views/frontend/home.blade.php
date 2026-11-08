@extends('layouts.app')
@section('title','Home')
@section('content')
<div class="gm-home-upgrade">
@if($heroTitles->isNotEmpty())
<section class="gm-hero" aria-label="Featured titles" x-data="{
  active:0,
  count:{{ $heroTitles->count() }},
  position(index) {
    let delta=(index-this.active+this.count)%this.count;
    if(delta>Math.floor(this.count/2)) delta-=this.count;
    if(delta===0) return 'is-center';
    if(delta===-1) return 'is-left';
    if(delta===1) return 'is-right';
    if(delta===-2) return 'is-far-left';
    if(delta===2) return 'is-far-right';
    return 'is-hidden';
  },
  previous(){this.active=(this.active+this.count-1)%this.count},
  next(){this.active=(this.active+1)%this.count}
}">
  <div class="gm-hero-backlight" aria-hidden="true"></div>
  {{-- The background tracks the same active index as the existing five-card carousel. --}}
  <div class="gm-hero-scene" aria-hidden="true">
    @foreach($heroTitles as $index => $entry)
      @php
        $scenePath = $entry['item']->backdrop_path ?: $entry['item']->poster_path;
        $sceneUrl = \App\Support\CatalogArtwork::url($scenePath, 'w780');
      @endphp
      @if($sceneUrl)
        <img src="{{ $sceneUrl }}" alt="" class="gm-hero-scene-image"
             x-show="active === {{ $index }}" @if($index > 0) x-cloak @endif
             x-transition.opacity.duration.650ms
             loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
             decoding="async" onerror="this.style.opacity='0'">
      @endif
    @endforeach
    <span class="gm-hero-scene-tint"></span>
  </div>
  <p class="gm-hero-kicker">CINEMATIC STORIES · CURATED DISCOVERY</p>
  <div class="gm-hero-stage">
    @foreach($heroTitles as $index=>$entry)
      @php
        $feature=$entry['item'];
        $image=\App\Support\CatalogArtwork::url($feature->poster_path ?? $feature->backdrop_path, 'w500');
      @endphp
      <button type="button" class="gm-hero-tile" :class="position({{ $index }})"
          @click="active={{ $index }}" :aria-current="active==={{ $index }} ? 'true' : 'false'"
          aria-label="Feature {{ $feature->title }}">
        @if($image)<img src="{{ $image }}" alt="" loading="{{ $index===0 ? 'eager' : 'lazy' }}" decoding="async">
        @else<div class="gm-hero-placeholder"><span aria-hidden="true">▶</span></div>@endif
        <span class="gm-hero-tile-overlay" aria-hidden="true"></span>
        <span class="gm-hero-badge">{{ $feature->is_premium ? '♛ Premium' : 'Free' }}</span>
        <span class="gm-hero-tile-caption"><strong>{{ $feature->title }}</strong><small>{{ $entry['kind']==='movie' ? 'Film' : 'TV Series' }}</small></span>
        <span class="gm-hero-play" aria-hidden="true">▶</span>
      </button>
    @endforeach
    @if($heroTitles->count()>1)
      <button class="gm-hero-arrow gm-hero-prev" type="button" @click="previous()" aria-label="Previous featured title">‹</button>
      <button class="gm-hero-arrow gm-hero-next" type="button" @click="next()" aria-label="Next featured title">›</button>
    @endif
  </div>
  <div class="gm-hero-pagination" aria-label="Select featured title">
    @foreach($heroTitles as $index=>$entry)
      <button type="button" @click="active={{ $index }}" :class="active==={{ $index }} ? 'is-current' : ''"
        :aria-current="active==={{ $index }} ? 'true' : 'false'"
        aria-label="Show featured title {{ $index+1 }}"></button>
    @endforeach
  </div>
  @foreach($heroTitles as $index=>$entry)
    @php $feature=$entry['item']; @endphp
    <div class="gm-hero-description" x-show="active==={{ $index }}" x-cloak x-transition.opacity>
      <div>
        <span class="gm-eyebrow">{{ $entry['kind']==='movie' ? 'Featured movie' : 'Featured series' }} <span aria-hidden="true">·</span> {{ $feature->is_premium ? 'Premium title' : 'Free title' }}</span>
        <h1>{{ $feature->title }}</h1>
        <p>{{ \Illuminate\Support\Str::limit($feature->overview ?: 'Explore the story, seasons, and publication details.',160) }}</p>
      </div>
      <div class="gm-hero-actions">
        <a class="gm-button gm-button-primary" href="{{ route('watch.show',[$entry['kind'],$feature->slug]) }}">▶ View title</a>
        <a class="gm-button gm-button-secondary" href="{{ route('browse') }}">Explore catalog</a>
      </div>
    </div>
  @endforeach
</section>
@else
<section class="gm-preview-hero" aria-labelledby="gm-preview-title">
  <div class="gm-preview-aura" aria-hidden="true"></div>
  <p class="gm-preview-overline">THE NEXT CHAPTER IN ENTERTAINMENT</p>
  <div class="gm-preview-stage" aria-hidden="true">
    <div class="gm-preview-cover gm-preview-cover--far-left"><span class="gm-preview-topline">GOMOVIE · COLLECTION</span><span class="gm-preview-symbol">✦</span><strong>EXPLORE</strong><small>NEW PERSPECTIVES</small></div>
    <div class="gm-preview-cover gm-preview-cover--left"><span class="gm-preview-topline">GOMOVIE · COLLECTION</span><span class="gm-preview-symbol">◈</span><strong>IMAGINE</strong><small>BEYOND THE FRAME</small></div>
    <div class="gm-preview-cover gm-preview-cover--center"><span class="gm-preview-topline">THE GOMOVIE EXPERIENCE</span><span class="gm-preview-focus-symbol">▶</span><strong>EVERY STORY<br>BEGINS HERE</strong><small>DISCOVER · SAVE · EXPLORE</small></div>
    <div class="gm-preview-cover gm-preview-cover--right"><span class="gm-preview-topline">GOMOVIE · COLLECTION</span><span class="gm-preview-symbol">✧</span><strong>DISCOVER</strong><small>STORIES WORTH SHARING</small></div>
    <div class="gm-preview-cover gm-preview-cover--far-right"><span class="gm-preview-topline">GOMOVIE · COLLECTION</span><span class="gm-preview-symbol">◇</span><strong>ENJOY</strong><small>THE ART OF CINEMA</small></div>
  </div>
  <div class="gm-preview-dots" aria-hidden="true"><i></i><i></i><i class="is-selected"></i><i></i><i></i></div>
  <div class="gm-preview-intro">
    <div class="gm-preview-heading">
      <p class="gm-eyebrow">WELCOME TO GOMOVIE</p>
      <h1 id="gm-preview-title">A world of stories. <em>Made for you.</em></h1>
      <p>A fresh cinematic experience is taking shape. Discover films and series as approved titles become available in our catalog.</p>
    </div>
    <div class="gm-preview-cta">
      <a href="{{ route('browse') }}" class="gm-button gm-button-primary"><span aria-hidden="true">⌕</span> Browse catalog</a>
      @guest<a href="{{ route('register') }}" class="gm-button gm-button-secondary">Create your account</a>@endguest
    </div>
  </div>
</section>
@endif

<section class="gm-promo-strip" aria-label="Gomovie viewing">
  <span class="gm-promo-icon" aria-hidden="true">✦</span>
  <div><strong>Gomovie <span>Stories</span></strong><p>Discover films · Explore series · Build your watchlist</p></div>
  <a href="{{ route('browse') }}" class="gm-button gm-button-gold">Explore titles <span aria-hidden="true">↗</span></a>
</section>

<section class="gm-content-section" aria-labelledby="gm-picks-title">
  <div class="gm-section-heading">
    <h2 id="gm-picks-title"><span class="gm-section-marker"></span>Top Picks for You</h2>
    <a href="{{ route('browse') }}" class="gm-view-all">View all <span aria-hidden="true">→</span></a>
  </div>
  @if($topPicks->isEmpty())
    <div class="gm-home-preview-collection gm-home-preview-featured" aria-hidden="true">
      @foreach(['CINEMA','STORIES','EXPLORE','DISCOVER'] as $index=>$artword)
        <div class="gm-home-preview-poster gm-home-preview-palette-{{ $index % 6 }}">
          <span class="gm-home-preview-artmark">✦</span><span class="gm-home-preview-artword">{{ $artword }}</span>
          <span class="gm-home-preview-artline">GOMOVIE COLLECTION</span>
        </div>
      @endforeach
    </div>
    <p class="gm-home-preview-note"><span>Curated picks will appear when the first titles are published.</span><a href="{{ route('browse') }}">Explore catalog <span aria-hidden="true">→</span></a></p>
  @else
  <div class="gm-feature-grid">
    @foreach($topPicks->take(4) as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind'],'cardMode'=>'featured'])
    @endforeach
  </div>
  @endif
</section>

<section class="gm-content-section" aria-labelledby="gm-releases-title">
  <div class="gm-section-heading">
    <h2 id="gm-releases-title"><span class="gm-section-marker"></span>New Releases</h2>
    <a class="gm-view-all" href="{{ route('browse') }}">View all <span aria-hidden="true">→</span></a>
  </div>
  @if($newReleases->isEmpty())
    <div class="gm-home-preview-collection gm-home-preview-rail" aria-hidden="true">
      @for($index=0;$index<6;$index++)
        <div class="gm-home-preview-poster gm-home-preview-palette-{{ ($index+2) % 6 }}">
          <span class="gm-home-preview-artmark">✦</span><span class="gm-home-preview-artline">UPCOMING SELECTION</span>
        </div>
      @endfor
    </div>
    <p class="gm-home-preview-note"><span>Fresh releases will fill this row as they are published.</span><a href="{{ route('browse') }}">Explore catalog <span aria-hidden="true">→</span></a></p>
  @else
  <div class="gm-content-rail">
    @foreach($newReleases as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind'],'cardMode'=>'rail'])
    @endforeach
  </div>
  @endif
</section>

@if($trending->isNotEmpty())
<section class="gm-content-section" aria-labelledby="gm-trending-title">
  <div class="gm-section-heading">
    <h2 id="gm-trending-title"><span class="gm-section-marker"></span>Trending Now <small>LAST 30 DAYS</small></h2>
    <a class="gm-view-all" href="{{ route('browse') }}">View all <span aria-hidden="true">→</span></a>
  </div>
  <div class="gm-content-rail">
    @foreach($trending as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind'],'cardMode'=>'rail','cardRank'=>$loop->iteration])
    @endforeach
  </div>
</section>
@endif

<section class="gm-content-section" aria-labelledby="gm-recent-title">
  <div class="gm-section-heading">
    <h2 id="gm-recent-title"><span class="gm-section-marker"></span>Recently Added</h2>
    <a class="gm-view-all" href="{{ route('browse') }}">View all <span aria-hidden="true">→</span></a>
  </div>
  @if($recentlyAdded->isEmpty())
    <div class="gm-home-preview-collection gm-home-preview-library" aria-hidden="true">
      @for($index=0;$index<12;$index++)
        <div class="gm-home-preview-poster gm-home-preview-palette-{{ ($index+4) % 6 }}">
          <span class="gm-home-preview-artmark">✧</span><span class="gm-home-preview-artline">GOMOVIE</span>
        </div>
      @endfor
    </div>
    <p class="gm-home-preview-note"><span>There are no published films or series yet. These are decorative preview cards, not movie listings.</span><a href="{{ route('browse') }}">Browse catalog <span aria-hidden="true">→</span></a></p>
  @else
  <div class="gm-poster-grid">
    @foreach($recentlyAdded as $entry)
      @include('frontend.partials.card',['item'=>$entry['item'],'kind'=>$entry['kind']])
    @endforeach
  </div>
  @endif
</section>

<style>
/* Homepage-only presentation layer. No shared header/footer/auth selectors. */
.gm-home-upgrade{isolation:isolate}
.gm-home-upgrade .gm-preview-hero{position:relative;isolation:isolate;overflow:hidden;margin:5px 0 0;padding:26px 28px 28px;border-radius:26px;border:1px solid rgba(255,117,137,.22);background:radial-gradient(ellipse at 50% 40%,rgba(135,34,90,.17),transparent 63%),linear-gradient(145deg,#1d1026,#0d091a 68%,#180d21);box-shadow:inset 0 1px rgba(255,255,255,.075),0 28px 80px rgba(0,0,0,.2)}
.gm-home-upgrade .gm-preview-aura{position:absolute;z-index:-1;inset:45px 10% auto;height:380px;border-radius:50%;background:radial-gradient(ellipse,rgba(248,46,109,.17),rgba(112,47,190,.1) 42%,transparent 75%);filter:blur(24px);pointer-events:none}
.gm-home-upgrade .gm-preview-overline{position:relative;z-index:3;text-align:center;font-size:10px;font-weight:850;letter-spacing:.32em;color:#dec6c9}
.gm-home-upgrade .gm-preview-stage{height:432px;position:relative;max-width:1020px;margin:0 auto;perspective:1000px;pointer-events:none}
.gm-home-upgrade .gm-preview-cover{position:absolute;left:50%;top:50%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;overflow:hidden;width:255px;height:370px;padding:22px 20px;text-align:center;border-radius:20px;border:1px solid rgba(250,187,189,.27);box-shadow:0 24px 48px #0009,inset 0 0 40px #ffffff08;color:#fff;transform-origin:center;transition:transform .3s}
.gm-home-upgrade .gm-preview-cover:before,.gm-home-upgrade .gm-preview-cover:after{content:"";position:absolute;pointer-events:none}
.gm-home-upgrade .gm-preview-cover:before{width:265px;height:265px;border:1px solid #ffffff2a;border-radius:50%;top:16%;left:50%;transform:translateX(-50%);box-shadow:0 0 0 27px #ffffff04,0 0 0 58px #ffffff03}
.gm-home-upgrade .gm-preview-cover:after{inset:0;background:linear-gradient(180deg,#09081725 0%,transparent 39%,#05040aab 100%)}
.gm-home-upgrade .gm-preview-cover>*{position:relative;z-index:1}
.gm-home-upgrade .gm-preview-cover--far-left{z-index:1;transform:translate(-190%,-49%) scale(.76) rotateY(17deg);background:radial-gradient(circle at 40% 28%,#f1846799,#7d264e 42%,#1a172a 88%);filter:brightness(.65)}
.gm-home-upgrade .gm-preview-cover--left{z-index:2;transform:translate(-115%,-49%) scale(.88) rotateY(11deg);background:radial-gradient(circle at 65% 29%,#fb7d6188,#4a1b65 55%,#0c0b23);filter:brightness(.82)}
.gm-home-upgrade .gm-preview-cover--center{z-index:5;transform:translate(-50%,-50%) scale(1.07);border-color:#ef927b8c;background:radial-gradient(circle at 50% 28%,#f5a061a1,#982e6d 43%,#2a163a 73%,#0c0a1b);box-shadow:0 0 46px #e4536e45,0 33px 66px #000c}
.gm-home-upgrade .gm-preview-cover--right{z-index:2;transform:translate(15%,-49%) scale(.88) rotateY(-11deg);background:radial-gradient(circle at 45% 31%,#7773bb,#392a76 45%,#1a1228);filter:brightness(.82)}
.gm-home-upgrade .gm-preview-cover--far-right{z-index:1;transform:translate(90%,-49%) scale(.76) rotateY(-17deg);background:radial-gradient(circle at 55% 26%,#d45b90aa,#61285d 44%,#170d2b);filter:brightness(.65)}
.gm-home-upgrade .gm-preview-topline{position:absolute;top:18px;left:15px;right:15px;font-size:8px;letter-spacing:.2em;font-weight:800;color:#ffe7d3}
.gm-home-upgrade .gm-preview-symbol{position:absolute;top:27%;font-size:75px;font-weight:300;text-shadow:0 0 24px #fff8}
.gm-home-upgrade .gm-preview-focus-symbol{display:grid;place-items:center;position:absolute;top:30%;width:74px;height:74px;padding-left:5px;border-radius:50%;border:1px solid #ffc9ac99;background:linear-gradient(130deg,#ff674d,#df204f);font-size:23px;box-shadow:0 0 35px #fc4a6388}
.gm-home-upgrade .gm-preview-cover strong{font-size:22px;line-height:1.1;font-weight:950;letter-spacing:-.04em;text-shadow:0 3px 20px #000b}
.gm-home-upgrade .gm-preview-cover--center strong{font-size:31px;line-height:1.04;letter-spacing:-.06em}
.gm-home-upgrade .gm-preview-cover small{font-size:8px;letter-spacing:.17em;color:#f6d3df;margin-top:12px;font-weight:800}
.gm-home-upgrade .gm-preview-dots{display:flex;justify-content:center;align-items:center;gap:10px;margin:-9px auto 21px}
.gm-home-upgrade .gm-preview-dots i{display:block;width:6px;height:6px;border-radius:50%;background:#786173}
.gm-home-upgrade .gm-preview-dots i.is-selected{width:14px;height:14px;border:3px solid #8c2649;background:#ff5a73;box-shadow:0 0 14px #f53f75}
.gm-home-upgrade .gm-preview-intro{display:flex;align-items:end;justify-content:space-between;gap:25px;border-top:1px solid #ffffff1e;padding:18px 12px 4px}
.gm-home-upgrade .gm-preview-heading{max-width:670px}
.gm-home-upgrade .gm-preview-heading h1{margin:9px 0 11px;font-size:clamp(26px,3.7vw,44px);line-height:1.1;letter-spacing:-.055em;font-weight:950;color:#fff}
.gm-home-upgrade .gm-preview-heading h1 em{color:#ff667a;font-style:normal}
.gm-home-upgrade .gm-preview-heading>p:last-child{max-width:630px;font-size:13px;color:#c6b8c9;line-height:1.65}
.gm-home-upgrade .gm-preview-cta{display:flex;flex-shrink:0;gap:9px;flex-wrap:wrap;padding-bottom:8px}
.gm-home-upgrade .gm-content-section{margin-top:53px}
.gm-home-upgrade .gm-section-heading{margin-bottom:20px}
.gm-home-upgrade .gm-section-heading h2{font-size:clamp(19px,2.3vw,25px)}
.gm-home-upgrade .gm-section-marker{height:24px;background:linear-gradient(160deg,#bd51fa,#f74476)}
.gm-home-upgrade .gm-promo-strip{position:relative;overflow:hidden;margin-top:30px;border-color:#d7616a66;background:radial-gradient(circle at 90% 20%,#dd4b5c38,transparent 36%),linear-gradient(115deg,#361023,#230c1c 57%,#411a30)}
.gm-home-upgrade .gm-promo-strip:after{content:"";position:absolute;width:170px;height:170px;border:1px solid #ffffff18;transform:rotate(26deg);right:13%;top:-103px;border-radius:44px;pointer-events:none}
.gm-home-upgrade .gm-poster-card-featured .gm-poster-frame{aspect-ratio:2/3}
.gm-home-upgrade .gm-poster-card-featured .gm-poster-image{object-position:center}
.gm-home-upgrade .gm-content-rail{gap:17px}
.gm-home-upgrade .gm-home-preview-collection{display:grid;gap:16px}
.gm-home-upgrade .gm-home-preview-featured{grid-template-columns:repeat(4,minmax(0,1fr))}
.gm-home-upgrade .gm-home-preview-rail{grid-template-columns:repeat(6,minmax(0,1fr))}
.gm-home-upgrade .gm-home-preview-library{grid-template-columns:repeat(6,minmax(0,1fr));gap:16px 14px}
.gm-home-upgrade .gm-home-preview-poster{position:relative;isolation:isolate;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;overflow:hidden;aspect-ratio:2/3;min-width:0;min-height:0;padding:15px 10px;text-align:center;border-radius:15px;border:1px solid #ffffff24;box-shadow:inset 0 1px #ffffff15;background:#241429}
.gm-home-upgrade .gm-home-preview-poster:before{content:"";position:absolute;z-index:-1;top:15%;left:50%;width:85%;aspect-ratio:1;border:1px solid rgba(255,255,255,.2);border-radius:40% 60% 60% 40%;transform:translateX(-50%) rotate(-30deg);box-shadow:0 0 0 16px #ffffff05,0 0 0 33px #ffffff03}
.gm-home-upgrade .gm-home-preview-poster:after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(0deg,#090718d9 0%,transparent 55%);pointer-events:none}
.gm-home-upgrade .gm-home-preview-artmark{position:absolute;top:27%;font-size:clamp(35px,7vw,83px);color:#ffffffb5;text-shadow:0 0 28px #ffffff55}
.gm-home-upgrade .gm-home-preview-artword{font-size:clamp(14px,2vw,27px);font-weight:950;letter-spacing:-.045em}
.gm-home-upgrade .gm-home-preview-artline{font-size:9px;letter-spacing:.17em;font-weight:900;color:#f7ccda}
.gm-home-upgrade .gm-home-preview-artword+.gm-home-preview-artline{margin-top:10px}
.gm-home-upgrade .gm-home-preview-rail .gm-home-preview-artline,.gm-home-upgrade .gm-home-preview-library .gm-home-preview-artline{font-size:8px}
.gm-home-upgrade .gm-home-preview-palette-0{background:radial-gradient(circle at 50% 27%,#f28c6dcc,#9a2d65 48%,#1e1133)}
.gm-home-upgrade .gm-home-preview-palette-1{background:radial-gradient(circle at 50% 27%,#6c89c3,#3a327f 48%,#1a1030)}
.gm-home-upgrade .gm-home-preview-palette-2{background:radial-gradient(circle at 50% 24%,#c97ba5,#6f2d5f 54%,#1a1128)}
.gm-home-upgrade .gm-home-preview-palette-3{background:radial-gradient(circle at 50% 24%,#ce9257,#9b335b 52%,#22132e)}
.gm-home-upgrade .gm-home-preview-palette-4{background:radial-gradient(circle at 50% 24%,#866cd1,#51315f 52%,#180e2b)}
.gm-home-upgrade .gm-home-preview-palette-5{background:radial-gradient(circle at 50% 24%,#f06f8c,#61396f 52%,#160d24)}
.gm-home-upgrade .gm-home-preview-note{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0 0;padding:10px 0;color:#a99cad;font-size:12px;line-height:1.6}
.gm-home-upgrade .gm-home-preview-note a{white-space:nowrap;text-decoration:none;font-weight:800;color:#fbc57e}
.gm-home-upgrade .gm-home-preview-note a:hover{color:#fff}

/* Cinematic backdrop V2: full viewport, high-visibility artwork.
   Home-only selectors: shared header/footer, movie cards and catalog grids untouched. */
.gm-home-upgrade .gm-hero,
.gm-home-upgrade .gm-preview-hero{
  position:relative;
  left:50%;
  transform:translateX(-50%);
  width:100vw;
  width:100dvw;
  max-width:none;
  box-sizing:border-box;
  margin:-34px 0 0;
  padding:28px max(24px,calc((100vw - 1240px)/2)) 29px;
  border-left:0;
  border-right:0;
  border-top:0;
  border-radius:0;
  overflow:hidden;
  background:#11101b;
}
.gm-home-upgrade .gm-preview-hero{
  padding-top:28px;
  padding-bottom:34px;
}
.gm-home-upgrade .gm-preview-stage,
.gm-home-upgrade .gm-hero-stage{
  max-width:1200px;
}
.gm-home-upgrade .gm-hero .gm-hero-scene{
  position:absolute;
  inset:0;
  z-index:0;
  overflow:hidden;
  pointer-events:none;
  background:radial-gradient(ellipse at 50% 25%,#5a2444,#141022 72%);
}
.gm-home-upgrade .gm-hero .gm-hero-scene-image{
  position:absolute;
  inset:0;
  width:100%;
  height:100%;
  object-fit:cover;
  object-position:center 39%;
  filter:brightness(1.08) saturate(1.28) blur(1px);
  transform:scale(1.025);
  transition:opacity .65s ease;
}
.gm-home-upgrade .gm-hero .gm-hero-scene-tint{
  position:absolute;
  z-index:2;
  inset:0;
  background:
    linear-gradient(180deg,
      rgba(9,7,18,.14) 0%,
      rgba(9,7,18,.06) 27%,
      rgba(9,7,18,.18) 52%,
      rgba(9,7,18,.48) 73%,
      rgba(9,7,18,.92) 100%),
    linear-gradient(90deg,
      rgba(9,7,18,.39) 0%,
      rgba(9,7,18,.08) 26%,
      rgba(9,7,18,.06) 72%,
      rgba(9,7,18,.38) 100%);
}
.gm-home-upgrade .gm-hero .gm-hero-backlight{
  z-index:1;
  opacity:.25;
}
.gm-home-upgrade .gm-hero > .gm-hero-kicker,
.gm-home-upgrade .gm-hero > .gm-hero-stage,
.gm-home-upgrade .gm-hero > .gm-hero-pagination,
.gm-home-upgrade .gm-hero > .gm-hero-description{
  position:relative;
  z-index:3;
}
.gm-home-upgrade .gm-hero > .gm-hero-description{
  border-top-color:rgba(255,255,255,.26);
  padding-top:24px;
  text-shadow:0 2px 20px rgba(0,0,0,.75);
}
@media(max-width:760px){
  .gm-home-upgrade .gm-hero,
  .gm-home-upgrade .gm-preview-hero{
    margin-top:-22px;
    padding:22px 14px 24px;
    width:100vw;
    width:100dvw;
    border-radius:0;
  }
  .gm-home-upgrade .gm-hero .gm-hero-scene-image{
    object-position:50% 32%;
    filter:brightness(.99) saturate(1.24) blur(1px);
  }
  .gm-home-upgrade .gm-hero .gm-hero-scene-tint{
    background:
      linear-gradient(180deg,rgba(9,7,18,.18),rgba(9,7,18,.27) 40%,rgba(9,7,18,.92)),
      linear-gradient(90deg,rgba(9,7,18,.32),transparent 50%,rgba(9,7,18,.32));
  }
}
@media (prefers-reduced-motion:reduce){
  .gm-home-upgrade .gm-hero .gm-hero-scene-image{transition:none!important}
}

@media (max-width:1100px){
.gm-home-upgrade .gm-preview-cover--far-left{transform:translate(-165%,-49%) scale(.68) rotateY(17deg)}
.gm-home-upgrade .gm-preview-cover--far-right{transform:translate(65%,-49%) scale(.68) rotateY(-17deg)}
.gm-home-upgrade .gm-preview-intro{flex-direction:column;align-items:start}
.gm-home-upgrade .gm-preview-cta{padding-bottom:0}
.gm-home-upgrade .gm-home-preview-library{grid-template-columns:repeat(5,minmax(0,1fr))}
}
@media (max-width:760px){
.gm-home-upgrade .gm-preview-hero{padding:22px 18px}
.gm-home-upgrade .gm-preview-stage{height:350px}
.gm-home-upgrade .gm-preview-cover{height:300px;width:210px;padding:17px}
.gm-home-upgrade .gm-preview-cover--far-left,.gm-home-upgrade .gm-preview-cover--far-right{opacity:0}
.gm-home-upgrade .gm-preview-cover--left{transform:translate(-108%,-49%) scale(.75) rotateY(11deg)}
.gm-home-upgrade .gm-preview-cover--right{transform:translate(8%,-49%) scale(.75) rotateY(-11deg)}
.gm-home-upgrade .gm-preview-cover--center{transform:translate(-50%,-50%) scale(1.02)}
.gm-home-upgrade .gm-preview-cover--center strong{font-size:25px}
.gm-home-upgrade .gm-home-preview-featured{grid-template-columns:repeat(2,minmax(0,1fr))}
.gm-home-upgrade .gm-home-preview-rail{grid-template-columns:repeat(3,minmax(0,1fr))}
.gm-home-upgrade .gm-home-preview-library{grid-template-columns:repeat(4,minmax(0,1fr))}
.gm-home-upgrade .gm-content-section{margin-top:39px}
}
@media (max-width:480px){
.gm-home-upgrade .gm-preview-overline{font-size:8px;letter-spacing:.17em}
.gm-home-upgrade .gm-preview-stage{height:305px}
.gm-home-upgrade .gm-preview-cover{width:183px;height:265px;border-radius:15px}
.gm-home-upgrade .gm-preview-cover--left{transform:translate(-95%,-49%) scale(.71)}
.gm-home-upgrade .gm-preview-cover--right{transform:translate(-5%,-49%) scale(.71)}
.gm-home-upgrade .gm-preview-cover--center{transform:translate(-50%,-50%) scale(1)}
.gm-home-upgrade .gm-preview-topline{font-size:7px}
.gm-home-upgrade .gm-preview-focus-symbol{width:55px;height:55px;font-size:18px}
.gm-home-upgrade .gm-preview-cover--center strong{font-size:20px}
.gm-home-upgrade .gm-preview-cover strong{font-size:17px}
.gm-home-upgrade .gm-preview-cover small{font-size:7px}
.gm-home-upgrade .gm-preview-heading h1{font-size:clamp(25px,7.5vw,34px)}
.gm-home-upgrade .gm-preview-cta{width:100%}
.gm-home-upgrade .gm-preview-cta .gm-button{flex:1 1 auto}
.gm-home-upgrade .gm-home-preview-library{grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.gm-home-upgrade .gm-home-preview-rail{gap:10px}
.gm-home-upgrade .gm-home-preview-poster{border-radius:12px;padding:10px 5px}
.gm-home-upgrade .gm-home-preview-artword{font-size:15px}
.gm-home-upgrade .gm-home-preview-artline{font-size:7px}
}
@media (prefers-reduced-motion:reduce){.gm-home-upgrade .gm-preview-cover{transition:none}}
</style>
</div>
@endsection
