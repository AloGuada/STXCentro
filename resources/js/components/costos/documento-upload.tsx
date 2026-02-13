import { Button } from '@/components/ui/button';
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

    const documentoArchivos = archivos.filter((a) => a.archivo_id === documento.id);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }

        setUploading(true);
        router.post(
            storeUrl,
            { archivo: file, archivo_id: documento.id },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploading(false);
                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
            },
        );
    };

    const handleDelete = (id: number) => {
        setDeletingId(id);
        router.delete(`${destroyUrlPrefix}/${id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    const canUpload = documento.multiple || documentoArchivos.length === 0;

    return (
        <div className="rounded-lg border border-base-300 p-4">
            <div className="flex items-center justify-between mb-3">
                <div>
                    <h4 className="font-medium">{documento.titulo}</h4>
                    {documento.texto && <p className="text-xs text-base-content/60">{documento.texto}</p>}
                </div>
                {!readOnly && canUpload && (
                    <div>
                        <input
                            ref={fileInputRef}
                            type="file"
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

            {documentoArchivos.length > 0 ? (
                <div className="space-y-2">
                    {documentoArchivos.map((archivo) => (
                        <div key={archivo.id} className="flex items-center justify-between rounded bg-base-200 p-2">
                            <div className="flex items-center gap-2">
                                <FileTextIcon className="size-4 text-base-content/60" />
                                <a
                                    href={`/storage/${archivo.ruta_archivo}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm hover:underline"
                                >
                                    {archivo.nombre_original}
                                </a>
                            </div>
                            {!readOnly && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-error"
                                    onClick={() => handleDelete(archivo.id)}
                                    disabled={deletingId === archivo.id}
                                >
                                    {deletingId === archivo.id ? (
                                        <Loader2Icon className="size-4 animate-spin" />
                                    ) : (
                                        <Trash2Icon className="size-4" />
                                    )}
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-base-content/60">Sin archivos.</p>
            )}
        </div>
    );
}
