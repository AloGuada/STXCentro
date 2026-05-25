import { RubroSelector } from '@/components/costos/rubro-selector';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, ObraRubroOption } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';

type Detalle = {
    descripcion: string;
    unidad: string;
    cantidad: number;
    obra_rubro_id: number | '';
    notas: string;
};

type FormData = {
    departamento_id: number | '';
    justificacion: string;
    detalles: Detalle[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/requisiciones' },
    { title: 'Requisiciones', href: '/admin/costos/requisiciones' },
    { title: 'Nueva', href: '/admin/costos/requisiciones/create' },
];

type Props = {
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    obraRubros: ObraRubroOption[];
};

const blankDetalle = (): Detalle => ({
    descripcion: '',
    unidad: 'pza',
    cantidad: 1,
    obra_rubro_id: '',
    notas: '',
});

export default function RequisicionesCreate({ departamentos, obraRubros }: Props) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        departamento_id: '',
        justificacion: '',
        detalles: [blankDetalle()],
    });

    const addDetalle = () => {
        setData('detalles', [...data.detalles, blankDetalle()]);
    };

    const removeDetalle = (idx: number) => {
        setData('detalles', data.detalles.filter((_, i) => i !== idx));
    };

    const updateDetalle = (idx: number, field: keyof Detalle, value: string | number) => {
        const next = data.detalles.map((d, i) => (i === idx ? { ...d, [field]: value } : d));
        setData('detalles', next);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/costos/requisiciones');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva requisición" />

            <form onSubmit={handleSubmit} className="p-6">
                <h1 className="mb-4 text-2xl font-semibold">Nueva requisición</h1>

                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label className="label label-text">Departamento *</label>
                        <select
                            className="select select-bordered w-full"
                            value={data.departamento_id}
                            onChange={(e) => setData('departamento_id', e.target.value ? Number(e.target.value) : '')}
                        >
                            <option value="">Selecciona un departamento</option>
                            {departamentos.map((d) => (
                                <option key={d.id} value={d.id}>{d.descripcion}</option>
                            ))}
                        </select>
                        {errors.departamento_id && <p className="text-error text-sm mt-1">{errors.departamento_id}</p>}
                    </div>

                    <div className="md:col-span-2">
                        <label className="label label-text">Justificación</label>
                        <textarea
                            className="textarea textarea-bordered w-full"
                            rows={3}
                            value={data.justificacion}
                            onChange={(e) => setData('justificacion', e.target.value)}
                            placeholder="Por qué se necesita y cuál es el impacto esperado"
                        />
                    </div>
                </div>

                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-lg font-medium">Partidas</h2>
                    <Button type="button" variant="outline" onClick={addDetalle}>
                        <PlusIcon className="size-3.5" /> Agregar partida
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-base-300">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Descripción *</th>
                                <th className="min-w-[220px]">Rubro *</th>
                                <th className="w-24">Unidad</th>
                                <th className="w-28 text-right">Cantidad *</th>
                                <th>Notas</th>
                                <th className="w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.detalles.map((d, i) => (
                                <tr key={i}>
                                    <td>
                                        <input
                                            type="text"
                                            className="input input-bordered input-sm w-full"
                                            value={d.descripcion}
                                            onChange={(e) => updateDetalle(i, 'descripcion', e.target.value)}
                                        />
                                        {errors[`detalles.${i}.descripcion` as keyof typeof errors] && (
                                            <p className="text-error text-xs mt-1">{errors[`detalles.${i}.descripcion` as keyof typeof errors]}</p>
                                        )}
                                    </td>
                                    <td>
                                        <RubroSelector
                                            value={d.obra_rubro_id}
                                            options={obraRubros}
                                            onChange={(value) => updateDetalle(i, 'obra_rubro_id', value)}
                                        />
                                        {errors[`detalles.${i}.obra_rubro_id` as keyof typeof errors] && (
                                            <p className="text-error text-xs mt-1">{errors[`detalles.${i}.obra_rubro_id` as keyof typeof errors]}</p>
                                        )}
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            className="input input-bordered input-sm w-full"
                                            value={d.unidad}
                                            onChange={(e) => updateDetalle(i, 'unidad', e.target.value)}
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="input input-bordered input-sm w-full text-right"
                                            value={d.cantidad}
                                            onChange={(e) => updateDetalle(i, 'cantidad', Number(e.target.value))}
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            className="input input-bordered input-sm w-full"
                                            value={d.notas}
                                            onChange={(e) => updateDetalle(i, 'notas', e.target.value)}
                                        />
                                    </td>
                                    <td>
                                        {data.detalles.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeDetalle(i)}
                                                className="btn btn-ghost btn-sm text-error"
                                            >
                                                <Trash2Icon className="size-3.5" />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {errors.detalles && <p className="text-error text-sm mt-2">{errors.detalles}</p>}

                <div className="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <a href="/admin/costos/requisiciones">Cancelar</a>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Guardando...' : 'Guardar requisición'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

