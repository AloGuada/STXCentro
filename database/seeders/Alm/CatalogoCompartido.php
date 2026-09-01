<?php

namespace Database\Seeders\Alm;

use App\Models\Costos\Producto;

/**
 * Los artículos del layout que **ya estaban** en el catálogo cuando se revisó
 * producción, el 2026-09-01.
 *
 * `costos_productos` es una sola tabla para los dos módulos —lo dice
 * {@see \App\Http\Controllers\Admin\Alm\ArticuloController}: *"el mismo código
 * sirve para cotizar y para llevar existencias, y duplicarlo sería garantizar
 * que los dos catálogos dejen de empatar"*— así que estos 44 códigos son
 * artículos vivos de Compras, no sobrantes. La carga inicial les abre existencia
 * en vez de levantar un gemelo.
 *
 * La lista va **congelada y no se resuelve al vuelo** a propósito: lo que corre
 * en producción es exactamente lo que se revisó. Si Compras da de alta algo más
 * entre hoy y la carga, ese artículo se duplicará y saldrá en la siguiente
 * revisión, que es preferible a que el seeder empareje solo con algo que nadie
 * miró.
 *
 * Se empareja por descripción sin distinguir mayúsculas porque así llegan: el
 * mismo cepillo viene como `CEPILLO DE ALAMBRE` en el layout de CONS y como
 * `Cepillo de alambre` en el de INS.
 */
final class CatalogoCompartido
{
    /**
     * Descripción en mayúsculas => código con el que ya existe.
     *
     * @var array<string, string>
     */
    public const CODIGOS = [
        'AEROSOL LIMPIADOR Y DESENGRASANTE' => 'ART-00180',
        'ALCOHOL ISOPROPILICO' => 'ART-00179',
        'ATOMIZADOR' => 'ART-00131',
        'CABLE PORTA ELECTRODO 2/0' => 'ART-00187',
        'CAJA CLIP MARIPOSA' => 'ART-00142',
        'CALCULADORA' => 'ART-00145',
        'CARBONES BOSCH 1.619.P11.715' => 'ART-00174',
        'CARETA DE SOLDADOR' => 'ART-00321',
        'CEPILLO DE ALAMBRE' => 'ART-00258',
        'CINCHO DE PLASTICO 2.5MMX100MM' => 'ART-00178',
        'CIRCUITO RESET 10 AM 250 V' => 'ART-00414',
        'CRISTAL CLARO' => 'ART-00233',
        'CUTTER USO RUDO' => 'ART-00155',
        'DETERGENTE POLVO 10 KG' => 'ART-00123',
        'DISCO DE CORTE 9"' => 'ART-00228',
        'DISCO DE DESBASTE 9"' => 'ART-00227',
        'ENGRAPADORA' => 'ART-00156',
        'ESCOBA VENECIANA' => 'ART-00128',
        'ETIQUETAS PLASTIFICADAS 9.5 X 6.5' => 'ART-00210',
        'FILTRO DE ALTA EFICIENCIA 3M' => 'ART-00183',
        'GUANTES DE BOLITA' => 'ART-00235',
        'GUANTES DE SOLDADOR' => 'ART-00232',
        'JABONERA DE TOCADOR' => 'ART-00222',
        'LENTES OBSCUROS' => 'ART-00319',
        'MANGAS DE CARNAZA' => 'ART-00257',
        'MECHUDO DE PABILO' => 'ART-00221',
        'MICA DE CARETA FACIAL' => 'ART-00255',
        'MINI ABRAZADERA REFORZADA 7/32-5/8' => 'ART-00413',
        'PAÑO DE MICROFIBRA' => 'ART-00134',
        'PASTILLAS SANITARIAS' => 'ART-00115',
        'PEGAMENTO ADHESIVO' => 'ART-00161',
        'PINTURA EN AEROSOL AZUL MARINO' => 'ART-00260',
        'PINZA DE TIERRA 600 AMP' => 'ART-00185',
        'PLACA DUPLEX ABS' => 'ART-00172',
        'RECOGEDOR' => 'ART-00120',
        'RIBBON 110*74 RESINA ER-100 P/ZEBRA' => 'ART-00211',
        'SELLADOR SHELLAC' => 'ART-00415',
        'SEPARADOR 12 DIVISIONES' => 'ART-00165',
        'SEPARADOR 8 DIVISIONES' => 'ART-00166',
        'TAPETE LISO BASIC' => 'ART-00130',
        'TAPONES AUDITIVOS' => 'ART-00251',
        'TERMINAL SIN AISLANTE 1/4 CAL 8' => 'ART-00182',
        'TIJERA' => 'ART-00169',
        'WIESE AROMA' => 'ART-00125',
    ];

    /**
     * El artículo con el que ya cuenta el catálogo, o null si esta descripción
     * no estaba en la revisión.
     *
     * Tienen que coincidir **las dos cosas**, el código y la descripción. El
     * consecutivo `ART-` es de cada base: en desarrollo `ART-00120` es otro
     * artículo cualquiera, y emparejar sólo por código le abriría existencia al
     * equivocado. Con las dos, la lista congelada sólo acierta donde de verdad
     * es el mismo artículo que se revisó, y en cualquier otra base no encuentra
     * nada y la carga levanta el suyo.
     */
    public static function existente(string $descripcion): ?Producto
    {
        $buscada = mb_strtoupper(trim($descripcion));
        $codigo = self::CODIGOS[$buscada] ?? null;

        if ($codigo === null) {
            return null;
        }

        $producto = Producto::where('codigo', $codigo)->first();

        return $producto !== null && mb_strtoupper(trim($producto->descripcion)) === $buscada
            ? $producto
            : null;
    }
}
