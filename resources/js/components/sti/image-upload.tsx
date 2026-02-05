import { Button } from '@/components/ui/button';
import type { Media } from '@/types/models';
import { router } from '@inertiajs/react';
import { ImageIcon, Loader2Icon, Trash2Icon, UploadIcon } from 'lucide-react';
import { useRef, useState } from 'react';

type ImageUploadProps = {
    media: Media[];
    storeUrl: string;
    destroyUrlPrefix: string;
    readOnly?: boolean;
};

export function ImageUpload({ media, storeUrl, destroyUrlPrefix, readOnly = false }: ImageUploadProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);
        router.post(
            storeUrl,
            { archivo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploading(false);
                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
            }
        );
    };

    const handleDelete = (id: number) => {
        setDeletingId(id);
        router.delete(`${destroyUrlPrefix}/${id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <div className="space-y-4">
            {media.length > 0 && (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                    {media.map((item) => (
                        <div key={item.id} className="group relative overflow-hidden rounded-lg border">
                            {item.mime.startsWith('image/') ? (
                                <img
                                    src={`/storage/${item.path}`}
                                    alt={item.descripcion}
                                    className="aspect-square w-full object-cover"
                                />
                            ) : (
                                <div className="flex aspect-square items-center justify-center bg-gray-100 dark:bg-gray-800">
                                    <ImageIcon className="size-12 text-gray-400" />
                                </div>
                            )}
                            <div className="absolute inset-x-0 bottom-0 bg-black/50 p-2">
                                <p className="truncate text-xs text-white">{item.descripcion}</p>
                            </div>
                            {!readOnly && (
                                <Button
                                    type="button"
                                    variant="error"
                                    size="sm"
                                    className="absolute right-2 top-2 opacity-0 transition-opacity group-hover:opacity-100"
                                    onClick={() => handleDelete(item.id)}
                                    disabled={deletingId === item.id}
                                >
                                    {deletingId === item.id ? (
                                        <Loader2Icon className="size-4 animate-spin" />
                                    ) : (
                                        <Trash2Icon className="size-4" />
                                    )}
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {!readOnly && (
                <div>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*"
                        onChange={handleFileChange}
                        className="hidden"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={uploading}
                    >
                        {uploading ? (
                            <Loader2Icon className="size-4 animate-spin" />
                        ) : (
                            <UploadIcon className="size-4" />
                        )}
                        Subir imagen
                    </Button>
                </div>
            )}

            {media.length === 0 && readOnly && (
                <p className="text-sm text-gray-500">No hay imagenes registradas.</p>
            )}
        </div>
    );
}
