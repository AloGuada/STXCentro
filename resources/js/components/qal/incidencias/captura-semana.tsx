/**
 * La captura de una semana: el avance de montaje y las incidencias.
 *
 * Los dos bloques van separados por una línea a propósito, y el aviso de arriba
 * lo dice con todas sus letras. Es la corrección de fondo sobre el Excel que
 * sustituye este módulo: allá el avance y las incidencias comparten fila, y
 * cuando en una semana hay tres hallazgos hacen falta tres renglones con la
 * cifra de montaje escrita en el primero y ceros en los demás. Basta que la
 * cifra caiga en el renglón equivocado —o que se borre el primero— para que el
 * porcentaje se dispare.
 *
 * Aquí las piezas montadas se escriben una vez y añadir la tercera incidencia
 * es pulsar «Añadir» por tercera vez, sin tocar nada de lo anterior.
 */

import { router, useForm } from '@inertiajs/react';
import { CheckCircle2Icon, PlusIcon } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { QalObraMontaje, QalOpcion } from '@/types/models';

type Props = {
    obraId: number;
    anio: number;
    semana: number;
    rango: string;
    esSemanaActual: boolean;
    montaje: QalObraMontaje | null;
    incidenciasSemana: number;
    defectoSemana: number;
    areas: QalOpcion[];
    departamentos: QalOpcion[];
    puedeCapturar: boolean;
};

function Campo({ etiqueta, error, children }: { etiqueta: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-base-content/60 text-[11px] font-semibold tracking-wide uppercase">{etiqueta}</span>
            {children}
            {error && <span className="text-error text-xs">{error}</span>}
        </label>
    );
}

export function CapturaSemana({
    obraId,
    anio,
    semana,
    rango,
    esSemanaActual,
    montaje,
    incidenciasSemana,
    defectoSemana,
    areas,
    departamentos,
    puedeCapturar,
}: Props) {
    const base = `/admin/calidad/incidencias/${obraId}`;

    const avance = useForm({
        anio,
        semana,
        pz_montadas: montaje?.pz_montadas != null ? String(montaje.pz_montadas) : '',
        notas: montaje?.notas ?? '',
    });

    const nueva = useForm({
        anio,
        semana,
        fecha: new Date().toISOString().slice(0, 10),
        area: areas[0]?.valor ?? '',
        departamento: departamentos[0]?.valor ?? '',
        pz_defecto: '1',
        folio: '',
        descripcion: '',
    });

    // Al cambiar de semana los dos formularios tienen que seguirla, o se
    // guardaría el avance de la semana que se está mirando en la anterior.
    useEffect(() => {
        avance.setData({
            anio,
            semana,
            pz_montadas: montaje?.pz_montadas != null ? String(montaje.pz_montadas) : '',
            notas: montaje?.notas ?? '',
        });
        nueva.setData((actual) => ({ ...actual, anio, semana }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [anio, semana, montaje?.id, montaje?.pz_montadas, montaje?.notas]);

    const guardarAvance = (evento: FormEvent) => {
        evento.preventDefault();
        avance.post(`${base}/montaje`, { preserveScroll: true });
    };

    const anadir = (evento: FormEvent) => {
        evento.preventDefault();
        nueva.post(base, {
            preserveScroll: true,
            onSuccess: () => nueva.setData((actual) => ({ ...actual, pz_defecto: '1', folio: '', descripcion: '' })),
        });
    };

    // Va por `router` y no por uno de los dos formularios: no es un campo de
    // ninguno de los dos, es una afirmación sobre la semana.
    const marcarSinIncidencias = (valor: boolean) => {
        router.post(
            `${base}/sin-incidencias`,
            { anio, semana, sin_incidencias: valor },
            { preserveScroll: true },
        );
    };

    const montadas = montaje?.pz_montadas ?? null;
    const tasa = montadas && montadas > 0 ? (defectoSemana * 100) / montadas : null;

    return (
        <section className="rounded-box border-base-300 bg-base-100 border">
            <header className="border-base-300 flex flex-wrap items-baseline justify-between gap-2 border-b px-4 py-3">
                <h2 className="font-semibold">
                    Captura de la semana {semana}
                    <span className="text-base-content/50 ml-2 text-sm font-normal">{rango}</span>
                </h2>
                {esSemanaActual && <span className="badge badge-sm badge-primary badge-outline">esta semana</span>}
            </header>

            <div className="space-y-4 p-4">
                <p className="rounded-box bg-info/10 text-base-content/80 px-3 py-2 text-sm">
                    <b>Son dos cosas separadas.</b> Las <b>piezas montadas</b> son una cifra de la semana: se escribe
                    una vez. Las <b>incidencias</b> son ninguna, una o veinte: se van añadiendo sin tocar las
                    anteriores.
                </p>

                <form onSubmit={guardarAvance} className="flex flex-wrap items-end gap-3">
                    <div className="w-44">
                        <Campo etiqueta="Piezas montadas" error={avance.errors.pz_montadas}>
                            <Input
                                type="number"
                                min={0}
                                step={1}
                                placeholder="0"
                                value={avance.data.pz_montadas}
                                onChange={(e) => avance.setData('pz_montadas', e.target.value)}
                                disabled={!puedeCapturar}
                            />
                        </Campo>
                    </div>
                    <div className="min-w-56 flex-1">
                        <Campo etiqueta="Nota del avance (opcional)" error={avance.errors.notas}>
                            <Input
                                placeholder="ej. sin montaje por lluvia"
                                value={avance.data.notas}
                                onChange={(e) => avance.setData('notas', e.target.value)}
                                disabled={!puedeCapturar}
                            />
                        </Campo>
                    </div>
                    {puedeCapturar && (
                        <Button type="submit" variant="primary" loading={avance.processing}>
                            Guardar avance
                        </Button>
                    )}
                    <span className={`badge badge-sm ${montadas === null ? 'badge-ghost' : 'badge-success'}`}>
                        {montadas === null ? 'sin capturar' : 'guardado'}
                    </span>
                </form>

                <div className="border-base-300 space-y-3 border-t border-dashed pt-4">
                    <form onSubmit={anadir} className="grid gap-3 md:grid-cols-[10rem_11rem_7rem_9rem_1fr_auto]">
                        <Campo etiqueta="Área" error={nueva.errors.area}>
                            <Select
                                value={nueva.data.area}
                                onValueChange={(valor) => nueva.setData('area', valor)}
                                disabled={!puedeCapturar}
                            >
                                {areas.map((a) => (
                                    <SelectItem key={a.valor} value={a.valor}>
                                        {a.etiqueta}
                                    </SelectItem>
                                ))}
                            </Select>
                        </Campo>
                        <Campo etiqueta="Departamento" error={nueva.errors.departamento}>
                            <Select
                                value={nueva.data.departamento}
                                onValueChange={(valor) => nueva.setData('departamento', valor)}
                                disabled={!puedeCapturar}
                            >
                                {departamentos.map((d) => (
                                    <SelectItem key={d.valor} value={d.valor}>
                                        {d.etiqueta}
                                    </SelectItem>
                                ))}
                            </Select>
                        </Campo>
                        <Campo etiqueta="Pz con defecto" error={nueva.errors.pz_defecto}>
                            <Input
                                type="number"
                                min={1}
                                step={1}
                                value={nueva.data.pz_defecto}
                                onChange={(e) => nueva.setData('pz_defecto', e.target.value)}
                                disabled={!puedeCapturar}
                            />
                        </Campo>
                        <Campo etiqueta="Folio NC" error={nueva.errors.folio}>
                            <Input
                                placeholder="opcional"
                                value={nueva.data.folio}
                                onChange={(e) => nueva.setData('folio', e.target.value)}
                                disabled={!puedeCapturar}
                            />
                        </Campo>
                        <Campo etiqueta="Descripción" error={nueva.errors.descripcion}>
                            <Input
                                placeholder="qué pasó"
                                value={nueva.data.descripcion}
                                onChange={(e) => nueva.setData('descripcion', e.target.value)}
                                disabled={!puedeCapturar}
                            />
                        </Campo>
                        {puedeCapturar && (
                            <div className="flex items-end">
                                <Button type="submit" className="btn-success" loading={nueva.processing}>
                                    <PlusIcon className="size-4" /> Añadir
                                </Button>
                            </div>
                        )}
                    </form>

                    <p className="text-base-content/60 text-sm">
                        {incidenciasSemana > 0 ? (
                            <>
                                <b>
                                    {incidenciasSemana} incidencia{incidenciasSemana === 1 ? '' : 's'}
                                </b>{' '}
                                en esta semana · {defectoSemana} pieza{defectoSemana === 1 ? '' : 's'} con defecto
                                {tasa !== null ? (
                                    <> · {tasa.toFixed(2)}% de lo montado</>
                                ) : (
                                    <> · falta capturar las piezas montadas</>
                                )}
                            </>
                        ) : montaje?.sin_incidencias ? (
                            <span className="text-success inline-flex items-center gap-1.5">
                                <CheckCircle2Icon className="size-4" />
                                Semana revisada sin incidencias.
                                {puedeCapturar && (
                                    <button
                                        type="button"
                                        className="link text-base-content/60"
                                        onClick={() => marcarSinIncidencias(false)}
                                    >
                                        quitar la marca
                                    </button>
                                )}
                            </span>
                        ) : (
                            <>
                                Ninguna incidencia en esta semana. Si se revisó y no hubo,{' '}
                                {puedeCapturar ? (
                                    <button
                                        type="button"
                                        className="link font-semibold"
                                        onClick={() => marcarSinIncidencias(true)}
                                    >
                                        déjalo registrado
                                    </button>
                                ) : (
                                    <b>tiene que quedar registrado</b>
                                )}{' '}
                                — no es lo mismo que no haberla revisado.
                            </>
                        )}
                    </p>
                </div>
            </div>
        </section>
    );
}
