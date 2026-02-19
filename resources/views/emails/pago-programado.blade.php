<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago Programado</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Pago Programado</h2>

    <p>Estimado(a) {{ $proveedor->razon_social }},</p>

    <p>Le informamos que se ha programado un pago con los siguientes datos:</p>

    <table style="border-collapse: collapse; width: 100%; max-width: 500px;">
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Folio</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $pago->folio }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Monto</td>
            <td style="padding: 8px; border: 1px solid #ddd;">${{ number_format($pago->monto_pago, 2) }} {{ strtoupper($pago->moneda) }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Fecha Programada</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $pago->fecha_pago_programada->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Tipo de Pago</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $pago->tipo_pago === 'contado' ? 'Contado' : 'Credito' }}</td>
        </tr>
    </table>

    @if($proveedor->tiene_acceso_portal)
    <p style="margin-top: 16px;">Puede consultar el estado de sus pagos en el portal de proveedores.</p>
    @endif

    <p style="margin-top: 16px;">Saludos cordiales.</p>
</body>
</html>
