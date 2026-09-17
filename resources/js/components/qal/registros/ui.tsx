/**
 * Piezas sueltas de la pantalla de Registros.
 *
 * La barra de filtros, la cabecera ordenable y la paginación viven aquí porque
 * las comparten las dos tablas —piezas y sublotes—, que son la misma pantalla
 * con dos conjuntos distintos.
 */

import { Link } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpIcon, ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Select, SelectItem } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { PaginatedData } from '@/types/models';

/** Un filtro de la barra: etiqueta arriba, control abajo, ancho propio. */
export function Filtro({ label, className, children }: { label: string; className?: string; children: ReactNode }) {
    return (
        <label className={cn('flex flex-col gap-1', className)}>
            <span className="text-base-content/60 text-xs font-medium">{label}</span>
            {children}
        </label>
    );
}

/**
 * Un desplegable de filtro, con su opción «todas» al frente. Las opciones
 * admiten el par [valor, texto] para los catálogos que filtran por id.
 */
export function FiltroSelect({
    label,
    value,
    onChange,
    opciones,
    todas,
    className,
}: {
    label: string;
    value: string;
    onChange: (valor: string) => void;
    opciones: readonly (string | readonly [string, string])[];
    todas: string;
    className?: string;
}) {
    return (
        <Filtro label={label} className={className}>
            <Select value={value} onValueChange={onChange} className="select-sm">
                <SelectItem value="">{todas}</SelectItem>
                {opciones.map((opcion) => {
                    const [valor, texto] = typeof opcion === 'string' ? [opcion, opcion] : opcion;
                    return (
                        <SelectItem key={valor} value={valor}>
                            {texto}
                        </SelectItem>
                    );
                })}
            </Select>
        </Filtro>
    );
}

export type Orden = { campo: string; dir: 'asc' | 'desc' };

/**
 * Cabecera ordenable.
 *
 * El orden es del servidor y va en la URL: ordenar sólo la página que se ve
 * engañaría, porque las filas de las demás páginas no entrarían en la cuenta.
 */
export function Th({
    campo,
    orden,
    onOrdenar,
    className,
    children,
}: {
    campo?: string;
    orden?: Orden;
    onOrdenar?: (campo: string) => void;
    className?: string;
    /** La columna de acciones no lleva titulo: es una cabecera vacia a proposito. */
    children?: ReactNode;
}) {
    if (!campo || !onOrdenar) {
        return <th className={cn('bg-base-200 text-base-content/70 text-xs font-semibold', className)}>{children}</th>;
    }

    const activo = orden?.campo === campo;

    return (
        <th className={cn('bg-base-200 p-0 text-xs font-semibold', className)}>
            <button
                type="button"
                onClick={() => onOrdenar(campo)}
                className={cn(
                    'hover:bg-base-300 flex w-full items-center gap-1 px-4 py-3 text-left transition-colors',
                    activo ? 'text-base-content' : 'text-base-content/70',
                )}
            >
                {children}
                {activo &&
                    (orden.dir === 'asc' ? <ArrowUpIcon className="size-3" /> : <ArrowDownIcon className="size-3" />)}
            </button>
        </th>
    );
}

const ESTATUS: Record<string, string> = { liberado: 'Liberado', rechazado: 'Rechazado', pendiente: 'Pendiente' };

/**
 * El estatus de una inspección.
 *
 * Tres estados y tres colores fijos: verde liberado, rojo rechazado, ámbar
 * pendiente. Es estado, no categoría — no se reusan para otra cosa.
 */
export function PastillaEstatus({ estatus }: { estatus: string }) {
    const tono =
        estatus === 'liberado'
            ? 'bg-success/15 text-success'
            : estatus === 'rechazado'
              ? 'bg-error/15 text-error'
              : 'bg-warning/15 text-warning';

    return <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-bold', tono)}>{ESTATUS[estatus] ?? estatus}</span>;
}

/** El veredicto de un sublote: sin veredicto es muestra incompleta, no aceptado. */
export function PastillaVeredicto({ veredicto, liberado }: { veredicto: string | null; liberado?: boolean }) {
    if (veredicto === null) {
        return <span className="bg-warning/15 text-warning rounded-full px-2.5 py-0.5 text-xs font-bold">EN CURSO</span>;
    }

    return (
        <span
            className={cn(
                'rounded-full px-2.5 py-0.5 text-xs font-bold',
                veredicto === 'aceptado' || liberado ? 'bg-success/15 text-success' : 'bg-error/15 text-error',
            )}
        >
            {veredicto.toUpperCase()}
        </span>
    );
}

/** El marco de una tabla de la pantalla, con su mensaje de vacío. */
export function Tabla({ vacio, children }: { vacio: string | null; children: ReactNode }) {
    if (vacio) {
        return (
            <div className="border-base-300 bg-base-100 text-base-content/60 rounded-xl border p-10 text-center">
                {vacio}
            </div>
        );
    }

    return (
        <div className="border-base-300 bg-base-100 overflow-x-auto rounded-xl border">
            <table className="table-sm table w-full whitespace-nowrap">{children}</table>
        </div>
    );
}

/** Anterior y siguiente, con los filtros que ya trae la URL del paginador. */
export function Paginacion<T>({ datos }: { datos: PaginatedData<T> }) {
    if (datos.last_page <= 1) {
        return null;
    }

    const boton = (url: string | null, contenido: ReactNode) =>
        url ? (
            <Link href={url} preserveState preserveScroll className="btn btn-sm btn-ghost">
                {contenido}
            </Link>
        ) : (
            <span className="btn btn-sm btn-ghost btn-disabled">{contenido}</span>
        );

    return (
        <div className="flex items-center justify-end gap-2 text-sm">
            <span className="text-base-content/60">
                {datos.from}–{datos.to} de {datos.total} · página {datos.current_page} de {datos.last_page}
            </span>
            {boton(datos.prev_page_url, <ChevronLeftIcon className="size-4" />)}
            {boton(datos.next_page_url, <ChevronRightIcon className="size-4" />)}
        </div>
    );
}
