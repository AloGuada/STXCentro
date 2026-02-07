import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { StiCheckEjecucion } from '@/types/models';
import { router } from '@inertiajs/react';
import { CheckCircleIcon, CircleIcon, Loader2Icon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    mantenimientoId: number;
    checkEjecuciones: StiCheckEjecucion[];
    readonly?: boolean;
};

export function CheckEjecucionList({ mantenimientoId, checkEjecuciones, readonly = false }: Props) {
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [observaciones, setObservaciones] = useState<Record<number, string>>({});

    const handleToggle = (checkEjecucion: StiCheckEjecucion) => {
        setProcessingId(checkEjecucion.id);
        router.post(
            `/admin/sti/mantenimientos/${mantenimientoId}/checks/${checkEjecucion.id}/toggle`,
            {
                resultado: !checkEjecucion.resultado,
                observaciones: observaciones[checkEjecucion.id] ?? checkEjecucion.observaciones,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessingId(null),
            },
        );
    };

    const completados = checkEjecuciones.filter((ce) => ce.resultado).length;
    const total = checkEjecuciones.length;
    const porcentaje = total > 0 ? Math.round((completados / total) * 100) : 0;

    if (checkEjecuciones.length === 0) {
        return <p className="py-4 text-center text-sm text-gray-500">Este mantenimiento no tiene checklist asociado.</p>;
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="font-medium">Checklist de Verificacion</h3>
                <span className="text-sm text-gray-500">
                    {completados} / {total} ({porcentaje}%)
                </span>
            </div>

            <div className="h-2 overflow-hidden rounded-full bg-gray-200">
                <div
                    className={`h-full transition-all ${porcentaje === 100 ? 'bg-green-500' : 'bg-blue-500'}`}
                    style={{ width: `${porcentaje}%` }}
                />
            </div>

            <div className="divide-y rounded-lg border">
                {checkEjecuciones.map((ce, index) => (
                    <div key={ce.id} className="p-3">
                        <div className="flex items-start gap-3">
                            {readonly ? (
                                ce.resultado ? (
                                    <CheckCircleIcon className="mt-0.5 size-5 shrink-0 text-green-500" />
                                ) : (
                                    <CircleIcon className="mt-0.5 size-5 shrink-0 text-gray-300" />
                                )
                            ) : (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mt-0.5 size-6 shrink-0"
                                    onClick={() => handleToggle(ce)}
                                    disabled={processingId === ce.id}
                                >
                                    {processingId === ce.id ? (
                                        <Loader2Icon className="size-5 animate-spin" />
                                    ) : ce.resultado ? (
                                        <CheckCircleIcon className="size-5 text-green-500" />
                                    ) : (
                                        <CircleIcon className="size-5 text-gray-300 hover:text-gray-400" />
                                    )}
                                </Button>
                            )}

                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-gray-500">{index + 1}.</span>
                                    <span className={ce.resultado ? 'text-gray-500 line-through' : ''}>
                                        {ce.check?.descripcion ?? 'Check'}
                                    </span>
                                </div>

                                {!readonly && (
                                    <Input
                                        className="mt-2"
                                        placeholder="Observaciones (opcional)"
                                        value={observaciones[ce.id] ?? ce.observaciones ?? ''}
                                        onChange={(e) => setObservaciones({ ...observaciones, [ce.id]: e.target.value })}
                                        onBlur={() => {
                                            const newObs = observaciones[ce.id];
                                            if (newObs !== undefined && newObs !== ce.observaciones) {
                                                router.post(
                                                    `/admin/sti/mantenimientos/${mantenimientoId}/checks/${ce.id}/toggle`,
                                                    {
                                                        resultado: ce.resultado,
                                                        observaciones: newObs,
                                                    },
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    />
                                )}

                                {readonly && ce.observaciones && (
                                    <p className="mt-1 text-sm text-gray-500">Observaciones: {ce.observaciones}</p>
                                )}

                                {ce.tecnico && ce.resultado && (
                                    <p className="mt-1 text-xs text-gray-400">Completado por: {ce.tecnico.descripcion}</p>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
