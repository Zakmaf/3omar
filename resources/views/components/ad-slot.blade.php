@props(['placement'])

@php
    $ad = config("ads.placements.{$placement}");
    $enabled = app()->environment('production')
        && config('ads.enabled')
        && config('ads.client')
        && data_get($ad, 'slot');
@endphp

@if ($enabled)
<aside {{ $attributes->class(['ad-slot', data_get($ad, 'class')]) }} aria-label="{{ __('ui.ads.label') }}">
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ config('ads.client') }}"
         data-ad-slot="{{ data_get($ad, 'slot') }}"
         data-ad-format="auto"
         data-full-width-responsive="true"></ins>
</aside>
@endif
