import { Link } from '@inertiajs/react';
import { PackageSearchIcon } from 'lucide-react';

type Props = {
    /**
     * El almacén del que se quiere ver el inventario. Sin él lleva al
     * consolidado de la empresa.
     */
    almacen?: { id: number; clave: string; nombre: string } | null;
    etiqueta?: string;
    /** Botón discreto para el renglón de una tabla; suelto va normal. */
    compacto?: boolean;
};

/**
 * Lleva al inventario valuado de una bodega.
 *
 * Antes era un modal que dibujaba el reporte con datos de ejemplo. Con el kardex
 * en pie eso pasó de ser una maqueta útil a un número falso al lado de uno real,
 * así que ahora manda a Existencias ya filtrado. Cuando exista la plantilla en
 * `resources/views/pdf/alm/` este mismo botón puede volverse la descarga.
 */
export function BotonReporteExistencias({ almacen = null, etiqueta = 'Existencias', compacto = false }: Props) {
    const href = almacen
        ? `/admin/almacen/existencias?almacen_id=${almacen.id}`
        : '/admin/almacen/existencias';

    return (
        <Link
            href={href}
            className={compacto ? 'btn btn-ghost btn-xs' : 'btn btn-outline btn-sm'}
            title={almacen ? `Inventario valuado de ${almacen.clave}` : 'Inventario valuado de todos los almacenes'}
            // Vive dentro del enlace del renglón: sin esto la tabla navega a la
            // edición del almacén en lugar de abrir el inventario.
            onClick={(e) => e.stopPropagation()}
        >
            <PackageSearchIcon className={compacto ? 'size-3.5' : 'size-4'} />
            {!compacto && etiqueta}
        </Link>
    );
}
