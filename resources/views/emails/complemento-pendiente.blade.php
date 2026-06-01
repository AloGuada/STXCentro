<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Complemento de pago pendiente</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Complemento de pago pendiente</h2>

    <p>Estimado(a) {{ $proveedor->razon_social }},</p>

    <p>Hemos registrado un pago a una factura con método de pago <strong>PPD</strong>.
       Conforme a la normativa fiscal, debe emitir y subir el <strong>complemento de pago</strong>
       correspondiente en el portal de proveedores.</p>

    <table style="border-collapse: collapse; width: 100%; max-width: 500px;">
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Folio obligación</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $complemento->folio }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Monto del pago</td>
            <td style="padding: 8px; border: 1px solid #ddd;">${{ number_format($complemento->monto_pago, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Fecha del pago</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $complemento->fecha_pago->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Fecha límite</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $complemento->fecha_limite->format('d/m/Y') }}</td>
        </tr>
    </table>

    <p style="margin-top: 16px; color: #b91c1c;"><strong>Importante:</strong> mientras existan complementos
       de pago pendientes, no se podrán generar nuevas órdenes ni solicitudes de pago a su favor.</p>

    <p style="margin-top: 16px;">Saludos cordiales.</p>
</body>
</html>
