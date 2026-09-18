/**
 * Captura de inspección — la pantalla del inspector.
 *
 * Es el `captura.html` de la aplicación anterior, portado al mono y ahora
 * contra la base. Se conserva su estructura y su orden: la transformación manda
 * sobre el formulario entero, los datos generales son comunes, y cada fase trae
 * sus propios bloques. Las reglas que calculaba sola (muestreo AQL, regla del
 * 80 % de espesores, filete bajo el nominal, falta de vestido que rechaza) se
 * adelantan aquí para que el inspector vea el veredicto mientras captura; al
 * guardar, el servidor las vuelve a calcular y las suyas son las que valen.
 *
 * La pieza viene de Producción: en 1ª es una marca del catálogo vigente y su
 * consecutivo; en 2ª y pintura, la pieza física que se escanea por su QR. Un
 * lote de accesorios es otra cosa —cientos de unidades iguales por muestreo— y
 * se guarda como una entrega de su lote.
 *
 * La misma pantalla sirve para corregir y reinspeccionar: llega con una
 * `precarga` que arma el servidor, y entonces lo que identifica a la pieza
 * (fase, obra, pieza, sub-etapa) queda fijo.
 *
 * No hay modo sin conexión: se trabaja contra el servidor como el resto del
 * mono.
 */

import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { NivelMuestreo } from '@/components/qal/captura/datos';
import { useCampos, type Junta, type PiezaRechazada } from '@/components/qal/captura/estado';
import { FasePrimera } from '@/components/qal/captura/fase-primera';
import { FaseSegunda } from '@/components/qal/captura/fase-segunda';
import { FaseTercera } from '@/components/qal/captura/fase-tercera';
import type { Evidencia, FotoGuardada } from '@/components/qal/captura/fotos';
import { LoteAccesorios, type LoteDeObra } from '@/components/qal/captura/lote-accesorios';
import { MUESTREO_VACIO, type EstadoMuestreo } from '@/components/qal/captura/muestreo';
import { PiezaFisica, type PiezaResuelta } from '@/components/qal/captura/pieza-fisica';
import { RegistrosDeCaptura, type MarcaConAvance, type PiezasDeMarca } from '@/components/qal/captura/registros';
import {
    ESPESOR_MEDICIONES_BASE,
    ESPESOR_MEDICIONES_MAX,
    hoyLocal,
    planMuestreo,
    semanaIso,
} from '@/components/qal/captura/reglas';
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

type Opcion = { id: number; nombre: string };

type Marca = {
    id: number;
    marca: string;
    lote: string | null;
    descripcion: string | null;
    cantidad: number | null;
    peso_unitario: string | null;
};

type TipoPieza = { id: number; prefijo: string; descripcion: string };

type Catalogos = {
    equipos: Opcion[];
    operadores: Opcion[];
    responsables: Opcion[];
    supervisoresPintura: Opcion[];
    soldadores: (Opcion & { clave: string | null })[];
    tiposPieza: TipoPieza[];
    defectosSoldadura: Opcion[];
    defectosPintura: Opcion[];
    defectosAccDimensional: Opcion[];
    defectosAccBarrenos: Opcion[];
    defectosAccLimpieza: Opcion[];
};

/** Lo que el servidor manda ya llenado para corregir o reinspeccionar. */
type Precarga = {
    modo: 'editar' | 'reinspeccionar' | 'nuevo';
    titulo: string;
    destino: { metodo: 'post' | 'put'; url: string };
    modoCaptura: 'pieza' | 'acc';
    campos: Record<string, string>;
    pieza: PiezaResuelta | null;
    marcaTexto: string;
    juntas: Junta[];
    lecturas: string[][] | null;
    mediciones: number | null;
    adherencia: boolean;
    fotosGuardadas: FotoGuardada[];
    defectosSoldadura: Record<string, number>;
    defectosPintura: string[];
    muestreo: EstadoMuestreo | null;
    acc: {
        conformes: number;
        rechazadas: PiezaRechazada[];
        disposicion: string;
        origenId: number | null;
        numero: number;
    } | null;
};

type Props = {
    obras: { id: number; no: string | null; descripcion: string | null }[];
    obraId: number | null;
    marcas: Marca[];
    lotes: LoteDeObra[];
    avance: MarcaConAvance[];
    piezasDeMarca: PiezasDeMarca | null;
    catalogos: Catalogos;
    precarga: Precarga | null;
};

type Cuerpo = NonNullable<Parameters<typeof router.post>[1]>;

const FASES = [
    { valor: '1ª', titulo: '1ª', nota: 'Corte / habilitado' },
    { valor: '2ª', titulo: '2ª', nota: 'Armado / soldado' },
    { valor: '3ª', titulo: '3ª', nota: 'Pintura' },
];

const LINEAS = ['L1', 'L2', 'L3', 'L4', 'L5'];

/** Lo que muestra el formulario → lo que guarda el servidor. */
const ESTATUS: Record<string, string> = { Liberado: 'liberado', Rechazado: 'rechazado', Pendiente: 'pendiente' };
const SUBETAPAS: Record<string, string> = { 'Armado-Vestido': 'armado_vestido', Soldado: 'soldado' };
const SUBTIPOS: Record<string, string> = { Perfil: 'perfil', Placa: 'placa' };
const PREFIJO_FASE: Record<string, string> = { '1ª': 'p1_', '2ª': 'p2_', '3ª': 'p3_' };

/**
 * Claves del diccionario que no son puntos del catálogo: deciden el formulario
 * o van en su propia sección (muestreo, espesores, adherencia).
 */
const NO_SON_PUNTOS = new Set([
    'p1_subtipo',
    'p1_lote',
    'p1_nivel',
    'p2_subetapa',
    'p3_req',
    'p3_metodo',
    'p3_area',
    'p3_rev',
    'p3_accion',
    'p3_adhmet',
    'p3_adhmet2',
    'p3_adhmet3',
    'p3_adhres',
    'p3_adhclas',
    'p3_adhclas2',
    'p3_adhclas3',
]);

/**
 * Lo que se queda al pasar a la siguiente pieza: el inspector sigue en la
 * misma obra, línea y sub-etapa, y volver a elegirlas cada vez es lo que hace
 * que la tablet se deje de usar.
 */
const GENERALES = new Set([
    'fase',
    'fecha',
    'obra',
    'linea',
    'modulo',
    'equipo',
    'operador',
    'responsable',
    'supervisor',
    'p1_subtipo',
    'p2_subetapa',
    'p3_req',
    'p3_metodo',
]);

/**
 * Dónde cae la columna del formulario en pantalla, para pegarle debajo la
 * barra de guardar. Va `fixed` y no `sticky` porque la página scrollea en el
 * body y el `main` del layout lleva `overflow-auto`: un sticky se ancla a ese
 * `main`, que mide lo que el contenido, y la barra acaba fuera de la vista.
 * El sidebar cambia de ancho, así que la posición se mide, no se supone.
 */
function useColumna() {
    const ref = useRef<HTMLDivElement>(null);
    const [caja, setCaja] = useState<{ left: number; width: number } | null>(null);

    useLayoutEffect(() => {
        const medir = () => {
            const rect = ref.current?.getBoundingClientRect();
            if (rect) {
                setCaja({ left: rect.left, width: rect.width });
            }
        };
        medir();
        const observador = new ResizeObserver(medir);
        observador.observe(document.body);
        if (ref.current) {
            observador.observe(ref.current);
        }
        window.addEventListener('resize', medir);
        return () => {
            observador.disconnect();
            window.removeEventListener('resize', medir);
        };
    }, []);

    return [ref, caja] as const;
}

/** Rejilla de espesores vacía: 15 mediciones × 3 lecturas. */
function lecturasVacias(): string[][] {
    return Array.from({ length: ESPESOR_MEDICIONES_MAX }, () => ['', '', '']);
}

function etiquetaMarca(marca: Marca): string {
    return marca.lote ? `${marca.marca} · lote ${marca.lote}` : marca.marca;
}

function vacioANulo(valor: string): string | null {
    const limpio = valor.trim();
    return limpio === '' ? null : limpio;
}

/**
 * El tipo que sugiere la marca. Las marcas vienen como OBRA-PREFIJO[n]-N, así
 * que el prefijo oficial abre el segundo tramo; se prueba del más largo al más
 * corto porque si no CMV se confunde con CM y RCV con R.
 */
function tipoDeMarca(marca: string, tipos: TipoPieza[]): string {
    const partes = marca.toUpperCase().split('-');
    if (partes.length < 2) {
        return '';
    }
    const segmento = partes[1].replace(/[^A-Z0-9]/g, '');
    const tipo = tipos
        .filter((t) => t.prefijo && t.prefijo.toUpperCase() !== 'OTRO')
        .sort((a, b) => b.prefijo.length - a.prefijo.length)
        .find((t) => segmento.startsWith(t.prefijo.toUpperCase()));

    return tipo ? String(tipo.id) : '';
}

const aPares = (lista: Opcion[]): [string, string][] => lista.map((opcion) => [String(opcion.id), opcion.nombre]);

/** Los ids de los defectos elegidos por nombre en una lista del catálogo. */
const idsDe = (lista: Opcion[], nombres: string[]): number[] =>
    nombres.map((nombre) => lista.find((defecto) => defecto.nombre === nombre)?.id).filter((id): id is number => id !== undefined);

export default function CapturaCalidad({ obras, obraId, marcas, lotes, avance, piezasDeMarca, catalogos, precarga }: Props) {
    const { auth } = usePage<SharedData>().props;

    const iniciales = useMemo(
        () => ({
            fase: '2ª',
            fecha: hoyLocal(),
            cant: '1',
            p1_subtipo: 'Perfil',
            obra: obraId ? String(obraId) : '',
            ...(precarga?.campos ?? {}),
        }),
        [obraId, precarga],
    );
    const [campos, reiniciarCampos] = useCampos(iniciales);

    const [pestana, setPestana] = useState<'capturar' | 'registros'>('capturar');
    const [columnaRef, cajaColumna] = useColumna();
    const [modo, setModo] = useState<'pieza' | 'acc'>(precarga?.modoCaptura ?? 'pieza');
    const [tecladoFolio, setTecladoFolio] = useState(false);
    const [tipoDeducido, setTipoDeducido] = useState('');
    const [marcaTexto, setMarcaTexto] = useState(precarga?.marcaTexto ?? '');
    const [pieza, setPieza] = useState<PiezaResuelta | null>(precarga?.pieza ?? null);

    const [muestreo, setMuestreo] = useState<EstadoMuestreo>(precarga?.muestreo ?? MUESTREO_VACIO);
    const [juntas, setJuntas] = useState<Junta[]>(precarga?.juntas ?? []);
    const [defectosSoldadura, setDefectosSoldadura] = useState<Record<string, number>>(precarga?.defectosSoldadura ?? {});
    const [defectosPintura, setDefectosPintura] = useState<string[]>(precarga?.defectosPintura ?? []);
    const [lecturas, setLecturas] = useState<string[][]>(() => precarga?.lecturas ?? lecturasVacias());
    const [mediciones, setMediciones] = useState(precarga?.mediciones ?? ESPESOR_MEDICIONES_BASE);
    const [adherencia, setAdherencia] = useState(precarga?.adherencia ?? false);
    const [fotos, setFotos] = useState<Evidencia[]>([]);
    const [fotosGuardadas, setFotosGuardadas] = useState<FotoGuardada[]>(precarga?.fotosGuardadas ?? []);
    const [fotosQuitar, setFotosQuitar] = useState<number[]>([]);

    const [conformes, setConformes] = useState(precarga?.acc?.conformes ?? 0);
    const [rechazadas, setRechazadas] = useState<PiezaRechazada[]>(precarga?.acc?.rechazadas ?? []);
    const [disposicionAcc, setDisposicionAcc] = useState(precarga?.acc?.disposicion ?? '');

    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    const [aviso, setAviso] = useState<{ mensaje: string; tono: 'ok' | 'error' | 'neutro' } | null>(null);

    const fase = campos.v('fase') || '2ª';
    const esPrimera = fase === '1ª';
    const esSegunda = fase === '2ª';
    const esTercera = fase === '3ª';
    // La misma marca de accesorios se revisa soldada en 2ª y pintada en 3ª: las
    // dos entregas son un muestreo, cada una con los defectos de su etapa.
    const esAccesorios = (esSegunda || esTercera) && modo === 'acc';
    const armado = campos.v('p2_subetapa') === 'Armado-Vestido';
    const soldado = esSegunda && campos.v('p2_subetapa') === 'Soldado';

    // Al corregir o reinspeccionar, lo que identifica a la pieza no cambia: el
    // folio y el número de inspección dependen de eso.
    const conPrecarga = precarga !== null;
    const piezaFija = conPrecarga && precarga.modoCaptura === 'pieza';

    const opcionesObras: [string, string][] = obras.map((obra) => [
        String(obra.id),
        [obra.no, obra.descripcion].filter(Boolean).join(' — '),
    ]);
    const soldadores: [string, string][] = catalogos.soldadores.map((s) => [
        String(s.id),
        s.clave ? `${s.nombre} (${s.clave})` : s.nombre,
    ]);
    const tiposPieza: [string, string][] = catalogos.tiposPieza.map((t) => [String(t.id), `${t.prefijo} — ${t.descripcion}`]);
    const marcaElegida = marcas.find((marca) => String(marca.id) === campos.v('concepto'));

    /** El aviso se va solo, como el toast del formulario original. */
    useEffect(() => {
        if (!aviso) {
            return;
        }
        const reloj = setTimeout(() => setAviso(null), 4000);
        return () => clearTimeout(reloj);
    }, [aviso]);

    const avisar = (mensaje: string, tono: 'ok' | 'error' | 'neutro' = 'neutro') => setAviso({ mensaje, tono });

    /** El tipo deducido es sugerencia: no pisa uno que el inspector eligió a mano. */
    const sugerirTipo = (marca: string, deducido?: number | null) => {
        if (campos.v('tipo') && !tipoDeducido) {
            return;
        }
        const id = deducido != null ? String(deducido) : tipoDeMarca(marca, catalogos.tiposPieza);
        if (!id) {
            return;
        }
        const descripcion = catalogos.tiposPieza.find((tipo) => String(tipo.id) === id)?.descripcion ?? '';
        campos.set('tipo', id);
        setTipoDeducido(`Deducido de la marca: ${descripcion}. Cámbialo si no es.`);
    };

    /** Marcas y lotes son de la obra: al cambiarla se piden los suyos al servidor. */
    const cambiarObra = (valor: string) => {
        campos.set('obra', valor);
        campos.limpiar(['concepto', 'kg', 'tipo']);
        setMarcaTexto('');
        setTipoDeducido('');
        setPieza(null);
        if (valor) {
            router.reload({ only: ['marcas', 'lotes', 'avance', 'obraId'], data: { obra: valor, marca: null } });
        }
    };

    /** 1ª: la marca se elige de la lista del catálogo vigente; teclear no basta. */
    const elegirMarca = (texto: string) => {
        setMarcaTexto(texto);
        const buscado = texto.trim().toUpperCase();
        const porEtiqueta = marcas.find((marca) => etiquetaMarca(marca).toUpperCase() === buscado);
        const porMarca = marcas.filter((marca) => marca.marca.toUpperCase() === buscado);
        const marca = porEtiqueta ?? (porMarca.length === 1 ? porMarca[0] : undefined);

        campos.set('concepto', marca ? String(marca.id) : '');
        if (marca) {
            if (marca.peso_unitario) {
                campos.set('kg', String(Number(marca.peso_unitario)));
            }
            sugerirTipo(marca.marca);
        }
    };

    /**
     * Desde Registros: la pieza que falta pasa a Capturar ya resuelta, como si
     * se hubiera escaneado.
     */
    const capturarQr = async (qr: string) => {
        setPestana('capturar');
        if (esPrimera) {
            campos.set('fase', '2ª');
        }
        try {
            const respuesta = await fetch(`/admin/calidad/piezas/resolver?${new URLSearchParams({ codigo: qr, obra_id: campos.v('obra') })}`, {
                headers: { Accept: 'application/json' },
            });
            if (respuesta.ok) {
                elegirPieza((await respuesta.json()) as PiezaResuelta);
            } else {
                avisar('No se pudo cargar la pieza', 'error');
            }
        } catch {
            avisar('No se pudo consultar la pieza. Revisa la conexión.', 'error');
        }
        window.scrollTo(0, 0);
    };

    /** 2ª y pintura: la pieza escaneada trae su obra, su marca y su peso. */
    const elegirPieza = (resuelta: PiezaResuelta | null) => {
        if (resuelta && String(resuelta.obra_id) !== campos.v('obra')) {
            cambiarObra(String(resuelta.obra_id));
        }
        setPieza(resuelta);
        if (!resuelta) {
            return;
        }
        if (resuelta.concepto.peso_unitario) {
            campos.set('kg', String(Number(resuelta.concepto.peso_unitario)));
        }
        sugerirTipo(resuelta.concepto.marca, resuelta.tipo_pieza_id);
        avisar(`Pieza ${resuelta.etiqueta}`, 'ok');
    };

    /** El número que va a tocar; en 1ª lo sabe sólo el servidor, y al corregir se conserva. */
    const numeroInspeccion = (() => {
        if (precarga?.modo === 'editar') {
            return 'se conserva';
        }
        if (esPrimera || !pieza) {
            return 'auto';
        }
        const subetapa = esSegunda ? (SUBETAPAS[campos.v('p2_subetapa')] ?? null) : null;
        const previas = pieza.inspecciones.filter((previa) => previa.fase === fase && (previa.subetapa ?? null) === subetapa);
        return String(previas.reduce((maximo, previa) => Math.max(maximo, previa.numero_inspeccion), 0) + 1);
    })();

    const reiniciarSecciones = () => {
        setPieza(null);
        setMarcaTexto('');
        setMuestreo(MUESTREO_VACIO);
        setJuntas([]);
        setDefectosSoldadura({});
        setDefectosPintura([]);
        setLecturas(lecturasVacias());
        setMediciones(ESPESOR_MEDICIONES_BASE);
        setAdherencia(false);
        setFotos([]);
        setFotosGuardadas([]);
        setFotosQuitar([]);
        setConformes(0);
        setRechazadas([]);
        setDisposicionAcc('');
        setTipoDeducido('');
        setErrores({});
    };

    const limpiar = () => {
        reiniciarCampos();
        reiniciarSecciones();
        avisar('Formulario limpio');
    };

    /** Tras guardar se pasa a la siguiente pieza con lo general intacto. */
    const siguientePieza = () => {
        campos.limpiar(Object.keys(campos.valores).filter((clave) => !GENERALES.has(clave)));
        campos.set('cant', '1');
        reiniciarSecciones();
    };

    const construirInspeccion = () => {
        const prefijo = PREFIJO_FASE[fase];
        const puntos = Object.fromEntries(
            Object.entries(campos.valores).filter(
                ([clave, valor]) => clave.startsWith(prefijo) && !NO_SON_PUNTOS.has(clave) && valor !== '',
            ),
        );
        const idDe = (lista: Opcion[], nombre: string) => lista.find((defecto) => defecto.nombre === nombre)?.id ?? null;

        const defectos = soldado
            ? Object.entries(defectosSoldadura)
                  .filter(([, cantidad]) => cantidad > 0)
                  .map(([nombre, cantidad]) => ({ defecto_id: idDe(catalogos.defectosSoldadura, nombre), cantidad }))
            : esTercera
              ? defectosPintura.map((nombre) => ({ defecto_id: idDe(catalogos.defectosPintura, nombre), cantidad: 1 }))
              : [];

        return {
            obra_id: campos.v('obra'),
            fase,
            subetapa: esSegunda ? (SUBETAPAS[campos.v('p2_subetapa')] ?? null) : null,
            subtipo: esPrimera ? SUBTIPOS[campos.v('p1_subtipo') || 'Perfil'] : null,
            fecha: campos.v('fecha'),
            concepto_id: esPrimera ? vacioANulo(campos.v('concepto')) : null,
            consecutivo: esPrimera ? vacioANulo(campos.v('consec')) : null,
            cantidad_lote: esPrimera ? vacioANulo(campos.v('cant')) : null,
            prod_pieza_id: esPrimera ? null : (pieza?.id ?? null),
            kg: campos.v('kg'),
            folio_strumis: vacioANulo(campos.v('folio')),
            tipo_pieza_id: vacioANulo(campos.v('tipo')),
            linea: esPrimera ? null : vacioANulo(campos.v('linea')),
            modulo: esPrimera ? null : vacioANulo(campos.v('modulo')),
            equipo_id: esPrimera ? vacioANulo(campos.v('equipo')) : null,
            operador_id: esPrimera ? vacioANulo(campos.v('operador')) : null,
            responsable_id: esSegunda ? vacioANulo(campos.v('responsable')) : null,
            supervisor_pintura_id: esTercera ? vacioANulo(campos.v('supervisor')) : null,
            soldador_id: esSegunda ? vacioANulo(campos.v('soldador')) : null,
            estatus: ESTATUS[campos.v('status')] ?? '',
            avance_iv: esSegunda ? vacioANulo(campos.v('iv')) : null,
            avance_is: esSegunda ? vacioANulo(campos.v('is')) : null,
            observaciones: vacioANulo(campos.v('obs')),
            puntos,
            defectos,
            muestreo:
                esPrimera && campos.v('p1_lote')
                    ? {
                          tamano_lote: campos.v('p1_lote'),
                          nivel: campos.v('p1_nivel') || 'II',
                          conformes: muestreo.conformes,
                          rechazadas: muestreo.fallas.length,
                          disposicion: vacioANulo(muestreo.disposicion),
                          detalle_fallas: muestreo.fallas.length
                              ? muestreo.fallas.map((falla, i) => `#${i + 1} ${falla.detalle || 'sin detalle'}`).join('\n')
                              : null,
                      }
                    : null,
            juntas: soldado
                ? juntas.map((junta) => ({
                      identificador: junta.junta,
                      tipo: junta.tipo.toLowerCase(),
                      soldador_id: vacioANulo(junta.soldador),
                      espesor_requerido_mm: vacioANulo(junta.espesorRequerido),
                      espesor_medido_mm: vacioANulo(junta.espesorMedido),
                      es_empate: junta.esEmpate,
                      cordon_id: junta.cordonId ?? null,
                      puntos: junta.puntos,
                  }))
                : [],
            pintura: esTercera
                ? {
                      espesor_requerido_mils: vacioANulo(campos.v('p3_req')),
                      metodo: vacioANulo(campos.v('p3_metodo')),
                      area_m2: vacioANulo(campos.v('p3_area')),
                      mediciones_visibles: mediciones,
                      revision: vacioANulo(campos.v('p3_rev')),
                      accion: vacioANulo(campos.v('p3_accion')),
                      lecturas: lecturas.slice(0, mediciones).map((fila) => fila.map(vacioANulo)),
                  }
                : null,
            adherencia:
                esTercera && adherencia
                    ? {
                          resultado: vacioANulo(campos.v('p3_adhres')),
                          // El método va por tira: lo decide el espesor donde se cortó.
                          tiras: [
                              ['p3_adhmet', 'p3_adhclas'],
                              ['p3_adhmet2', 'p3_adhclas2'],
                              ['p3_adhmet3', 'p3_adhclas3'],
                          ].map(([metodo, clasificacion]) => ({
                              metodo: vacioANulo(campos.v(metodo)),
                              clasificacion: vacioANulo(campos.v(clasificacion)),
                          })),
                      }
                    : null,
            fotos: esTercera && adherencia ? fotos.map((foto) => foto.archivo) : [],
            fotos_quitar: fotosQuitar,
        };
    };

    /** Una entrega de accesorios: los datos de la marca y el muestreo de esta entrega. */
    const construirSublote = () => ({
        obra_id: campos.v('obra'),
        fase,
        fecha: campos.v('fecha'),
        marca: campos.v('ac_marca'),
        descripcion: vacioANulo(campos.v('ac_desc')),
        total_unidades: campos.v('ac_total'),
        kg_unitario: vacioANulo(campos.v('ac_kg')),
        elementos_unitarios: vacioANulo(campos.v('ac_elem')),
        unidades: campos.v('ac_unid'),
        nivel: campos.v('ac_nivel') || 'II',
        conformes,
        rechazadas: rechazadas.map((unidad) => ({
            // Pintada sólo cuenta la pintura: el servidor rechaza un defecto de
            // otra familia en un sublote de 3ª, y son etapas distintas del dato.
            defectos: esTercera
                ? idsDe(catalogos.defectosPintura, unidad.pintura)
                : [
                      ...idsDe(catalogos.defectosSoldadura, unidad.soldadura),
                      ...idsDe(catalogos.defectosAccDimensional, unidad.dimensional),
                      ...idsDe(catalogos.defectosAccBarrenos, unidad.barrenos),
                      ...(unidad.limpieza && catalogos.defectosAccLimpieza[0] ? [catalogos.defectosAccLimpieza[0].id] : []),
                  ],
        })),
        disposicion: vacioANulo(disposicionAcc),
        linea: vacioANulo(campos.v('linea')),
        modulo: vacioANulo(campos.v('modulo')),
        responsable_id: vacioANulo(campos.v('responsable')),
        soldador_id: vacioANulo(campos.v('soldador')),
        observaciones: vacioANulo(campos.v('obs')),
        sublote_origen_id: precarga?.acc?.origenId ?? null,
    });

    /**
     * Corregir va como PUT, pero por POST con `_method`: un PUT multipart no
     * trae los archivos, y la corrección de pintura puede traer fotos.
     */
    const enviar = (url: string, datos: Cuerpo) => {
        setGuardando(true);
        setErrores({});
        router.post(url, datos, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (pagina) => {
                const mensaje = (pagina.props as { flash?: { success?: string | null } }).flash?.success;
                siguientePieza();
                avisar(mensaje ?? 'Guardado', 'ok');
            },
            onError: (fallas) => {
                setErrores(fallas);
                avisar(Object.values(fallas)[0] ?? 'Revisa el formulario', 'error');
            },
            onFinish: () => setGuardando(false),
        });
    };

    const metodo = precarga?.destino.metodo === 'put' ? { _method: 'put' } : {};

    const guardarSublote = () => {
        if (!campos.v('obra')) {
            avisar('Falta la obra', 'error');
            return;
        }
        if (!campos.v('ac_marca').trim()) {
            avisar('Falta la marca del accesorio', 'error');
            return;
        }
        if (!campos.v('ac_total')) {
            avisar('Falta el total de unidades de la marca', 'error');
            return;
        }
        const unidades = parseInt(campos.v('ac_unid'), 10);
        if (!unidades) {
            avisar('Faltan las unidades de esta entrega', 'error');
            return;
        }

        const plan = planMuestreo(unidades, (campos.v('ac_nivel') || 'II') as NivelMuestreo, unidades);
        const vistas = conformes + rechazadas.length;
        if (
            plan &&
            vistas < plan.muestra &&
            !window.confirm(
                `Sólo se inspeccionaron ${vistas} de las ${plan.muestra} de la muestra. El sublote queda en curso, sin veredicto.\n\n¿Guardar igual?`,
            )
        ) {
            return;
        }

        enviar(precarga?.destino.url ?? '/admin/calidad/accesorios/sublotes', { ...construirSublote(), ...metodo });
    };

    const guardar = () => {
        if (esAccesorios) {
            guardarSublote();
            return;
        }

        if (!campos.v('obra')) {
            avisar('Falta la obra', 'error');
            return;
        }
        if (esPrimera && !campos.v('concepto')) {
            avisar('Elige la marca de la lista del catálogo de la obra', 'error');
            return;
        }
        if (!esPrimera && !pieza) {
            avisar('Escanea la pieza o teclea su código', 'error');
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
        if (soldado && !campos.v('p2_elem')) {
            avisar('Falta el nº de elementos de la pieza (del plano)', 'error');
            return;
        }

        /* Liberar una pieza con defectos marcados es una contradicción: o la pieza
           está bien, o no está liberada. Se avisa antes de guardar; no se bloquea,
           porque a veces se libera por concesión y eso lo decide una persona. */
        const marcados = (soldado ? Object.keys(defectosSoldadura).length : 0) + (esTercera ? defectosPintura.length : 0);
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

        enviar(precarga?.destino.url ?? '/admin/calidad/inspecciones', { ...construirInspeccion(), ...metodo });
    };

    const consecutivo = parseFloat(campos.v('consec'));
    const cantidad = parseFloat(campos.v('cant'));
    const consecutivoInvalido = !Number.isNaN(consecutivo) && !Number.isNaN(cantidad) && consecutivo > cantidad;
    const marcaSinCatalogo = esPrimera && marcaTexto.trim() !== '' && !campos.v('concepto');
    const obraTexto = opcionesObras.find(([id]) => id === campos.v('obra'))?.[1] ?? '';

    return (
        <AppLayout breadcrumbs={[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Calidad', href: '/admin/calidad/catalogos' },
            { title: 'Captura de inspección', href: '/admin/calidad/formularios' },
        ] satisfies BreadcrumbItem[]}>
            <Head title="Calidad — Captura de inspección" />

            <div className="bg-base-200 text-base-content">
                <div ref={columnaRef} className="w-full px-[14px] pt-4 pb-32">
                    <div className="mb-[14px] rounded-xl bg-primary px-4 py-[14px] text-primary-content">
                        <div className="text-[22px] font-extrabold tracking-[.5px]">Captura de inspección</div>
                        <div className="text-xs opacity-85">Control de Calidad · {auth.user.name}</div>
                    </div>

                    {precarga && (
                        <div className="mb-[14px] rounded-xl border border-info/40 bg-info/10 px-4 py-3 text-sm">
                            {precarga.titulo}{' '}
                            <Link
                                href={`/admin/calidad/formularios${campos.v('obra') ? `?obra=${campos.v('obra')}` : ''}`}
                                className="link font-semibold"
                            >
                                Salir sin guardar
                            </Link>
                        </div>
                    )}

                    {!precarga && (
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
                    )}

                    {pestana === 'registros' ? (
                        <>
                            <Tarjeta titulo="Obra">
                                <Campo label="Obra">
                                    <Selector
                                        value={campos.v('obra')}
                                        onChange={cambiarObra}
                                        opciones={opcionesObras}
                                        vacio="— Elige la obra —"
                                    />
                                </Campo>
                                <Pista className="mb-0">
                                    El listado completo, con filtros y exportación, está en{' '}
                                    <Link href="/admin/calidad/registros" className="link">
                                        Registros
                                    </Link>
                                    ; los lotes de accesorios, en la pestaña{' '}
                                    <Link href="/admin/calidad/dashboard?tab=accesorios" className="link">
                                        Accesorios
                                    </Link>{' '}
                                    del Tablero.
                                </Pista>
                            </Tarjeta>
                            <RegistrosDeCaptura
                                obraId={campos.v('obra')}
                                marcas={avance}
                                piezasDeMarca={piezasDeMarca}
                                onCapturar={capturarQr}
                            />
                        </>
                    ) : (
                        <>
                            <Tarjeta titulo="Transformación">
                                {conPrecarga ? (
                                    <Pista className="mb-0">
                                        <b>
                                            {FASES.find((opcion) => opcion.valor === fase)?.titulo} ·{' '}
                                            {FASES.find((opcion) => opcion.valor === fase)?.nota}
                                            {esAccesorios && ' · lote de accesorios'}
                                        </b>{' '}
                                        — no cambia aquí: es parte de lo que se corrige o reinspecciona.
                                    </Pista>
                                ) : (
                                    <>
                                        <Pista>Elige la etapa. El formulario cambia según la transformación.</Pista>
                                        <SelectorFase value={fase} onChange={(valor) => campos.set('fase', valor)} opciones={FASES} />
                                    </>
                                )}

                                {(esSegunda || esTercera) && !conPrecarga && (
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
                                                    Una unidad concreta, con su etiqueta QR, revisada al <b>100%</b>.
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
                                                <Selector
                                                    value={campos.v('equipo')}
                                                    onChange={(valor) => campos.set('equipo', valor)}
                                                    opciones={aPares(catalogos.equipos)}
                                                />
                                            </Campo>
                                        )}
                                    </Rejilla>
                                </div>

                                <div className="mt-3">
                                    <Rejilla>
                                        <Campo
                                            label="Obra / Proyecto"
                                            req
                                            ayuda={
                                                obras.length === 0
                                                    ? 'Ninguna obra activa en Calidad: una obra entra al abrir su catálogo en Producción.'
                                                    : undefined
                                            }
                                        >
                                            {conPrecarga ? (
                                                <Texto value={obraTexto} readOnly />
                                            ) : (
                                                <Selector value={campos.v('obra')} onChange={cambiarObra} opciones={opcionesObras} />
                                            )}
                                        </Campo>
                                        {esPrimera ? (
                                            <Campo label="Operador">
                                                <Selector
                                                    value={campos.v('operador')}
                                                    onChange={(valor) => campos.set('operador', valor)}
                                                    opciones={aPares(catalogos.operadores)}
                                                />
                                            </Campo>
                                        ) : esTercera ? (
                                            <Campo label="Supervisor de pintura">
                                                <Selector
                                                    value={campos.v('supervisor')}
                                                    onChange={(valor) => campos.set('supervisor', valor)}
                                                    opciones={aPares(catalogos.supervisoresPintura)}
                                                />
                                            </Campo>
                                        ) : (
                                            <Campo label="Responsable del módulo">
                                                <Selector
                                                    value={campos.v('responsable')}
                                                    onChange={(valor) => campos.set('responsable', valor)}
                                                    opciones={aPares(catalogos.responsables)}
                                                />
                                            </Campo>
                                        )}
                                    </Rejilla>
                                </div>
                            </Tarjeta>

                            {esAccesorios ? (
                                <>
                                    <LoteAccesorios
                                        campos={campos}
                                        conformes={conformes}
                                        onConformes={setConformes}
                                        rechazadas={rechazadas}
                                        onRechazadas={setRechazadas}
                                        disposicion={disposicionAcc}
                                        onDisposicion={setDisposicionAcc}
                                        familias={{
                                            soldadura: catalogos.defectosSoldadura.map((defecto) => defecto.nombre),
                                            dimensional: catalogos.defectosAccDimensional.map((defecto) => defecto.nombre),
                                            barrenos: catalogos.defectosAccBarrenos.map((defecto) => defecto.nombre),
                                            pintura: catalogos.defectosPintura.map((defecto) => defecto.nombre),
                                        }}
                                        lotes={lotes}
                                        numero={precarga?.acc?.numero ?? 1}
                                        marcaFija={conPrecarga}
                                        esPintura={esTercera}
                                        onAviso={avisar}
                                    />
                                    <Tarjeta titulo="Observaciones">
                                        <AreaTexto
                                            value={campos.v('obs')}
                                            onChange={(valor) => campos.set('obs', valor)}
                                            placeholder="ej. 3 piezas con rebaba en el barreno"
                                        />
                                    </Tarjeta>
                                </>
                            ) : (
                                <>
                                    <Tarjeta titulo="Pieza">
                                        {!esPrimera && (
                                            <div className="mb-3">
                                                <PiezaFisica
                                                    obraId={campos.v('obra')}
                                                    pieza={pieza}
                                                    onPieza={elegirPieza}
                                                    fija={piezaFija}
                                                    onAviso={avisar}
                                                />
                                            </div>
                                        )}

                                        <Rejilla cols={3}>
                                            {esPrimera && (
                                                <Campo
                                                    label="Marca de fabricación"
                                                    req
                                                    ayuda={
                                                        marcaSinCatalogo ? (
                                                            <span className="text-error">No está en el catálogo vigente de la obra.</span>
                                                        ) : (
                                                            marcaElegida?.descripcion ?? undefined
                                                        )
                                                    }
                                                >
                                                    <input
                                                        list="qal-marcas"
                                                        value={marcaTexto}
                                                        onChange={(e) => elegirMarca(e.target.value)}
                                                        placeholder={campos.v('obra') ? 'Busca la marca…' : 'Elige primero la obra'}
                                                        disabled={!campos.v('obra') || piezaFija}
                                                        className="input input-bordered w-full text-base"
                                                    />
                                                    <datalist id="qal-marcas">
                                                        {marcas.map((marca) => (
                                                            <option key={marca.id} value={etiquetaMarca(marca)} />
                                                        ))}
                                                    </datalist>
                                                </Campo>
                                            )}
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
                                                    opciones={tiposPieza}
                                                />
                                            </Campo>
                                            {!esPrimera && (
                                                <Campo label="Peso unitario (kg)" req>
                                                    <Texto
                                                        tipo="number"
                                                        paso="0.01"
                                                        value={campos.v('kg')}
                                                        onChange={(valor) => campos.set('kg', valor)}
                                                        placeholder="ej. 850"
                                                    />
                                                </Campo>
                                            )}
                                        </Rejilla>

                                        {tecladoFolio && (
                                            <TecladoFolio
                                                onTecla={(caracter) => campos.set('folio', (campos.v('folio') + caracter).toUpperCase())}
                                                onBorrar={() => campos.set('folio', campos.v('folio').slice(0, -1))}
                                                onListo={() => setTecladoFolio(false)}
                                            />
                                        )}

                                        {esPrimera && (
                                            <>
                                                <Pista className="mt-[10px]">
                                                    Un lote puede traer varias piezas con la <b>misma marca</b>. Indica
                                                    cuántas hay y cuál es ésta.
                                                </Pista>
                                                <Rejilla cols={3}>
                                                    <Campo label="Piezas en el lote">
                                                        <Texto tipo="number" value={campos.v('cant')} onChange={(valor) => campos.set('cant', valor)} />
                                                    </Campo>
                                                    <Campo label="# de pieza (consecutivo)" req>
                                                        <Texto
                                                            tipo="number"
                                                            value={campos.v('consec')}
                                                            onChange={(valor) => campos.set('consec', valor)}
                                                            readOnly={piezaFija}
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
                                            </>
                                        )}

                                        <div className="mt-[6px] max-w-[160px]">
                                            <Campo label="# Inspección">
                                                <Texto value={numeroInspeccion} readOnly />
                                            </Campo>
                                        </div>

                                        {esSegunda && (
                                            <div className="mt-3">
                                                <Campo label="Soldador Principal de la pieza">
                                                    <Selector
                                                        value={campos.v('soldador')}
                                                        onChange={(valor) => campos.set('soldador', valor)}
                                                        opciones={soldadores}
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
                                            defectosCatalogo={catalogos.defectosSoldadura.map((defecto) => defecto.nombre)}
                                            juntas={juntas}
                                            onJuntas={setJuntas}
                                            soldadores={soldadores}
                                            subetapaFija={piezaFija}
                                            modelo3d={pieza ? { marcaId: pieza.modelo_marca_id, estado: pieza.modelo_3d } : null}
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
                                            defectosCatalogo={catalogos.defectosPintura.map((defecto) => defecto.nombre)}
                                            adherenciaAbierta={adherencia}
                                            onAdherencia={setAdherencia}
                                            fotos={fotos}
                                            onFotos={setFotos}
                                            guardadas={fotosGuardadas}
                                            onQuitarGuardada={(id) => {
                                                setFotosGuardadas((previas) => previas.filter((foto) => foto.id !== id));
                                                setFotosQuitar((previas) => [...previas, id]);
                                            }}
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

                            {Object.keys(errores).length > 0 && (
                                <div className="mb-3.5 rounded-box bg-error/10 px-3 py-2 text-sm text-error">
                                    <div className="font-semibold">No se guardó:</div>
                                    <ul className="mt-1 list-disc pl-5">
                                        {Object.entries(errores).map(([clave, mensaje]) => (
                                            <li key={clave}>{mensaje}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            {pestana === 'capturar' && (
                <div
                    className="fixed bottom-0 z-30 flex gap-[10px] border-t border-base-300 bg-base-100 px-[14px] py-3 shadow-[0_-2px_12px_rgba(18,35,61,.08)]"
                    style={cajaColumna ? { left: cajaColumna.left, width: cajaColumna.width } : { left: 0, right: 0 }}
                >
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
                        disabled={guardando}
                        className="flex-1 rounded-[11px] bg-primary py-4 text-[17px] font-bold text-primary-content disabled:opacity-60"
                    >
                        {guardando
                            ? 'Guardando…'
                            : precarga?.modo === 'editar'
                              ? 'Guardar corrección'
                              : esAccesorios
                                ? 'Guardar sublote'
                                : 'Guardar registro'}
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
