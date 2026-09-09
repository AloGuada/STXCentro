import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClockIcon, PlusIcon, TriangleAlertIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Inventarios cíclicos', href: '/admin/almacen/conteos' },
];

type ConteoFila = {
    id: number;
    folio: string;
    origen: 'programado' | 'manual';
    origen_etiqueta: string;
    almacen: string | null;
    programa_id: number | null;
    fecha_programada: string;
    responsable: string | null;
    estatus: 'pendiente' | 'contando' | 'cerrado' | 'cancelado';
    estatus_etiqueta: string;
    vencido: boolean;
    renglones: number;
    contados: number;
    ajuste_folio: string | null;
};

type Programa = {
    id: number;
    almacen: string | null;
    fecha_inicio: string;
    /** La puso el sistema: el día en que cae la última hoja. */
    fecha_fin: string;
    dias_semana: number[];
    articulos_por_dia: number;
    articulos_programados: number;
    hojas: number;
    cerradas: number;
};

type AlmacenOpcion = {
    id: number;
    clave: string;
    nombre: string;
    obra: string | null;
    /** Cuántos artículos tiene para contar: lo que cabe en un programa. */
    articulos: number;
};

type Props = {
    conteos: {
        data: ConteoFila[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    programas: Programa[];
    filters: { almacen_id?: string; estatus?: string; programa_id?: string; search?: string };
    almacenes: AlmacenOpcion[];
    estatus: { value: string; label: string }[];
};

const ESTATUS_CLASE: Record<ConteoFila['estatus'], string> = {
    pendiente: 'badge-ghost',
    contando: 'badge-info',
    cerrado: 'badge-success',
    cancelado: 'badge-error',
};

/** ISO: 1 = lunes … 7 = domingo, que es como lo guarda el servidor. */
const DIAS_SEMANA = [
    { valor: 1, corto: 'L', nombre: 'Lunes' },
    { valor: 2, corto: 'M', nombre: 'Martes' },
    { valor: 3, corto: 'X', nombre: 'Miércoles' },
    { valor: 4, corto: 'J', nombre: 'Jueves' },
    { valor: 5, corto: 'V', nombre: 'Viernes' },
    { valor: 6, corto: 'S', nombre: 'Sábado' },
    { valor: 7, corto: 'D', nombre: 'Domingo' },
];

const hoy = () => new Date().toISOString().slice(0, 10);

const fechaLarga = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });

/**
 * Misma cuenta que hace el servidor: recorre el calendario desde el inicio y
 * se queda con los días marcados hasta juntar las hojas que hacen falta. La
 * última fecha es cuándo se termina. Se repite aquí para que el modal lo diga
 * antes de generar nada.
 */
function fechasDeConteo(inicio: string, dias: number[], cuantas: number): string[] {
    if (!inicio || dias.length === 0 || cuantas < 1) return [];
    const cursor = new Date(`${inicio}T00:00:00`);
    if (Number.isNaN(cursor.getTime())) return [];
    const fechas: string[] = [];
    while (fechas.length < cuantas) {
        const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
        if (dias.includes(iso)) {
            fechas.push(
                `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`,
            );
        }
        cursor.setDate(cursor.getDate() + 1);
    }
    return fechas;
}

/**
 * Inventario cíclico: contar un pedazo del almacén cada día en vez de parar
 * todo un fin de semana al año.
 *
 * El **programa** reparte el almacén: se elige cuántos artículos por día y qué
 * días de la semana, y el sistema dice cuántas hojas salen y en qué fecha se
 * termina. Los artículos de cada hoja van al azar. Ninguna hoja mueve el
 * saldo: al cerrar genera un **ajuste**, que es el único documento al que el
 * kardex le permite corregir existencias.
 */
export default function ConteosIndex({ conteos, programas, filters, almacenes, estatus }: Props) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const [abierto, setAbierto] = useState(false);

    const form = useForm({
        almacen_id: filters.almacen_id ?? '',
        fecha_inicio: hoy(),
        dias_semana: [1, 2, 3, 4, 5] as number[],
        articulos_por_dia: 20,
    });

    const almacenElegido = almacenes.find((a) => String(a.id) === String(form.data.almacen_id));

    // Lo que va a pasar, antes de que pase: cuántas hojas y cuándo se termina.
    // La duración es una salida, no algo que se captura.
    const previa = useMemo(() => {
        const articulos = almacenElegido?.articulos ?? 0;
        const porDia = Math.max(1, Number(form.data.articulos_por_dia) || 0);
        const hojas = Math.ceil(articulos / porDia);
        const fechas = fechasDeConteo(form.data.fecha_inicio, form.data.dias_semana, hojas);
        const fin = fechas.length > 0 ? fechas[fechas.length - 1] : null;
        const diasNaturales =
            fin === null
                ? 0
                : Math.round(
                      (new Date(`${fin}T00:00:00`).getTime() - new Date(`${form.data.fecha_inicio}T00:00:00`).getTime()) /
                          86_400_000,
                  ) + 1;

        return { articulos, hojas, fin, diasNaturales };
    }, [form.data, almacenElegido]);

    const alternarDia = (valor: number, marcado: boolean) => {
        const actual = form.data.dias_semana;
        form.setData(
            'dias_semana',
            marcado ? [...actual, valor].sort((a, b) => a - b) : actual.filter((d) => d !== valor),
        );
    };

    const generar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/almacen/conteos/programas', {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
        });
    };

    const filtrar = (cambios: Partial<Props['filters']>) =>
        router.get('/admin/almacen/conteos', { ...filters, ...cambios }, { preserveState: true, replace: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventarios cíclicos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Inventarios cíclicos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Se cuenta una parte del almacén cada día, sin parar la operación. Tú dices cuántos
                            artículos por día y qué días; el sistema los sortea y te dice cuándo terminas.
                        </p>
                    </div>
                    <Button variant="primary" onClick={() => setAbierto(true)}>
                        <PlusIcon className="size-4" />
                        Programar inventario
                    </Button>
                </div>

                {flash?.success && (
                    <div className="alert alert-success mb-4">
                        <span>{flash.success}</span>
                    </div>
                )}

                {programas.length > 0 && (
                    <div className="rounded-box border-base-300 mb-6 border">
                        <div className="border-base-300 flex items-center gap-2 border-b px-4 py-3">
                            <CalendarClockIcon className="text-base-content/50 size-4" />
                            <h2 className="font-medium">Programas recientes</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead className="bg-base-200">
                                    <tr>
                                        <th>Almacén</th>
                                        <th>Del</th>
                                        <th>Al</th>
                                        <th>Días</th>
                                        <th className="text-right">Por día</th>
                                        <th className="text-right">Hojas</th>
                                        <th className="text-right">Artículos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {programas.map((p) => (
                                        <tr key={p.id} className="hover">
                                            <td>
                                                <span className="badge badge-sm badge-ghost font-mono">{p.almacen}</span>
                                            </td>
                                            <td className="font-mono text-xs">{p.fecha_inicio}</td>
                                            <td className="font-mono text-xs">{p.fecha_fin}</td>
                                            <td className="text-xs">
                                                {DIAS_SEMANA.filter((d) => p.dias_semana.includes(d.valor))
                                                    .map((d) => d.corto)
                                                    .join(' ')}
                                            </td>
                                            <td className="text-right font-mono">{p.articulos_por_dia}</td>
                                            <td className="text-right font-mono">
                                                <button
                                                    type="button"
                                                    className="link link-hover"
                                                    onClick={() => filtrar({ programa_id: String(p.id) })}
                                                >
                                                    {p.cerradas}/{p.hojas}
                                                </button>
                                            </td>
                                            <td className="text-right font-mono">{p.articulos_programados}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-56">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined })}
                        >
                            <SelectItem value="">Todos los almacenes</SelectItem>
                            {almacenes.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {a.clave} — {a.nombre}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-48">
                        <label className="label label-text text-xs">Estado</label>
                        <Select value={filters.estatus ?? ''} onValueChange={(v) => filtrar({ estatus: v || undefined })}>
                            <SelectItem value="">Todos</SelectItem>
                            {estatus.map((e) => (
                                <SelectItem key={e.value} value={e.value}>
                                    {e.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    {filters.programa_id && (
                        <button className="btn btn-sm btn-ghost" onClick={() => filtrar({ programa_id: undefined })}>
                            Quitar filtro de programa
                        </button>
                    )}
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Origen</th>
                                <th>Almacén</th>
                                <th>Programado</th>
                                <th>Responsable</th>
                                <th className="text-right">Avance</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {conteos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-base-content/50 py-6 text-center">
                                        No hay hojas de conteo con esos filtros. Programa un inventario para generar las
                                        primeras.
                                    </td>
                                </tr>
                            ) : (
                                conteos.data.map((c) => (
                                    <tr key={c.id} className="hover">
                                        <td>
                                            <Link
                                                href={`/admin/almacen/conteos/${c.id}`}
                                                className="link link-hover font-mono font-medium"
                                            >
                                                {c.folio}
                                            </Link>
                                            {c.ajuste_folio && (
                                                <span className="text-base-content/50 block text-xs">
                                                    → {c.ajuste_folio}
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost">{c.origen_etiqueta}</span>
                                        </td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{c.almacen}</span>
                                        </td>
                                        <td className="font-mono text-xs">
                                            {c.fecha_programada}
                                            {c.vencido && (
                                                <span className="text-error ml-1" title="Se pasó la fecha">
                                                    <TriangleAlertIcon className="inline size-3" />
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-sm">
                                            {c.responsable ?? <span className="text-base-content/40">—</span>}
                                        </td>
                                        <td className="text-right font-mono text-xs">
                                            {c.contados}/{c.renglones}
                                        </td>
                                        <td>
                                            <span className={`badge badge-sm ${ESTATUS_CLASE[c.estatus]}`}>
                                                {c.estatus_etiqueta}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {conteos.links.length > 3 && (
                    <div className="join mt-4">
                        {conteos.links.map((l, i) => (
                            <Link
                                key={i}
                                href={l.url ?? '#'}
                                className={`join-item btn btn-sm ${l.active ? 'btn-active' : ''} ${!l.url ? 'btn-disabled' : ''}`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                                preserveState
                            />
                        ))}
                    </div>
                )}

                <p className="text-base-content/60 mt-4 text-sm">
                    La hoja <strong>no mueve el saldo</strong>. Al cerrarse genera un <strong>ajuste</strong> con las
                    diferencias, que es el único documento al que el kardex le permite corregir existencias.
                </p>
            </div>

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent className="max-w-2xl">
                    <form onSubmit={generar}>
                        <DialogHeader>
                            <DialogTitle>Programar inventario</DialogTitle>
                            <DialogDescription className="py-1 text-sm">
                                Di cuántos artículos se cuentan por día y qué días. El sistema los sortea, arma una
                                hoja por día hasta cubrir todo el almacén y te dice cuándo terminas.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField label="Almacén" htmlFor="almacen_id" required error={form.errors.almacen_id}>
                                <Select
                                    value={String(form.data.almacen_id)}
                                    onValueChange={(v) => form.setData('almacen_id', v)}
                                    placeholder="Elige el almacén"
                                >
                                    {almacenes.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''} · {a.articulos} art.
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Empieza el"
                                htmlFor="fecha_inicio"
                                required
                                error={form.errors.fecha_inicio}
                            >
                                <Input
                                    id="fecha_inicio"
                                    type="date"
                                    value={form.data.fecha_inicio}
                                    onChange={(e) => form.setData('fecha_inicio', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Artículos por día"
                                htmlFor="articulos_por_dia"
                                required
                                error={form.errors.articulos_por_dia}
                            >
                                <Input
                                    id="articulos_por_dia"
                                    type="number"
                                    min={1}
                                    max={500}
                                    value={form.data.articulos_por_dia}
                                    onChange={(e) => form.setData('articulos_por_dia', Number(e.target.value))}
                                />
                            </FormField>
                        </div>

                        <FormField
                            label="Días de la semana en que se cuenta"
                            htmlFor="dias_semana"
                            required
                            error={form.errors.dias_semana}
                            className="mt-2"
                        >
                            <div className="flex flex-wrap gap-3" id="dias_semana">
                                {DIAS_SEMANA.map((d) => (
                                    <label key={d.valor} className="flex cursor-pointer items-center gap-2">
                                        <Checkbox
                                            className="checkbox-sm"
                                            checked={form.data.dias_semana.includes(d.valor)}
                                            onCheckedChange={(v) => alternarDia(d.valor, v)}
                                        />
                                        <span className="text-sm">{d.nombre}</span>
                                    </label>
                                ))}
                            </div>
                        </FormField>

                        <div className="bg-base-200 rounded-box mt-4 p-3 text-sm">
                            {!almacenElegido ? (
                                <span className="text-base-content/60">
                                    Elige un almacén para ver cuántas hojas saldrían y cuándo terminas.
                                </span>
                            ) : previa.articulos === 0 ? (
                                <span className="text-error">Este almacén no tiene artículos que contar.</span>
                            ) : form.data.dias_semana.length === 0 ? (
                                <span className="text-error">Marca al menos un día de la semana.</span>
                            ) : previa.fin === null ? (
                                <span className="text-error">Revisa la fecha de inicio.</span>
                            ) : (
                                <>
                                    <strong>{previa.hojas}</strong> hoja(s) para {previa.articulos} artículos. Termina
                                    el <strong>{fechaLarga(previa.fin)}</strong>: {previa.diasNaturales} día(s)
                                    naturales desde el inicio.
                                    <span className="text-base-content/60 block">
                                        Para acabar antes, sube los artículos por día o marca más días de la semana.
                                    </span>
                                </>
                            )}
                        </div>

                        <DialogFooter>
                            <DialogClose className="btn-ghost">Cancelar</DialogClose>
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={form.processing || !almacenElegido || previa.hojas === 0}
                            >
                                Generar {previa.hojas > 0 ? `${previa.hojas} hoja(s)` : 'hojas'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
