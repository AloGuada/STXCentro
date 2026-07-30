import { Button, ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra, ProdGrupoPrecio, ProdGrupoPrecioConcepto } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronRightIcon, PlusIcon, TrashIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

type GrupoPrecioWithConceptos = ProdGrupoPrecio & {
    grupo_precio_conceptos: (ProdGrupoPrecioConcepto & { concepto: Concepto })[];
    grupo_precio_conceptos_count: number;
};

type Props = {
    obra: Obra;
    grupoPrecios: GrupoPrecioWithConceptos[];
    unassignedConceptos: Concepto[];
};

const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 4, maximumFractionDigits: 4 });

export default function GrupoPreciosShow({ obra, grupoPrecios, unassignedConceptos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        { title: `${obra.no} - ${obra.descripcion}`, href: `/admin/prod/grupo-precios/obra/${obra.id}` },
    ];

    const [selectedGrupo, setSelectedGrupo] = useState('');
    const [selectedConcepto, setSelectedConcepto] = useState('');

    const conceptoOptions = useMemo(
        () => unassignedConceptos.map((c) => ({ value: String(c.id), label: `${c.marca} - ${c.descripcion}` })),
        [unassignedConceptos],
    );

    const handleAssign = () => {
        if (!selectedGrupo || !selectedConcepto) return;
        router.post(`/admin/prod/grupo-precios/${selectedGrupo}/assign-conceptos`, {
            concepto_ids: [selectedConcepto],
        }, {
            preserveScroll: true,
            onSuccess: () => setSelectedConcepto(''),
        });
    };

    const handleRemove = (id: number) => {
        router.delete(`/admin/prod/grupo-precio-conceptos/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Grupo Precios - ${obra.no}`} />

            <div className="p-6">
                <div className="mb-6 flex items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Obra {obra.no}</h1>
                        <p className="mt-1 text-sm text-base-content/60">{obra.descripcion}</p>
                    </div>
                    <ButtonLink href={`/admin/prod/grupo-precios/create?obra_id=${obra.id}`} variant="primary">
                        <PlusIcon className="size-4" />
                        Nuevo grupo de precio
                    </ButtonLink>
                </div>

                {grupoPrecios.length === 0 ? (
                    <div className="rounded-box border border-base-300 p-8 text-center text-base-content/50">
                        No hay grupos de precio para esta obra. Crea uno para empezar a asignar conceptos.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {grupoPrecios.map((gp) => (
                            <div key={gp.id} className="collapse collapse-arrow rounded-box border border-base-300 bg-base-100">
                                <input type="checkbox" defaultChecked />
                                <div className="collapse-title flex items-center gap-4">
                                    <div className="flex-1">
                                        <span className="font-medium">{gp.descripcion}</span>
                                        <span className="ml-4 font-mono text-sm text-base-content/60">${fmt(gp.precio_kilo)}/kg</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="badge badge-sm badge-ghost">{gp.grupo_precio_conceptos_count} piezas</span>
                                        <Link
                                            href={`/admin/prod/grupo-precios/${gp.id}/edit`}
                                            className="btn btn-ghost btn-xs"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            Editar
                                            <ChevronRightIcon className="size-3" />
                                        </Link>
                                    </div>
                                </div>
                                <div className="collapse-content">
                                    <div className="rounded-box border border-base-300 overflow-hidden">
                                        <table className="table table-sm">
                                            <thead className="bg-base-200">
                                                <tr>
                                                    <th>Marca</th>
                                                    <th>Descripcion</th>
                                                    <th className="text-right">Peso Unitario</th>
                                                    <th className="w-12"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {gp.grupo_precio_conceptos.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={4} className="text-center text-base-content/50 py-6">
                                                            Sin conceptos asignados
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    gp.grupo_precio_conceptos.map((gpc) => (
                                                        <tr key={gpc.id} className="hover">
                                                            <td className="font-medium">{gpc.concepto?.marca}</td>
                                                            <td>{gpc.concepto?.descripcion}</td>
                                                            <td className="text-right font-mono">{gpc.concepto?.peso_unitario}</td>
                                                            <td>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() => handleRemove(gpc.id)}
                                                                >
                                                                    <TrashIcon className="size-4 text-error" />
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {unassignedConceptos.length > 0 && (
                    <div className="mt-8">
                        <h2 className="mb-4 text-lg font-semibold">
                            Piezas sin precio
                            <span className="badge badge-sm badge-warning ml-2">{unassignedConceptos.length}</span>
                        </h2>

                        {grupoPrecios.length > 0 && (
                            <div className="mb-4 flex flex-wrap items-end gap-2">
                                <div className="w-56">
                                    <label className="label text-xs">Grupo</label>
                                    <Select
                                        value={selectedGrupo}
                                        onValueChange={setSelectedGrupo}
                                        placeholder="Seleccionar grupo"
                                    >
                                        {grupoPrecios.map((gp) => (
                                            <SelectItem key={gp.id} value={gp.id}>
                                                {gp.descripcion} (${fmt(gp.precio_kilo)}/kg)
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </div>
                                <div className="min-w-64 flex-1">
                                    <label className="label text-xs">Concepto</label>
                                    <SearchSelect
                                        options={conceptoOptions}
                                        value={selectedConcepto}
                                        onValueChange={setSelectedConcepto}
                                        placeholder="Buscar concepto..."
                                    />
                                </div>
                                <Button onClick={handleAssign} disabled={!selectedGrupo || !selectedConcepto}>
                                    Asignar
                                </Button>
                            </div>
                        )}

                        <div className="rounded-box border border-base-300 overflow-hidden">
                            <table className="table table-sm">
                                <thead className="bg-base-200">
                                    <tr>
                                        <th>Marca</th>
                                        <th>Descripcion</th>
                                        <th className="text-right">Peso Unitario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {unassignedConceptos.map((c) => (
                                        <tr key={c.id} className="hover">
                                            <td className="font-medium">{c.marca}</td>
                                            <td>{c.descripcion}</td>
                                            <td className="text-right font-mono">{c.peso_unitario}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
