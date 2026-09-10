import { DataTable, type Column } from '@/components/data-table';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmOpcion, PaginatedData } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Ajustes', href: '/admin/almacen/ajustes' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type AjusteFila = {
    id: number;
    folio: string | null;
    fecha: string | null;
    almacen: string | null;
    motivo: string;
    motivo_etiqueta: string;
    renglones: number;
    /** Con signo, sumando todos los renglones: si repuso o si bajó. */
    diferencia_neta: number;
    autorizo: string | null;
};

type Props = {
    ajustes: PaginatedData<AjusteFila>;
    filters: { almacen_id?: string; motivo?: string; desde?: string; hasta?: string; search?: string };
    almacenes: AlmAlmacenOpcion[];
    motivos: AlmOpcion[];
};

const columns: Column<AjusteFila>[] = [
    { key: 'folio', label: 'Folio', render: (a) => <span className="font-mono font-medium">{a.folio}</span> },
    { key: 'fecha', label: 'Fecha', className: 'font-mono text-xs' },
    {
        key: 'almacen',
        label: 'Almacén',
        render: (a) => <span className="badge badge-sm badge-ghost font-mono">{a.almacen}</span>,
    },
    {
        key: 'motivo',
        label: 'Motivo',
        render: (a) => <span className="badge badge-sm badge-ghost">{a.motivo_etiqueta}</span>,
    },
    { key: 'renglones', label: 'Renglones', className: 'text-right font-mono' },
    {
        key: 'diferencia_neta',
        label: 'Diferencia neta',
        className: 'text-right',
        render: (a) => (
            <span
                className={`font-mono ${
                    a.diferencia_neta < 0 ? 'text-error' : a.diferencia_neta > 0 ? 'text-success' : 'text-base-content/40'
                }`}
            >
                {a.diferencia_neta > 0 ? '+' : ''}
                {numero(a.diferencia_neta)}
            </span>
        ),
    },
    { key: 'autorizo', label: 'Autorizó' },
];

export default function AjustesIndex({ ajustes, filters, almacenes, motivos }: Props) {
    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/ajustes', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajustes" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Ajustes</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        El único movimiento que cambia la existencia sin que haya entrado ni salido material. Queda con
                        folio, motivo y quién lo autorizó — y no se edita: un ajuste equivocado se corrige con otro.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={ajustes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por folio..."
                    createHref="/admin/almacen/ajustes/create"
                    createLabel="Nuevo ajuste"
                    emptyMessage="Todavía no se ha capturado ningún ajuste."
                    getRowHref={(a) => `/admin/almacen/ajustes/${a.id}`}
                >
                    <div className="w-52">
                        <Select
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined })}
                            placeholder="Todos los almacenes"
                        >
                            {almacenes.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {etiquetaDeAlmacen(a)}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-44">
                        <Select
                            value={filters.motivo ?? ''}
                            onValueChange={(v) => filtrar({ motivo: v || undefined })}
                            placeholder="Todos los motivos"
                        >
                            {motivos.map((m) => (
                                <SelectItem key={m.value} value={m.value}>
                                    {m.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                </DataTable>
            </div>
        </AppLayout>
    );
}
