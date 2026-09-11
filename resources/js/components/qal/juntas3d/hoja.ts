/**
 * La hoja imprimible de una marca: cuatro isométricas con sus cordones
 * numerados, para una A4 apaisada.
 *
 * Portada de `viewer_mark.html` de demo3d. Allá la hoja se sacaba del mismo
 * visor en pantalla, cambiándole materiales y cámara y devolviéndolos al
 * terminar; aquí se arma una escena aparte, fuera de la pantalla, con el mismo
 * .glb. El visor no se toca, y la hoja se puede pedir desde donde no hay visor
 * (la ficha de Registros).
 *
 * Las cuatro maneras de dibujarla son las de demo3d:
 *  - sombreado: el acero en gris y cada cordón numerado en las cuatro vistas;
 *  - línea: lo mismo, en dibujo de línea;
 *  - reparto: cada cordón se numera UNA vez, en una vista desde la que se ve,
 *    con el número al margen y una línea del mismo color hasta la junta;
 *  - repartonum: igual, con el número sobre la propia junta.
 *
 * El modelo viene de Tekla con Z hacia arriba, en metros, igual que los puntos
 * de los cordones.
 */

import * as THREE from 'three';
import { LineMaterial } from 'three/examples/jsm/lines/LineMaterial.js';
import { LineSegments2 } from 'three/examples/jsm/lines/LineSegments2.js';
import { LineSegmentsGeometry } from 'three/examples/jsm/lines/LineSegmentsGeometry.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';

export type ModoHoja = 'sombreado' | 'linea' | 'reparto' | 'repartonum';

/** Lo mínimo de un cordón para dibujarlo: su número (el S de la junta) y su trazo. */
export type CordonHoja = { numero: number; puntos: number[][] };

export type VistaHoja = { nombre: string; src: string };

export type HojaGenerada = {
    /** Una marca alta va en cuatro celdas verticales; una tumbada, en una rejilla de 2×2. */
    clase: 'alta' | 'tumbada';
    modo: ModoHoja;
    vistas: VistaHoja[];
    /** Los cordones tapados en las cuatro vistas: el reparto no los numera. */
    ocultos: number[];
    /** Si hubo que encoger los números de las llamadas por falta de lugar. */
    apretado: boolean;
};

/** Altura de cámara: unos 30 grados sobre el horizonte. */
const ELEV = 0.58;
/** Separación de cada vista respecto al azimut ideal. */
const DESVIO = (25 * Math.PI) / 180;
const PAPEL = {
    fondo: 0xffffff,
    acero: 0x9fa8b2,
    cordon: '#d83a00',
    tinta: { relleno: '#a02c00', contorno: 'rgba(255,255,255,0.95)' },
};
/** Alto del número impreso: por debajo no se lee. */
const DIGITO_MM = 2.0;
/** Fracción del ancho que el reparto con llamadas guarda a cada lado para los números. */
const MARGEN_X = 0.155;

/**
 * Colores de llamada: se rotan, así que sólo tienen que distinguirse de sus
 * vecinos. Oscuros, para que impriman bien sobre blanco.
 */
const TINTAS = ['#c0392b', '#1f6f3f', '#1b4f9c', '#8e44ad', '#a0522d', '#00707d', '#a8322d', '#3d5a1e'];

type Disposicion = { clase: 'alta' | 'tumbada'; corta: boolean; celdaMM: number; W: number; H: number };
type Vista = { nombre: string; corto: string; dir: THREE.Vector3 };
type Soldadura = { numero: number; fina: THREE.Vector3[]; mid: THREE.Vector3 };
type Numeros = {
    malla: THREE.Mesh<THREE.InstancedBufferGeometry, THREE.ShaderMaterial>;
    textura: THREE.CanvasTexture;
    plano: THREE.PlaneGeometry;
};

/**
 * Cuatro isométricas desde las cuatro esquinas: un cordón tapado en una se ve
 * en otra. El azimut se fija al lado largo de la marca y no a los ejes: si no,
 * una pieza de 3 m se ve en diagonal y deja el papel medio vacío.
 */
function vistasDe(caja: THREE.Box3): Vista[] {
    const tamano = caja.getSize(new THREE.Vector3());
    const largo = tamano.y >= tamano.x ? Math.PI / 2 : 0;
    const base = largo - Math.PI / 2;
    const elevacion = Math.round((Math.atan(ELEV) * 180) / Math.PI);

    return [-DESVIO, DESVIO, Math.PI - DESVIO, Math.PI + DESVIO].map((desvio, i) => {
        const phi = base + desvio;
        const azimut = Math.round(((((phi * 180) / Math.PI) % 360) + 360) % 360);
        return {
            nombre: `Isométrica ${i + 1} · azimut ${azimut}° · elevación ${elevacion}°`,
            corto: `Iso ${i + 1} · az ${azimut}°`,
            dir: new THREE.Vector3(Math.cos(phi), Math.sin(phi), ELEV).normalize(),
        };
    });
}

/**
 * La hoja se reparte según la forma de la marca: una columna de 12 m en una
 * celda apaisada sale como una tira; en cuatro celdas verticales llena el papel.
 */
function disposicion(caja: THREE.Box3): Disposicion {
    const tamano = caja.getSize(new THREE.Vector3());

    return tamano.z > Math.hypot(tamano.x, tamano.y)
        ? { clase: 'alta', corta: true, celdaMM: 68, W: 700, H: 1538 }
        : { clase: 'tumbada', corta: false, celdaMM: 139, W: 1400, H: 711 };
}

/**
 * La cámara de papel es ortográfica: una isométrica de taller no lleva
 * perspectiva, y así el encuadre es exacto (las ocho esquinas de la caja se
 * proyectan sobre los ejes de la cámara y el marco se ajusta a esa medida).
 */
function encuadrar(camara: THREE.OrthographicCamera, caja: THREE.Box3, dir: THREE.Vector3, aspecto: number, margenX = 0): void {
    const centro = caja.getCenter(new THREE.Vector3());
    const vista = dir.clone().negate();
    const derecha = new THREE.Vector3().crossVectors(vista, camara.up).normalize();
    const arriba = new THREE.Vector3().crossVectors(derecha, vista).normalize();

    let ex = 0;
    let ey = 0;
    let ez = 0;
    for (let i = 0; i < 8; i++) {
        const esquina = new THREE.Vector3(
            i % 2 ? caja.max.x : caja.min.x,
            Math.floor(i / 2) % 2 ? caja.max.y : caja.min.y,
            Math.floor(i / 4) % 2 ? caja.max.z : caja.min.z,
        ).sub(centro);
        ex = Math.max(ex, Math.abs(esquina.dot(derecha)));
        ey = Math.max(ey, Math.abs(esquina.dot(arriba)));
        ez = Math.max(ez, Math.abs(esquina.dot(dir)));
    }

    let w = Math.max(ex, 0.02) * 1.04;
    let h = Math.max(ey, 0.02) * 1.04;
    // El modelo se aparta a la banda central y los lados quedan para los números.
    if (margenX) {
        w /= 1 - 2 * margenX;
    }
    if (w / h < aspecto) {
        w = h * aspecto;
    } else {
        h = w / aspecto;
    }

    camara.left = -w;
    camara.right = w;
    camara.top = h;
    camara.bottom = -h;
    camara.near = 0.01;
    camara.far = 2 * ez + 10;
    camara.position.copy(centro).addScaledVector(dir, ez + 1);
    camara.lookAt(centro);
    camara.updateProjectionMatrix();
}

function muestrasDe(puntos: THREE.Vector3[], n = 5): THREE.Vector3[] {
    if (puntos.length <= n) {
        return puntos;
    }

    return Array.from({ length: n }, (_, i) => puntos[Math.round((i * (puntos.length - 1)) / (n - 1))]);
}

/**
 * Un cordón repetido en las cuatro vistas es ruido: en una marca de 368
 * cordones son 1472 números. Se tira un rayo del cordón hacia la cámara y se
 * le adjudica una vista desde la que se ve sin nada delante. Van primero los
 * que sólo se ven desde una vista, y a igualdad, a la vista que menos lleve:
 * adjudicar a la primera que lo vea dejaba la vista 1 con casi todo.
 */
function repartir(soldaduras: Soldadura[], mallas: THREE.Mesh[], caja: THREE.Box3, vistas: Vista[]): { asignado: Map<number, number>; ocultos: number[] } {
    const diagonal = caja.getSize(new THREE.Vector3()).length();
    const rayo = new THREE.Raycaster();
    rayo.far = diagonal * 2;
    // ~1 mm: el cordón está pegado al acero y el rayo no debe salir de dentro.
    const salto = Math.max(diagonal / 2500, 0.0008);

    const opciones = soldaduras.map((soldadura) => {
        const muestras = muestrasDe(soldadura.fina);
        const visibles = vistas.flatMap((vista, indice) =>
            muestras.some((punto) => {
                rayo.set(punto.clone().addScaledVector(vista.dir, salto), vista.dir);
                return rayo.intersectObjects(mallas, false).length === 0;
            })
                ? [indice]
                : [],
        );
        return { numero: soldadura.numero, visibles };
    });

    opciones.sort((a, b) => a.visibles.length - b.visibles.length || a.numero - b.numero);
    const carga = vistas.map(() => 0);
    const asignado = new Map<number, number>();
    const ocultos: number[] = [];

    for (const opcion of opciones) {
        if (!opcion.visibles.length) {
            ocultos.push(opcion.numero);
            continue;
        }
        let mejor = opcion.visibles[0];
        for (const vista of opcion.visibles) {
            if (carga[vista] < carga[mejor]) {
                mejor = vista;
            }
        }
        asignado.set(opcion.numero, mejor);
        carga[mejor]++;
    }

    return { asignado, ocultos: ocultos.sort((a, b) => a - b) };
}

/**
 * Todos los números en UNA textura y UNA malla instanciada: cientos de
 * etiquetas sueltas serían cientos de llamadas de dibujo.
 */
function numerosDe(numeros: number[], posiciones: Float32Array): Numeros {
    const celda = 96;
    const lado = Math.ceil(Math.sqrt(numeros.length));
    const lienzo = document.createElement('canvas');
    lienzo.width = lado * celda;
    lienzo.height = lado * celda;
    const g = lienzo.getContext('2d');
    if (!g) {
        throw new Error('El navegador no deja dibujar los números de la hoja.');
    }

    g.font = `bold ${Math.round(celda * 0.6)}px system-ui, sans-serif`;
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    g.lineWidth = Math.round(celda * 0.1);
    g.strokeStyle = PAPEL.tinta.contorno;
    g.fillStyle = PAPEL.tinta.relleno;

    const uv = new Float32Array(numeros.length * 2);
    numeros.forEach((numero, i) => {
        const x = (i % lado) * celda + celda / 2;
        const y = Math.floor(i / lado) * celda + celda / 2;
        g.strokeText(String(numero), x, y);
        g.fillText(String(numero), x, y);
        uv[i * 2] = (i % lado) / lado;
        uv[i * 2 + 1] = 1 - (Math.floor(i / lado) + 1) / lado;
    });

    const textura = new THREE.CanvasTexture(lienzo);
    textura.anisotropy = 4;
    const plano = new THREE.PlaneGeometry(1, 1);
    const geometria = new THREE.InstancedBufferGeometry();
    geometria.setIndex(plano.index);
    geometria.setAttribute('position', plano.attributes.position);
    geometria.setAttribute('uv', plano.attributes.uv);
    geometria.setAttribute('aOffset', new THREE.InstancedBufferAttribute(posiciones, 3));
    geometria.setAttribute('aUv', new THREE.InstancedBufferAttribute(uv, 2));
    geometria.setAttribute('aAlpha', new THREE.InstancedBufferAttribute(new Float32Array(numeros.length).fill(1), 1));
    geometria.instanceCount = numeros.length;

    const material = new THREE.ShaderMaterial({
        transparent: true,
        depthTest: false,
        depthWrite: false,
        uniforms: { uTex: { value: textura }, uSize: { value: 1 }, uCell: { value: 1 / lado } },
        vertexShader: [
            'attribute vec3 aOffset; attribute vec2 aUv; attribute float aAlpha;',
            'uniform float uSize;',
            'varying vec2 vUv; varying vec2 vQuad; varying float vAlpha;',
            'void main() {',
            '  vUv = aUv; vQuad = uv; vAlpha = aAlpha;',
            '  vec4 mv = modelViewMatrix * vec4(aOffset, 1.0);',
            '  mv.xy += position.xy * uSize;',
            '  gl_Position = projectionMatrix * mv;',
            '}',
        ].join('\n'),
        fragmentShader: [
            'uniform sampler2D uTex; uniform float uCell;',
            'varying vec2 vUv; varying vec2 vQuad; varying float vAlpha;',
            'void main() {',
            '  vec4 t = texture2D(uTex, vUv + vQuad * uCell);',
            '  if (t.a < 0.04 || vAlpha < 0.01) discard;',
            '  gl_FragColor = vec4(t.rgb, t.a * vAlpha);',
            '}',
        ].join('\n'),
    });

    const malla = new THREE.Mesh(geometria, material);
    malla.frustumCulled = false;
    malla.renderOrder = 999;

    return { malla, textura, plano };
}

function lineasDe(soldaduras: Soldadura[], material: LineMaterial, colorDe: (numero: number) => string): LineSegments2 | null {
    const posiciones: number[] = [];
    const colores: number[] = [];
    const color = new THREE.Color();

    for (const soldadura of soldaduras) {
        color.set(colorDe(soldadura.numero));
        for (let i = 0; i < soldadura.fina.length - 1; i++) {
            const [a, b] = [soldadura.fina[i], soldadura.fina[i + 1]];
            posiciones.push(a.x, a.y, a.z, b.x, b.y, b.z);
            colores.push(color.r, color.g, color.b, color.r, color.g, color.b);
        }
    }

    if (!posiciones.length) {
        return null;
    }

    const geometria = new LineSegmentsGeometry();
    geometria.setPositions(posiciones);
    geometria.setColors(colores);
    const lineas = new LineSegments2(geometria, material);
    lineas.computeLineDistances();
    lineas.frustumCulled = false;

    return lineas;
}

/**
 * Las aristas de las piezas, para el dibujo de línea: el acero va en blanco y
 * sin ellas no se vería la forma. 25 grados quita el mallado de las caras
 * planas.
 */
function bordesDe(mallas: THREE.Mesh[], material: LineMaterial): LineSegments2 | null {
    const posiciones: number[] = [];
    const punto = new THREE.Vector3();

    for (const malla of mallas) {
        const aristas = new THREE.EdgesGeometry(malla.geometry, 25);
        const valores = aristas.attributes.position.array;
        for (let i = 0; i < valores.length; i += 3) {
            punto.set(valores[i], valores[i + 1], valores[i + 2]).applyMatrix4(malla.matrixWorld);
            posiciones.push(punto.x, punto.y, punto.z);
        }
        aristas.dispose();
    }

    if (!posiciones.length) {
        return null;
    }

    const geometria = new LineSegmentsGeometry();
    geometria.setPositions(posiciones);
    const lineas = new LineSegments2(geometria, material);
    lineas.computeLineDistances();
    lineas.frustumCulled = false;
    lineas.renderOrder = 1;

    return lineas;
}

function proyectar(punto: THREE.Vector3, camara: THREE.OrthographicCamera, W: number, H: number): { x: number; y: number } {
    const v = punto.clone().project(camara);

    return { x: (v.x * 0.5 + 0.5) * W, y: (-v.y * 0.5 + 0.5) * H };
}

/**
 * El número encima de la pieza se pierde entre la geometría. Aquí se proyecta
 * cada cordón a píxeles, la etiqueta se manda al margen y se une con una línea
 * del mismo color con el que se pinta el cordón. Todo en 2D sobre la imagen ya
 * renderizada: ordenarlo en 3D sería imposible.
 */
function dibujarLlamadas(
    g: CanvasRenderingContext2D,
    disp: Disposicion,
    propias: Soldadura[],
    camara: THREE.OrthographicCamera,
    tintaDe: (numero: number) => string,
): boolean {
    const { W, H } = disp;
    const banda = W * MARGEN_X;
    const arriba = H * 0.02;
    const abajo = H * 0.98;

    // A qué lado va cada cordón: por mitades y no por «de qué lado cae». En una
    // pieza en diagonal todos caen del mismo lado y un margen se apretuja.
    const todos = propias.map((soldadura) => ({ numero: soldadura.numero, q: proyectar(soldadura.mid, camara, W, H) }));
    todos.sort((a, b) => a.q.x - b.q.x);
    const corte = Math.ceil(todos.length / 2);
    const lados = [todos.slice(0, corte), todos.slice(corte)];

    const px = Math.max(9, Math.min((W * (DIGITO_MM / disp.celdaMM)) / 0.62, 34));
    let apretado = false;

    lados.forEach((columna, lado) => {
        if (!columna.length) {
            return;
        }
        columna.sort((a, b) => a.q.y - b.q.y);
        // Una ranura por etiqueta, repartidas por el alto: así no se pisan nunca.
        const paso = (abajo - arriba) / columna.length;
        const alto = Math.min(px, paso * 0.86);
        if (alto < px) {
            apretado = true;
        }
        g.font = `600 ${alto.toFixed(1)}px system-ui, sans-serif`;
        g.textBaseline = 'middle';
        g.textAlign = lado === 0 ? 'right' : 'left';
        const xTexto = lado === 0 ? banda * 0.94 : W - banda * 0.94;
        const xCodo = lado === 0 ? banda : W - banda;

        columna.forEach((llamada, i) => {
            const y = arriba + paso * (i + 0.5);
            const color = tintaDe(llamada.numero);
            g.strokeStyle = color;
            g.lineWidth = Math.max(1, alto * 0.075);
            g.beginPath();
            g.moveTo(xTexto + (lado === 0 ? 3 : -3), y);
            g.lineTo(xCodo, y);
            g.lineTo(llamada.q.x, llamada.q.y);
            g.stroke();
            g.fillStyle = color;
            g.beginPath();
            g.arc(llamada.q.x, llamada.q.y, Math.max(1.6, alto * 0.13), 0, Math.PI * 2);
            g.fill();
            // El número con halo blanco, por si cae sobre el dibujo.
            g.lineWidth = Math.max(2, alto * 0.3);
            g.strokeStyle = '#fff';
            g.strokeText(String(llamada.numero), xTexto, y);
            g.fillText(String(llamada.numero), xTexto, y);
        });
    });

    return apretado;
}

/**
 * Genera las cuatro vistas de la hoja. `colores` pinta cada cordón de un color
 * fijo (el resultado de su junta, en la hoja del mapeo); sin él, los cordones
 * van en naranja y el reparto rota sus tintas.
 */
export async function generarHoja({
    glbUrl,
    cordones,
    modo,
    colores,
}: {
    glbUrl: string;
    cordones: CordonHoja[];
    modo: ModoHoja;
    colores?: Map<number, string>;
}): Promise<HojaGenerada> {
    const gltf = await new GLTFLoader().loadAsync(glbUrl);
    const raiz = gltf.scene;
    const linea = modo !== 'sombreado';
    const reparte = modo === 'reparto' || modo === 'repartonum';
    const llamadas = modo === 'reparto';

    const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
    renderer.setPixelRatio(1);
    const escena = new THREE.Scene();
    escena.background = new THREE.Color(PAPEL.fondo);
    escena.add(new THREE.HemisphereLight(0xffffff, 0x334455, 1.15));
    const sol = new THREE.DirectionalLight(0xffffff, 0.75);
    sol.position.set(1, 2, 3);
    const sol2 = new THREE.DirectionalLight(0xffffff, 0.35);
    sol2.position.set(-2, -1, 1);
    escena.add(sol, sol2);

    const acero = new THREE.MeshStandardMaterial({ color: linea ? 0xffffff : PAPEL.acero, metalness: 0.6, roughness: 0.5, flatShading: true });
    if (linea) {
        // En línea el acero pasa a blanco plano: sigue tapando lo de detrás,
        // que es lo que da la profundidad, y se hunde un poco para no tapar
        // las aristas.
        acero.emissive.setHex(0xffffff);
        acero.polygonOffset = true;
        acero.polygonOffsetFactor = 1.5;
        acero.polygonOffsetUnits = 1.5;
    }

    const mallas: THREE.Mesh[] = [];
    const capas: LineSegments2[] = [];
    const materiales: LineMaterial[] = [];
    let numeros: Numeros | null = null;

    try {
        raiz.traverse((objeto) => {
            const malla = objeto as THREE.Mesh;
            if (malla.isMesh) {
                (malla.material as THREE.Material).dispose();
                malla.material = acero;
                mallas.push(malla);
            }
        });
        escena.add(raiz);
        raiz.updateMatrixWorld(true);

        const soldaduras: Soldadura[] = cordones.flatMap((cordon) => {
            const puntos = cordon.puntos.map(([x, y, z]) => new THREE.Vector3(x, y, z));
            if (puntos.length < 2) {
                return [];
            }
            // La curva suaviza la arista; el punto medio es donde va el número.
            const curva = new THREE.CatmullRomCurve3(puntos, false, 'catmullrom', 0.1);
            return [{ numero: cordon.numero, fina: curva.getPoints(Math.max(8, puntos.length * 4)), mid: curva.getPoint(0.5) }];
        });

        const caja = new THREE.Box3().setFromObject(raiz);
        const disp = disposicion(caja);
        const vistas = vistasDe(caja);
        const reparto = reparte && soldaduras.length ? repartir(soldaduras, mallas, caja, vistas) : null;

        const base = (numero: number) => colores?.get(numero) ?? PAPEL.cordon;
        // Un color por cordón, rotando dentro de cada vista para que los
        // vecinos no coincidan; lo comparten el número, su línea y el cordón.
        const tinta = new Map<number, string>();
        if (reparto && llamadas && !colores) {
            const cuenta = vistas.map(() => 0);
            [...reparto.asignado]
                .sort((a, b) => a[0] - b[0])
                .forEach(([numero, vista]) => tinta.set(numero, TINTAS[cuenta[vista]++ % TINTAS.length]));
        }
        const tintaDe = (numero: number) => tinta.get(numero) ?? base(numero);

        const nuevo = (parametros: ConstructorParameters<typeof LineMaterial>[0]) => {
            const material = new LineMaterial(parametros);
            materiales.push(material);
            return material;
        };
        const fuerte = nuevo({ linewidth: 4.5, vertexColors: true, transparent: true, opacity: 0.95, polygonOffset: true, polygonOffsetFactor: -6, polygonOffsetUnits: -6 });
        const tenue = nuevo({ linewidth: 1.5, vertexColors: true, depthTest: false, transparent: true, opacity: 0.3 });
        const borde = nuevo({ color: 0x2f3540, linewidth: 1.6, transparent: true, opacity: 0.9 });

        const agregar = (lineas: LineSegments2 | null, orden: number) => {
            if (lineas) {
                lineas.renderOrder = orden;
                escena.add(lineas);
                capas.push(lineas);
            }
        };
        // El trazo tenue, sin profundidad: el cordón tapado se intuye a través del acero.
        agregar(lineasDe(soldaduras, tenue, base), 2);
        if (!reparto) {
            agregar(lineasDe(soldaduras, fuerte, base), 3);
        }
        if (linea) {
            agregar(bordesDe(mallas, borde), 1);
        }
        if (!llamadas && soldaduras.length) {
            numeros = numerosDe(
                soldaduras.map((soldadura) => soldadura.numero),
                new Float32Array(soldaduras.flatMap((soldadura) => [soldadura.mid.x, soldadura.mid.y, soldadura.mid.z])),
            );
            escena.add(numeros.malla);
        }

        const camara = new THREE.OrthographicCamera(-1, 1, 1, -1, 0.01, 4000);
        camara.up.set(0, 0, 1);
        const salida: VistaHoja[] = [];
        let apretado = false;

        vistas.forEach((vista, indice) => {
            const propias = reparto ? soldaduras.filter((soldadura) => reparto.asignado.get(soldadura.numero) === indice) : soldaduras;
            // En el reparto, sólo los cordones de esta vista van en trazo fuerte.
            const deLaVista = reparto ? lineasDe(propias, fuerte, tintaDe) : null;
            if (deLaVista) {
                deLaVista.renderOrder = 3;
                escena.add(deLaVista);
            }
            if (numeros && reparto) {
                const alfa = numeros.malla.geometry.attributes.aAlpha as THREE.InstancedBufferAttribute;
                soldaduras.forEach((soldadura, i) => {
                    (alfa.array as Float32Array)[i] = reparto.asignado.get(soldadura.numero) === indice ? 1 : 0;
                });
                alfa.needsUpdate = true;
            }

            encuadrar(camara, caja, vista.dir, disp.W / disp.H, llamadas ? MARGEN_X : 0);
            if (numeros) {
                // El número se mide contra el marco y contra los milímetros que
                // ocupa la celda en el papel; 0.6 es el alto del glifo en su celda.
                numeros.malla.material.uniforms.uSize.value = ((camara.right - camara.left) * (DIGITO_MM / disp.celdaMM)) / 0.6;
            }
            renderer.setSize(disp.W, disp.H, false);
            materiales.forEach((material) => material.resolution.set(disp.W, disp.H));
            renderer.render(escena, camara);

            let src: string;
            if (llamadas) {
                const lienzo = document.createElement('canvas');
                lienzo.width = disp.W;
                lienzo.height = disp.H;
                const g = lienzo.getContext('2d');
                if (!g) {
                    throw new Error('El navegador no deja dibujar las llamadas de la hoja.');
                }
                g.fillStyle = '#fff';
                g.fillRect(0, 0, disp.W, disp.H);
                g.drawImage(renderer.domElement, 0, 0);
                apretado = dibujarLlamadas(g, disp, propias, camara, tintaDe) || apretado;
                src = lienzo.toDataURL('image/png');
            } else {
                src = renderer.domElement.toDataURL('image/png');
            }

            const nombre = disp.corta ? vista.corto : vista.nombre;
            salida.push({ nombre: reparto ? `${nombre} · ${propias.length} cordones` : nombre, src });

            if (deLaVista) {
                escena.remove(deLaVista);
                deLaVista.geometry.dispose();
            }
        });

        return { clase: disp.clase, modo, vistas: salida, ocultos: reparto?.ocultos ?? [], apretado };
    } finally {
        capas.forEach((lineas) => lineas.geometry.dispose());
        materiales.forEach((material) => material.dispose());
        if (numeros) {
            numeros.malla.geometry.dispose();
            numeros.malla.material.dispose();
            numeros.textura.dispose();
            numeros.plano.dispose();
        }
        mallas.forEach((malla) => malla.geometry.dispose());
        acero.dispose();
        renderer.dispose();
        renderer.forceContextLoss();
    }
}
