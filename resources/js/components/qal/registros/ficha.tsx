/**
 * La ficha de un registro: lo único de la pantalla que enseña todo lo capturado.
 *
 * La tabla lista ocho columnas porque más no caben; aquí sale el registro
 * entero, campo por campo y con el nombre que usa calidad al hablar —de eso se
 * encarga el diccionario `CAMPOS`—.
 *
 * Lo que la aplicación anterior no tenía y aquí sí: **el historial de la pieza**
 * (RF-18.4). El original abría un registro suelto, así que para saber por qué se
 * había rechazado una pieza que hoy está liberada había que cerrar, adivinar el
 * filtro y volver a buscar. Una pieza reinspeccionada es una historia, no tres
 * hechos sueltos, y desde cualquiera de sus inspecciones se llega a las demás.
 */

import { XIcon } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { CAMPOS, CAMPOS_ACC, clavePieza, type RegistroPieza, type RegistroSublote } from './datos';
import { PastillaEstatus } from './ui';

/** Los campos que nunca se enseñan: son plomería, no dato de calidad. */
const OCULTOS = ['id', 'pieza_id', 'synced', 'junta', 'rechazado'];

type Fila = { etiqueta: string; valor: string };

/**
 * Ordena los campos según el diccionario y deja al final lo que no reconoce.
 *
 * Lo desconocido no se tira: un campo que la aplicación anterior guardaba y el
 * diccionario no contempla seguiría siendo dato capturado por un inspector. Se
 * enseña con su nombre crudo y separado, para que se note que falta traducirlo.
 */
function repartir(
    datos: Record<string, unknown>,
    diccionario: Record<string, string>,
): { conocidos: Fila[]; otros: Fila[] } {
    const vivo = (v: unknown) => v !== null && v !== undefined && v !== '';

    const conocidos = Object.entries(diccionario)
        .filter(([clave]) => vivo(datos[clave]))
        .map(([clave, etiqueta]) => ({ etiqueta, valor: String(datos[clave]) }));

    const otros = Object.entries(datos)
        .filter(([clave, valor]) => !(clave in diccionario) && !OCULTOS.includes(clave) && vivo(valor))
        .map(([clave, valor]) => ({ etiqueta: clave, valor: String(valor) }));

    return { conocidos, otros };
}

function TablaCampos({ conocidos, otros }: { conocidos: Fila[]; otros: Fila[] }) {
    return (
        <>
            <table className="w-full text-sm">
                <tbody>
                    {conocidos.map((fila) => (
                        <tr key={fila.etiqueta} className="border-base-200 border-b last:border-0">
                            <td className="text-base-content/60 w-2/5 py-1.5 pr-3 align-top">{fila.etiqueta}</td>
                            <td className="py-1.5 font-semibold">{fila.valor}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {otros.length > 0 && (
                <div className="text-base-content/60 border-base-200 mt-3 border-t pt-3 text-xs">
                    <span className="font-semibold">Sin traducir todavía: </span>
                    {otros.map((fila) => `${fila.etiqueta} = ${fila.valor}`).join(' · ')}
                </div>
            )}
        </>
    );
}

function Cabecera({ titulo, nota, onCerrar }: { titulo: string; nota: string; onCerrar: () => void }) {
    return (
        <div className="-m-6 mb-4 flex items-start justify-between gap-4 rounded-t-2xl bg-neutral px-5 py-4 text-neutral-content">
            <div>
                <h2 className="text-base font-bold">{titulo}</h2>
                <p className="mt-0.5 text-xs opacity-70">{nota}</p>
            </div>
            <button
                type="button"
                onClick={onCerrar}
                aria-label="Cerrar"
                className="rounded-lg bg-neutral-content/15 p-1.5 hover:bg-neutral-content/25"
            >
                <XIcon className="size-4" />
            </button>
        </div>
    );
}

/**
 * El historial de la pieza.
 *
 * Se enseña siempre, aunque sólo haya una inspección: que una pieza pasara a la
 * primera es información, y una franja vacía obligaría a preguntarse si falta
 * cargar algo.
 */
function Historial({
    inspecciones,
    actual,
    onIr,
}: {
    inspecciones: RegistroPieza[];
    actual: RegistroPieza;
    onIr: (registro: RegistroPieza) => void;
}) {
    return (
        <div className="border-base-300 bg-base-200/50 mb-4 rounded-xl border p-3">
            <div className="text-base-content/60 mb-2 text-xs font-semibold">
                Historial de la pieza · {inspecciones.length}{' '}
                {inspecciones.length === 1 ? 'inspección' : 'inspecciones'}
            </div>
            <div className="flex flex-wrap gap-2">
                {inspecciones.map((r) => (
                    <button
                        key={r.id}
                        type="button"
                        onClick={() => onIr(r)}
                        className={cn(
                            'flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs transition-colors',
                            r.id === actual.id
                                ? 'border-primary bg-primary/10 font-semibold'
                                : 'border-base-300 bg-base-100 hover:border-primary/50',
                        )}
                    >
                        <span className="font-mono">#{r.ninsp}</span>
                        <span className="text-base-content/60">{r.fecha}</span>
                        <PastillaEstatus estatus={r.estatus} />
                    </button>
                ))}
            </div>
        </div>
    );
}

export function FichaPieza({
    registro,
    registros,
    onIr,
    onCerrar,
}: {
    registro: RegistroPieza | null;
    registros: RegistroPieza[];
    onIr: (registro: RegistroPieza) => void;
    onCerrar: () => void;
}) {
    if (!registro) {
        return null;
    }

    // El historial sale de TODOS los registros, no de los filtrados: filtrar por
    // «Liberado» y perder de vista el rechazo que explica la pieza sería
    // justamente lo que esta ficha viene a arreglar.
    const clave = clavePieza(registro);
    const inspecciones = registros
        .filter((r) => clavePieza(r) === clave && r.fase === registro.fase)
        .sort((a, b) => a.ninsp - b.ninsp);

    const { conocidos, otros } = repartir(
        {
            ...registro,
            ...registro.campos,
            campos: undefined,
        },
        CAMPOS,
    );

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="max-w-3xl">
                <Cabecera
                    titulo={`${registro.marca || registro.folio} · ${registro.fase}${registro.consec ? ` · #${registro.consec}` : ''}`}
                    nota={`${registro.obra} · inspección ${registro.ninsp}`}
                    onCerrar={onCerrar}
                />

                <Historial inspecciones={inspecciones} actual={registro} onIr={onIr} />

                <TablaCampos conocidos={conocidos} otros={otros} />
            </DialogContent>
        </Dialog>
    );
}

export function FichaSublote({ registro, onCerrar }: { registro: RegistroSublote | null; onCerrar: () => void }) {
    if (!registro) {
        return null;
    }

    const { conocidos, otros } = repartir({ ...registro, ...registro.campos, campos: undefined }, CAMPOS_ACC);

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="max-w-3xl">
                <Cabecera
                    titulo={`${registro.marca} · ${registro.grupo}`}
                    nota={`${registro.obra} · inspección ${registro.ninsp}`}
                    onCerrar={onCerrar}
                />
                <TablaCampos conocidos={conocidos} otros={otros} />
            </DialogContent>
        </Dialog>
    );
}
