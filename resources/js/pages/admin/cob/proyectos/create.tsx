import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Cliente } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Proyectos', href: '/admin/cob/proyectos' },
    { title: 'Nuevo', href: '/admin/cob/proyectos/create' },
];

type Props = {
    clientes: Pick<Cliente, 'id' | 'nombre'>[];
};

export default function ProyectoCreate({ clientes }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        no: '',
        descripcion: '',
        cliente_id: '' as string | number,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cob/proyectos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo proyecto" />

            <form onSubmit={submit} className="flex max-w-2xl flex-col gap-4 p-6">
                <h1 className="text-xl font-semibold">Nuevo proyecto</h1>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Campo label="No *" error={errors.no}>
                        <input className="input input-bordered w-full" value={data.no} onChange={(e) => setData('no', e.target.value)} />
                    </Campo>
                    <Campo label="Descripción *" error={errors.descripcion}>
                        <input className="input input-bordered w-full" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                    </Campo>
                    <Campo label="Cliente" error={errors.cliente_id}>
                        <select className="select select-bordered w-full" value={data.cliente_id} onChange={(e) => setData('cliente_id', e.target.value)}>
                            <option value="">— Sin cliente —</option>
                            {clientes.map((c) => (
                                <option key={c.id} value={c.id}>{c.nombre}</option>
                            ))}
                        </select>
                    </Campo>
                </div>

                <p className="text-base-content/60 text-sm">
                    Los datos de contrato (montos, anticipo, garantía) se capturan en cada obra del proyecto.
                </p>

                <div>
                    <button type="submit" className="btn btn-primary" disabled={processing}>
                        Crear proyecto
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}

function Campo({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-sm font-medium">{label}</span>
            {children}
            {error && <span className="text-error text-xs">{error}</span>}
        </label>
    );
}
