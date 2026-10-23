{{-- US-012: Libro de guardia digital, de una Apertura de turno al Cierre siguiente. Vista Blade PURA
     (sin Inertia) que renderiza PdfExportador con barryvdh/laravel-dompdf.
     Fuente DejaVu Sans: viene con dompdf y cubre tildes y "ñ".
     Logo: se usa public/images/logo-municipalidad.png si existe.
     // TODO: pedir a la Municipalidad el logo oficial en PNG. --}}
@php
    $logo = public_path('images/logo-municipalidad.png');
    $logoBase64 = file_exists($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libro de guardia #{{ $turno['id'] }}</title>
    <style>
        @page { margin: 28mm 16mm 22mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1f2937; }
        header { position: fixed; top: -20mm; left: 0; right: 0; height: 16mm; border-bottom: 1px solid #9ca3af; }
        footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 8mm; font-size: 7.5pt; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 2mm; }
        header table, .datos, .eventos, .resumen { width: 100%; border-collapse: collapse; }
        .logo { width: 30mm; }
        .logo img { max-height: 14mm; max-width: 28mm; }
        .logo-texto { font-size: 7pt; font-weight: bold; color: #374151; line-height: 1.2; }
        h1 { font-size: 12pt; margin: 0; }
        .subtitulo { font-size: 8pt; color: #6b7280; }
        h2 { font-size: 10pt; margin: 14px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 2px; }
        .datos td { padding: 2px 6px 2px 0; vertical-align: top; }
        .datos .etiqueta { color: #6b7280; width: 32mm; }
        .eventos th, .resumen th { background: #f3f4f6; text-align: left; font-size: 8pt; padding: 4px; border-bottom: 1px solid #d1d5db; }
        .eventos td, .resumen td { padding: 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .eventos tr { page-break-inside: avoid; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .muted { color: #6b7280; }
        .derecha { text-align: right; }
        .vacio { padding: 10px; color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td class="logo">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="Municipalidad de Villa María">
                    @else
                        <div class="logo-texto">MUNICIPALIDAD DE<br>VILLA MARÍA</div>
                    @endif
                </td>
                <td>
                    <h1>Libro de Guardia - Centro de Monitoreo Urbano</h1>
                    <div class="subtitulo">Apertura #{{ $turno['id'] }} · {{ $turno['fecha'] }} · {{ $turno['inicio'] }} a {{ $turno['fin'] }} hs</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        Generado el {{ $generado['fecha'] }} por {{ $generado['por'] }} · ATALAYA — Centro de Monitoreo Urbano
    </footer>

    <h2>Datos del turno</h2>
    <table class="datos">
        <tr><td class="etiqueta">Fecha del turno</td><td>{{ $turno['fecha'] }}</td></tr>
        <tr><td class="etiqueta">Horario</td><td>{{ $turno['inicio'] }} a {{ $turno['fin'] }} hs</td></tr>
        <tr><td class="etiqueta">Abrió</td><td>{{ $turno['supervisor'] ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Apertura</td><td>{{ $turno['apertura'] ?: '—' }}</td></tr>
        <tr><td class="etiqueta">Cerró</td><td>{{ $turno['supervisor_cierre'] ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Cierre</td><td>{{ $turno['cierre'] ?: '—' }}</td></tr>
    </table>

    <h2>Eventos del turno ({{ count($eventos) }})</h2>
    @if (count($eventos) === 0)
        <div class="vacio">No se registraron eventos en este turno.</div>
    @else
        <table class="eventos">
            <thead>
                <tr>
                    <th style="width: 15mm">Hora</th>
                    <th style="width: 38mm">Tipo</th>
                    <th>Descripción</th>
                    <th style="width: 40mm">PM</th>
                    <th style="width: 18mm">Video</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($eventos as $e)
                    <tr>
                        <td class="mono">{{ $e['hora'] }}@if ($e['fecha'] !== $turno['fecha'])<br><span class="muted">{{ $e['fecha'] }}</span>@endif</td>
                        <td>{{ $e['tipo'] }}<br><span class="muted">{{ $e['categoria'] }}</span></td>
                        <td>{{ $e['descripcion'] ?: '—' }}</td>
                        <td>{{ $e['punto'] ?? 'Sin ubicación' }}</td>
                        <td class="mono">{{ $e['timestamp_video'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Resumen por tipo de evento</h2>
    @if (count($resumen) === 0)
        <div class="vacio">Sin eventos.</div>
    @else
        <table class="resumen">
            <thead>
                <tr><th>Tipo</th><th>Categoría</th><th class="derecha">Cantidad</th></tr>
            </thead>
            <tbody>
                @foreach ($resumen as $r)
                    <tr><td>{{ $r['tipo'] }}</td><td class="muted">{{ $r['categoria'] }}</td><td class="derecha">{{ $r['cantidad'] }}</td></tr>
                @endforeach
                <tr><td colspan="2"><strong>Total</strong></td><td class="derecha"><strong>{{ count($eventos) }}</strong></td></tr>
            </tbody>
        </table>
    @endif
</body>
</html>
