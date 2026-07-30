import { Button } from '@/components/ui/button';
import { FormattedDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckCircle2Icon, Loader2Icon, SaveIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

type Empleado = { id: number; nombre: string; no_empleado: string | null; porcentaje: number };
type Grupo = { id: number; descripcion: string; linea: number | null; modulo: number | null; empleados: Empleado[] };
type Dia = { fecha: string; label: string };
type Destajo = { id: number; anio: number; semana: number; fecha_inicio: string; fecha_fin: string; cerrado: boolean };

type Estado = 'asistencia' | 'falta' | 'vacaciones' | 'no_aplica';

type Props = {
    destajo: Destajo;
    grupos: Grupo[];
    dias: Dia[];
    /** Marcas ya guardadas, indexadas por "empleadoId:fecha". */
    marcas: Record<string, Estado>;
};

const ESTADOS: Estado[] = ['asistencia', 'falta', 'vacaciones', 'no_aplica'];

const META: Record<Estado, { letra: string; label: string; cls: string }> = {
    asistencia: { letra: 'A', label: 'Asistencia', cls: 'bg-emerald-500 text-white' },
    falta: { letra: 'F', label: 'Falta', cls: 'bg-red-500 text-white' },
    vacaciones: { letra: 'V', label: 'Vacaciones', cls: 'bg-sky-500 text-white' },
    no_aplica: { letra: 'N', label: 'No aplica', cls: 'bg-base-300 text-base-content/50' },
};

export default function DestajoAsistencia({ destajo, grupos, dias, marcas: guardadas }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Destajos', href: '/admin/prod/destajos' },
        { title: `Semana ${destajo.semana}`, href: `/admin/prod/destajos/${destajo.id}` },
        { title: 'Asistencia', href: `/admin/prod/destajos/${destajo.id}/asistencia` },
    ];

    const [marcas, setMarcas] = useState<Record<string, Estado>>(guardadas);
    const [sucio, setSucio] = useState(false);
    const form = useForm({});

    const get = (empId: number, fecha: string): Estado => marcas[`${empId}:${fecha}`] ?? 'asistencia';

    const cycle = (empId: number, fecha: string) => {
        if (destajo.cerrado) return;

        const key = `${empId}:${fecha}`;
        const actual = marcas[key] ?? 'asistencia';
        const next = ESTADOS[(ESTADOS.indexOf(actual) + 1) % ESTADOS.length];
        setMarcas((m) => ({ ...m, [key]: next }));
        setSucio(true);
    };

    /**
     * Se manda la cuadrícula completa: las celdas que nadie tocó valen
     * "asistencia", que es justo lo que la pantalla está mostrando.
     */
    const guardar = () => {
        const payload = grupos.flatMap((g) =>
            g.empleados.flatMap((emp) =>
                dias.map((d) => ({
                    grupo_empleado_id: emp.id,
                    fecha: d.fecha,
                    estado: get(emp.id, d.fecha),
                })),
            ),
        );

        form.transform(() => ({ marcas: payload }));
        form.post(`/admin/prod/destajos/${destajo.id}/asistencia`, {
            preserveScroll: true,
            onSuccess: () => setSucio(false),
        });
    };

    const totalCeldas = grupos.reduce((acc, g) => acc + g.empleados.length * dias.length, 0);

    const capturadas = useMemo(
        () =>
            grupos.reduce(
                (acc, g) =>
                    acc +
                    g.empleados.reduce(
                        (a, emp) => a + dias.filter((d) => guardadas[`${emp.id}:${d.fecha}`] !== undefined).length,
                        0,
                    ),
                0,
            ),
        [grupos, dias, guardadas],
    );

    const completa = totalCeldas > 0 && capturadas === totalCeldas;

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
                            Año {destajo.anio} · <FormattedDate value={destajo.fecha_inicio} /> —{' '}
                            <FormattedDate value={destajo.fecha_fin} />. Clic en cada celda para cambiar el estado.
                        </p>
                        {totalCeldas > 0 &&
                            (completa && !sucio ? (
                                <p className="text-success mt-1 flex items-center gap-1 text-sm">
                                    <CheckCircle2Icon className="size-4" /> Asistencia completa de la semana
                                </p>
                            ) : (
                                <p className="text-warning mt-1 text-sm">
                                    {sucio
                                        ? 'Hay cambios sin guardar.'
                                        : `Faltan ${totalCeldas - capturadas} de ${totalCeldas} celdas por guardar.`}{' '}
                                    El destajo no se puede cerrar sin la asistencia completa.
                                </p>
                            ))}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/prod/destajos/${destajo.id}`}>
                                <ArrowLeftIcon className="size-4" /> Volver al destajo
                            </Link>
                        </Button>
                        <Button onClick={guardar} disabled={form.processing || destajo.cerrado || totalCeldas === 0}>
                            {form.processing ? (
                                <Loader2Icon className="size-4 animate-spin" />
                            ) : (
                                <SaveIcon className="size-4" />
                            )}
                            Guardar
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
                                                                    <td key={d.fecha} className="text-center">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => cycle(emp.id, d.fecha)}
                                                                            title={META[estado].label}
                                                                            className={`inline-flex size-7 items-center justify-center rounded text-xs font-bold ${META[estado].cls}`}
                                                                        >
                                                                            {META[estado].letra}
                                                                        </button>
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
