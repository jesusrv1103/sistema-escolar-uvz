<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tipo_label ?? 'Documento UVZ' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #171517;
            background: #fbfaf7;
        }

        .page {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 40px 50px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #72132e;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .brand-info {
            text-align: left;
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 22pt;
            font-weight: 600;
            color: #72132e;
            line-height: 1.2;
        }

        .brand-short {
            font-size: 10pt;
            color: #696369;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .brand-slogan {
            font-size: 9pt;
            color: #e6ad34;
            font-style: italic;
            margin-top: 2px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-type {
            font-family: 'Playfair Display', serif;
            font-size: 18pt;
            font-weight: 600;
            color: #72132e;
        }

        .doc-folio {
            font-size: 9pt;
            color: #696369;
            margin-top: 5px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 13pt;
            font-weight: 600;
            color: #72132e;
            border-bottom: 1px solid #e6ad34;
            padding-bottom: 5px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 25px;
        }

        .data-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .data-label {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #696369;
            font-weight: 600;
        }

        .data-value {
            font-size: 10pt;
            color: #171517;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }

        th, td {
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #ddd6ce;
        }

        th {
            background-color: #72132e;
            color: white;
            font-weight: 600;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:nth-child(even) td {
            background-color: #f4f0e9;
        }

        tr:hover td {
            background-color: #fbfaf7;
        }

        /* Estados de fila (kardex, boleta, historial, avance, tira, acta).
           Deben ir después de :nth-child(even) para poder sobre-escribirlo. */
        tr.table-success td { background-color: #e6f4ea; }
        tr.table-danger td { background-color: #fbe9ea; }
        tr.table-warning td { background-color: #fdf6e0; }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd6ce;
            text-align: center;
            font-size: 8pt;
            color: #696369;
        }

        .footer-brand {
            font-family: 'Playfair Display', serif;
            font-size: 11pt;
            color: #72132e;
            margin-bottom: 5px;
        }

        .footer-contact {
            font-size: 8pt;
            color: #696369;
            line-height: 1.6;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 7pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-activa { background: #d4edda; color: #155724; }
        .badge-cursando { background: #cce5ff; color: #004085; }
        .badge-aprobada { background: #d4edda; color: #155724; }
        .badge-reprobada { background: #f8d7da; color: #721c24; }
        .badge-baja { background: #e2e3e5; color: #383d41; }
        .badge-validada { background: #d1ecf1; color: #0c5460; }
        .badge-abierta { background: #fff3cd; color: #856404; }
        .badge-cerrada { background: #d4edda; color: #155724; }
        .badge-cancelada { background: #f8d7da; color: #721c24; }

        /* Utilidades de color que las plantillas usan sobre .badge
           (mismo palette que los badges de arriba). */
        .bg-success { background-color: #d4edda; color: #155724; }
        .bg-danger { background-color: #f8d7da; color: #721c24; }
        .bg-warning { background-color: #fff3cd; color: #856404; }
        .text-dark { color: #383d41; }

        .page-break {
            page-break-after: always;
        }

        .no-print {
            display: none;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .page { padding: 20mm; }
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-20 { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="page">
        @yield('content')

        <div class="footer">
            <div class="footer-brand">{{ config('branding.name') }}</div>
            <div class="footer-contact">
                {{ config('branding.address') }}<br>
                Tel: {{ config('branding.phone') }} | Email: {{ config('branding.email') }}<br>
                {{ config('branding.website') }}
            </div>
            <div class="mt-10">
                Documento generado el {{ $fecha_emision_larga ?? now()->locale('es_MX')->isoFormat('D [de] MMMM [de] YYYY') }}
                @if(isset($folio))
                    | Folio: {{ $folio }}
                @endif
                @if(isset($emitido_por))
                    | Emitido por: {{ $emitido_por }}
                @endif
            </div>
        </div>
    </div>
</body>
</html>