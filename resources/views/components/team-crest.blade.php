@props(['equipo' => null])

<span {{ $attributes->merge(['class' => 'team-crest']) }} aria-hidden="true">
    @if($escudoUrl = $equipo?->escudo_url)
        <img src="{{ $escudoUrl }}" alt="">
    @else
        <i class="bi bi-shield-fill"></i>
    @endif
</span>
