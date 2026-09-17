import { useState } from 'react';
import { CatalogoPanel, type FilaCatalogo } from '@/components/qal/catalogo-panel';
import type { QalAmbitoDefecto, QalDefecto, QalOpcionAmbitoDefecto } from '@/types/models';

type Props = {
    defectos: QalDefecto[];
    ambitos: QalOpcionAmbitoDefecto[];
    puedeCrear: boolean;
    puedeEditar: boolean;
};

/** Qué se marca con cada lista, para quien la administra. */
const DESCRIPCIONES: Record<QalAmbitoDefecto, string> = {
    soldadura: 'Los que marca el inspector en la soldadura de 2ª, con su contador. También los usan los accesorios.',
    pintura: 'Los que marca el inspector de 3ª.',
    accesorio_dimensional: 'Fallas dimensionales de una unidad rechazada en el lote de accesorios.',
    accesorio_barrenos: 'Fallas de barrenos habilitados de una unidad rechazada en el lote de accesorios.',
    accesorio_limpieza: 'Fallas de limpieza de una unidad rechazada en el lote de accesorios.',
};

/**
 * El catálogo de defectos: una sola tabla, una lista por ámbito.
 *
 * El ámbito se elige arriba y viaja fijo en cada alta; al editar no cambia,
 * porque mover un defecto de lista cambiaría el significado de lo ya capturado.
 */
export function CatalogoDefectos({ defectos, ambitos, puedeCrear, puedeEditar }: Props) {
    const [ambito, setAmbito] = useState<QalAmbitoDefecto>(ambitos[0]?.valor ?? 'soldadura');
    const filas = defectos.filter((defecto) => defecto.ambito === ambito);

    return (
        <div>
            <div className="mb-4 flex flex-wrap gap-2">
                {ambitos.map((opcion) => {
                    const activos = defectos.filter((d) => d.ambito === opcion.valor && d.activo).length;

                    return (
                        <button
                            key={opcion.valor}
                            type="button"
                            className={`btn btn-sm ${opcion.valor === ambito ? 'btn-primary' : 'btn-outline'}`}
                            onClick={() => setAmbito(opcion.valor)}
                        >
                            <span className="font-mono text-xs opacity-70">{opcion.fase}</span>
                            {opcion.etiqueta}
                            <span className="text-xs opacity-60">{activos}</span>
                        </button>
                    );
                })}
            </div>

            <CatalogoPanel
                key={ambito}
                clave={`defectos-${ambito}`}
                ruta="defectos"
                fijos={{ ambito }}
                descripcion={DESCRIPCIONES[ambito]}
                campos={[{ k: 'nombre', label: 'Defecto', ph: 'Socavación', req: true, ancho: 'ancho' }]}
                filas={filas as unknown as FilaCatalogo[]}
                puedeCrear={puedeCrear}
                puedeEditar={puedeEditar}
                ayuda={
                    <>
                        Las capturas guardan el defecto por su id: corregir un nombre corrige también lo ya
                        capturado, en vez de partir el histórico en dos defectos distintos.
                    </>
                }
            />
        </div>
    );
}
