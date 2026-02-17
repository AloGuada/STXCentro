import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra, ProdGrupoPrecio, ProdGrupoPrecioConcepto } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronRightIcon, PlusIcon, TrashIcon } from 'lucide-react';
import { useState } from 'react';

type GrupoPrecioWithConceptos = ProdGrupoPrecio & {
    grupo_precio_conceptos: (ProdGrupoPrecioConcepto & { concepto: Concepto })[];
    grupo_precio_conceptos_count: number;
};

type Props = {
    obra: Obra;
    grupoPrecios: GrupoPrecioWithConceptos[];
    unassignedConceptos: Concepto[];
};

export default function GrupoPreciosShow({ obra, grupoPrecios, unassignedConceptos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        { title: `${obra.no} - ${obra.descripcion}`, href: `/admin/prod/grupo-precios/obra/${obra.id}` },
    ];

    const [selectedGrupo, setSelectedGrupo] = useState('');
    const [selectedConcepto, setSelectedConcepto] = useState('');

    const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 4, maximumFractionDigits: 4 });

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
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Obra {obra.no}</h1>
                        <p className="text-sm text-gray-500">{obra.descripcion}</p>
                    </div>
                    <Button asChild>
                        <Link href={`/admin/prod/grupo-precios/create?obra_id=${obra.id}`}>
                            <PlusIcon className="mr-1 size-4" />
                            Nuevo Grupo Precio
                        </Link>
                    </Button>
                </div>

                {/* Grupos de precio */}
                {grupoPrecios.length === 0 ? (
                    <div className="rounded-lg border border-base-300 p-8 text-center text-gray-500">
                        No hay grupos de precio para esta obra. Crea uno para empezar a asignar conceptos.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {grupoPrecios.map((gp) => (
                            <div key={gp.id} className="collapse collapse-arrow border border-base-300 bg-base-100">
                                <input type="checkbox" defaultChecked />
                                <div className="collapse-title flex items-center gap-4">
                                    <div className="flex-1">
                                        <span className="font-medium">{gp.descripcion}</span>
                                        <span className="ml-4 font-mono text-sm text-gray-500">${fmt(gp.precio_kilo)}/kg</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="badge badge-neutral">{gp.grupo_precio_conceptos_count} piezas</span>
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
                                    {gp.grupo_precio_conceptos.length === 0 ? (
                                        <p className="py-2 text-sm text-gray-500">Sin conceptos asignados</p>
                                    ) : (
                                        <table className="table table-sm w-full">
                                            <thead>
                                                <tr>
                                                    <th>Marca</th>
                                                    <th>Descripcion</th>
                                                    <th className="text-right">Peso Unitario</th>
                                                    <th className="w-12"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {gp.grupo_precio_conceptos.map((gpc) => (
                                                    <tr key={gpc.id}>
                                                        <td>{gpc.concepto?.marca}</td>
                                                        <td>{gpc.concepto?.descripcion}</td>
                                                        <td className="text-right font-mono">{gpc.concepto?.peso_unitario}</td>
                                                        <td>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-6 w-6"
                                                                onClick={() => handleRemove(gpc.id)}
                                                            >
                                                                <TrashIcon className="size-3 text-red-500" />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Piezas sin asignar */}
                {unassignedConceptos.length > 0 && (
                    <div className="mt-8">
                        <h2 className="mb-4 text-lg font-medium">
                            Piezas sin precio
                            <span className="badge badge-warning ml-2">{unassignedConceptos.length}</span>
                        </h2>

                        {grupoPrecios.length > 0 && (
                            <div className="mb-4 flex items-end gap-2">
                                <div className="w-48">
                                    <label className="label text-xs">Grupo</label>
                                    <Select
                                        value={selectedGrupo}
                                        onValueChange={setSelectedGrupo}
                                        placeholder="Seleccionar grupo"
                                    >
                                        {grupoPrecios.map((gp) => (
                                            <option key={gp.id} value={gp.id}>
                                                {gp.descripcion} (${fmt(gp.precio_kilo)}/kg)
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div className="flex-1">
                                    <label className="label text-xs">Concepto</label>
                                    <Select
                                        value={selectedConcepto}
                                        onValueChange={setSelectedConcepto}
                                        placeholder="Seleccionar concepto"
                                    >
                                        {unassignedConceptos.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.marca} - {c.descripcion}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <Button onClick={handleAssign} disabled={!selectedGrupo || !selectedConcepto}>
                                    Asignar
                                </Button>
                            </div>
                        )}

                        <table className="table table-sm w-full">
                            <thead>
                                <tr>
                                    <th>Marca</th>
                                    <th>Descripcion</th>
                                    <th className="text-right">Peso Unitario</th>
                                </tr>
                            </thead>
                            <tbody>
                                {unassignedConceptos.map((c) => (
                                    <tr key={c.id}>
                                        <td>{c.marca}</td>
                                        <td>{c.descripcion}</td>
                                        <td className="text-right font-mono">{c.peso_unitario}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
