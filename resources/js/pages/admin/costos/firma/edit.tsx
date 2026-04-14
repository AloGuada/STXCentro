import { SignaturePad, type SignaturePadRef } from '@/components/sti/signature-pad';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Trash2Icon } from 'lucide-react';
import { useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Mi Firma', href: '/admin/costos/firma' },
];

type Props = {
    firmaUrl: string | null;
};

export default function FirmaEdit({ firmaUrl }: Props) {
    const { flash } = usePage<{ flash: { success?: string; warning?: string } }>().props;
    const padRef = useRef<SignaturePadRef>(null);
    const [signature, setSignature] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const handleSave = () => {
        if (!signature) return;
        setProcessing(true);
        router.post('/admin/costos/firma', { firma: signature }, {
            onFinish: () => {
                setProcessing(false);
                setSignature(null);
            },
        });
    };

    const handleDelete = () => {
        if (!confirm('¿Eliminar su firma?')) return;
        setProcessing(true);
        router.delete('/admin/costos/firma', {
            onFinish: () => setProcessing(false),
        });
    };

    const handleClear = () => {
        padRef.current?.clear();
        setSignature(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configurar Firma" />

            <div className="p-6">
                <div className="mx-auto max-w-lg">
                    <h1 className="mb-2 text-2xl font-semibold">Mi Firma</h1>
                    <p className="mb-6 text-sm text-base-content/60">
                        Dibuje su firma digital. Es obligatoria para poder aprobar o rechazar solicitudes de pago.
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

                    <div className="rounded-lg border border-base-300 p-6">
                        {firmaUrl && !signature && (
                            <div className="mb-6 space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Firma actual</span>
                                    <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">
                                        Configurada
                                    </span>
                                </div>
                                <div className="flex items-center justify-center rounded-lg border border-dashed border-base-300 bg-white p-6">
                                    <img src={firmaUrl} alt="Firma actual" className="max-h-24 object-contain" />
                                </div>
                            </div>
                        )}

                        <div className="space-y-3">
                            <span className="text-sm font-medium">
                                {firmaUrl ? 'Dibujar nueva firma' : 'Dibuje su firma'}
                            </span>
                            <SignaturePad
                                ref={padRef}
                                value={null}
                                onChange={setSignature}
                                width={450}
                                height={200}
                            />
                        </div>

                        <div className="mt-6 flex items-center gap-3">
                            <Button type="button" onClick={handleSave} disabled={processing || !signature}>
                                Guardar firma
                            </Button>
                            <Button type="button" variant="outline" onClick={handleClear} disabled={processing}>
                                Limpiar
                            </Button>
                            {firmaUrl && (
                                <Button type="button" variant="destructive" onClick={handleDelete} disabled={processing}>
                                    <Trash2Icon className="size-4" />
                                    Eliminar firma
                                </Button>
                            )}
                        </div>

                        <p className="mt-4 text-[11px] text-base-content/50">
                            Dibuje su firma en el recuadro. Use el botón de borrador para limpiar y volver a intentar.
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
