import { Download, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type DocumentoVisor = {
    titulo: string;
    url: string;
    nombre?: string | null;
};

/**
 * Visor de un adjunto del proveedor. Los archivos se sirven desde
 * `portal.media.show`, que los manda en línea cuando el navegador puede
 * mostrarlos (PDF e imagen) y como descarga en cualquier otro caso.
 */
export function VisorDocumentoModal({ doc, onClose }: { doc: DocumentoVisor; onClose: () => void }) {
    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-4xl">
                <h3 className="text-lg font-bold">{doc.titulo}</h3>
                {doc.nombre && <p className="mt-1 truncate text-sm text-base-content/60">{doc.nombre}</p>}

                <iframe src={doc.url} title={doc.titulo} className="mt-4 h-[60vh] w-full rounded-lg border border-base-300" />

                <div className="modal-action">
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Cerrar
                    </Button>
                    <a href={doc.url} target="_blank" rel="noopener noreferrer" className="btn btn-outline btn-sm gap-1">
                        <ExternalLink className="size-4" /> Abrir en pestaña
                    </a>
                    <a href={`${doc.url}?download=1`} className="btn btn-primary btn-sm gap-1">
                        <Download className="size-4" /> Descargar
                    </a>
                </div>
            </div>
            <div className="modal-backdrop" onClick={onClose}></div>
        </dialog>
    );
}
