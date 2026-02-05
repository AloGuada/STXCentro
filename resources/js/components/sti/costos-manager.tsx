import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { StiCostoMantenimiento } from '@/types/models';
import { router } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';

type CostosManagerProps = {
    costos: StiCostoMantenimiento[];
    storeUrl: string;
    destroyUrlPrefix: string;
    readOnly?: boolean;
};

export function CostosManager({ costos, storeUrl, destroyUrlPrefix, readOnly = false }: CostosManagerProps) {
    const [descripcion, setDescripcion] = useState('');
    const [cantidad, setCantidad] = useState('');
    const [adding, setAdding] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const total = costos.reduce((sum, c) => sum + c.cantidad, 0);

    const handleAdd = () => {
        if (!descripcion.trim() || !cantidad) return;

        setAdding(true);
        router.post(
            storeUrl,
            { descripcion, cantidad: parseFloat(cantidad) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDescripcion('');
                    setCantidad('');
                },
                onFinish: () => setAdding(false),
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
        <div className="space-y-3">
            {costos.length > 0 && (
                <div className="rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">Descripcion</th>
                                <th className="px-3 py-2 text-right font-medium">Cantidad</th>
                                {!readOnly && <th className="w-12 px-3 py-2"></th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {costos.map((costo) => (
                                <tr key={costo.id}>
                                    <td className="px-3 py-2">{costo.descripcion}</td>
                                    <td className="px-3 py-2 text-right font-mono">
                                        ${costo.cantidad.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                    </td>
                                    {!readOnly && (
                                        <td className="px-3 py-2 text-center">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDelete(costo.id)}
                                                disabled={deletingId === costo.id}
                                            >
                                                {deletingId === costo.id ? (
                                                    <Loader2Icon className="size-4 animate-spin" />
                                                ) : (
                                                    <Trash2Icon className="size-4 text-error" />
                                                )}
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <td className="px-3 py-2 font-medium">Total</td>
                                <td className="px-3 py-2 text-right font-mono font-bold">
                                    ${total.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                </td>
                                {!readOnly && <td></td>}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}

            {!readOnly && (
                <div className="flex gap-2">
                    <Input
                        placeholder="Descripcion del costo"
                        value={descripcion}
                        onChange={(e) => setDescripcion(e.target.value)}
                        className="flex-1"
                    />
                    <Input
                        type="number"
                        placeholder="Cantidad"
                        value={cantidad}
                        onChange={(e) => setCantidad(e.target.value)}
                        className="w-32"
                        min="0"
                        step="0.01"
                    />
                    <Button type="button" onClick={handleAdd} disabled={adding || !descripcion.trim() || !cantidad}>
                        {adding ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                        Agregar
                    </Button>
                </div>
            )}

            {costos.length === 0 && readOnly && (
                <p className="text-sm text-gray-500">No hay costos registrados.</p>
            )}
        </div>
    );
}
