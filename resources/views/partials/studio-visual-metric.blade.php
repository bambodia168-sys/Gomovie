{{-- Reusable dashboard metric. All numbers and progress derive from the controller. --}}
@php
    $metricTone = in_array($tone ?? 'slate', ['violet', 'blue', 'teal', 'amber', 'rose', 'indigo', 'slate'], true) ? ($tone ?? 'slate') : 'slate';
    $metricPercent = isset($percentage) ? max(0, min(100, (int) $percentage)) : null;
@endphp
<article class="gm-visual-metric gm-visual-metric--{{ $metricTone }}"
         aria-labelledby="gm-metric-{{ $key }}">
    <span class="gm-visual-metric-art" aria-hidden="true">
        @include('partials.studio-icon', ['name'=>$icon])
    </span>
    <div class="gm-visual-metric-header">
        <span class="gm-visual-metric-icon" aria-hidden="true">
            @include('partials.studio-icon', ['name'=>$icon])
        </span>
        @if(!empty($href))
            <a href="{{ $href }}" class="gm-visual-metric-link"
               aria-label="Manage {{ $label }}" title="Manage {{ $label }}">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none"
                     stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14m-6-6 6 6-6 6"/>
                </svg>
            </a>
        @endif
    </div>
    <div class="gm-visual-metric-data">
        <h3 id="gm-metric-{{ $key }}">{{ $label }}</h3>
        <strong class="gm-visual-metric-value">{{ number_format((int) $value) }}</strong>
        <p class="gm-visual-metric-caption">{{ $description }}</p>
    </div>
    @if($metricPercent !== null)
        <div class="gm-visual-metric-progress" role="progressbar" aria-label="{{ $label }} as a percentage of all titles"
             aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $metricPercent }}">
            <span style="width: {{ $metricPercent }}%"></span>
        </div>
    @else
        <span class="gm-visual-metric-accent" aria-hidden="true"></span>
    @endif
</article>
