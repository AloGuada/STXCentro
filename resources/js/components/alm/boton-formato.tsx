import { PrinterIcon } from 'lucide-react';
import type { MouseEvent } from 'react';

type Props = {
    /** URL del PDF. */
    href: string;
    /** Texto y rótulo del botón. */
    etiqueta?: string;
    /** En el listado sólo cabe el ícono; en la ficha va con su texto. */
    soloIcono?: boolean;
    className?: string;
};

/**
 * Abre el formato impreso de un movimiento.
 *
 * Se abre en pestaña nueva y no navega: la hoja se manda a la impresora y el
 * almacenista se queda donde estaba. Y va como `window.open` en vez de `<a>`
 * porque en el listado cada celda vive dentro del `Link` del renglón —un ancla
 * dentro de otra ancla no es HTML válido y el clic terminaría abriendo la
 * ficha.
 *
 * No es el `BotonPdf` de las maquetas: aquél enseña el sello y no descarga
 * nada, éste pide el PDF de verdad.
 */
export function BotonFormato({ href, etiqueta = 'PDF', soloIcono = false, className }: Props) {
    const abrir = (e: MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();
        e.stopPropagation();
        window.open(href, '_blank', 'noopener');
    };

    return (
        <button
            type="button"
            className={className ?? (soloIcono ? 'btn btn-ghost btn-xs' : 'btn btn-outline')}
            onClick={abrir}
            title={`Imprimir ${etiqueta}`}
            aria-label={`Imprimir ${etiqueta}`}
        >
            <PrinterIcon className={soloIcono ? 'size-3.5' : 'size-4'} />
            {!soloIcono && etiqueta}
        </button>
    );
}
