<?php

namespace App\Enums\Qal;

/**
 * A qué lista pertenece un defecto del catálogo.
 *
 * Los defectos viven en una sola tabla y se tipan por etapa: soldadura y las
 * familias de accesorios son de 2ª, pintura de 3ª. Es enum y no catálogo
 * porque cada ámbito lo lee un formulario distinto; añadir uno es añadir un
 * lugar donde capturarlo, no una fila.
 *
 * Los accesorios que fallan por soldadura usan la lista de soldadura: es el
 * mismo cordón con los mismos defectos, sólo que en una pieza chica.
 */
enum AmbitoDefecto: string
{
    case Soldadura = 'soldadura';
    case Pintura = 'pintura';
    case AccesorioDimensional = 'accesorio_dimensional';
    case AccesorioBarrenos = 'accesorio_barrenos';
    case AccesorioLimpieza = 'accesorio_limpieza';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Soldadura => 'Soldadura',
            self::Pintura => 'Pintura',
            self::AccesorioDimensional => 'Accesorios · dimensional',
            self::AccesorioBarrenos => 'Accesorios · barrenos',
            self::AccesorioLimpieza => 'Accesorios · limpieza',
        };
    }

    /** La etapa en la que se captura. */
    public function fase(): FaseTransformacion
    {
        return $this === self::Pintura ? FaseTransformacion::Tercera : FaseTransformacion::Segunda;
    }

    /**
     * @return list<array{valor: string, etiqueta: string, fase: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $ambito): array => [
                'valor' => $ambito->value,
                'etiqueta' => $ambito->etiqueta(),
                'fase' => $ambito->fase()->value,
            ],
            self::cases(),
        );
    }
}
