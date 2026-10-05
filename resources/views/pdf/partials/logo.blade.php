@php
    $resolver = app(\App\Services\LogoResolverService::class);
    $logoInput = $logoBase64 ?? ($empresa['logo_base64'] ?? ($empresa['logo_url'] ?? null));
    $logoSrc = $resolver->resolveForPdf($logoInput);
    $logoHeight = $height ?? '64px';
@endphp

@if(!empty($logoSrc))
    <img src="{{ $logoSrc }}" alt="" style="height: {{ $logoHeight }}; max-width: 220px; width: auto;">
@else
    <div style="font-size: 15px; font-weight: bold; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px;">
        {{ $empresa['nombre'] ?? $sucursal->nombre ?? config('app.name', 'SISTEMA DE EMPEÑOS') }}
    </div>
@endif
