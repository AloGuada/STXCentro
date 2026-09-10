/**
 * Piezas sueltas de la pantalla de Registros.
 *
 * La barra de filtros y la cabecera ordenable viven aquí porque las comparten
 * las dos tablas —piezas y sublotes—, que son la misma pantalla con dos
 * conjuntos distintos.
 */

import { ArrowDownIcon, ArrowUpIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Select, SelectItem } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { Estatus } from './datos';

/** Un filtro de la barra: etiqueta arriba, control abajo, ancho propio. */
export function Filtro({ label, className, children }: { label: string; className?: string; children: ReactNode }) {
    return (
        <label className={cn('flex flex-col gap-1', className)}>
            <span className="text-base-content/60 text-xs font-medium">{label}</span>
            {children}
        </label>
    );
}

/** Un desplegable de filtro, con su opción «todas» al frente. */
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
    opciones: readonly string[];
    todas: string;
    className?: string;
}) {
    return (
        <Filtro label={label} className={className}>
            <Select value={value} onValueChange={onChange} className="select-sm">
                <SelectItem value="">{todas}</SelectItem>
                {opciones.map((opcion) => (
                    <SelectItem key={opcion} value={opcion}>
                        {opcion}
                    </SelectItem>
                ))}
            </Select>
        </Filtro>
    );
}

export type Orden = { campo: string; dir: 1 | -1 };

/**
 * Cabecera ordenable.
 *
 * El orden es del cliente, no del servidor: la maqueta trae sus filas en el
 * front. Cuando esto lea de la base habrá que moverlo a la URL como en PND,
 * porque ordenar sólo la página que se ve engaña.
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
                    (orden.dir > 0 ? <ArrowUpIcon className="size-3" /> : <ArrowDownIcon className="size-3" />)}
            </button>
        </th>
    );
}

/**
 * El estatus de un registro.
 *
 * Tres estados y tres colores fijos: verde liberado, rojo rechazado, ámbar
 * pendiente. Es estado, no categoría — no se reusan para otra cosa.
 */
export function PastillaEstatus({ estatus }: { estatus: Estatus }) {
    const tono =
        estatus === 'Liberado'
            ? 'bg-success/15 text-success'
            : estatus === 'Rechazado'
              ? 'bg-error/15 text-error'
              : 'bg-warning/15 text-warning';

    return <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-bold', tono)}>{estatus}</span>;
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
