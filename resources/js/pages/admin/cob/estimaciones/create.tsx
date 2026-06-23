import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { formatearMXN } from '@/components/cob/money-display';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobEstimacionNivel, CobPartida, Obra, Proyecto } from '@/types/models';

type ObraConPartidas = Pick<Obra, 'id' | 'no' | 'descripcion'> & { partidas: Pick<CobPartida, 'id' | 'descripcion' | 'tipo' | 'monto'>[] };

type Props = {
    proyecto: Pick<Proyecto, 'id' | 'no' | 'descripcion'>;
    obras: ObraConPartidas[];
    nextNumber: number;
};

export default function EstimacionCreate({ proyecto, obras, nextNumber }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/proyectos' },
        { title: `Proyecto ${proyecto.no}`, href: `/admin/cob/proyectos/${proyecto.id}` },
        { title: 'Nueva Estimación', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        nivel: 'proyecto' as CobEstimacionNivel,
        obra_id: '' as string,
        partida_ids: [] as number[],
        numero_estimacion: nextNumber,
        folio: '',
        tipo: '',
        fecha_emision: '',
        inicio: '',
        fin: '',
        monto_estimado: '',
        monto_total: '',
        moneda: 'MXN',
        comentarios: '',
    });

    const obraSel = obras.find((o) => String(o.id) === data.obra_id);

    const cambiarNivel = (nivel: CobEstimacionNivel) => {
        setData((prev) => ({
            ...prev,
            nivel,
            obra_id: nivel === 'proyecto' ? '' : prev.obra_id,
            partida_ids: nivel === 'partida' ? prev.partida_ids : [],
        }));
    };

    const togglePartida = (id: number) => {
        setData('partida_ids', data.partida_ids.includes(id) ? data.partida_ids.filter((p) => p !== id) : [...data.partida_ids, id]);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/proyectos/${proyecto.id}/estimaciones`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Estimación" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Estimación · Proyecto {proyecto.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {/* Nivel */}
                        <FormField label="Nivel" htmlFor="nivel" error={errors.nivel}>
                            <select className="select select-bordered w-full" value={data.nivel} onChange={(e) => cambiarNivel(e.target.value as CobEstimacionNivel)}>
                                <option value="proyecto">Global (todo el proyecto)</option>
                                <option value="obra">Una obra</option>
                                <option value="partida">Partidas de una obra</option>
                            </select>
                        </FormField>

                        {data.nivel !== 'proyecto' && (
                            <FormField label="Obra" htmlFor="obra_id" error={errors.obra_id} required>
                                <select
                                    className="select select-bordered w-full"
                                    value={data.obra_id}
                                    onChange={(e) => setData((prev) => ({ ...prev, obra_id: e.target.value, partida_ids: [] }))}
                                >
                                    <option value="">Seleccionar obra</option>
                                    {obras.map((o) => (
                                        <option key={o.id} value={o.id}>{o.no} — {o.descripcion}</option>
                                    ))}
                                </select>
                            </FormField>
                        )}

                        {data.nivel === 'partida' && obraSel && (
                            <FormField label="Partidas" htmlFor="partida_ids" error={errors.partida_ids}>
                                <div className="rounded-box max-h-48 space-y-1 overflow-auto border border-base-300 p-2">
                                    {(obraSel.partidas ?? []).map((p) => (
                                        <label key={p.id} className="flex cursor-pointer items-center gap-2 text-sm">
                                            <input type="checkbox" className="checkbox checkbox-sm" checked={data.partida_ids.includes(p.id)} onChange={() => togglePartida(p.id)} />
                                            <span className="capitalize">{p.tipo}</span>
                                            <span className="flex-1">{p.descripcion}</span>
                                            <span className="opacity-60">{formatearMXN(Number(p.monto))}</span>
                                        </label>
                                    ))}
                                    {(obraSel.partidas ?? []).length === 0 && <p className="text-sm opacity-50">La obra no tiene partidas.</p>}
                                </div>
                            </FormField>
                        )}

                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Número Estimación" htmlFor="numero_estimacion" error={errors.numero_estimacion} required>
                                <Input type="number" min="1" value={data.numero_estimacion} onChange={(e) => setData('numero_estimacion', Number(e.target.value))} />
                            </FormField>
                            <FormField label="Folio" htmlFor="folio" error={errors.folio}>
                                <Input value={data.folio} onChange={(e) => setData('folio', e.target.value)} />
                            </FormField>
                            <FormField label="Tipo" htmlFor="tipo" error={errors.tipo}>
                                <Input value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} placeholder="ej. normal, extraordinaria" />
                            </FormField>
                            <FormField label="Fecha Emisión" htmlFor="fecha_emision" error={errors.fecha_emision}>
                                <Input type="date" value={data.fecha_emision} onChange={(e) => setData('fecha_emision', e.target.value)} />
                            </FormField>
                            <FormField label="Inicio Periodo" htmlFor="inicio" error={errors.inicio}>
                                <Input type="date" value={data.inicio} onChange={(e) => setData('inicio', e.target.value)} />
                            </FormField>
                            <FormField label="Fin Periodo" htmlFor="fin" error={errors.fin}>
                                <Input type="date" value={data.fin} onChange={(e) => setData('fin', e.target.value)} />
                            </FormField>
                            <FormField label="Monto Estimado" htmlFor="monto_estimado" error={errors.monto_estimado} required>
                                <Input type="number" step="0.01" value={data.monto_estimado} onChange={(e) => setData('monto_estimado', e.target.value)} />
                            </FormField>
                            <FormField label="Monto Total" htmlFor="monto_total" error={errors.monto_total}>
                                <Input type="number" step="0.01" value={data.monto_total} onChange={(e) => setData('monto_total', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Comentarios" htmlFor="comentarios" error={errors.comentarios}>
                            <textarea className="textarea textarea-bordered w-full" value={data.comentarios} onChange={(e) => setData('comentarios', e.target.value)} rows={3} />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/cob/proyectos/${proyecto.id}`}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
