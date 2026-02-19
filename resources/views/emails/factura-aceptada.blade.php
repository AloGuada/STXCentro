<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Aceptada</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Factura Aceptada</h2>

    <p>Estimado(a) {{ $proveedor->razon_social }},</p>

    <p>Le informamos que su factura ha sido aceptada y el pago se encuentra pendiente de programación:</p>

    <table style="border-collapse: collapse; width: 100%; max-width: 500px;">
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Folio Factura</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $factura->folio }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Monto</td>
            <td style="padding: 8px; border: 1px solid #ddd;">${{ number_format($factura->total, 2) }} {{ strtoupper($factura->moneda) }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Fecha Factura</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $factura->fecha_factura?->format('d/m/Y') ?? '-' }}</td>
        </tr>
    </table>

    @if($proveedor->tiene_acceso_portal)
    <p style="margin-top: 16px;">Puede consultar el estado de sus facturas y pagos en el portal de proveedores.</p>
    @endif

    <p style="margin-top: 16px;">Saludos cordiales.</p>
</body>
</html>
