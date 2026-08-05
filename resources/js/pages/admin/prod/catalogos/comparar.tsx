import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { clavePieza, etiquetaDePieza } from '@/lib/prod/piezas';
import type { ProdCatalogo } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { ArrowRightIcon, MinusCircleIcon, PencilIcon, PlusCircleIcon } from 'lucide-react';

type PiezaSimple = { marca: string; etapa: string | null; descripcion: string };
type Cambio = { campo: string; antes: string | number | boolean | null; despues: string | number | boolean | null };
type PiezaModificada = PiezaSimple & { cambios: Cambio[] };

type Diff = {
    agregadas: PiezaSimple[];
    eliminadas: PiezaSimple[];
    modificadas: PiezaModificada[];
    sin_cambios: number;
};

type Props = {
    catalogo: ProdCatalogo;
    contra: ProdCatalogo;
    diff: Diff;
    versiones: Pick<ProdCatalogo, 'id' | 'version' | 'nombre' | 'vigente'>[];
};

const valor = (v: Cambio['antes']): string => {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'boolean') return v ? 'Sí' : 'No';
    return String(v);
};

export default function CatalogoComparar({ catalogo, contra, diff, versiones }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Catalogos', href: '/admin/prod/catalogos' },
        { title: catalogo.nombre, href: `/admin/prod/catalogos/${catalogo.id}` },
        { title: `v${catalogo.version} vs v${contra.version}`, href: '#' },
    ];

    const irA = (baseId: string, contraId: string) => {
        router.visit(`/admin/prod/catalogos/${baseId}/comparar/${contraId}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Comparar v${catalogo.version} vs v${contra.version}`} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Comparar versiones</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        {catalogo.obra ? `Obra ${catalogo.obra.no} — ${catalogo.obra.descripcion}` : catalogo.nombre}
                    </p>
                </div>

                <div className="mb-6 flex flex-wrap items-end gap-3">
                    <div className="w-40">
                        <label className="label label-text text-xs">Base</label>
                        <Select value={String(catalogo.id)} onValueChange={(v) => irA(v, String(contra.id))}>
                            {versiones.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    v{v.version}
                                    {v.vigente ? ' (vigente)' : ''}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                    <ArrowRightIcon className="text-base-content/40 mb-3 size-5" />
                    <div className="w-40">
                        <label className="label label-text text-xs">Contra</label>
                        <Select value={String(contra.id)} onValueChange={(v) => irA(String(catalogo.id), v)}>
                            {versiones.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    v{v.version}
                                    {v.vigente ? ' (vigente)' : ''}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                </div>

                <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="text-success text-2xl font-semibold">{diff.agregadas.length}</div>
                        <div className="text-base-content/60 text-sm">Agregadas</div>
                    </div>
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="text-error text-2xl font-semibold">{diff.eliminadas.length}</div>
                        <div className="text-base-content/60 text-sm">Eliminadas</div>
                    </div>
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="text-warning text-2xl font-semibold">{diff.modificadas.length}</div>
                        <div className="text-base-content/60 text-sm">Modificadas</div>
                    </div>
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="text-2xl font-semibold">{diff.sin_cambios}</div>
                        <div className="text-base-content/60 text-sm">Sin cambios</div>
                    </div>
                </div>

                {diff.agregadas.length === 0 && diff.eliminadas.length === 0 && diff.modificadas.length === 0 && (
                    <div className="rounded-box border-base-300 border border-dashed p-8 text-center">
                        <p className="text-base-content/60">
                            Las dos versiones tienen exactamente las mismas piezas.
                        </p>
                    </div>
                )}

                <div className="space-y-6">
                    {diff.modificadas.length > 0 && (
                        <section>
                            <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <PencilIcon className="text-warning size-4" />
                                Modificadas en v{contra.version}
                            </h2>
                            <div className="rounded-box border-base-300 overflow-hidden border">
                                <table className="table table-sm">
                                    <thead className="bg-base-200">
                                        <tr>
                                            <th>Marca</th>
                                            <th>Campo</th>
                                            <th className="text-right">v{catalogo.version}</th>
                                            <th className="text-right">v{contra.version}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {diff.modificadas.flatMap((p) =>
                                            p.cambios.map((c, i) => (
                                                <tr
                                                    key={`${clavePieza(p.marca, p.etapa)}-${c.campo}`}
                                                    className="hover"
                                                >
                                                    <td className="font-medium">
                                                        {i === 0 ? etiquetaDePieza(p.marca, p.etapa) : ''}
                                                    </td>
                                                    <td>{c.campo}</td>
                                                    <td className="text-base-content/60 text-right font-mono line-through">
                                                        {valor(c.antes)}
                                                    </td>
                                                    <td className="text-right font-mono font-semibold">
                                                        {valor(c.despues)}
                                                    </td>
                                                </tr>
                                            )),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    {diff.agregadas.length > 0 && (
                        <section>
                            <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <PlusCircleIcon className="text-success size-4" />
                                Agregadas en v{contra.version}
                            </h2>
                            <div className="rounded-box border-base-300 overflow-hidden border">
                                <table className="table table-sm">
                                    <thead className="bg-base-200">
                                        <tr>
                                            <th>Marca</th>
                                            <th>Descripcion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {diff.agregadas.map((p) => (
                                            <tr key={clavePieza(p.marca, p.etapa)} className="hover">
                                                <td className="font-medium">{etiquetaDePieza(p.marca, p.etapa)}</td>
                                                <td>{p.descripcion}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    {diff.eliminadas.length > 0 && (
                        <section>
                            <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <MinusCircleIcon className="text-error size-4" />
                                Ya no están en v{contra.version}
                            </h2>
                            <div className="rounded-box border-base-300 overflow-hidden border">
                                <table className="table table-sm">
                                    <thead className="bg-base-200">
                                        <tr>
                                            <th>Marca</th>
                                            <th>Descripcion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {diff.eliminadas.map((p) => (
                                            <tr key={clavePieza(p.marca, p.etapa)} className="hover">
                                                <td className="font-medium">{etiquetaDePieza(p.marca, p.etapa)}</td>
                                                <td>{p.descripcion}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
