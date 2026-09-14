/**
 * Visor 3D de una marca: su geometría y sus cordones de soldadura.
 *
 * Es el `viewer_mark.html` de demo3d reducido a lo que necesita Calidad:
 * cargar el .glb de la marca, dibujar los cordones coloreados por cómo van y
 * dejar elegir uno con un toque. No se portan la hoja imprimible, el atlas de
 * números ni el despiece.
 *
 * El modelo viene de Tekla con Z hacia arriba y así se deja: la cámara se
 * orienta a Z en lugar de rotar la geometría, para que los puntos de los
 * cordones caigan donde dice la base sin convertirlos.
 *
 * Los cordones van en una sola geometría de líneas con grosor en píxeles
 * (LineSegments2): cientos de cordones son una sola llamada de dibujo, y el
 * rayo devuelve qué segmento se tocó.
 */

import { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { LineMaterial } from 'three/examples/jsm/lines/LineMaterial.js';
import { LineSegments2 } from 'three/examples/jsm/lines/LineSegments2.js';
import { LineSegmentsGeometry } from 'three/examples/jsm/lines/LineSegmentsGeometry.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { cn } from '@/lib/utils';
import { COLOR_ESTADO, COLOR_SELECCION, ETIQUETA_ESTADO, type EstadoCordon } from './tipos';

export type CordonDibujo = { id: number; puntos: number[][]; estado: EstadoCordon };

/** Dónde mira la cámara: su posición y el punto al que apunta. */
type Vista = { posicion: THREE.Vector3; objetivo: THREE.Vector3 };

type Escena = {
    raiz: THREE.Group;
    material: LineMaterial;
    lineas: LineSegments2 | null;
    /** Índice de segmento → id del cordón al que pertenece. */
    segmentos: number[];
    camara: THREE.PerspectiveCamera;
    controles: OrbitControls;
    /** La pieza entera, para volver de un acercamiento. */
    caja: THREE.Box3 | null;
    /** Viaje de cámara en curso, si se está encuadrando algo. */
    viaje: { desde: Vista; hasta: Vista; inicio: number } | null;
};

/** Un toque que se mueve más que esto es un arrastre para girar, no una selección. */
const TOLERANCIA_TOQUE_PX = 6;

/** Lo que tarda en encuadrar un cordón. Un salto seco desorienta. */
const VIAJE_MS = 420;

/** Desde dónde se ve una caja completa, en el eje de siempre (Z arriba). */
function vistaDe(caja: THREE.Box3): Vista {
    const tamano = caja.getSize(new THREE.Vector3());
    const centro = caja.getCenter(new THREE.Vector3());
    const distancia = Math.max(tamano.x, tamano.y, tamano.z, 0.3) * 1.5;

    return {
        objetivo: centro,
        posicion: new THREE.Vector3(centro.x + distancia, centro.y - distancia, centro.z + distancia * 0.45),
    };
}

/** Lleva la cámara a una vista: de golpe al cargar, animada cuando ya se está viendo. */
function irA(escena: Escena, vista: Vista, animado: boolean): void {
    if (!animado) {
        escena.viaje = null;
        escena.camara.position.copy(vista.posicion);
        escena.controles.target.copy(vista.objetivo);
        escena.controles.update();
        return;
    }
    escena.viaje = {
        desde: { posicion: escena.camara.position.clone(), objetivo: escena.controles.target.clone() },
        hasta: vista,
        inicio: performance.now(),
    };
}

/** Caja que encierra al cordón, holgada para que se vea con algo de pieza alrededor. */
function cajaDelCordon(puntos: number[][]): THREE.Box3 | null {
    if (puntos.length === 0) {
        return null;
    }
    const caja = new THREE.Box3();
    puntos.forEach(([x, y, z]) => caja.expandByPoint(new THREE.Vector3(x, y, z)));
    const tamano = caja.getSize(new THREE.Vector3());
    caja.expandByScalar(Math.max(tamano.x, tamano.y, tamano.z, 0.2) * 0.8);

    return caja;
}

export function Visor({
    glbUrl,
    cordones,
    seleccionado,
    onSeleccionar,
    className,
}: {
    glbUrl: string;
    cordones: CordonDibujo[];
    seleccionado: number | null;
    onSeleccionar: (id: number | null) => void;
    className?: string;
}) {
    const contenedor = useRef<HTMLDivElement>(null);
    const escena = useRef<Escena | null>(null);
    const alSeleccionar = useRef(onSeleccionar);
    const cordonesRef = useRef(cordones);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        alSeleccionar.current = onSeleccionar;
        // El encuadre lee los cordones de aquí: si dependiera de ellos volvería
        // a encuadrar cada vez que se responde una celda del mapeo.
        cordonesRef.current = cordones;
    }, [onSeleccionar, cordones]);

    // La escena se arma una vez por marca; quien usa el visor le pone `key`
    // con la url para que cambiar de marca empiece de cero.
    useEffect(() => {
        const div = contenedor.current;
        if (!div) {
            return;
        }

        let vivo = true;
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x1a1a1e);

        const camara = new THREE.PerspectiveCamera(50, 1, 0.02, 2000);
        camara.up.set(0, 0, 1);

        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        div.appendChild(renderer.domElement);

        const controles = new OrbitControls(camara, renderer.domElement);
        controles.enableDamping = true;

        scene.add(new THREE.HemisphereLight(0xffffff, 0x334455, 1.15));
        const sol = new THREE.DirectionalLight(0xffffff, 0.75);
        sol.position.set(1, 2, 3);
        const sol2 = new THREE.DirectionalLight(0xffffff, 0.35);
        sol2.position.set(-2, -1, 1);
        scene.add(sol, sol2);

        const raiz = new THREE.Group();
        scene.add(raiz);

        const acero = new THREE.MeshStandardMaterial({
            color: 0xa8b3c0,
            metalness: 0.6,
            roughness: 0.5,
            flatShading: true,
            transparent: true,
            opacity: 0.8,
        });
        // Sin prueba de profundidad: un cordón tapado por el acero se sigue
        // viendo, y se puede elegir sin girar la pieza.
        const material = new LineMaterial({ linewidth: 4, vertexColors: true, dashed: false, depthTest: false, transparent: true });

        escena.current = { raiz, material, lineas: null, segmentos: [], camara, controles, caja: null, viaje: null };

        const ajustar = () => {
            const ancho = div.clientWidth;
            const alto = Math.max(div.clientHeight, 1);
            renderer.setSize(ancho, alto);
            camara.aspect = ancho / alto;
            camara.updateProjectionMatrix();
            material.resolution.set(ancho, alto);
        };
        const observador = new ResizeObserver(ajustar);
        observador.observe(div);
        ajustar();

        let cuadro = 0;
        const pintar = () => {
            const viaje = escena.current?.viaje;
            if (viaje) {
                const avance = Math.min(1, (performance.now() - viaje.inicio) / VIAJE_MS);
                // Suavizado en las dos puntas: arranca y frena sin tirones.
                const paso = avance < 0.5 ? 2 * avance * avance : 1 - 2 * (1 - avance) ** 2;
                camara.position.lerpVectors(viaje.desde.posicion, viaje.hasta.posicion, paso);
                controles.target.lerpVectors(viaje.desde.objetivo, viaje.hasta.objetivo, paso);
                if (avance === 1 && escena.current) {
                    escena.current.viaje = null;
                }
            }
            controles.update();
            renderer.render(scene, camara);
            cuadro = requestAnimationFrame(pintar);
        };
        pintar();

        new GLTFLoader().load(
            glbUrl,
            (gltf) => {
                if (!vivo) {
                    return;
                }
                gltf.scene.traverse((objeto) => {
                    const malla = objeto as THREE.Mesh;
                    if (malla.isMesh) {
                        (malla.material as THREE.Material).dispose();
                        malla.material = acero;
                    }
                });
                raiz.add(gltf.scene);
                const caja = new THREE.Box3().setFromObject(gltf.scene);
                if (escena.current) {
                    escena.current.caja = caja;
                    irA(escena.current, vistaDe(caja), false);
                }
                setCargando(false);
            },
            undefined,
            (fallo: unknown) => {
                if (vivo) {
                    // Con la url y el motivo: el visor se usa en la tablet, donde no
                    // hay consola que abrir para saber qué archivo faltó.
                    const motivo = fallo instanceof Error ? fallo.message : '';
                    setError(`No se pudo cargar la geometría de la marca: ${glbUrl}${motivo ? ` — ${motivo}` : ''}`);
                    setCargando(false);
                }
            },
        );

        const rayo = new THREE.Raycaster();
        (rayo.params as { Line2?: { threshold: number } }).Line2 = { threshold: 12 };
        let inicio: { x: number; y: number } | null = null;

        const alPresionar = (evento: PointerEvent) => {
            inicio = { x: evento.clientX, y: evento.clientY };
            // Tocar la pieza manda sobre el encuadre en curso.
            if (escena.current) {
                escena.current.viaje = null;
            }
        };
        const alSoltar = (evento: PointerEvent) => {
            const lineas = escena.current?.lineas;
            if (!inicio || !lineas || Math.hypot(evento.clientX - inicio.x, evento.clientY - inicio.y) > TOLERANCIA_TOQUE_PX) {
                return;
            }
            const caja = renderer.domElement.getBoundingClientRect();
            const puntero = new THREE.Vector2(
                ((evento.clientX - caja.left) / caja.width) * 2 - 1,
                -((evento.clientY - caja.top) / caja.height) * 2 + 1,
            );
            rayo.setFromCamera(puntero, camara);
            const [golpe] = rayo.intersectObject(lineas, false);
            const segmento = golpe?.faceIndex;
            alSeleccionar.current(segmento != null ? (escena.current?.segmentos[segmento] ?? null) : null);
        };
        renderer.domElement.addEventListener('pointerdown', alPresionar);
        renderer.domElement.addEventListener('pointerup', alSoltar);

        return () => {
            vivo = false;
            cancelAnimationFrame(cuadro);
            observador.disconnect();
            renderer.domElement.removeEventListener('pointerdown', alPresionar);
            renderer.domElement.removeEventListener('pointerup', alSoltar);
            controles.dispose();
            raiz.traverse((objeto) => {
                const malla = objeto as THREE.Mesh;
                if (malla.geometry) {
                    malla.geometry.dispose();
                }
            });
            acero.dispose();
            material.dispose();
            renderer.dispose();
            renderer.domElement.remove();
            escena.current = null;
        };
    }, [glbUrl]);

    // Los cordones se rehacen cuando cambian sus estados o la selección: son
    // unos cientos de segmentos, y rehacerlos es más simple que parchar colores.
    useEffect(() => {
        const actual = escena.current;
        if (!actual) {
            return;
        }

        if (actual.lineas) {
            actual.raiz.remove(actual.lineas);
            actual.lineas.geometry.dispose();
            actual.lineas = null;
        }

        const posiciones: number[] = [];
        const colores: number[] = [];
        const segmentos: number[] = [];
        const color = new THREE.Color();

        for (const cordon of cordones) {
            color.setHex(cordon.id === seleccionado ? COLOR_SELECCION : COLOR_ESTADO[cordon.estado]);
            for (let i = 0; i < cordon.puntos.length - 1; i++) {
                const [a, b] = [cordon.puntos[i], cordon.puntos[i + 1]];
                posiciones.push(a[0], a[1], a[2], b[0], b[1], b[2]);
                colores.push(color.r, color.g, color.b, color.r, color.g, color.b);
                segmentos.push(cordon.id);
            }
        }

        actual.segmentos = segmentos;
        if (!posiciones.length) {
            return;
        }

        const geometria = new LineSegmentsGeometry();
        geometria.setPositions(posiciones);
        geometria.setColors(colores);
        const lineas = new LineSegments2(geometria, actual.material);
        lineas.computeLineDistances();
        lineas.renderOrder = 10;
        lineas.frustumCulled = false;
        actual.raiz.add(lineas);
        actual.lineas = lineas;
    }, [cordones, seleccionado]);

    // Elegir un cordón lo encuadra: en una marca con cientos de cordones, el
    // color no basta para encontrarlo. Al soltar la selección se ve toda la pieza.
    useEffect(() => {
        const actual = escena.current;
        if (!actual || cargando) {
            return;
        }
        const cordon = cordonesRef.current.find((candidato) => candidato.id === seleccionado);
        const caja = cordon ? cajaDelCordon(cordon.puntos) : actual.caja;
        if (caja) {
            irA(actual, vistaDe(caja), true);
        }
    }, [seleccionado, cargando]);

    /** Volver a la pieza completa sin perder la junta que se está capturando. */
    const verTodo = () => {
        const actual = escena.current;
        if (actual?.caja) {
            irA(actual, vistaDe(actual.caja), true);
        }
    };

    return (
        <div className={cn('relative h-[420px] overflow-hidden rounded-box border border-base-300', className)}>
            <div ref={contenedor} className="size-full touch-none" />
            {cargando && (
                <div className="absolute inset-0 flex items-center justify-center text-sm text-white/70">Cargando la marca…</div>
            )}
            {error && (
                <div className="absolute inset-0 flex items-center justify-center px-4 text-center text-sm break-words text-error">
                    {error}
                </div>
            )}
            {!cargando && !error && (
                <button
                    type="button"
                    onClick={verTodo}
                    className="absolute top-2 right-2 rounded-lg bg-black/60 px-2 py-1 text-[11px] font-semibold text-white"
                >
                    ⤢ Toda la pieza
                </button>
            )}
            <div className="pointer-events-none absolute bottom-2 left-2 flex flex-wrap gap-2 rounded-lg bg-black/60 px-2 py-1 text-[11px] text-white">
                {(Object.keys(COLOR_ESTADO) as EstadoCordon[]).map((estado) => (
                    <span key={estado} className="flex items-center gap-1">
                        <span className="inline-block size-2.5 rounded-full" style={{ background: `#${COLOR_ESTADO[estado].toString(16).padStart(6, '0')}` }} />
                        {ETIQUETA_ESTADO[estado]}
                    </span>
                ))}
            </div>
        </div>
    );
}
