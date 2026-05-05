import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, Proveedor } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';

type FormData = {
    proveedor_id: number | '';
    obra_id: number | '';
    monto: number;
    moneda: 'mxn' | 'usd' | 'eur';
    fecha: string;
    referencia: string;
    notas: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/anticipos' },
    { title: 'Anticipos', href: '/admin/costos/anticipos' },
    { title: 'Nuevo', href: '/admin/costos/anticipos/create' },
];

type Props = {
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>[];
    obras: Pick<Obra, 'id' | 'descripcion'>[];
    preset?: {
        proveedor_id: number | null;
        obra_id: number | null;
    };
};

export default function AnticiposCreate({ proveedores, obras, preset }: Props) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        proveedor_id: preset?.proveedor_id ?? '',
        obra_id: preset?.obra_id ?? '',
        monto: 0,
        moneda: 'mxn',
        fecha: new Date().toISOString().slice(0, 10),
        referencia: '',
        notas: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/costos/anticipos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo anticipo" />

            <form onSubmit={handleSubmit} className="p-6 max-w-3xl">
                <h1 className="mb-4 text-2xl font-semibold">Nuevo anticipo</h1>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="label label-text">Proveedor *</label>
                        <select
                            className="select select-bordered w-full"
                            value={data.proveedor_id}
                            onChange={(e) => setData('proveedor_id', e.target.value ? Number(e.target.value) : '')}
                        >
                            <option value="">Selecciona un proveedor</option>
                            {proveedores.map((p) => (
                                <option key={p.id} value={p.id}>{p.razon_social}</option>
                            ))}
                        </select>
                        {errors.proveedor_id && <p className="text-error text-sm mt-1">{errors.proveedor_id}</p>}
                    </div>

                    <div>
                        <label className="label label-text">Obra (opcional)</label>
                        <select
                            className="select select-bordered w-full"
                            value={data.obra_id}
                            onChange={(e) => setData('obra_id', e.target.value ? Number(e.target.value) : '')}
                        >
                            <option value="">Sin obra específica</option>
                            {obras.map((o) => (
                                <option key={o.id} value={o.id}>{o.descripcion}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="label label-text">Monto *</label>
                        <input
                            type="number"
                            step="0.01"
                            className="input input-bordered w-full"
                            value={data.monto}
                            onChange={(e) => setData('monto', Number(e.target.value))}
                        />
                        {errors.monto && <p className="text-error text-sm mt-1">{errors.monto}</p>}
                    </div>

                    <div>
                        <label className="label label-text">Moneda *</label>
                        <select
                            className="select select-bordered w-full"
                            value={data.moneda}
                            onChange={(e) => setData('moneda', e.target.value as 'mxn' | 'usd' | 'eur')}
                        >
                            <option value="mxn">MXN</option>
                            <option value="usd">USD</option>
                            <option value="eur">EUR</option>
                        </select>
                    </div>

                    <div>
                        <label className="label label-text">Fecha *</label>
                        <input
                            type="date"
                            className="input input-bordered w-full"
                            value={data.fecha}
                            onChange={(e) => setData('fecha', e.target.value)}
                        />
                    </div>

                    <div>
                        <label className="label label-text">Referencia (folio bancario, etc.)</label>
                        <input
                            type="text"
                            className="input input-bordered w-full"
                            value={data.referencia}
                            onChange={(e) => setData('referencia', e.target.value)}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <label className="label label-text">Notas</label>
                        <textarea
                            className="textarea textarea-bordered w-full"
                            rows={3}
                            value={data.notas}
                            onChange={(e) => setData('notas', e.target.value)}
                        />
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <a href="/admin/costos/anticipos">Cancelar</a>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Guardando...' : 'Guardar anticipo'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
