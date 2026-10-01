@php
    $logoSrc = null;
    if (!empty($logoBase64)) {
        $logoSrc = $logoBase64;
    } elseif (!empty($empresa['logo_base64'])) {
        $logoSrc = $empresa['logo_base64'];
    } elseif (!empty($empresa['logo_url'])) {
        $logoSrc = $empresa['logo_url'];
    } elseif (file_exists(public_path('storage/logos/logo.png'))) {
        $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('storage/logos/logo.png')));
    } elseif (file_exists(resource_path('logos/logo.png'))) {
        $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents(resource_path('logos/logo.png')));
    }
    $logoHeight = $height ?? '64px';
@endphp

@if(!empty($logoSrc))
    <img src="{{ $logoSrc }}" alt="Logo" style="height: {{ $logoHeight }}; max-width: 200px; object-fit: contain;">
@else
    <div style="font-size: 15px; font-weight: bold; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px;">
        {{ $empresa['nombre'] ?? $sucursal->nombre ?? config('app.name', 'SISTEMA DE EMPEÑOS') }}
    </div>
@endif
