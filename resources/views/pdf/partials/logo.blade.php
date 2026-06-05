{{--
    Logo Steelex para PDFs (dompdf). El SVG vive en public/images/logo-steelex.svg
    (mismo trazo que resources/js/components/app-logo-icon.tsx). dompdf no soporta
    SVG inline, por eso se referencia como <img>.
    Parámetro opcional: $width (px, default 170). La altura se calcula del
    aspect ratio del viewBox (1111x304).
--}}
@php
    $width = $width ?? 170;
    $height = (int) round($width * 304 / 1111);
@endphp
<img src="{{ public_path('images/logo-steelex.svg') }}" width="{{ $width }}" height="{{ $height }}" alt="Steelex">
