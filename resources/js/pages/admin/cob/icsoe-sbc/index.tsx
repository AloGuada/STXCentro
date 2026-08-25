import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobIcsoeSbcAnio } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'SBC por año', href: '/admin/cob/icsoe-sbc' },
];

type Props = {
    anios: CobIcsoeSbcAnio[];
};

/** Celda editable que guarda al salir del campo. */
function CeldaNumerica({
    valor,
    step,
    onGuardar,
}: {
    valor: string;
    step: string;
    onGuardar: (nuevo: string) => void;
}) {
    const [local, setLocal] = useState(valor);

    return (
        <Input
            type="number"
            step={step}
            min="0"
            className="input-sm text-right"
            value={local}
            onChange={(e) => setLocal(e.target.value)}
            onBlur={() => {
                if (local !== valor) {
                    onGuardar(local);
                }
            }}
        />
    );
}

export default function IcsoeSbcIndex({ anios }: Props) {
    const proximoAnio = anios.length > 0 ? Math.max(...anios.map((a) => a.anio)) + 1 : new Date().getFullYear();

    const form = useForm({
        anio: String(proximoAnio),
        sbc: '',
        costo_m2: '1154',
        prima_riesgo: '7.58875',
        notas: '',
    });

    const agregar = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/cob/icsoe-sbc', {
            preserveScroll: true,
            onSuccess: () => form.reset('sbc', 'notas'),
        });
    };

    const guardar = (anio: CobIcsoeSbcAnio, campos: Partial<Record<string, string>>) => {
        router.put(
            `/admin/cob/icsoe-sbc/${anio.id}`,
            {
                anio: String(anio.anio),
                sbc: anio.sbc,
                costo_m2: anio.costo_m2,
                prima_riesgo: anio.prima_riesgo,
                notas: anio.notas ?? '',
                ...campos,
            },
            { preserveScroll: true },
        );
    };

    const eliminar = (anio: CobIcsoeSbcAnio) => {
        if (!window.confirm(`¿Eliminar el año ${anio.anio} del catálogo?`)) return;
        router.delete(`/admin/cob/icsoe-sbc/${anio.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SBC por año" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Parámetros del IMSS por año</h1>
                    <p className="text-sm text-base-content/60">
                        Salario base de cotización diario, costo de construcción por m² del DOF y prima de riesgo. Los seguimientos ICSOE
                        guardan el valor con el que se calcularon, así que cambiar un año aquí no los altera hasta que se recalculen.
                    </p>
                </div>

                <form onSubmit={agregar} className="flex flex-wrap items-end gap-3 rounded-box border border-base-300 p-4">
                    <div className="w-28">
                        <label className="mb-1 block text-sm font-medium">Año</label>
                        <Input type="number" value={form.data.anio} onChange={(e) => form.setData('anio', e.target.value)} />
                        {form.errors.anio && <p className="mt-1 text-sm text-error">{form.errors.anio}</p>}
                    </div>
                    <div className="w-36">
                        <label className="mb-1 block text-sm font-medium">SBC diario</label>
                        <Input type="number" step="0.01" value={form.data.sbc} onChange={(e) => form.setData('sbc', e.target.value)} />
                        {form.errors.sbc && <p className="mt-1 text-sm text-error">{form.errors.sbc}</p>}
                    </div>
                    <div className="w-36">
                        <label className="mb-1 block text-sm font-medium">Costo DOF $/m²</label>
                        <Input type="number" step="0.01" value={form.data.costo_m2} onChange={(e) => form.setData('costo_m2', e.target.value)} />
                    </div>
                    <div className="w-36">
                        <label className="mb-1 block text-sm font-medium">Prima de riesgo %</label>
                        <Input
                            type="number"
                            step="0.00001"
                            value={form.data.prima_riesgo}
                            onChange={(e) => form.setData('prima_riesgo', e.target.value)}
                        />
                    </div>
                    <div className="min-w-48 flex-1">
                        <label className="mb-1 block text-sm font-medium">Notas</label>
                        <Input value={form.data.notas} onChange={(e) => form.setData('notas', e.target.value)} placeholder="Referencia del DOF" />
                    </div>
                    <Button type="submit" disabled={form.processing || !form.data.sbc}>
                        {form.processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                        Agregar año
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-box border border-base-300">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th className="w-24">Año</th>
                                <th className="w-40 text-right">SBC diario</th>
                                <th className="w-40 text-right">Costo DOF $/m²</th>
                                <th className="w-40 text-right">Prima de riesgo %</th>
                                <th>Notas</th>
                                <th className="w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {anios.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-8 text-center text-base-content/60">
                                        Sin años capturados. El ICSOE no puede calcularse hasta que haya al menos uno.
                                    </td>
                                </tr>
                            ) : (
                                anios.map((anio) => (
                                    <tr key={anio.id} className="hover">
                                        <td className="font-medium">{anio.anio}</td>
                                        <td>
                                            <CeldaNumerica valor={anio.sbc} step="0.01" onGuardar={(sbc) => guardar(anio, { sbc })} />
                                        </td>
                                        <td>
                                            <CeldaNumerica valor={anio.costo_m2} step="0.01" onGuardar={(costo_m2) => guardar(anio, { costo_m2 })} />
                                        </td>
                                        <td>
                                            <CeldaNumerica
                                                valor={anio.prima_riesgo}
                                                step="0.00001"
                                                onGuardar={(prima_riesgo) => guardar(anio, { prima_riesgo })}
                                            />
                                        </td>
                                        <td className="text-sm text-base-content/60">{anio.notas}</td>
                                        <td>
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-xs text-error"
                                                title="Eliminar"
                                                onClick={() => eliminar(anio)}
                                            >
                                                <Trash2Icon className="size-3.5" />
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
