import { Select, SelectItem } from '@/components/ui/select';
import type { Opcion, OpcionesFiltros } from './tipos';

/**
 * La barra de filtros del tablero. Acota TODO lo que hay debajo.
 *
 * Va una sola vez y arriba de todo a propósito. Un filtro dentro de una tarjeta
 * hace que dos gráficas de la misma pantalla estén mirando periodos distintos
 * sin que se note, que es la forma más rápida de sacar una conclusión falsa.
 *
 * Los desplegables que sí viven dentro de una tarjeta —«Pareto de soldadura» o
 * «Rechazo por soldador»— no son filtros: cambian la pregunta de esa tarjeta,
 * no el trozo de datos que se está mirando.
 *
 * Los filtros viajan en la URL y los aplica el servidor. Obra, soldador,
 * inspector y tipo van por id; las listas sólo traen lo que tiene inspecciones.
 */

export type FiltrosTablero = {
    fase: string;
    obra: string;
    subetapa: string;
    soldador: string;
    inspector: string;
    tipo: string;
    subtipo1: string;
    desde: string;
    hasta: string;
    semana: string;
};

export const FILTROS_VACIOS: FiltrosTablero = {
    fase: '',
    obra: '',
    subetapa: '',
    soldador: '',
    inspector: '',
    tipo: '',
    subtipo1: '',
    desde: '',
    hasta: '',
    semana: '',
};

const FASES: Opcion[] = [
    { valor: '1ª', texto: '1ª' },
    { valor: '2ª', texto: '2ª' },
    { valor: '3ª', texto: '3ª' },
];

const SUBETAPAS: Opcion[] = [
    { valor: 'armado_vestido', texto: 'Armado y vestido' },
    { valor: 'soldado', texto: 'Soldado' },
];

const SUBTIPOS_PRIMERA: Opcion[] = [
    { valor: 'perfil', texto: 'Perfil' },
    { valor: 'placa', texto: 'Placa' },
];

/** Los filtros como llegan del servidor, con los vacíos en nulo. */
export function filtrosDeLaUrl(filtros: Record<keyof FiltrosTablero, string | null>): FiltrosTablero {
    return Object.fromEntries(
        Object.keys(FILTROS_VACIOS).map((clave) => [clave, filtros[clave as keyof FiltrosTablero] ?? '']),
    ) as FiltrosTablero;
}

/** Lo que está acotando ahora mismo, en palabras. El Pareto necesita esto al lado. */
export function resumenFiltros(f: FiltrosTablero, opciones: OpcionesFiltros): string {
    const texto = (lista: Opcion[], valor: string) => lista.find((o) => o.valor === valor)?.texto ?? valor;

    const partes = [
        f.obra && texto(opciones.obras, f.obra),
        f.fase && `${f.fase} transformación`,
        f.subetapa && texto(SUBETAPAS, f.subetapa),
        f.soldador && `soldador ${texto(opciones.soldadores, f.soldador)}`,
        f.inspector && `inspector ${texto(opciones.inspectores, f.inspector)}`,
        f.tipo && texto(opciones.tipos, f.tipo),
        f.subtipo1 && texto(SUBTIPOS_PRIMERA, f.subtipo1),
        f.semana,
        f.desde && `desde ${f.desde}`,
        f.hasta && `hasta ${f.hasta}`,
    ].filter(Boolean);

    return partes.length ? partes.join(' · ') : 'todas las obras y etapas';
}

type Props = {
    valor: FiltrosTablero;
    opciones: OpcionesFiltros;
    onChange: (valor: FiltrosTablero) => void;
};

export function BarraFiltros({ valor, opciones, onChange }: Props) {
    const set = (cambios: Partial<FiltrosTablero>) => onChange({ ...valor, ...cambios });

    const campo = (etiqueta: string, hijo: React.ReactNode) => (
        <label className="flex min-w-0 flex-col gap-1">
            <span className="text-base-content/60 text-[11px] font-medium">{etiqueta}</span>
            {hijo}
        </label>
    );

    const lista = (etiqueta: string, clave: keyof FiltrosTablero, lista: Opcion[], todos = 'Todas') =>
        campo(
            etiqueta,
            <Select
                className="select-sm"
                value={valor[clave]}
                onValueChange={(v) => set({ [clave]: v } as Partial<FiltrosTablero>)}
            >
                <SelectItem value="">{todos}</SelectItem>
                {lista.map((o) => (
                    <SelectItem key={o.valor} value={o.valor}>
                        {o.texto}
                    </SelectItem>
                ))}
            </Select>,
        );

    return (
        <div className="rounded-box border border-base-300 bg-base-100 p-3">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                {lista('Transformación', 'fase', FASES)}
                {lista('Obra', 'obra', opciones.obras)}
                {lista('Sub-etapa (2ª)', 'subetapa', SUBETAPAS)}
                {lista('Soldador', 'soldador', opciones.soldadores, 'Todos')}
                {lista('Inspector', 'inspector', opciones.inspectores, 'Todos')}
                {lista('Tipo de pieza', 'tipo', opciones.tipos, 'Todos')}
                {lista('Perfil/Placa (1ª)', 'subtipo1', SUBTIPOS_PRIMERA, 'Todos')}
                {lista(
                    'Semana',
                    'semana',
                    opciones.semanas.map((s) => ({ valor: s, texto: s })),
                )}
                {campo(
                    'Desde',
                    <input
                        type="date"
                        className="input input-bordered input-sm w-full"
                        value={valor.desde}
                        onChange={(e) => set({ desde: e.target.value })}
                    />,
                )}
                {campo(
                    'Hasta',
                    <input
                        type="date"
                        className="input input-bordered input-sm w-full"
                        value={valor.hasta}
                        onChange={(e) => set({ hasta: e.target.value })}
                    />,
                )}
            </div>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-base-300 pt-2">
                <span className="text-base-content/60 text-xs">
                    Acotado a: <span className="text-base-content font-medium">{resumenFiltros(valor, opciones)}</span>
                </span>
                <button type="button" className="btn btn-ghost btn-xs" onClick={() => onChange(FILTROS_VACIOS)}>
                    Limpiar
                </button>
            </div>
        </div>
    );
}
