import { Select, SelectItem } from '@/components/ui/select';
import { INSPECTORES, OBRAS, SEMANAS, SOLDADORES, SUBETAPAS, SUBTIPOS_PRIMERA, TIPOS_PIEZA } from './datos';

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

/** Lo que está acotando ahora mismo, en palabras. El Pareto necesita esto al lado. */
export function resumenFiltros(f: FiltrosTablero): string {
    const partes = [
        f.obra,
        f.fase && `${f.fase} transformación`,
        f.subetapa,
        f.soldador && `soldador ${f.soldador}`,
        f.inspector && `inspector ${f.inspector}`,
        f.tipo,
        f.subtipo1,
        f.semana,
        f.desde && `desde ${f.desde}`,
        f.hasta && `hasta ${f.hasta}`,
    ].filter(Boolean);

    return partes.length ? partes.join(' · ') : 'todas las obras y etapas';
}

type Props = {
    valor: FiltrosTablero;
    onChange: (valor: FiltrosTablero) => void;
};

export function BarraFiltros({ valor, onChange }: Props) {
    const set = (cambios: Partial<FiltrosTablero>) => onChange({ ...valor, ...cambios });

    const campo = (etiqueta: string, hijo: React.ReactNode) => (
        <label className="flex min-w-0 flex-col gap-1">
            <span className="text-base-content/60 text-[11px] font-medium">{etiqueta}</span>
            {hijo}
        </label>
    );

    const lista = (
        etiqueta: string,
        clave: keyof FiltrosTablero,
        opciones: string[],
        todos = 'Todas',
    ) =>
        campo(
            etiqueta,
            <Select
                className="select-sm"
                value={valor[clave]}
                onValueChange={(v) => set({ [clave]: v } as Partial<FiltrosTablero>)}
            >
                <SelectItem value="">{todos}</SelectItem>
                {opciones.map((o) => (
                    <SelectItem key={o} value={o}>
                        {o}
                    </SelectItem>
                ))}
            </Select>,
        );

    return (
        <div className="rounded-box border border-base-300 bg-base-100 p-3">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                {lista('Transformación', 'fase', ['1ª', '2ª', '3ª'])}
                {lista('Obra', 'obra', OBRAS)}
                {lista('Sub-etapa (2ª)', 'subetapa', SUBETAPAS)}
                {lista('Soldador', 'soldador', SOLDADORES, 'Todos')}
                {lista('Inspector', 'inspector', INSPECTORES, 'Todos')}
                {lista('Tipo de pieza', 'tipo', TIPOS_PIEZA, 'Todos')}
                {lista('Perfil/Placa (1ª)', 'subtipo1', SUBTIPOS_PRIMERA, 'Todos')}
                {lista('Semana', 'semana', SEMANAS)}
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
                    Acotado a: <span className="text-base-content font-medium">{resumenFiltros(valor)}</span>
                </span>
                <button type="button" className="btn btn-ghost btn-xs" onClick={() => onChange(FILTROS_VACIOS)}>
                    Limpiar
                </button>
            </div>
        </div>
    );
}
