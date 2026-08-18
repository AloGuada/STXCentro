import { CodigoBarras } from '@/components/alm/codigo-barras';
import { Button } from '@/components/ui/button';
import { FileTextIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    /** Folio del documento. Es lo que se imprime y lo que se escanea. */
    folio: string;
    /** Cómo se llama el impreso: la salida imprime un "vale", el resto su formato. */
    etiqueta?: string;
};

/**
 * El formato impreso de un movimiento.
 *
 * Toda hoja que sale del almacén se sella con su folio en código de barras. El
 * papel vuelve firmado —el vale con la firma de quien recibió, la transferencia
 * con la del destino— y entonces hay que encontrarlo en el sistema: escanearlo
 * es un tiro, teclear `TRA-2608-0007` es un dígito equivocado y un documento que
 * "no existe". Es el mismo Code 39 de las etiquetas de anaquel, así que lo lee
 * el lector que ya está en la caseta.
 *
 * Mientras el módulo sea maqueta el botón no descarga nada: enseña el sello que
 * va a llevar la hoja. El PDF necesita el controlador y la plantilla de
 * `resources/views/pdf/alm/`, que todavía no existen.
 */
export function BotonPdf({ folio, etiqueta = 'PDF' }: Props) {
    const [abierto, setAbierto] = useState(false);

    return (
        <>
            <button
                type="button"
                className="btn btn-ghost btn-xs"
                onClick={() => setAbierto(true)}
                title={`Ver el sello del ${etiqueta} de ${folio}`}
                aria-label={`${etiqueta} de ${folio}`}
            >
                <FileTextIcon className="size-3.5" />
                {etiqueta}
            </button>

            {abierto && (
                <dialog className="modal modal-open">
                    <div className="modal-box max-w-md">
                        <h3 className="text-lg font-bold">
                            {etiqueta} de <span className="font-mono">{folio}</span>
                        </h3>

                        <p className="text-base-content/60 mt-1 text-sm">
                            Así va sellada la hoja: el folio impreso como código de barras, para localizar el documento
                            escaneándolo cuando regrese firmado.
                        </p>

                        {/* Fondo blanco fijo: el lector necesita el contraste del
                            papel, y en tema oscuro un SVG transparente no se lee. */}
                        <div className="rounded-box mt-4 bg-white p-4">
                            <CodigoBarras valor={folio} altura={56} />
                        </div>

                        <div className="alert alert-warning mt-4">
                            <span>La maqueta todavía no genera el {etiqueta}; esto es sólo el sello.</span>
                        </div>

                        <div className="modal-action">
                            <Button type="button" variant="outline" onClick={() => setAbierto(false)}>
                                Cerrar
                            </Button>
                            <Button type="button" disabled title="Falta el controlador y la plantilla del PDF">
                                Descargar {etiqueta}
                            </Button>
                        </div>
                    </div>
                    <div className="modal-backdrop" onClick={() => setAbierto(false)}></div>
                </dialog>
            )}
        </>
    );
}
