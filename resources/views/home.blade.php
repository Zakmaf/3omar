@extends('layouts.app')

@section('title', __('ui.meta_title'))

@section('content')
<div class="container">
    <section class="row justify-content-center text-center my-4 my-lg-5" aria-labelledby="hero-title">
        <div class="col-lg-8">
            <div class="d-flex gap-2 flex-wrap justify-content-center mb-3">
                <span class="badge rounded-pill px-3 py-2" style="background:var(--g-50);color:var(--g-700);border:1px solid var(--g-200)">
                    <i class="bi bi-calendar-check me-1"></i>{{ __('ui.home.year') }}
                </span>
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-info-bg);color:var(--s-info)">
                    <i class="bi bi-unlock me-1"></i>{{ __('ui.home.open_source') }}
                </span>
            </div>
            <h1 id="hero-title" class="display-4 fw-bold mb-3" style="letter-spacing:-0.035em">
                {{ __('ui.home.title') }}
            </h1>
            <p class="lead mb-4 mx-auto" style="color:var(--ink-2);max-width:38rem">
                {{ __('ui.home.intro') }}
            </p>
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold" style="background:var(--g-500)">
                    <i class="bi bi-calculator me-2"></i>{{ __('ui.home.simulate') }}
                </a>
                <a href="{{ route('documentation') }}" class="btn btn-lg px-4 fw-semibold" style="color:var(--g-500);border:1px solid var(--g-500)">
                    <i class="bi bi-journal-text me-2"></i>{{ __('ui.home.rules') }}
                </a>
            </div>
            <p class="small mt-3 mb-0" style="color:var(--ink-3)">
                <i class="bi bi-shield-check me-1"></i>{{ __('ui.footer.privacy') }} {{ __('ui.footer.privacy_detail') }}
            </p>
        </div>
    </section>

    <section class="row g-4 mt-4" aria-labelledby="benefits-title">
        <div class="col-12">
            <h2 id="benefits-title" class="h3 fw-bold">{{ __('ui.home.benefits_title') }}</h2>
        </div>
        @foreach (collect(__('ui.home.benefits'))->map(fn ($item, $index) => $item + [
            'icon' => ['bi-list-check', 'bi-journal-text', 'bi-github'][$index],
            'color' => ['var(--s-info)', 'var(--s-tax)', 'var(--s-succ)'][$index],
        ]) as $item)
        <div class="col-md-4">
            <article class="section-card h-100 p-4">
                <i class="bi {{ $item['icon'] }} fs-2" style="color:{{ $item['color'] }}"></i>
                <h3 class="h5 fw-bold mt-3">{{ $item['title'] }}</h3>
                <p class="mb-0" style="color:var(--ink-2)">{{ $item['text'] }}</p>
            </article>
        </div>
        @endforeach
    </section>

    <section class="section-card p-4 p-lg-5 mt-5 position-relative overflow-hidden" aria-labelledby="coverage-title">
        <div style="position:absolute;inset:0;background-image:var(--zellige-bg);background-size:220px;opacity:.04;pointer-events:none"></div>
        <div class="position-relative">
            <h2 id="coverage-title" class="h3 fw-bold">{{ __('ui.home.coverage_title') }}</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3 mt-2">
                @foreach (__('ui.home.coverage') as $item)
                <div class="col d-flex gap-2">
                    <i class="bi bi-check-circle-fill flex-shrink-0" style="color:var(--s-succ)"></i>
                    <span>{{ $item }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="text-center my-5 py-4" aria-labelledby="final-cta-title">
        <h2 id="final-cta-title" class="h3 fw-bold">{{ __('ui.home.ready') }}</h2>
        <p style="color:var(--ink-3)">{{ __('ui.home.free') }}</p>
        <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold" style="background:var(--g-500)">
            <i class="bi bi-calculator me-2"></i>{{ __('ui.home.simulate') }}
        </a>
    </section>
</div>
@endsection
