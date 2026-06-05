import { EyeIcon, FileTextIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export type DocumentoActual = {
    url: string;
    nombre: string | null;
    mime: string;
};

type Visor = {
    url: string;
    titulo: string;
    esImagen: boolean;
};

type Props = {
    id: string;
    actual?: DocumentoActual | null;
    archivo: File | null;
    onChange: (file: File | null) => void;
};

export function DocumentoField({ id, actual = null, archivo, onChange }: Props) {
    const [visor, setVisor] = useState<Visor | null>(null);

    const previewUrl = useMemo(() => (archivo ? URL.createObjectURL(archivo) : null), [archivo]);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const documento =
        archivo && previewUrl
            ? { url: previewUrl, nombre: archivo.name, mime: archivo.type, nuevo: true }
            : actual
              ? { url: actual.url, nombre: actual.nombre ?? 'Documento', mime: actual.mime, nuevo: false }
              : null;

    return (
        <>
            <input
                id={id}
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                className="file-input file-input-bordered w-full"
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            />
            {documento ? (
                <div className="mt-2 flex items-center justify-between gap-2 rounded bg-base-200 p-2">
                    <div className="flex min-w-0 items-center gap-2">
                        <FileTextIcon className="size-4 shrink-0 text-base-content/60" />
                        <span className="truncate text-sm">{documento.nombre}</span>
                        {documento.nuevo ? (
                            <span className="badge badge-info badge-sm shrink-0">Nuevo</span>
                        ) : (
                            <span className="shrink-0 text-xs text-base-content/60">(actual, deja vacío para conservarlo)</span>
                        )}
                    </div>
                    <button
                        type="button"
                        className="btn btn-ghost btn-sm shrink-0"
                        onClick={() => setVisor({ url: documento.url, titulo: documento.nombre, esImagen: documento.mime.startsWith('image/') })}
                    >
                        <EyeIcon className="size-4" />
                        Ver
                    </button>
                </div>
            ) : (
                <span className="mt-1 text-xs text-base-content/60">Sin archivo.</span>
            )}
            {visor && <DocumentoModal visor={visor} onClose={() => setVisor(null)} />}
        </>
    );
}

function DocumentoModal({ visor, onClose }: { visor: Visor; onClose: () => void }) {
    return (
        <dialog className="modal modal-open">
            <div className="modal-box flex h-[85vh] max-w-4xl flex-col p-0">
                <div className="flex items-center justify-between border-b border-base-300 px-4 py-3">
                    <h3 className="text-sm font-medium">{visor.titulo}</h3>
                    <button type="button" className="btn btn-sm btn-ghost" onClick={onClose}>
                        ✕
                    </button>
                </div>
                {visor.esImagen ? (
                    <div className="flex flex-1 items-center justify-center overflow-auto bg-base-200 p-4">
                        <img src={visor.url} alt={visor.titulo} className="max-h-full max-w-full object-contain" />
                    </div>
                ) : (
                    <iframe src={visor.url} className="w-full flex-1" title={visor.titulo} />
                )}
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}
