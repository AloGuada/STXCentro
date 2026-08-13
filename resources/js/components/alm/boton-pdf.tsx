import { FileTextIcon } from 'lucide-react';

type Props = {
    /** Folio del documento; sólo se usa para el tooltip y el aria-label. */
    folio: string;
    /** Cómo se llama el impreso: la salida imprime un "vale", el resto su formato. */
    etiqueta?: string;
};

/**
 * Imprime el formato del documento. Deshabilitado mientras el módulo sea
 * maqueta: el PDF necesita el controlador y la plantilla de
 * `resources/views/pdf/alm/`, que todavía no existen.
 */
export function BotonPdf({ folio, etiqueta = 'PDF' }: Props) {
    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs"
            disabled
            title={`La maqueta todavía no genera el ${etiqueta} de ${folio}`}
            aria-label={`${etiqueta} de ${folio}`}
        >
            <FileTextIcon className="size-3.5" />
            {etiqueta}
        </button>
    );
}
