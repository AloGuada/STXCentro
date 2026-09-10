{{--
    Los espacios de firma de un formato de Almacén.

    No hay flujo de aprobación en el módulo: nadie autoriza nada en pantalla y
    ningún documento queda detenido esperando a alguien. Lo que hay es esto —una
    raya para firmar en la hoja impresa—, que es como se autoriza hoy y por eso
    el formato tiene que llevarla.

    Cuando el sistema ya sabe quién es —el solicitante, quien entregó, quien
    autorizó el ajuste— el nombre se imprime arriba de la raya y quien firma
    sólo confirma. Cuando no lo sabe, la raya va en blanco para llenarse a mano.

    Uso:
        @include('pdf.partials.firmas', ['firmas' => [
            ['nombre' => $pedido->solicitante?->name, 'rol' => 'Solicitó'],
            ['nombre' => null, 'rol' => 'Autorizó'],
        ]])

    `nombre` puede venir vacío; `rol` no.
--}}
<table class="firmas-table">
    <tr>
        @foreach ($firmas as $firma)
        <td>
            <div class="firma-linea">
                <div class="firma-nombre">{{ $firma['nombre'] ?: ' ' }}</div>
                <div class="firma-rol">{{ $firma['rol'] }}</div>
            </div>
        </td>
        @endforeach
    </tr>
</table>
