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

type Escena = {
    raiz: THREE.Group;
    material: LineMaterial;
    lineas: LineSegments2 | null;
    /** Índice de segmento → id del cordón al que pertenece. */
    segmentos: number[];
};

/** Un toque que se mueve más que esto es un arrastre para girar, no una selección. */
const TOLERANCIA_TOQUE_PX = 6;

function encuadrar(camara: THREE.PerspectiveCamera, controles: OrbitControls, caja: THREE.Box3): void {
    const tamano = caja.getSize(new THREE.Vector3());
    const centro = caja.getCenter(new THREE.Vector3());
    const distancia = Math.max(tamano.x, tamano.y, tamano.z, 0.3) * 1.5;
    controles.target.copy(centro);
    camara.position.set(centro.x + distancia, centro.y - distancia, centro.z + distancia * 0.45);
    controles.update();
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
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        alSeleccionar.current = onSeleccionar;
    }, [onSeleccionar]);

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

        escena.current = { raiz, material, lineas: null, segmentos: [] };

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
                encuadrar(camara, controles, new THREE.Box3().setFromObject(gltf.scene));
                setCargando(false);
            },
            undefined,
            () => {
                if (vivo) {
                    setError('No se pudo cargar la geometría de la marca.');
                    setCargando(false);
                }
            },
        );

        const rayo = new THREE.Raycaster();
        (rayo.params as { Line2?: { threshold: number } }).Line2 = { threshold: 12 };
        let inicio: { x: number; y: number } | null = null;

        const alPresionar = (evento: PointerEvent) => {
            inicio = { x: evento.clientX, y: evento.clientY };
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

    return (
        <div className={cn('relative h-[420px] overflow-hidden rounded-box border border-base-300', className)}>
            <div ref={contenedor} className="size-full touch-none" />
            {cargando && (
                <div className="absolute inset-0 flex items-center justify-center text-sm text-white/70">Cargando la marca…</div>
            )}
            {error && <div className="absolute inset-0 flex items-center justify-center text-sm text-error">{error}</div>}
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
