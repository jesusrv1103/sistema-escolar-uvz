{{-- Comprobante de pago institucional (Fase 9). Renderizado por DomPDF:
     CSS propio compatible con DomPDF, SIN Tailwind/Vite. Todo dato dinámico
     se escapa con {{ }}; la identidad sale de config('branding.*'). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de pago {{ $folio }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: {{ $colores['texto'] }};
            margin: 0;
        }
        .hoja { padding: 28px 34px; }

        /* Encabezado institucional */
        .cabecera {
            border-bottom: 3px solid {{ $colores['primario'] }};
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .cabecera table { width: 100%; border-collapse: collapse; }
        .cabecera td { vertical-align: middle; }
        .logo { width: 130px; }
        .institucion {
            font-size: 17px;
            font-weight: bold;
            color: {{ $colores['primario'] }};
            margin: 0;
        }
        .lema { font-size: 10px; color: {{ $colores['tenue'] }}; font-style: italic; margin: 3px 0 0; }
        .titulo { text-align: right; }
        .titulo h1 {
            font-size: 15px;
            margin: 0;
            color: {{ $colores['texto'] }};
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .folio {
            display: inline-block;
            margin-top: 6px;
            padding: 5px 10px;
            background-color: {{ $colores['primario'] }};
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
        }

        /* Secciones */
        h2 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: {{ $colores['primario'] }};
            border-bottom: 1px solid {{ $colores['borde'] }};
            padding-bottom: 4px;
            margin: 22px 0 10px;
        }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th {
            text-align: left;
            width: 24%;
            padding: 6px 8px;
            background-color: {{ $colores['borde'] }};
            color: {{ $colores['texto'] }};
            font-weight: bold;
            border: 1px solid {{ $colores['borde'] }};
        }
        table.datos td {
            padding: 6px 8px;
            border: 1px solid {{ $colores['borde'] }};
        }
        .monto { font-size: 14px; font-weight: bold; color: {{ $colores['primario'] }}; }

        /* Pie institucional */
        .pie {
            margin-top: 26px;
            border-top: 2px solid {{ $colores['primario'] }};
            padding-top: 10px;
            font-size: 9.5px;
            color: {{ $colores['tenue'] }};
        }
        .pie .contacto { margin-bottom: 8px; }
        .aviso {
            margin-top: 8px;
            padding: 8px;
            border: 1px solid {{ $colores['borde'] }};
            background-color: {{ $colores['fondo'] }};
            text-align: center;
        }
        .aviso strong { color: {{ $colores['secundario'] }}; }
    </style>
</head>
<body>
<div class="hoja">

    {{-- Encabezado institucional --}}
    <div class="cabecera">
        <table>
            <tr>
                <td style="width: 150px;">
                    @if($logo)
                        <img class="logo" src="{{ $logo }}" alt="Logo institucional">
                    @endif
                </td>
                <td>
                    <p class="institucion">{{ $institucion }}</p>
                    <p class="lema">{{ $slogan }}</p>
                </td>
                <td class="titulo">
                    <h1>Comprobante de pago</h1>
                    <span class="folio">{{ $folio }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Datos de identificación --}}
    <h2>Datos del comprobante</h2>
    <table class="datos">
        <tr>
            <th>Folio</th>
            <td>{{ $folio }}</td>
            <th>Fecha de pago</th>
            <td>{{ $fechaPago ?? '—' }}</td>
        </tr>
        <tr>
            <th>Fecha de validación</th>
            <td>{{ $fechaValidacion }}</td>
            <th>Sistema</th>
            <td>{{ $sistema }}</td>
        </tr>
        <tr>
            <th>Alumno</th>
            <td>{{ $alumno }}</td>
            <th>Número de control</th>
            <td>{{ $numeroControl ?? 'Por asignar' }}</td>
        </tr>
        <tr>
            <th>Programa</th>
            <td>{{ $programa }}</td>
            <th>Tipo de concepto</th>
            <td>{{ $tipoConcepto }}</td>
        </tr>
    </table>

    {{-- Detalle del pago --}}
    <h2>Detalle del pago</h2>
    <table class="datos">
        <tr>
            <th>Concepto</th>
            <td colspan="3">{{ $concepto }}</td>
        </tr>
        <tr>
            <th>Método de pago</th>
            <td>{{ $metodo ?? '—' }}</td>
            <th>Referencia</th>
            <td>{{ $referencia !== null && $referencia !== '' ? $referencia : '—' }}</td>
        </tr>
        <tr>
            <th>Monto pagado</th>
            <td colspan="3" class="monto">{{ $monto }} {{ $moneda }}</td>
        </tr>
    </table>

    {{-- Pie institucional --}}
    <div class="pie">
        <div class="contacto">
            <strong>{{ $institucion }}</strong> ·
            {{ $contacto['domicilio'] }} ·
            Tel. {{ $contacto['telefono'] }} ·
            {{ $contacto['correo'] }}
        </div>
        <div class="aviso">
            <strong>Comprobante administrativo de pago</strong><br>
            No constituye factura fiscal.
        </div>
    </div>

</div>
</body>
</html>
