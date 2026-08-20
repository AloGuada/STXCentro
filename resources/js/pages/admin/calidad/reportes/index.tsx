/**
 * Captura de inspección — la pantalla del inspector.
 *
 * Es el `captura.html` de la aplicación anterior, portado al mono. Se conserva
 * su estructura y su orden: la transformación manda sobre el formulario
 * entero, los datos generales son comunes, y cada fase trae sus propios
 * bloques. También se conservan las reglas que calculaba sola (muestreo AQL,
 * regla del 80% de espesores, filete por debajo del nominal, falta de vestido
 * que rechaza) porque son criterio de calidad, no adorno.
 *
 * Dos diferencias con el original, deliberadas:
 *
 *  - No hay modo sin conexión. El original guardaba en la tablet y sincronizaba
 *    después; aquí se trabaja contra el servidor como el resto del mono.
 *  - Todavía no guarda. Las tablas de inspección (`qal_inspecciones`,
 *    `qal_puntos_inspeccion`, `qal_juntas`…) no existen; los catálogos que se
 *    ven son de ejemplo y salen de `components/qal/captura/datos.ts`.
 */

import { Head, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    descripcionTipo,
    EQUIPOS,
    OBRAS,
    OPERADORES,
    RESPONSABLES,
    SOLDADORES,
    SUPERVISORES_PINTURA,
    TIPOS_PIEZA,
    tipoDeMarca,
} from '@/components/qal/captura/datos';
import { useCampos, type Junta, type PiezaRechazada } from '@/components/qal/captura/estado';
import { FasePrimera } from '@/components/qal/captura/fase-primera';
import { FaseSegunda } from '@/components/qal/captura/fase-segunda';
import { FaseTercera } from '@/components/qal/captura/fase-tercera';
import { LoteAccesorios } from '@/components/qal/captura/lote-accesorios';
import { MUESTREO_VACIO, type EstadoMuestreo } from '@/components/qal/captura/muestreo';
import { ESPESOR_MEDICIONES_BASE, ESPESOR_MEDICIONES_MAX, hoyLocal, semanaIso } from '@/components/qal/captura/reglas';
import { TecladoFolio } from '@/components/qal/captura/teclado-folio';
import {
    AreaTexto,
    Campo,
    Pista,
    Rejilla,
    Segmentado,
    Selector,
    SelectorFase,
    Tarjeta,
    Texto,
} from '@/components/qal/captura/ui';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Captura de inspección', href: '#' },
];

const FASES = [
    { valor: '1ª', titulo: '1ª', nota: 'Corte / habilitado' },
    { valor: '2ª', titulo: '2ª', nota: 'Armado / soldado' },
    { valor: '3ª', titulo: '3ª', nota: 'Pintura' },
];

const LINEAS = ['L1', 'L2', 'L3', 'L4', 'L5'];

/** Rejilla de espesores vacía: 15 mediciones × 3 lecturas. */
function lecturasVacias(): string[][] {
    return Array.from({ length: ESPESOR_MEDICIONES_MAX }, () => ['', '', '']);
}

export default function CapturaCalidad() {
    const { auth } = usePage<SharedData>().props;

    const iniciales = useMemo(() => ({ fase: '2ª', fecha: hoyLocal(), cant: '1', ninsp: '1', p1_subtipo: 'Perfil' }), []);
    const [campos, reiniciarCampos] = useCampos(iniciales);

    const [pestana, setPestana] = useState<'capturar' | 'registros'>('capturar');
    const [modo, setModo] = useState<'pieza' | 'acc'>('pieza');
    const [tecladoFolio, setTecladoFolio] = useState(false);
    const [tipoDeducido, setTipoDeducido] = useState('');

    const [muestreo, setMuestreo] = useState<EstadoMuestreo>(MUESTREO_VACIO);
    const [juntas, setJuntas] = useState<Junta[]>([]);
    const [defectosSoldadura, setDefectosSoldadura] = useState<Record<string, number>>({});
    const [defectosPintura, setDefectosPintura] = useState<string[]>([]);
    const [lecturas, setLecturas] = useState<string[][]>(lecturasVacias);
    const [mediciones, setMediciones] = useState(ESPESOR_MEDICIONES_BASE);
    const [adherencia, setAdherencia] = useState(false);
    const [fotos, setFotos] = useState<{ nombre: string; url: string }[]>([]);

    const [conformes, setConformes] = useState(0);
    const [rechazadas, setRechazadas] = useState<PiezaRechazada[]>([]);
    const [disposicionAcc, setDisposicionAcc] = useState('');

    const [aviso, setAviso] = useState<{ mensaje: string; tono: 'ok' | 'error' | 'neutro' } | null>(null);

    const fase = campos.v('fase') || '2ª';
    const esPrimera = fase === '1ª';
    const esSegunda = fase === '2ª';
    const esTercera = fase === '3ª';
    const esAccesorios = esSegunda && modo === 'acc';
    const armado = campos.v('p2_subetapa') === 'Armado-Vestido';

    /** El aviso se va solo, como el toast del formulario original. */
    useEffect(() => {
        if (!aviso) {
            return;
        }
        const reloj = setTimeout(() => setAviso(null), 4000);
        return () => clearTimeout(reloj);
    }, [aviso]);

    const avisar = (mensaje: string, tono: 'ok' | 'error' | 'neutro' = 'neutro') => setAviso({ mensaje, tono });

    /** El tipo se deduce del prefijo de la marca. Es sugerencia: se puede cambiar. */
    const sugerirTipo = () => {
        const actual = campos.v('tipo');
        if (actual && actual !== 'OTRO') {
            return;
        }
        const clave = tipoDeMarca(campos.v('marca'));
        if (!clave) {
            return;
        }
        campos.set('tipo', clave);
        setTipoDeducido(`Deducido de la marca: ${descripcionTipo(clave)}. Cámbialo si no es.`);
    };

    const limpiar = () => {
        reiniciarCampos();
        setMuestreo(MUESTREO_VACIO);
        setJuntas([]);
        setDefectosSoldadura({});
        setDefectosPintura([]);
        setLecturas(lecturasVacias());
        setMediciones(ESPESOR_MEDICIONES_BASE);
        setAdherencia(false);
        setFotos([]);
        setConformes(0);
        setRechazadas([]);
        setDisposicionAcc('');
        setTipoDeducido('');
        avisar('Formulario limpio');
    };

    const guardar = () => {
        if (esAccesorios) {
            if (!campos.v('ac_marca').trim()) {
                avisar('Falta la marca del accesorio', 'error');
                return;
            }
            if (!campos.v('ac_unid')) {
                avisar('Faltan las unidades de la entrega', 'error');
                return;
            }
            avisar('Sublote listo — todavía no hay dónde guardarlo (falta el backend)', 'ok');
            return;
        }

        if (!campos.v('obra')) {
            avisar('Falta la obra', 'error');
            return;
        }
        if (!campos.v('marca').trim()) {
            avisar('Falta la marca', 'error');
            return;
        }
        if (!campos.v('status')) {
            avisar('Elige el status de la pieza', 'error');
            return;
        }
        if (esSegunda && !campos.v('p2_subetapa')) {
            avisar('Elige la sub-etapa: Armado/Vestido o Soldado', 'error');
            return;
        }
        // Armado/Vestido no es producto terminado: no puede quedar liberado.
        if (esSegunda && armado && campos.v('status') === 'Liberado') {
            avisar('En Armado/Vestido no aplica «Liberado» — usa Pendiente o Rechazado', 'error');
            return;
        }
        if (!campos.v('kg').trim()) {
            avisar('Falta el peso de la pieza (kg)', 'error');
            return;
        }
        if (esSegunda && campos.v('p2_subetapa') === 'Soldado' && !campos.v('p2_elem')) {
            avisar('Falta el nº de elementos de la pieza (del plano)', 'error');
            return;
        }

        /* Liberar una pieza con defectos marcados es una contradicción: o la pieza
           está bien, o no está liberada. Se avisa antes de guardar; no se bloquea,
           porque a veces se libera por concesión y eso lo decide una persona. */
        const marcados = Object.keys(defectosSoldadura).length + defectosPintura.length;
        if (campos.v('status') === 'Liberado' && marcados > 0) {
            const seguir = window.confirm(
                `Vas a liberar la pieza con ${marcados} defecto(s) marcados.\n\n` +
                    'Si ya se corrigieron, desmárcalos: si no, contarán como defectos de una pieza liberada.\n\n' +
                    '¿Guardar igualmente?',
            );
            if (!seguir) {
                return;
            }
        }

        const cola = juntas.length ? ` · ${juntas.length} juntas` : '';
        avisar(`Registro listo${cola} — todavía no hay dónde guardarlo (falta el backend)`, 'ok');
    };

    const consecutivo = parseFloat(campos.v('consec'));
    const cantidad = parseFloat(campos.v('cant'));
    const consecutivoInvalido = !Number.isNaN(consecutivo) && !Number.isNaN(cantidad) && consecutivo > cantidad;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calidad — Captura de inspección" />

            <div className="bg-base-200 text-base-content">
                <div className="mx-auto max-w-[820px] px-[14px] pt-4 pb-32">
                    <div className="mb-[14px] flex items-center justify-between rounded-xl bg-primary px-4 py-[14px] text-primary-content">
                        <div>
                            <div className="text-[22px] font-extrabold tracking-[.5px]">Captura de inspección</div>
                            <div className="text-xs opacity-85">Control de Calidad · {auth.user.name}</div>
                        </div>
                        <span className="rounded-full bg-primary-content/15 px-3 py-1 text-xs font-semibold">
                            Maqueta · sin guardar
                        </span>
                    </div>

                    <div className="mb-[14px] flex gap-2">
                        {(
                            [
                                ['capturar', 'Capturar'],
                                ['registros', 'Registros'],
                            ] as const
                        ).map(([clave, texto]) => (
                            <button
                                key={clave}
                                type="button"
                                onClick={() => setPestana(clave)}
                                className={`flex-1 rounded-[10px] p-3 text-[15px] font-bold ${
                                    pestana === clave
                                        ? 'bg-primary text-primary-content'
                                        : 'border border-base-300 bg-base-100 text-base-content'
                                }`}
                            >
                                {texto}
                            </button>
                        ))}
                    </div>

                    {pestana === 'registros' ? (
                        <Tarjeta titulo="Registros">
                            <Pista className="mb-0">
                                La consulta —lotes por marca, piezas en proceso, ficha con el historial de
                                inspecciones— es la siguiente rebanada. Necesita leer de la base, así que llega con el
                                backend del módulo.
                            </Pista>
                        </Tarjeta>
                    ) : (
                        <>
                            <Tarjeta titulo="Transformación">
                                <Pista>Elige la etapa. El formulario cambia según la transformación.</Pista>
                                <SelectorFase value={fase} onChange={(valor) => campos.set('fase', valor)} opciones={FASES} />

                                {esSegunda && (
                                    <div className="mt-4 border-t border-base-300 pt-[14px]">
                                        {/* Segunda decisión fundamental, y va aquí arriba porque cambia TODO el
                                            formulario: una pieza es una unidad concreta; un lote de accesorios son
                                            cientos de piezas iguales que se controlan por muestreo. */}
                                        <Campo label="¿Qué estás inspeccionando?">
                                            <Segmentado
                                                value={modo}
                                                onChange={(valor) => setModo(valor as 'pieza' | 'acc')}
                                                opciones={[
                                                    { valor: 'pieza', texto: 'Una Pieza' },
                                                    { valor: 'acc', texto: 'Un Lote de Accesorios' },
                                                ]}
                                            />
                                        </Campo>
                                        <Pista className="mt-2 mb-0">
                                            {esAccesorios ? (
                                                <>
                                                    Cientos de piezas iguales de la misma marca, que llegan por entregas
                                                    y se revisan por <b>muestreo</b>.
                                                </>
                                            ) : (
                                                <>
                                                    Una unidad concreta, con su marca y su consecutivo, revisada al{' '}
                                                    <b>100%</b>.
                                                </>
                                            )}
                                        </Pista>
                                    </div>
                                )}
                            </Tarjeta>

                            <Tarjeta titulo="Datos generales">
                                <Rejilla>
                                    <Campo label="Inspector" req>
                                        <Texto value={auth.user.name} readOnly />
                                    </Campo>
                                    <Campo label="Fecha" req>
                                        <Texto tipo="date" value={campos.v('fecha')} onChange={(valor) => campos.set('fecha', valor)} />
                                    </Campo>
                                </Rejilla>

                                <div className="mt-3">
                                    <Rejilla cols={3}>
                                        <Campo label="Semana">
                                            <Texto value={semanaIso(campos.v('fecha'))} readOnly placeholder="auto" />
                                        </Campo>
                                        {/* En 1ª no hay línea ni módulo: la pieza todavía no llegó a la nave. */}
                                        {!esPrimera && (
                                            <>
                                                <Campo label="Línea">
                                                    <Selector value={campos.v('linea')} onChange={(valor) => campos.set('linea', valor)} opciones={LINEAS} />
                                                </Campo>
                                                <Campo label="Módulo">
                                                    <Texto value={campos.v('modulo')} onChange={(valor) => campos.set('modulo', valor)} placeholder="ej. 2.3" />
                                                </Campo>
                                            </>
                                        )}
                                        {esPrimera && (
                                            <Campo label="Nombre del equipo">
                                                <Selector value={campos.v('equipo')} onChange={(valor) => campos.set('equipo', valor)} opciones={EQUIPOS} />
                                            </Campo>
                                        )}
                                    </Rejilla>
                                </div>

                                <div className="mt-3">
                                    <Rejilla>
                                        <Campo label="Obra / Proyecto" req>
                                            <Selector value={campos.v('obra')} onChange={(valor) => campos.set('obra', valor)} opciones={OBRAS} />
                                        </Campo>
                                        {esPrimera ? (
                                            <Campo label="Operador">
                                                <Selector
                                                    value={campos.v('operador')}
                                                    onChange={(valor) => campos.set('operador', valor)}
                                                    opciones={OPERADORES}
                                                />
                                            </Campo>
                                        ) : (
                                            <Campo label={esTercera ? 'Supervisor de pintura' : 'Responsable del módulo'}>
                                                <Selector
                                                    value={campos.v('responsable')}
                                                    onChange={(valor) => campos.set('responsable', valor)}
                                                    opciones={esTercera ? SUPERVISORES_PINTURA : RESPONSABLES}
                                                />
                                            </Campo>
                                        )}
                                    </Rejilla>
                                </div>
                            </Tarjeta>

                            {esAccesorios ? (
                                <LoteAccesorios
                                    campos={campos}
                                    conformes={conformes}
                                    onConformes={setConformes}
                                    rechazadas={rechazadas}
                                    onRechazadas={setRechazadas}
                                    disposicion={disposicionAcc}
                                    onDisposicion={setDisposicionAcc}
                                    onAviso={avisar}
                                />
                            ) : (
                                <>
                                    <Tarjeta titulo="Pieza">
                                        <Rejilla cols={3}>
                                            <Campo label="Marca de fabricación" req>
                                                <Texto
                                                    value={campos.v('marca')}
                                                    onChange={(valor) => campos.set('marca', valor)}
                                                    onBlur={sugerirTipo}
                                                    placeholder="ej. SX-CM22-2"
                                                    mayusculas
                                                />
                                            </Campo>
                                            <Campo label="Folio (Strumis)">
                                                <Texto
                                                    value={campos.v('folio')}
                                                    onFocus={() => setTecladoFolio(true)}
                                                    sinTeclado
                                                    placeholder="Folio único · trazabilidad"
                                                />
                                            </Campo>
                                            <Campo label="Tipo" ayuda={tipoDeducido ? <span className="text-primary">{tipoDeducido}</span> : undefined}>
                                                <Selector
                                                    value={campos.v('tipo')}
                                                    onChange={(valor) => {
                                                        campos.set('tipo', valor);
                                                        setTipoDeducido('');
                                                    }}
                                                    opciones={TIPOS_PIEZA.map(([clave, descripcion]) => [clave, `${clave} — ${descripcion}`] as [string, string])}
                                                />
                                            </Campo>
                                        </Rejilla>

                                        {tecladoFolio && (
                                            <TecladoFolio
                                                onTecla={(caracter) => campos.set('folio', (campos.v('folio') + caracter).toUpperCase())}
                                                onBorrar={() => campos.set('folio', campos.v('folio').slice(0, -1))}
                                                onListo={() => setTecladoFolio(false)}
                                            />
                                        )}

                                        <Pista className="mt-[10px]">
                                            Un lote puede traer varias piezas con la <b>misma marca</b>. Indica cuántas
                                            hay y cuál es ésta.
                                        </Pista>
                                        <Rejilla cols={3}>
                                            <Campo label="Piezas en el lote">
                                                <Texto tipo="number" value={campos.v('cant')} onChange={(valor) => campos.set('cant', valor)} />
                                            </Campo>
                                            <Campo label="# de pieza (consecutivo)">
                                                <Texto
                                                    tipo="number"
                                                    value={campos.v('consec')}
                                                    onChange={(valor) => campos.set('consec', valor)}
                                                    placeholder="ej. 2"
                                                />
                                            </Campo>
                                            <Campo label="Peso unitario (kg)" req>
                                                <Texto
                                                    tipo="number"
                                                    paso="0.01"
                                                    value={campos.v('kg')}
                                                    onChange={(valor) => campos.set('kg', valor)}
                                                    placeholder="ej. 850"
                                                />
                                            </Campo>
                                        </Rejilla>
                                        {consecutivoInvalido && (
                                            <p className="mt-1 text-xs text-error">
                                                ⚠️ «# de pieza» no puede ser mayor que las piezas del lote.
                                            </p>
                                        )}

                                        <div className="mt-[6px] max-w-[160px]">
                                            <Campo label="# Inspección">
                                                <Texto value={campos.v('ninsp') || '1'} readOnly />
                                            </Campo>
                                        </div>

                                        {esSegunda && (
                                            <div className="mt-3">
                                                <Campo label="Soldador Principal de la pieza">
                                                    <Selector
                                                        value={campos.v('soldador')}
                                                        onChange={(valor) => campos.set('soldador', valor)}
                                                        opciones={SOLDADORES.map(
                                                            ([nombre, clave]) => [nombre, `${nombre} (${clave})`] as [string, string],
                                                        )}
                                                    />
                                                </Campo>
                                            </div>
                                        )}
                                    </Tarjeta>

                                    {esPrimera && <FasePrimera campos={campos} muestreo={muestreo} onMuestreo={setMuestreo} />}

                                    {esSegunda && (
                                        <FaseSegunda
                                            campos={campos}
                                            defectos={defectosSoldadura}
                                            onDefectos={setDefectosSoldadura}
                                            juntas={juntas}
                                            onJuntas={setJuntas}
                                            onAviso={avisar}
                                            onRechazar={() => campos.set('status', 'Rechazado')}
                                        />
                                    )}

                                    {esTercera && (
                                        <FaseTercera
                                            campos={campos}
                                            lecturas={lecturas}
                                            onLecturas={setLecturas}
                                            mediciones={mediciones}
                                            onMediciones={setMediciones}
                                            defectos={defectosPintura}
                                            onDefectos={setDefectosPintura}
                                            adherenciaAbierta={adherencia}
                                            onAdherencia={setAdherencia}
                                            fotos={fotos}
                                            onFotos={setFotos}
                                            onAviso={avisar}
                                        />
                                    )}

                                    <Tarjeta titulo="Resultado">
                                        <Campo label="Status de la pieza" req>
                                            <Segmentado
                                                tono="estatus"
                                                value={campos.v('status')}
                                                onChange={(valor) => campos.set('status', valor)}
                                                opciones={[
                                                    // Armado/Vestido no es producto terminado: no puede liberarse.
                                                    ...(armado ? [] : [{ valor: 'Liberado', texto: 'Liberado', color: 'lib' as const }]),
                                                    { valor: 'Rechazado', texto: 'Rechazado', color: 'rej' as const },
                                                    { valor: 'Pendiente', texto: 'Pendiente', color: 'pen' as const },
                                                ]}
                                            />
                                        </Campo>

                                        {esSegunda && (
                                            <div className="mt-[14px]">
                                                <Rejilla>
                                                    <Campo label="Avance IV-50 (vestido)">
                                                        <Selector value={campos.v('iv')} onChange={(valor) => campos.set('iv', valor)} opciones={['Ok', 'X', 'n/a']} />
                                                    </Campo>
                                                    <Campo label="Avance IS-60 (soldadura)">
                                                        <Selector value={campos.v('is')} onChange={(valor) => campos.set('is', valor)} opciones={['Ok', 'X', 'n/a']} />
                                                    </Campo>
                                                </Rejilla>
                                            </div>
                                        )}

                                        <div className="mt-3">
                                            <Campo label="Observaciones">
                                                <AreaTexto
                                                    value={campos.v('obs')}
                                                    onChange={(valor) => campos.set('obs', valor)}
                                                    placeholder="ej. 1 poro en cordón, falta limpieza entre pasadas"
                                                />
                                            </Campo>
                                        </div>
                                    </Tarjeta>
                                </>
                            )}
                        </>
                    )}
                </div>
            </div>

            {pestana === 'capturar' && (
                <div className="sticky bottom-0 z-50 mx-auto flex max-w-[820px] gap-[10px] border-t border-base-300 bg-base-100 px-[14px] py-3 shadow-[0_-2px_12px_rgba(18,35,61,.08)]">
                    <button
                        type="button"
                        onClick={limpiar}
                        className="rounded-[11px] border border-base-300 bg-base-100 px-[18px] py-4 text-[17px] font-bold text-primary"
                    >
                        Limpiar
                    </button>
                    <button
                        type="button"
                        onClick={guardar}
                        className="flex-1 rounded-[11px] bg-primary py-4 text-[17px] font-bold text-primary-content"
                    >
                        Guardar registro
                    </button>
                </div>
            )}

            {aviso && (
                <div
                    className={`fixed bottom-24 left-1/2 z-[100] -translate-x-1/2 rounded-[30px] px-[22px] py-[13px] text-[15px] font-semibold ${
                        aviso.tono === 'ok'
                            ? 'bg-success text-success-content'
                            : aviso.tono === 'error'
                              ? 'bg-error text-error-content'
                              : 'bg-neutral text-neutral-content'
                    }`}
                >
                    {aviso.mensaje}
                </div>
            )}
        </AppLayout>
    );
}
