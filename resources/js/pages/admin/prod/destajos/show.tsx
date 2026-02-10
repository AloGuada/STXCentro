import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, Pieza, ProdDestajo, ProdFabricado, ProdGrupo, ProdPagoExtra, ProdTipo } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardListIcon, DollarSignIcon, Loader2Icon, LockIcon, PlusIcon, TrashIcon } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';

type Props = {
    destajo: ProdDestajo & {
        fabricados: (ProdFabricado & { pieza: Pieza & { obra: Obra }; dest_grupo?: ProdGrupo })[];
        pagos_extra: (ProdPagoExtra & { tipo: ProdTipo; dest_grupo?: ProdGrupo })[];
    };
    grupos: ProdGrupo[];
    piezas: (Pieza & { obra: Obra })[];
    tipos: ProdTipo[];
};

export default function DestajosShow({ destajo, grupos, piezas, tipos }: Props) {
    const [activeTab, setActiveTab] = useState<'fabricados' | 'pagos-extra' | 'resumen'>('fabricados');
    const [fabObraFilter, setFabObraFilter] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Destajos', href: '/admin/prod/destajos' },
        { title: `Semana ${destajo.semana}`, href: '#' },
    ];

    const fabForm = useForm({
        dest_grupo_id: '',
        pieza_id: '',
        cantidad: '1',
        porcentual: '100',
        precio_unitario_aplicado: '',
    });

    const pagoForm = useForm({
        dest_grupo_id: '',
        tipo_id: '',
        descripcion: '',
        precio: '',
        dias: '1',
        personas: '1',
    });

    const handleAddFabricado = (e: FormEvent) => {
        e.preventDefault();
        fabForm.post(`/admin/prod/destajos/${destajo.id}/fabricados`, {
            preserveScroll: true,
            onSuccess: () => fabForm.reset(),
        });
    };

    const handleDeleteFabricado = (fabricadoId: number) => {
        router.delete(`/admin/prod/destajos/${destajo.id}/fabricados/${fabricadoId}`, { preserveScroll: true });
    };

    const handleAddPago = (e: FormEvent) => {
        e.preventDefault();
        pagoForm.post(`/admin/prod/destajos/${destajo.id}/pagos-extra`, {
            preserveScroll: true,
            onSuccess: () => pagoForm.reset(),
        });
    };

    const handleDeletePago = (pagoId: number) => {
        router.delete(`/admin/prod/destajos/${destajo.id}/pagos-extra/${pagoId}`, { preserveScroll: true });
    };

    const handleCerrar = () => {
        if (!confirm('Cerrar este destajo? Una vez cerrado no se podra editar.')) return;
        router.post(`/admin/prod/destajos/${destajo.id}/cerrar`, {}, { preserveScroll: true });
    };

    // Piezas filtradas por obra
    const filteredPiezas = useMemo(() => {
        if (!fabObraFilter) return piezas;
        return piezas.filter((p) => p.obra_id.toString() === fabObraFilter);
    }, [fabObraFilter, piezas]);

    // Obras unicas de las piezas disponibles
    const obras = useMemo(() => {
        const obraMap = new Map<number, Obra>();
        piezas.forEach((p) => {
            if (p.obra && !obraMap.has(p.obra.id)) {
                obraMap.set(p.obra.id, p.obra);
            }
        });
        return Array.from(obraMap.values()).sort((a, b) => a.no.localeCompare(b.no));
    }, [piezas]);

    // Fabricados agrupados por dest_grupo
    const fabricadosByGrupo = useMemo(() => {
        const groups: Record<string, { grupo: ProdGrupo | null; fabricados: typeof destajo.fabricados }> = {};
        (destajo.fabricados ?? []).forEach((fab) => {
            const key = fab.dest_grupo_id?.toString() ?? 'sin-grupo';
            if (!groups[key]) {
                groups[key] = { grupo: fab.dest_grupo ?? null, fabricados: [] };
            }
            groups[key].fabricados.push(fab);
        });
        return Object.values(groups).sort((a, b) => (a.grupo?.descripcion ?? 'ZZZ').localeCompare(b.grupo?.descripcion ?? 'ZZZ'));
    }, [destajo.fabricados]);

    // Pagos extra agrupados por dest_grupo
    const pagosByGrupo = useMemo(() => {
        const groups: Record<string, { grupo: ProdGrupo | null; pagos: typeof destajo.pagos_extra }> = {};
        (destajo.pagos_extra ?? []).forEach((pago) => {
            const key = pago.dest_grupo_id?.toString() ?? 'sin-grupo';
            if (!groups[key]) {
                groups[key] = { grupo: pago.dest_grupo ?? null, pagos: [] };
            }
            groups[key].pagos.push(pago);
        });
        return Object.values(groups).sort((a, b) => (a.grupo?.descripcion ?? 'ZZZ').localeCompare(b.grupo?.descripcion ?? 'ZZZ'));
    }, [destajo.pagos_extra]);

    const totalFabricados = destajo.fabricados?.reduce((sum, f) => sum + Number(f.total_calculado), 0) ?? 0;
    const totalPagosExtra =
        destajo.pagos_extra?.reduce((sum, p) => sum + Number(p.precio) * Number(p.dias) * Number(p.personas), 0) ?? 0;
    const totalGeneral = totalFabricados + totalPagosExtra;

    const pagoTotal = Number(pagoForm.data.precio || 0) * Number(pagoForm.data.dias || 0) * Number(pagoForm.data.personas || 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Destajo - Semana ${destajo.semana}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Destajo - Semana {destajo.semana}</h1>
                        <p className="text-sm text-gray-500">
                            <span className={`badge badge-sm ${destajo.cerrada ? 'badge-success' : 'badge-warning'}`}>
                                {destajo.cerrada ? 'Cerrada' : 'Abierta'}
                            </span>
                            {destajo.fecha_cierre && (
                                <span className="ml-2">
                                    Cerrado: {new Date(destajo.fecha_cierre).toLocaleDateString('es-MX')}
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {!destajo.cerrada && (
                            <Button variant="outline" onClick={handleCerrar}>
                                <LockIcon className="mr-1 size-4" />
                                Cerrar Destajo
                            </Button>
                        )}
                        {!destajo.cerrada && (
                            <DeleteDialog
                                title="Eliminar destajo"
                                description="Eliminar este destajo? Se eliminaran todos los fabricados y pagos extra asociados."
                                deleteUrl={`/admin/prod/destajos/${destajo.id}`}
                            />
                        )}
                    </div>
                </div>

                {/* Tabs */}
                <div className="tabs tabs-boxed">
                    <button
                        type="button"
                        className={`tab ${activeTab === 'fabricados' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('fabricados')}
                    >
                        <ClipboardListIcon className="mr-1 size-4" />
                        Fabricados ({destajo.fabricados?.length ?? 0})
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'pagos-extra' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('pagos-extra')}
                    >
                        <DollarSignIcon className="mr-1 size-4" />
                        Pagos Extra ({destajo.pagos_extra?.length ?? 0})
                    </button>
                    <button type="button" className={`tab ${activeTab === 'resumen' ? 'tab-active' : ''}`} onClick={() => setActiveTab('resumen')}>
                        Resumen
                    </button>
                </div>

                {/* Tab: Fabricados */}
                {activeTab === 'fabricados' && (
                    <div className="space-y-6">
                        {fabricadosByGrupo.length > 0 ? (
                            fabricadosByGrupo.map((group) => (
                                <div key={group.grupo?.id ?? 'sin-grupo'}>
                                    <h3 className="mb-2 text-sm font-semibold text-gray-500">
                                        Grupo: {group.grupo?.descripcion ?? 'Sin grupo'}
                                    </h3>
                                    <div className="overflow-x-auto">
                                        <table className="table table-zebra w-full">
                                            <thead>
                                                <tr>
                                                    <th>Pieza</th>
                                                    <th>Obra</th>
                                                    <th className="text-right">Cant.</th>
                                                    <th className="text-right">Peso</th>
                                                    <th className="text-right">%</th>
                                                    <th className="text-right">Precio/kg</th>
                                                    <th className="text-right">Total</th>
                                                    <th className="text-right">Saldo Pend.</th>
                                                    {!destajo.cerrada && <th></th>}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {group.fabricados.map((fab) => (
                                                    <tr key={fab.id}>
                                                        <td>{fab.pieza?.marca}</td>
                                                        <td className="text-sm">{fab.pieza?.obra?.no}</td>
                                                        <td className="text-right font-mono">{fab.cantidad}</td>
                                                        <td className="text-right font-mono">{Number(fab.pieza?.peso).toFixed(2)}</td>
                                                        <td className="text-right font-mono">{Number(fab.porcentual).toFixed(0)}%</td>
                                                        <td className="text-right font-mono">${Number(fab.precio_unitario_aplicado).toFixed(2)}</td>
                                                        <td className="text-right font-mono font-medium">
                                                            ${Number(fab.total_calculado).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                        </td>
                                                        <td className="text-right font-mono">
                                                            {Number(fab.saldo_pendiente) > 0 ? (
                                                                <span className="text-warning">
                                                                    ${Number(fab.saldo_pendiente).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                                </span>
                                                            ) : (
                                                                '-'
                                                            )}
                                                        </td>
                                                        {!destajo.cerrada && (
                                                            <td>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleDeleteFabricado(fab.id)}
                                                                >
                                                                    <TrashIcon className="size-4 text-error" />
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-gray-500">No hay fabricados en este destajo.</p>
                        )}

                        {fabricadosByGrupo.length > 0 && (
                            <div className="text-right font-medium">
                                Total Fabricados:{' '}
                                <span className="font-mono font-bold">
                                    ${totalFabricados.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                </span>
                            </div>
                        )}

                        {!destajo.cerrada && (
                            <>
                                <div className="divider" />
                                <h2 className="text-lg font-semibold">Agregar Fabricado</h2>
                                <form onSubmit={handleAddFabricado} className="space-y-4">
                                    <div className="grid grid-cols-3 items-end gap-4">
                                        <FormField label="Grupo" htmlFor="fab_grupo" error={fabForm.errors.dest_grupo_id} required>
                                            <Select
                                                id="fab_grupo"
                                                value={fabForm.data.dest_grupo_id}
                                                onValueChange={(value) => fabForm.setData('dest_grupo_id', value)}
                                                placeholder="Seleccionar grupo"
                                            >
                                                {grupos.map((grupo) => (
                                                    <option key={grupo.id} value={grupo.id}>
                                                        {grupo.descripcion}
                                                    </option>
                                                ))}
                                            </Select>
                                        </FormField>
                                        <FormField label="Obra" htmlFor="fab_obra_filter">
                                            <Select
                                                id="fab_obra_filter"
                                                value={fabObraFilter}
                                                onValueChange={(value) => {
                                                    setFabObraFilter(value);
                                                    fabForm.setData('pieza_id', '');
                                                }}
                                                placeholder="Todas las obras"
                                            >
                                                <option value="">Todas las obras</option>
                                                {obras.map((obra) => (
                                                    <option key={obra.id} value={obra.id}>
                                                        {obra.no} - {obra.descripcion}
                                                    </option>
                                                ))}
                                            </Select>
                                        </FormField>
                                        <FormField label="Pieza (Marca)" htmlFor="fab_pieza_id" error={fabForm.errors.pieza_id} required>
                                            <Select
                                                id="fab_pieza_id"
                                                value={fabForm.data.pieza_id}
                                                onValueChange={(value) => fabForm.setData('pieza_id', value)}
                                                placeholder="Seleccionar pieza"
                                            >
                                                {filteredPiezas.map((pieza) => (
                                                    <option key={pieza.id} value={pieza.id}>
                                                        {pieza.marca} - {pieza.descripcion} ({pieza.obra?.no})
                                                    </option>
                                                ))}
                                            </Select>
                                        </FormField>
                                    </div>
                                    <div className="grid grid-cols-3 items-end gap-4">
                                        <FormField label="Cantidad" htmlFor="fab_cantidad" error={fabForm.errors.cantidad} required>
                                            <Input
                                                id="fab_cantidad"
                                                type="number"
                                                min="1"
                                                value={fabForm.data.cantidad}
                                                onChange={(e) => fabForm.setData('cantidad', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField label="% Pago" htmlFor="fab_porcentual" error={fabForm.errors.porcentual} required>
                                            <Input
                                                id="fab_porcentual"
                                                type="number"
                                                min="0"
                                                max="100"
                                                value={fabForm.data.porcentual}
                                                onChange={(e) => fabForm.setData('porcentual', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField label="Precio/kg" htmlFor="fab_precio" error={fabForm.errors.precio_unitario_aplicado} required>
                                            <Input
                                                id="fab_precio"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={fabForm.data.precio_unitario_aplicado}
                                                onChange={(e) => fabForm.setData('precio_unitario_aplicado', e.target.value)}
                                            />
                                        </FormField>
                                    </div>
                                    <Button type="submit" disabled={fabForm.processing}>
                                        {fabForm.processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                                        Agregar Fabricado
                                    </Button>
                                </form>
                            </>
                        )}
                    </div>
                )}

                {/* Tab: Pagos Extra */}
                {activeTab === 'pagos-extra' && (
                    <div className="space-y-6">
                        {pagosByGrupo.length > 0 ? (
                            pagosByGrupo.map((group) => (
                                <div key={group.grupo?.id ?? 'sin-grupo'}>
                                    <h3 className="mb-2 text-sm font-semibold text-gray-500">
                                        Grupo: {group.grupo?.descripcion ?? 'Sin grupo'}
                                    </h3>
                                    <div className="overflow-x-auto">
                                        <table className="table table-zebra w-full">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Descripcion</th>
                                                    <th className="text-right">Precio</th>
                                                    <th className="text-right">Dias</th>
                                                    <th className="text-right">Personas</th>
                                                    <th className="text-right">Total</th>
                                                    {!destajo.cerrada && <th></th>}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {group.pagos.map((pago) => {
                                                    const total = Number(pago.precio) * Number(pago.dias) * Number(pago.personas);
                                                    return (
                                                        <tr key={pago.id}>
                                                            <td>{pago.tipo?.descripcion}</td>
                                                            <td>{pago.descripcion ?? '-'}</td>
                                                            <td className="text-right font-mono">
                                                                ${Number(pago.precio).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                            </td>
                                                            <td className="text-right font-mono">{pago.dias}</td>
                                                            <td className="text-right font-mono">{pago.personas}</td>
                                                            <td className="text-right font-mono font-medium">
                                                                ${total.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                            </td>
                                                            {!destajo.cerrada && (
                                                                <td>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => handleDeletePago(pago.id)}
                                                                    >
                                                                        <TrashIcon className="size-4 text-error" />
                                                                    </Button>
                                                                </td>
                                                            )}
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-gray-500">No hay pagos extra en este destajo.</p>
                        )}

                        {pagosByGrupo.length > 0 && (
                            <div className="text-right font-medium">
                                Total Pagos Extra:{' '}
                                <span className="font-mono font-bold">
                                    ${totalPagosExtra.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                </span>
                            </div>
                        )}

                        {!destajo.cerrada && (
                            <>
                                <div className="divider" />
                                <h2 className="text-lg font-semibold">Agregar Pago Extra</h2>
                                <form onSubmit={handleAddPago} className="space-y-4">
                                    <div className="grid grid-cols-3 items-end gap-4">
                                        <FormField label="Grupo" htmlFor="pago_grupo" error={pagoForm.errors.dest_grupo_id} required>
                                            <Select
                                                id="pago_grupo"
                                                value={pagoForm.data.dest_grupo_id}
                                                onValueChange={(value) => pagoForm.setData('dest_grupo_id', value)}
                                                placeholder="Seleccionar grupo"
                                            >
                                                {grupos.map((grupo) => (
                                                    <option key={grupo.id} value={grupo.id}>
                                                        {grupo.descripcion}
                                                    </option>
                                                ))}
                                            </Select>
                                        </FormField>
                                        <FormField label="Tipo" htmlFor="pago_tipo_id" error={pagoForm.errors.tipo_id} required>
                                            <Select
                                                id="pago_tipo_id"
                                                value={pagoForm.data.tipo_id}
                                                onValueChange={(value) => pagoForm.setData('tipo_id', value)}
                                                placeholder="Seleccionar tipo"
                                            >
                                                {tipos.map((tipo) => (
                                                    <option key={tipo.id} value={tipo.id}>
                                                        {tipo.descripcion}
                                                    </option>
                                                ))}
                                            </Select>
                                        </FormField>
                                        <FormField label="Descripcion" htmlFor="pago_desc" error={pagoForm.errors.descripcion}>
                                            <Input
                                                id="pago_desc"
                                                value={pagoForm.data.descripcion}
                                                onChange={(e) => pagoForm.setData('descripcion', e.target.value)}
                                                placeholder="Descripcion opcional"
                                            />
                                        </FormField>
                                    </div>
                                    <div className="grid grid-cols-4 items-end gap-4">
                                        <FormField label="Precio" htmlFor="pago_precio" error={pagoForm.errors.precio} required>
                                            <Input
                                                id="pago_precio"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={pagoForm.data.precio}
                                                onChange={(e) => pagoForm.setData('precio', e.target.value)}
                                                placeholder="0.00"
                                            />
                                        </FormField>
                                        <FormField label="Dias" htmlFor="pago_dias" error={pagoForm.errors.dias} required>
                                            <Input
                                                id="pago_dias"
                                                type="number"
                                                min="1"
                                                value={pagoForm.data.dias}
                                                onChange={(e) => pagoForm.setData('dias', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField label="Personas" htmlFor="pago_personas" error={pagoForm.errors.personas} required>
                                            <Input
                                                id="pago_personas"
                                                type="number"
                                                min="1"
                                                value={pagoForm.data.personas}
                                                onChange={(e) => pagoForm.setData('personas', e.target.value)}
                                            />
                                        </FormField>
                                        <div className="pb-1">
                                            <span className="text-sm text-gray-500">Total: </span>
                                            <span className="font-mono font-medium">
                                                ${pagoTotal.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                            </span>
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={pagoForm.processing}>
                                        {pagoForm.processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                                        Agregar Pago Extra
                                    </Button>
                                </form>
                            </>
                        )}
                    </div>
                )}

                {/* Tab: Resumen */}
                {activeTab === 'resumen' && (
                    <div className="w-3/4 space-y-6">
                        <h2 className="text-lg font-semibold">Resumen del Destajo</h2>

                        <div className="stats stats-vertical w-full shadow lg:stats-horizontal">
                            <div className="stat">
                                <div className="stat-title">Total Fabricados</div>
                                <div className="stat-value text-lg">${totalFabricados.toLocaleString('es-MX', { minimumFractionDigits: 2 })}</div>
                                <div className="stat-desc">{destajo.fabricados?.length ?? 0} registros</div>
                            </div>
                            <div className="stat">
                                <div className="stat-title">Total Pagos Extra</div>
                                <div className="stat-value text-lg">${totalPagosExtra.toLocaleString('es-MX', { minimumFractionDigits: 2 })}</div>
                                <div className="stat-desc">{destajo.pagos_extra?.length ?? 0} registros</div>
                            </div>
                            <div className="stat">
                                <div className="stat-title">Total General</div>
                                <div className="stat-value text-lg">${totalGeneral.toLocaleString('es-MX', { minimumFractionDigits: 2 })}</div>
                            </div>
                        </div>

                        <div className="flex justify-start pt-4">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/destajos">Volver a Destajos</Link>
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
