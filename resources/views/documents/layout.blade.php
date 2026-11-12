<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<title>{{ $title }}{{ isset($reference) ? ' '.$reference : '' }}</title>
<style>
    @page { margin: 16mm 18mm 24mm 18mm; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 9.5pt; color: #1f2328; line-height: 1.45; }
    .doc-header { margin-bottom: 6mm; }
    .page-break { page-break-after: always; }
    footer { position: fixed; bottom: -16mm; left: 0; right: 0; font-size: 8pt; color: #6b7280; border-top: 0.5pt solid #d1d5db; padding-top: 2mm; }
    .logo { max-height: 14mm; max-width: 60mm; }
    .store { font-size: 13pt; font-weight: bold; }
    h1 { font-size: 18pt; margin: 0 0 1mm 0; letter-spacing: 0.3pt; }
    .muted { color: #6b7280; }
    .grid { width: 100%; border-collapse: collapse; margin-bottom: 7mm; }
    .grid td { vertical-align: top; padding: 0; }
    .label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #6b7280; margin-bottom: 1mm; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    table.lines th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #6b7280; border-bottom: 1pt solid #1f2328; padding: 1.5mm 1.5mm; }
    table.lines td { border-bottom: 0.5pt solid #e5e7eb; padding: 2mm 1.5mm; vertical-align: top; }
    .num, table.lines th.num, table.lines td.num { text-align: right; white-space: nowrap; }
    table.totals { width: 45%; margin-left: 55%; border-collapse: collapse; margin-top: 4mm; }
    table.totals td { padding: 1.2mm 1.5mm; }
    table.totals tr.grand td { border-top: 1pt solid #1f2328; font-weight: bold; font-size: 11pt; padding-top: 2mm; }
    .badge { display: inline-block; padding: 0.8mm 2.5mm; border-radius: 2mm; background: #ecfdf5; color: #047857; font-weight: bold; font-size: 8.5pt; }
    .pre { white-space: pre-line; }
</style>
</head>
<body>

<footer>
    @if ($footer)<div class="pre">{{ $footer }}</div>@endif
    <div>{{ $seller['name'] }}@if ($seller['email']) · {{ $seller['email'] }}@endif @if ($seller['tax_id']) · {{ __('Tax ID') }}: {{ $seller['tax_id'] }}@endif</div>
</footer>

<main>
    @yield('content')
</main>
</body>
</html>
