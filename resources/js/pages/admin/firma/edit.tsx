import { Head, router, usePage } from '@inertiajs/react';
import { Trash2Icon } from 'lucide-react';
import { useRef, useState } from 'react';
import { SignaturePad, type SignaturePadRef } from '@/components/sti/signature-pad';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Mi firma', href: '/admin/mi-firma' },
];

type Props = {
    firmaUrl: string | null;
};

/**
 * La rúbrica del usuario. Es una sola para todo el sistema: la estampan las
 * aprobaciones de Costos y los formatos de Calidad que firma.
 */
export default function MiFirma({ firmaUrl }: Props) {
    const { flash } = usePage<{ flash: { success?: string; warning?: string } }>().props;
    const padRef = useRef<SignaturePadRef>(null);
    const [firma, setFirma] = useState<string | null>(null);
    const [procesando, setProcesando] = useState(false);

    const guardar = () => {
        if (!firma) {
            return;
        }

        setProcesando(true);
        router.post(
            '/admin/mi-firma',
            { firma },
            {
                onFinish: () => {
                    setProcesando(false);
                    setFirma(null);
                },
            },
        );
    };

    const eliminar = () => {
        if (!confirm('¿Eliminar tu firma?')) {
            return;
        }

        setProcesando(true);
        router.delete('/admin/mi-firma', { onFinish: () => setProcesando(false) });
    };

    const limpiar = () => {
        padRef.current?.clear();
        setFirma(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi firma" />

            <div className="p-6">
                <div className="mx-auto max-w-lg">
                    <h1 className="mb-2 text-2xl font-semibold">Mi firma</h1>
                    <p className="text-base-content/60 mb-6 text-sm">
                        Dibuja tu firma una vez. Se estampa sobre la raya en los documentos que firmas: aprobaciones de
                        Costos y formatos de Calidad. Sin ella, tu lugar sale en blanco para firmarse a mano.
                    </p>

                    {flash?.warning && (
                        <div className="alert alert-warning mb-6">
                            <span>{flash.warning}</span>
                        </div>
                    )}

                    {flash?.success && (
                        <div className="alert alert-success mb-6">
                            <span>{flash.success}</span>
                        </div>
                    )}

                    <div className="border-base-300 rounded-lg border p-6">
                        {firmaUrl && !firma && (
                            <div className="mb-6 space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Firma actual</span>
                                    <span className="badge badge-sm badge-success">configurada</span>
                                </div>
                                <div className="border-base-300 flex items-center justify-center rounded-lg border border-dashed bg-white p-6">
                                    <img src={firmaUrl} alt="Firma actual" className="max-h-24 object-contain" />
                                </div>
                            </div>
                        )}

                        <div className="space-y-3">
                            <span className="text-sm font-medium">{firmaUrl ? 'Dibujar una nueva' : 'Dibuja tu firma'}</span>
                            <SignaturePad ref={padRef} value={null} onChange={setFirma} width={450} height={200} />
                        </div>

                        <div className="mt-6 flex flex-wrap items-center gap-3">
                            <Button type="button" onClick={guardar} disabled={procesando || !firma}>
                                Guardar firma
                            </Button>
                            <Button type="button" variant="outline" onClick={limpiar} disabled={procesando}>
                                Limpiar
                            </Button>
                            {firmaUrl && (
                                <Button type="button" variant="destructive" onClick={eliminar} disabled={procesando}>
                                    <Trash2Icon className="size-4" />
                                    Eliminar firma
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
