import { Button } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import { useState } from 'react';

type Empleado = { id: number; nombre: string; no_empleado: string | null; porcentaje: number };
type Grupo = { id: number; descripcion: string; linea: number | null; modulo: number | null; empleados: Empleado[] };
type Dia = { fecha: string; label: string };
type Destajo = { id: number; anio: number; semana: number; fecha_inicio: string; fecha_fin: string; cerrado: boolean };

type Props = { destajo: Destajo; grupos: Grupo[]; dias: Dia[] };

type Estado = 'asistencia' | 'falta' | 'vacaciones' | 'no_aplica';

const ESTADOS: Estado[] = ['asistencia', 'falta', 'vacaciones', 'no_aplica'];

const META: Record<Estado, { letra: string; label: string; cls: string; selectCls: string }> = {
    asistencia: { letra: 'A', label: 'Asistencia', cls: 'bg-emerald-500 text-white', selectCls: '!bg-emerald-100 !text-emerald-900' },
    falta: { letra: 'F', label: 'Falta', cls: 'bg-red-500 text-white', selectCls: '!bg-red-100 !text-red-900' },
    vacaciones: { letra: 'V', label: 'Vacaciones', cls: 'bg-sky-500 text-white', selectCls: '!bg-sky-100 !text-sky-900' },
    no_aplica: { letra: 'N', label: 'No aplica', cls: 'bg-base-300 text-base-content/50', selectCls: '!bg-base-200 !text-base-content/60' },
};

export default function DestajoAsistencia({ destajo, grupos, dias }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Destajos', href: '/admin/prod/destajos' },
        { title: `Semana ${destajo.semana}`, href: `/admin/prod/destajos/${destajo.id}` },
        { title: 'Asistencia', href: `/admin/prod/destajos/${destajo.id}/asistencia` },
    ];

    const [marcas, setMarcas] = useState<Record<string, Estado>>({});
    const get = (empId: number, fecha: string): Estado => marcas[`${empId}:${fecha}`] ?? 'asistencia';

    const set = (empId: number, fecha: string, estado: Estado) => {
        setMarcas((m) => ({ ...m, [`${empId}:${fecha}`]: estado }));
    };

    const totales = (empId: number): Record<Estado, number> => {
        const t: Record<Estado, number> = { asistencia: 0, falta: 0, vacaciones: 0, no_aplica: 0 };
        for (const d of dias) t[get(empId, d.fecha)]++;
        return t;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Asistencia · Semana ${destajo.semana}`} />

            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Asistencia · Semana {destajo.semana}</h1>
                        <p className="text-base-content/60 text-sm">
                            Año {destajo.anio} · {destajo.fecha_inicio} — {destajo.fecha_fin}. Clic en cada celda para
                            cambiar el estado.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/prod/destajos/${destajo.id}`}>
                                <ArrowLeftIcon className="size-4" /> Volver al destajo
                            </Link>
                        </Button>
                        <Button disabled title="Guardado y prorrateo pendientes (siguiente fase)">
                            <SaveIcon className="size-4" /> Guardar
                        </Button>
                    </div>
                </div>

                {/* Leyenda */}
                <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                    {ESTADOS.map((e) => (
                        <span key={e} className="inline-flex items-center gap-1.5">
                            <span className={`inline-flex size-5 items-center justify-center rounded text-xs font-bold ${META[e].cls}`}>
                                {META[e].letra}
                            </span>
                            {META[e].label}
                        </span>
                    ))}
                </div>

                {grupos.length === 0 ? (
                    <div className="rounded-box border border-dashed border-base-300 p-8 text-center">
                        <p className="text-base-content/60">
                            Este destajo no tiene grupos con producción ni pagos extra todavía.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {grupos.map((grupo) => (
                            <div key={grupo.id} className="rounded-box border border-base-300 overflow-hidden">
                                <div className="border-b border-base-300 bg-base-200 px-4 py-2 font-semibold">
                                    {grupo.descripcion}
                                    {(grupo.linea != null || grupo.modulo != null) && (
                                        <span className="text-base-content/60 ml-2 text-xs font-normal">
                                            Línea {grupo.linea ?? '-'} · Módulo {grupo.modulo ?? '-'}
                                        </span>
                                    )}
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="table table-sm">
                                        <thead>
                                            <tr>
                                                <th className="min-w-[180px]">Empleado</th>
                                                {dias.map((d) => (
                                                    <th key={d.fecha} className="text-center whitespace-nowrap">
                                                        {d.label}
                                                    </th>
                                                ))}
                                                {ESTADOS.map((e) => (
                                                    <th key={e} className="text-center" title={META[e].label}>
                                                        {META[e].letra}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {grupo.empleados.length === 0 ? (
                                                <tr>
                                                    <td colSpan={dias.length + 5} className="text-base-content/50 py-4 text-center">
                                                        Sin empleados en el grupo
                                                    </td>
                                                </tr>
                                            ) : (
                                                grupo.empleados.map((emp) => {
                                                    const t = totales(emp.id);
                                                    return (
                                                        <tr key={emp.id} className="hover">
                                                            <td>
                                                                <span className="font-medium">{emp.nombre}</span>{' '}
                                                                <span className="text-base-content/50 text-xs">
                                                                    ({emp.no_empleado || 's/n'})
                                                                </span>
                                                            </td>
                                                            {dias.map((d) => {
                                                                const estado = get(emp.id, d.fecha);
                                                                return (
                                                                    <td key={d.fecha} className="p-1">
                                                                        <Select
                                                                            value={estado}
                                                                            onValueChange={(v) => set(emp.id, d.fecha, v as Estado)}
                                                                            className={`select-xs min-w-[104px] ${META[estado].selectCls}`}
                                                                        >
                                                                            {ESTADOS.map((e) => (
                                                                                <SelectItem key={e} value={e}>
                                                                                    {META[e].label}
                                                                                </SelectItem>
                                                                            ))}
                                                                        </Select>
                                                                    </td>
                                                                );
                                                            })}
                                                            <td className="text-center font-mono">{t.asistencia}</td>
                                                            <td className="text-center font-mono">{t.falta}</td>
                                                            <td className="text-center font-mono">{t.vacaciones}</td>
                                                            <td className="text-center font-mono">{t.no_aplica}</td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
