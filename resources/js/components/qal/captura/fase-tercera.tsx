/**
 * 3ª transformación — pintura.
 *
 * Dos piezas propias: la rejilla de espesores (SSPC-PA2) y la prueba de
 * adherencia (F-STX-CA-08, ASTM D3359). La rejilla es lo que decide si la
 * pieza pasa, y su regla —una lectura baja avisa, el promedio decide— se
 * calcula sola para que el inspector no tenga que echar cuentas de pie.
 */

import type { Campos } from './estado';
import { evidenciaDe, type Evidencia, type FotoGuardada } from './fotos';
import { ESPESOR_MEDICIONES_BASE, ESPESOR_MEDICIONES_MAX, resumirEspesores } from './reglas';
import { Boton, Campo, Chips, Pista, Rejilla, Selector, Tarjeta, Texto } from './ui';

/** Clasificación de la tira según el método de la norma. */
const CLASIFICACIONES: [string, string][] = [
    ['5A', '5A — sin desprendimiento (método A)'],
    ['4A', '4A — en incisiones/intersección'],
    ['3A', '3A — > 1.6 mm'],
    ['2A', '2A — > 3.2 mm'],
    ['1A', '1A — mayor parte'],
    ['0A', '0A — total'],
    ['5B', '5B — 0% afectado (método B)'],
    ['4B', '4B — < 5%'],
    ['3B', '3B — 5-15%'],
    ['2B', '2B — 15-35%'],
    ['1B', '1B — 35-65%'],
    ['0B', '0B — peor que 1B'],
];

export function FaseTercera({
    campos,
    lecturas,
    onLecturas,
    mediciones,
    onMediciones,
    defectos,
    onDefectos,
    defectosCatalogo,
    adherenciaAbierta,
    onAdherencia,
    fotos,
    onFotos,
    guardadas = [],
    onQuitarGuardada,
    onAviso,
}: {
    campos: Campos;
    /** [medición][lectura] — 15 × 3, como en el formato. */
    lecturas: string[][];
    onLecturas: (lecturas: string[][]) => void;
    /** Cuántas mediciones están a la vista (5 a 15). */
    mediciones: number;
    onMediciones: (mediciones: number) => void;
    defectos: string[];
    onDefectos: (defectos: string[]) => void;
    /** Los defectos de pintura activos del catálogo. */
    defectosCatalogo: string[];
    adherenciaAbierta: boolean;
    onAdherencia: (abierta: boolean) => void;
    fotos: Evidencia[];
    onFotos: (fotos: Evidencia[]) => void;
    /** Al corregir: la evidencia que ya estaba guardada. */
    guardadas?: FotoGuardada[];
    onQuitarGuardada?: (id: number) => void;
    onAviso: (mensaje: string, tono?: 'ok' | 'error') => void;
}) {
    const resumen = resumirEspesores(lecturas, mediciones, campos.v('p3_req'));
    const requerido = parseFloat(campos.v('p3_req'));

    const escribir = (medicion: number, lectura: number, valor: string) => {
        const copia = lecturas.map((fila) => [...fila]);
        copia[medicion][lectura] = valor;
        onLecturas(copia);
    };

    const agregarArchivos = async (archivos: FileList | null) => {
        if (!archivos?.length) {
            return;
        }
        const nuevas = await Promise.all(Array.from(archivos).map(evidenciaDe));
        onFotos([...fotos, ...nuevas]);
        onAviso(`${nuevas.length} evidencia(s) adjuntada(s)`, 'ok');
    };

    const quitarFoto = (indice: number) => {
        const url = fotos[indice]?.url;
        if (url) {
            URL.revokeObjectURL(url);
        }
        onFotos(fotos.filter((_, i) => i !== indice));
    };

    return (
        <>
            <Tarjeta titulo="Espesores de pintura (3ª)" etiqueta="pintura">
                <Pista>
                    Método SSPC-PA2. El promedio y la aceptación (incluida la regla del 80% por punto) se calculan
                    solos.
                </Pista>
                <Rejilla>
                    <Campo label="Espesor requerido por proyecto (mils)">
                        <Texto tipo="number" value={campos.v('p3_req')} onChange={(valor) => campos.set('p3_req', valor)} placeholder="ej. 3" />
                    </Campo>
                    <Campo label="Método de prueba">
                        <Selector
                            value={campos.v('p3_metodo')}
                            onChange={(valor) => campos.set('p3_metodo', valor)}
                            opciones={['SSPC-PA2 (calibre magnético)', 'Película seca', 'Película húmeda', 'Otro']}
                        />
                    </Campo>
                </Rejilla>

                <div className="mt-3">
                    <Campo label="Área de pintura (m²) — del plano">
                        <Texto
                            tipo="number"
                            paso="0.01"
                            value={campos.v('p3_area')}
                            onChange={(valor) => campos.set('p3_area', valor)}
                            placeholder="ej. 30.5"
                        />
                    </Campo>
                </div>

                <div className="mt-[14px]">
                    <label className="mb-[5px] block text-[13px] font-semibold text-base-content">
                        Mediciones de espesor (mils) — 5 a 15 mediciones × 3 lecturas
                    </label>
                    <Pista>
                        Cada medición (columna) tiene 3 lecturas (P1–P3). Se llena columna por columna; el promedio de
                        cada medición es lo que va al reporte.
                    </Pista>

                    <div className="overflow-x-auto">
                        <table className="border-collapse text-[13px]">
                            <thead>
                                <tr>
                                    <th className="border border-base-300 bg-base-200 px-[7px] py-[5px]" />
                                    {Array.from({ length: mediciones }, (_, m) => (
                                        <th
                                            key={m}
                                            className="border border-base-300 bg-base-200 px-[7px] py-[5px] text-[11px] font-bold whitespace-nowrap text-primary"
                                        >
                                            Med {m + 1}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[0, 1, 2].map((lectura) => (
                                    <tr key={lectura}>
                                        <th className="border border-base-300 bg-base-200 px-[7px] py-[5px] text-[11px] font-bold text-primary">
                                            P{lectura + 1}
                                        </th>
                                        {Array.from({ length: mediciones }, (_, m) => (
                                            <td key={m} className="border border-base-300 p-0">
                                                <input
                                                    type="number"
                                                    inputMode="decimal"
                                                    value={lecturas[m][lectura]}
                                                    onChange={(e) => escribir(m, lectura, e.target.value)}
                                                    className="w-14 border-none bg-transparent px-[2px] py-[9px] text-center text-[15px] focus:outline-2 focus:-outline-offset-2 focus:outline-primary"
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th className="border border-base-300 bg-base-200 px-[7px] py-[5px] text-[11px] font-bold text-primary">
                                        Prom.
                                    </th>
                                    {Array.from({ length: mediciones }, (_, m) => {
                                        const promedio = resumen.promedios[m];
                                        const baja = !Number.isNaN(requerido) && requerido > 0 && promedio != null && promedio < 0.8 * requerido;
                                        return (
                                            <td
                                                key={m}
                                                className={`min-w-[52px] border border-base-300 px-1 py-[5px] text-center text-[13px] font-bold ${
                                                    baja ? 'bg-warning/10' : 'bg-base-200'
                                                }`}
                                            >
                                                {promedio == null ? '—' : promedio.toFixed(2)}
                                            </td>
                                        );
                                    })}
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div className="mt-[10px] flex flex-wrap justify-center gap-[10px]">
                        <Boton
                            tono="claro"
                            onClick={() =>
                                mediciones > ESPESOR_MEDICIONES_BASE
                                    ? onMediciones(mediciones - 1)
                                    : onAviso(`Mínimo ${ESPESOR_MEDICIONES_BASE} mediciones`, 'error')
                            }
                            className="bg-error/10 px-[14px] py-2 text-error"
                        >
                            ➖ Quitar medición
                        </Boton>
                        <Boton
                            tono="claro"
                            onClick={() =>
                                mediciones < ESPESOR_MEDICIONES_MAX
                                    ? onMediciones(mediciones + 1)
                                    : onAviso(`Máximo ${ESPESOR_MEDICIONES_MAX} mediciones`, 'error')
                            }
                            className="px-[14px] py-2"
                        >
                            ➕ Añadir medición
                        </Boton>
                    </div>

                    {resumen.promedio != null && (
                        <p className={`mt-2 text-center text-xs ${resumen.bajas.length ? 'text-warning' : 'text-base-content/60'}`}>
                            Promedio final de {resumen.promedios.filter((p, i) => p != null && i < mediciones).length} mediciones:{' '}
                            {resumen.promedio.toFixed(2)} mils
                            {resumen.bajas.length > 0 && (
                                <>
                                    {' '}
                                    · ⚠ {resumen.bajas.map((n) => `Med ${n}`).join(', ')} por debajo del 80% del mínimo
                                    (advertencia, no es rechazo)
                                </>
                            )}
                        </p>
                    )}
                </div>

                <div className="mt-3">
                    <Rejilla>
                        <Campo label="Promedio (automático)">
                            <Texto value={resumen.promedio != null ? resumen.promedio.toFixed(2) : ''} readOnly />
                        </Campo>
                        <Campo label="¿Cumple el requerido?">
                            <Texto value={resumen.cumple} readOnly />
                        </Campo>
                    </Rejilla>
                </div>
            </Tarjeta>

            <Tarjeta titulo="Prueba de adherencia (F-STX-CA-08)" etiqueta="pintura">
                <Pista>Opcional — no todas las piezas la llevan. Ábrela sólo si aplica. Norma ASTM D3359.</Pista>
                <Boton tono="acero" onClick={() => onAdherencia(!adherenciaAbierta)}>
                    {adherenciaAbierta ? 'Cerrar prueba de adherencia' : '🔬 Abrir prueba de adherencia'}
                </Boton>

                {adherenciaAbierta && (
                    <div className="mt-[14px]">
                        <Rejilla>
                            <Campo label="Método">
                                <Selector
                                    value={campos.v('p3_adhmet')}
                                    onChange={(valor) => campos.set('p3_adhmet', valor)}
                                    opciones={[
                                        ['A', 'A (en cruz, > 5 mils)'],
                                        ['B', 'B (cuadrícula, < 5 mils)'],
                                    ]}
                                />
                            </Campo>
                            <Campo label="Resultado">
                                <Selector
                                    value={campos.v('p3_adhres')}
                                    onChange={(valor) => campos.set('p3_adhres', valor)}
                                    opciones={['Aceptado', 'Rechazado']}
                                />
                            </Campo>
                        </Rejilla>

                        <div className="mt-3">
                            <label className="mb-[5px] block text-[13px] font-semibold text-base-content">
                                Clasificación por tira (se usan 3 tiras; cada una puede dar distinta)
                            </label>
                            <Rejilla cols={3}>
                                {['p3_adhclas', 'p3_adhclas2', 'p3_adhclas3'].map((id, indice) => (
                                    <Campo key={id} label={`Tira ${indice + 1}`}>
                                        <Selector value={campos.v(id)} onChange={(valor) => campos.set(id, valor)} opciones={CLASIFICACIONES} />
                                    </Campo>
                                ))}
                            </Rejilla>
                        </div>

                        <div className="mt-4 border-t border-base-300 pt-[13px]">
                            <label className="mb-[5px] block text-[13px] font-semibold text-base-content">
                                Evidencia de las tiras <span className="rounded-full bg-warning/10 px-2 py-[3px] text-[10px] text-warning">va al dosier</span>
                            </label>
                            <Pista>
                                Fotografía las 3 tiras de esta marca con la tablet. Sustituye al escaneo: el reporte
                                F-STX-CA-08 las coloca solo, agrupadas por marca y con su fecha.
                            </Pista>

                            <div className="mt-2 flex flex-wrap gap-2">
                                <label className="cursor-pointer rounded-[11px] bg-primary px-4 py-3 text-base font-bold text-primary-content">
                                    📷 Tomar foto
                                    <input
                                        type="file"
                                        accept="image/*"
                                        capture="environment"
                                        multiple
                                        className="hidden"
                                        onChange={(e) => agregarArchivos(e.target.files)}
                                    />
                                </label>
                                <label className="cursor-pointer rounded-[11px] border border-base-300 bg-base-100 px-4 py-3 text-base font-bold text-primary">
                                    📎 Archivo o PDF
                                    <input
                                        type="file"
                                        accept="image/*,application/pdf"
                                        multiple
                                        className="hidden"
                                        onChange={(e) => agregarArchivos(e.target.files)}
                                    />
                                </label>
                            </div>

                            <div className="mt-[11px] flex flex-wrap gap-[10px]">
                                {guardadas.map((foto) => (
                                    <div key={`guardada-${foto.id}`} className="relative">
                                        {foto.esImagen ? (
                                            <img src={foto.url} alt={foto.nombre} className="size-24 rounded-lg border border-base-300 object-cover" />
                                        ) : (
                                            <div className="flex size-24 items-center justify-center rounded-lg border border-base-300 bg-base-200 p-1 text-center text-[11px] break-all">
                                                📄 {foto.nombre}
                                            </div>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => onQuitarGuardada?.(foto.id)}
                                            className="absolute -top-2 -right-2 size-6 rounded-full bg-error text-sm font-bold text-error-content"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                ))}
                                {fotos.map((foto, indice) => (
                                    <div key={indice} className="relative">
                                        {foto.url ? (
                                            <img
                                                src={foto.url}
                                                alt={foto.archivo.name}
                                                className="size-24 rounded-lg border border-base-300 object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-24 items-center justify-center rounded-lg border border-base-300 bg-base-200 p-1 text-center text-[11px] break-all">
                                                📄 {foto.archivo.name}
                                            </div>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => quitarFoto(indice)}
                                            className="absolute -top-2 -right-2 size-6 rounded-full bg-error text-sm font-bold text-error-content"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                ))}
                            </div>
                            <Pista className="mt-[6px]">
                                {fotos.length + guardadas.length
                                    ? `${fotos.length + guardadas.length} archivo(s) adjunto(s).`
                                    : 'Sin evidencia adjunta.'}
                            </Pista>
                        </div>
                    </div>
                )}
            </Tarjeta>

            {/* El veredicto va al final: se dictamina con los espesores y la
                adherencia ya capturados, no antes de medirlos. */}
            <Tarjeta titulo="Inspección de pintura (3ª)">
                <Rejilla cols={3}>
                    <Campo label="Espesor">
                        <Selector value={campos.v('p3_esp')} onChange={(valor) => campos.set('p3_esp', valor)} opciones={['OK', 'Espesor bajo']} />
                    </Campo>
                    <Campo label="Visual">
                        <Selector value={campos.v('p3_vis')} onChange={(valor) => campos.set('p3_vis', valor)} opciones={['OK', 'Con defecto']} />
                    </Campo>
                    <Campo label="Adherencia">
                        <Selector
                            value={campos.v('p3_adh')}
                            onChange={(valor) => campos.set('p3_adh', valor)}
                            opciones={['OK', 'Falla adherencia']}
                        />
                    </Campo>
                </Rejilla>

                <div className="mt-3">
                    <Rejilla>
                        <Campo label="Revisión (R1/R2/R3)">
                            <Selector value={campos.v('p3_rev')} onChange={(valor) => campos.set('p3_rev', valor)} opciones={['R1', 'R2', 'R3']} />
                        </Campo>
                        <Campo label="Acción">
                            <Selector
                                value={campos.v('p3_accion')}
                                onChange={(valor) => campos.set('p3_accion', valor)}
                                opciones={[
                                    ['A', 'A = Aceptada'],
                                    ['R', 'R = Rechazada'],
                                    ['RM', 'RM = Regresar a módulo'],
                                ]}
                            />
                        </Campo>
                    </Rejilla>
                </div>

                <div className="mt-[14px]">
                    <Campo label="Defecto de pintura">
                        <Chips opciones={defectosCatalogo} valor={defectos} onChange={onDefectos} />
                    </Campo>
                </div>
            </Tarjeta>
        </>
    );
}
