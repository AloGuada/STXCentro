import type { ProdUbicacion } from '@/types/models';

type Props = {
    ubicaciones: Pick<ProdUbicacion, 'id' | 'nombre'>[];
    seleccionadas: number[];
    onChange: (ids: number[]) => void;
};

/**
 * Selección múltiple de ubicaciones. Un grupo puede trabajar en varias, así que
 * se muestran todas como chips y se marcan las que apliquen.
 */
export function UbicacionesMultiselect({ ubicaciones, seleccionadas, onChange }: Props) {
    if (ubicaciones.length === 0) {
        return (
            <p className="text-base-content/60 text-sm">
                No hay ubicaciones en el catálogo todavía. Créalas en Producción → Ubicaciones.
            </p>
        );
    }

    const alternar = (id: number) => {
        onChange(seleccionadas.includes(id) ? seleccionadas.filter((x) => x !== id) : [...seleccionadas, id]);
    };

    return (
        <div className="rounded-box border-base-300 flex flex-wrap gap-2 border p-3">
            {ubicaciones.map((u) => {
                const activa = seleccionadas.includes(u.id);

                return (
                    <label
                        key={u.id}
                        className={`badge cursor-pointer gap-1.5 py-3 ${activa ? 'badge-primary' : 'badge-ghost'}`}
                    >
                        <input
                            type="checkbox"
                            className="checkbox checkbox-xs"
                            checked={activa}
                            onChange={() => alternar(u.id)}
                        />
                        {u.nombre}
                    </label>
                );
            })}
        </div>
    );
}
