import { router } from '@inertiajs/react';
import { FileTextIcon, PlusIcon, XIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { CostosRequisicion } from '@/types/models';

/**
 * Documentos (PDF) adjuntos a la requisición como respaldo de la cotización.
 * Se muestra en el tab de Resumen para que quien aprueba pueda consultarlos sin
 * tener el permiso de cotizar; la carga y el borrado sólo con `editable`.
 */
export function DocumentosCotizacion({
    requisicion,
    editable,
}: {
    requisicion: CostosRequisicion;
    editable: boolean;
}) {
    const [archivo, setArchivo] = useState<File | null>(null);
    const [titulo, setTitulo] = useState('');
    const [subiendo, setSubiendo] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const documentos = requisicion.media ?? [];

    const subir = () => {
        if (!archivo) {
            return;
        }
        setSubiendo(true);
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/documentos`,
            { documento: archivo, titulo },
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    setArchivo(null);
                    setTitulo('');
                    if (inputRef.current) {
                        inputRef.current.value = '';
                    }
                },
                onFinish: () => setSubiendo(false),
            },
        );
    };

    const eliminar = (id: number) => {
        if (confirm('¿Eliminar este documento?')) {
            router.delete(
                `/admin/costos/requisiciones/${requisicion.id}/documentos/${id}`,
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <h3 className="mb-3 text-xs tracking-wider text-base-content/60 uppercase">
                Documentos de cotización (PDF) · información extra
            </h3>

            {documentos.length === 0 ? (
                <p className="mb-3 text-sm text-base-content/50">
                    Aún no hay documentos adjuntos.
                </p>
            ) : (
                <div className="mb-3 space-y-2">
                    {documentos.map((doc) => (
                        <div
                            key={doc.id}
                            className="flex items-center gap-2 rounded border border-base-300 bg-base-200 p-2 text-sm"
                        >
                            <FileTextIcon className="size-4 text-base-content/60" />
                            <a
                                href={`/storage/${doc.path}`}
                                target="_blank"
                                rel="noreferrer"
                                className="font-medium hover:underline"
                            >
                                {doc.descripcion || doc.nombre_original}
                            </a>
                            {editable && (
                                <button
                                    type="button"
                                    className="btn ml-auto text-error btn-ghost btn-xs"
                                    title="Eliminar"
                                    onClick={() => eliminar(doc.id)}
                                >
                                    <XIcon className="size-3" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {editable && (
                <div className="flex flex-wrap items-end gap-2">
                    <input
                        type="text"
                        className="input-bordered input input-sm w-56"
                        placeholder="Título (opcional)"
                        value={titulo}
                        onChange={(e) => setTitulo(e.target.value)}
                    />
                    <input
                        ref={inputRef}
                        type="file"
                        accept="application/pdf"
                        className="file-input-bordered file-input w-64 file-input-sm"
                        onChange={(e) => setArchivo(e.target.files?.[0] ?? null)}
                    />
                    <Button onClick={subir} disabled={!archivo || subiendo}>
                        <PlusIcon className="size-3" /> Agregar documento
                    </Button>
                </div>
            )}
        </div>
    );
}
