import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    formatBytes,
    MAX_FILE_SIZE_BYTES,
    MAX_FILE_SIZE_MB,
} from '@/lib/uploads';
import type { CostosDocumento, CostosSolicitudArchivo } from '@/types/models';
import { router } from '@inertiajs/react';
import { FileTextIcon, Loader2Icon, Trash2Icon, UploadIcon } from 'lucide-react';
import { useRef, useState } from 'react';

type Props = {
    documento: CostosDocumento;
    archivos: CostosSolicitudArchivo[];
    storeUrl: string;
    destroyUrlPrefix: string;
    readOnly?: boolean;
};

export function DocumentoUpload({ documento, archivos, storeUrl, destroyUrlPrefix, readOnly = false }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [sizeError, setSizeError] = useState<string | null>(null);

    const documentoArchivos = archivos.filter((a) => a.archivo_id === documento.id);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (!files || files.length === 0) {
            return;
        }

        const seleccionados = Array.from(files);
        const grandes = seleccionados.filter((f) => f.size > MAX_FILE_SIZE_BYTES);
        const validos = seleccionados.filter((f) => f.size <= MAX_FILE_SIZE_BYTES);

        if (grandes.length > 0) {
            const nombres = grandes
                .map((f) => `"${f.name}" (${formatBytes(f.size)})`)
                .join(', ');
            setSizeError(
                `${nombres} supera${grandes.length > 1 ? 'n' : ''} el máximo de ${MAX_FILE_SIZE_MB} MB por archivo y no se subió.`,
            );
        } else {
            setSizeError(null);
        }

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }

        if (validos.length === 0) {
            return;
        }

        setUploading(true);
        let pending = validos.length;

        validos.forEach((file) => {
            router.post(
                storeUrl,
                { archivo: file, archivo_id: documento.id },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onFinish: () => {
                        pending--;
                        if (pending === 0) {
                            setUploading(false);
                        }
                    },
                },
            );
        });
    };

    const handleDelete = (id: number) => {
        setDeletingId(id);
        router.delete(`${destroyUrlPrefix}/${id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    const handleUpdateTexto = (archivo: CostosSolicitudArchivo, value: string) => {
        router.patch(
            `${destroyUrlPrefix}/${archivo.id}`,
            { texto_adicional: value },
            { preserveScroll: true },
        );
    };

    const canUpload = documento.multiple || documentoArchivos.length === 0;

    return (
        <div className="rounded-lg border border-base-300 p-4">
            <div className="flex items-center justify-between mb-3">
                <div>
                    <h4 className="font-medium">{documento.titulo}</h4>
                    {documento.texto && <p className="text-xs text-base-content/60">{documento.texto}</p>}
                    <p className="text-xs text-base-content/50">Máx. {MAX_FILE_SIZE_MB} MB por archivo</p>
                </div>
                {!readOnly && canUpload && (
                    <div>
                        <input
                            ref={fileInputRef}
                            type="file"
                            multiple={documento.multiple}
                            onChange={handleFileChange}
                            className="hidden"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={uploading}
                        >
                            {uploading ? (
                                <Loader2Icon className="size-4 animate-spin" />
                            ) : (
                                <UploadIcon className="size-4" />
                            )}
                            Subir
                        </Button>
                    </div>
                )}
            </div>

            {sizeError && <p className="mb-2 text-xs text-error">{sizeError}</p>}

            {documentoArchivos.length > 0 ? (
                <div className="space-y-2">
                    {documentoArchivos.map((archivo) => (
                        <ArchivoRow
                            key={archivo.id}
                            archivo={archivo}
                            documento={documento}
                            readOnly={readOnly}
                            deleting={deletingId === archivo.id}
                            onDelete={() => handleDelete(archivo.id)}
                            onUpdateTexto={(value) => handleUpdateTexto(archivo, value)}
                        />
                    ))}
                </div>
            ) : (
                <p className="text-sm text-base-content/60">Sin archivos.</p>
            )}
        </div>
    );
}

function ArchivoRow({
    archivo,
    documento,
    readOnly,
    deleting,
    onDelete,
    onUpdateTexto,
}: {
    archivo: CostosSolicitudArchivo;
    documento: CostosDocumento;
    readOnly: boolean;
    deleting: boolean;
    onDelete: () => void;
    onUpdateTexto: (value: string) => void;
}) {
    const [texto, setTexto] = useState(archivo.texto_adicional ?? '');

    return (
        <div className="rounded bg-base-200 p-2 space-y-2">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <FileTextIcon className="size-4 text-base-content/60" />
                    <a
                        href={`/storage/${archivo.media?.path}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm hover:underline"
                    >
                        {archivo.media?.nombre_original ?? 'Archivo'}
                    </a>
                </div>
                {!readOnly && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-error"
                        onClick={onDelete}
                        disabled={deleting}
                    >
                        {deleting ? (
                            <Loader2Icon className="size-4 animate-spin" />
                        ) : (
                            <Trash2Icon className="size-4" />
                        )}
                    </Button>
                )}
            </div>
            {documento.texto_adicional && documento.texto && (
                readOnly ? (
                    archivo.texto_adicional && (
                        <p className="text-xs text-base-content/70 px-1">
                            <span className="font-medium">{documento.texto}:</span> {archivo.texto_adicional}
                        </p>
                    )
                ) : (
                    <Input
                        placeholder={documento.texto}
                        value={texto}
                        onChange={(e) => setTexto(e.target.value)}
                        onBlur={() => {
                            if (texto !== (archivo.texto_adicional ?? '')) {
                                onUpdateTexto(texto);
                            }
                        }}
                    />
                )
            )}
        </div>
    );
}
