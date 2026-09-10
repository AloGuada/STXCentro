"""
Deteccion GEOMETRICA de posibles cordones de soldadura dentro de un ensamble.

No hay soldaduras en el IFC (Tekla no las exporta), asi que se deducen de la
geometria. El cordon NO va por el centro de la zona de contacto: va por sus
BORDES, en el rincon que forman las dos piezas. Por eso el metodo es:

  1. agrupar las caras de cada pieza en parches planos
  2. emparejar parches antiparalelos separados menos de TOL -> plano de contacto
  3. intersectar los dos poligonos en ese plano -> HUELLA de contacto
  4. cada arista del contorno de la huella es un cordon candidato
  5. medir el angulo diedro del rincon y decidir si lleva filete

Casos basicos (placa sobre placa):
  * placa centrada  -> los dos bordes de la huella dan 90 y 90  -> 2 cordones
  * placa a ras     -> el borde comun a las dos piezas no forma rincon -> 1 cordon
  * placa inclinada -> un lado agudo y otro obtuso, pero las dos son piezas en
                       angulo y las dos se sueldan -> 2 cordones

Lo que decide NO es el angulo sino si hay rincon. El angulo se guarda como dato
de cada cordon, pero solo se descarta lo practicamente coplanar (ver ANG_MAX):
una placa rematada en filo sobre otra no forma rincon soldable.

Hay un segundo tipo de cordon: la COSTURA. Cuando las dos piezas acaban en la
misma arista y sus caras siguen coplanares (angulo de 180 grados) no hay rincon
para un filete, pero la junta si se suelda: un unico liston por el lado de
fuera. Es el caso de dos perfiles CE puestos punta con punta formando cajon, o
de dos perfiles cortados a inglete. El lado de fuera se decide tirando un rayo
desde la junta: si escapa del ensamble es accesible, y si choca contra otra
pieza (el interior del cajon) no lo es.

Cada cordon lleva "tipo": "filete" o "costura".

Uso como libreria:
    from welds import detect_welds
    welds = detect_welds({node: trimesh, ...})

Uso directo sobre un glb ya exportado (rapido, no abre el IFC):
    python welds.py SX-CM2-11
"""
import sys
import json
import collections
from pathlib import Path

import numpy as np
import trimesh
from shapely.geometry import Polygon, MultiPolygon, Point, LineString
from shapely.ops import unary_union

TOL_M     = 0.0015     # 1.5 mm: separacion maxima entre las dos superficies
MIN_LEN_M = 0.015      # 15 mm: cordones mas cortos se descartan (punto de soldadura)
ANG_MIN   = 0.0        # el angulo no decide: toda pieza en angulo lleva cordon
ANG_MAX   = 170.0      # salvo lo casi coplanar, que no llega a ser un rincon
PLANE_TOL = 2e-4       # 0.2 mm: dos caras en el mismo plano
EDGE_TOL  = 2e-4       # 0.2 mm: una arista cae sobre el contorno
COLIN_DEG = 1.0        # aristas mas alineadas que esto se fusionan en una

# Version del algoritmo. Cada modelo 3D guarda con cual se calcularon sus
# cordones: si se cambia la deteccion, se sube este valor.
WELDS_VERSION = "2026-08-21"

# --- AISC 360 J2 -----------------------------------------------------------
# OJO: J2.4 da un MINIMO para que el cordon no se agriete al enfriarse contra
# una masa de acero. NO es un requisito de resistencia: el cateto que pide el
# calculo depende de las fuerzas de la conexion, y el IFC no las trae. Por eso
# cada cordon lleva "cateto_mm": None, para que lo rellene quien calcula.
MM_IN = 25.4
GAUGE_EPS = 0.05       # 6.35 tiene que caer del lado bueno de 1/4"

TABLA_J24 = [          # (espesor de la parte MAS DELGADA, cateto minimo)
    (1 / 4 * MM_IN, 1 / 8 * MM_IN),
    (1 / 2 * MM_IN, 3 / 16 * MM_IN),
    (3 / 4 * MM_IN, 1 / 4 * MM_IN),
    (float("inf"), 5 / 16 * MM_IN),
]


def cateto_minimo(t_delgada_mm):
    """AISC 360 Tabla J2.4"""
    for limite, cateto in TABLA_J24:
        if t_delgada_mm <= limite + GAUGE_EPS:
            return cateto
    return TABLA_J24[-1][1]


def cateto_maximo(t_borde_mm):
    """AISC 360 J2.2b, a lo largo de un borde"""
    if t_borde_mm < 1 / 4 * MM_IN - GAUGE_EPS:
        return t_borde_mm
    return t_borde_mm - 1 / 16 * MM_IN


def garganta(cateto_mm, angulo_grados):
    """J2.2a. En junta a 90 sale el 0.707 de siempre"""
    return cateto_mm * np.sin(np.radians(angulo_grados) / 2)


# --- preparacion sugerida ---------------------------------------------------
# AISC 360 no dibuja los biseles: remite a las juntas prequalificadas de
# AWS D1.1, que el Manual del AISC reproduce. De los cinco datos que hacen
# falta para elegir una, tres salen del modelo (angulo, espesor y si se
# alcanza por una cara o por las dos) y dos no: la penetracion pedida y el
# proceso/posicion. Aqui se supone penetracion PARCIAL, porque la completa se
# pide expresamente, no se da por hecho. Es una SUGERENCIA, no un detalle.
PENETRACION = "parcial"


def _preparacion(tipo, angulo, t_delgada_mm, lados):
    """lados: por cuantas caras se alcanza la junta (1 o 2). Devuelve dict."""
    def d(bisel, angulo_bisel=None, nota=""):
        return {"bisel": bisel, "angulo_bisel": angulo_bisel,
                "lados": lados, "penetracion": PENETRACION, "nota": nota}

    if tipo == "filete":
        if 60 <= angulo <= 120:
            return d("ninguno", None, "corte recto, filete directo")
        if 30 <= angulo < 60:
            return d("ninguno", None,
                     "junta sesgada: AWS D1.1 aplica descuento Z a la garganta, "
                     "hay que verificarla")
        if angulo < 30:
            return d("por definir", None,
                     "angulo menor de 30: no hay junta prequalificada, "
                     "lo tiene que resolver ingenieria")
        return d("bisel simple", 45,
                 "por encima de 120 el filete deja de ser efectivo")

    # junta a tope: manda el espesor, y el acceso decide si es por una cara
    t = t_delgada_mm
    if t <= 1 / 4 * MM_IN + GAUGE_EPS:
        return d("ranura recta", None,
                 "una cara" if lados < 2 else "las dos caras")
    if t <= 1 / 2 * MM_IN + GAUGE_EPS:
        if lados < 2:
            return d("bisel simple", 45, "una sola cara: valorar respaldo")
        return d("V simple", 60, "resanar el reverso antes del segundo lado")
    if t <= MM_IN + GAUGE_EPS:
        if lados < 2:
            return d("V simple", 60, "una sola cara: lleva respaldo")
        return d("doble V", 60, "menos aporte y menos distorsion que la V simple")
    if lados < 2:
        return d("V simple", 60, "espesor grande por una sola cara: respaldo, "
                                 "y valorar U simple para ahorrar aporte")
    return d("doble V", 60, "en espesores grandes, valorar doble U")


def _aisc(tipo, junta, largo_mm, angulo, t1_mm, t2_mm):
    """lo que el codigo deja fijar solo con la geometria"""
    delgada = min(t1_mm, t2_mm)
    avisos = []
    if tipo == "costura":
        # junta a tope: la preparacion depende del proceso, la posicion, el
        # acceso y si se pide penetracion completa. Nada de eso esta en el
        # modelo, asi que aqui solo se deja constancia del espesor.
        return {"cateto_min_mm": None, "cateto_max_mm": None,
                "garganta_min_mm": None, "delgada_mm": round(delgada, 2),
                "avisos": ["junta a tope: la preparacion depende del proceso y "
                           "del tipo de penetracion, hay que definirla aparte"]}

    cmin = cateto_minimo(delgada)
    cmax = cateto_maximo(t1_mm)
    if cmax < cmin - GAUGE_EPS:
        avisos.append(f"el maximo por borde ({cmax:.1f} mm) queda por debajo "
                      f"del minimo de tabla ({cmin:.1f} mm)")
    if angulo < 60:
        avisos.append(f"angulo {angulo:.0f}: fuera del filete prequalificado "
                      f"de AWS D1.1, necesita preparacion")
    elif angulo > 120:
        avisos.append(f"angulo {angulo:.0f}: el filete no es efectivo, "
                      f"necesita bisel")
    if largo_mm < 4 * cmin:
        avisos.append(f"largo {largo_mm:.0f} mm < 4x cateto: por J2.2b el "
                      f"tamano efectivo baja a {largo_mm / 4:.1f} mm")
    return {"cateto_min_mm": round(cmin, 2), "cateto_max_mm": round(cmax, 2),
            "garganta_min_mm": round(garganta(cmin, angulo), 2),
            "delgada_mm": round(delgada, 2), "avisos": avisos}


# ---------------------------------------------------------------------------
def _boxes_overlap(a, b, tol):
    return bool(np.all(a[0] - tol <= b[1]) and np.all(b[0] - tol <= a[1]))


def _ang2(u, v):
    nu, nv = np.linalg.norm(u), np.linalg.norm(v)
    if nu < 1e-12 or nv < 1e-12:
        return 0.0
    return float(np.degrees(np.arccos(np.clip(u @ v / (nu * nv), -1, 1))))


def _basis(n):
    """dos ejes perpendiculares a n, para trabajar en 2D sobre ese plano"""
    e1 = np.array([1.0, 0.0, 0.0])
    if abs(n @ e1) > 0.9:
        e1 = np.array([0.0, 1.0, 0.0])
    e1 = e1 - (e1 @ n) * n
    e1 /= np.linalg.norm(e1)
    return e1, np.cross(n, e1)


def _planar_patches(mesh):
    """caras coplanares agrupadas -> [{n, d, poly(2D), e1, e2}]"""
    # las normales se recalculan de los vertices: trimesh no las corrige bien
    # si al nodo se le aplico un transform no rigido
    tri = mesh.triangles
    cr = np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0])
    ln = np.linalg.norm(cr, axis=1)
    ok = ln > 1e-12
    tri, fn = tri[ok], cr[ok] / ln[ok][:, None]
    fo = np.einsum('ij,ij->i', fn, tri[:, 0])               # offset del plano

    por_normal = collections.defaultdict(list)
    for i, k in enumerate(map(tuple, np.round(fn, 3))):
        por_normal[k].append(i)

    out = []
    for idx in por_normal.values():
        idx = np.array(idx)
        n = fn[idx].mean(axis=0)
        n /= np.linalg.norm(n)
        # dentro de una misma normal puede haber varios planos paralelos: se
        # separan por saltos en el offset (una rejilla partiria planos por la mitad)
        orden = idx[np.argsort(fo[idx])]
        corte = np.where(np.diff(fo[orden]) > PLANE_TOL)[0] + 1
        for grupo in np.split(orden, corte):
            d = float(fo[grupo].mean())
            e1, e2 = _basis(n)
            polys = []
            for t in tri[grupo]:
                p = Polygon(np.column_stack([t @ e1, t @ e2]))
                if p.is_valid and p.area > 1e-10:
                    polys.append(p)
            if not polys:
                continue
            u = unary_union(polys).buffer(1e-9).buffer(-1e-9)
            if u.is_empty or u.area < 1e-7:
                continue
            out.append({'n': n, 'd': d, 'poly': u, 'e1': e1, 'e2': e2})
    return out


def _to3(patch, u, v):
    return patch['n'] * patch['d'] + u * patch['e1'] + v * patch['e2']


def _reproject(src, dst):
    """el poligono de src, expresado en la base de dst (planos paralelos)"""
    def conv(poly):
        rings = []
        for ring in [poly.exterior] + list(poly.interiors):
            p3 = [_to3(src, u, v) for u, v in ring.coords]
            rings.append([(p @ dst['e1'], p @ dst['e2']) for p in p3])
        return Polygon(rings[0], rings[1:])
    g = src['poly']
    if isinstance(g, MultiPolygon):
        return unary_union([conv(p) for p in g.geoms])
    return conv(g)


def _merge_collinear(coords):
    """une aristas consecutivas casi alineadas: la interseccion mete vertices de
       mas y sin esto un cordon recto saldria partido en trozos"""
    pts = [np.asarray(c, dtype=float) for c in coords]
    if len(pts) >= 2 and np.allclose(pts[0], pts[-1]):
        pts = pts[:-1]
    n = len(pts)
    if n < 2:
        return []
    if n == 2:
        return [(pts[0], pts[1])]

    # esquinas reales del anillo (donde de verdad cambia la direccion)
    esq = [k for k in range(n)
           if _ang2(pts[k] - pts[k - 1], pts[(k + 1) % n] - pts[k]) > COLIN_DEG]
    if len(esq) < 2:                      # anillo degenerado (casi una recta)
        return [(pts[0], pts[n // 2])]
    return [(pts[esq[i]], pts[esq[(i + 1) % len(esq)]]) for i in range(len(esq))]


def _flanco(patches, nc, mid3, t, outw, up):
    """angulo diedro del rincon: entre la superficie que sigue hacia `outw` y el
       flanco de la pieza que sube hacia `up`. Devuelve (angulo, normal del
       flanco), o (None, None) si ahi no hay flanco."""
    mejor = mejor_m = None
    for p in patches:
        m = p['n']
        if abs(m @ nc) > 0.98:              # es la propia cara de contacto
            continue
        if abs(m @ t) > 0.05:               # el flanco debe contener la arista
            continue
        if m @ outw < 0.02:                 # y mirar hacia fuera del rincon
            continue
        if abs((mid3 - m * p['d']) @ m) > PLANE_TOL * 3:
            continue                        # su plano pasa por la arista
        uv = Point(mid3 @ p['e1'], mid3 @ p['e2'])
        if p['poly'].distance(uv) > EDGE_TOL * 5:
            continue                        # y la cara llega realmente hasta ahi
        w = np.cross(m, t)
        if w @ up < 0:
            w = -w
        w /= np.linalg.norm(w)
        ang = _ang2(outw, w)
        if mejor is None or ang < mejor:    # el rincon lo marca el material mas cercano
            mejor, mejor_m = ang, m
    return mejor, mejor_m


def _ancho(huella, mid2, nr):
    """cuanto material hay pegado a la arista, medido hacia dentro de la huella.
       En un tubo es el espesor de pared, no el ancho total del perfil."""
    x0, y0, x1, y1 = huella.bounds
    largo = float(np.hypot(x1 - x0, y1 - y0)) + 1e-3
    rayo = LineString([mid2 - nr * 1e-7, mid2 - nr * largo])
    corte = huella.intersection(rayo)
    if corte.is_empty:
        return 0.0
    trozos = list(corte.geoms) if hasattr(corte, 'geoms') else [corte]
    pm = Point(mid2)
    trozos = [t for t in trozos if t.length > 0]
    if not trozos:
        return 0.0
    return float(min(trozos, key=lambda t: t.distance(pm)).length)


def _choca(tri, origen, direccion, eps=1e-9):
    """hay algun triangulo delante del rayo? (Moller-Trumbore vectorizado).
       trimesh necesita rtree para esto y aqui no esta instalado."""
    v0, e1, e2 = tri[:, 0], tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0]
    pv = np.cross(direccion, e2)
    det = np.einsum('ij,ij->i', e1, pv)
    vale = np.abs(det) > eps
    if not vale.any():
        return False
    inv = np.zeros_like(det)
    inv[vale] = 1.0 / det[vale]
    tv = origen - v0
    u = np.einsum('ij,ij->i', tv, pv) * inv
    qv = np.cross(tv, e1)
    v = np.einsum('j,ij->i', direccion, qv) * inv
    d = np.einsum('ij,ij->i', e2, qv) * inv
    return bool(np.any(vale & (u >= -1e-6) & (v >= -1e-6) &
                       (u + v <= 1 + 1e-6) & (d > 1e-5)))


def _accesible(tri, origen, direccion, salto=5e-4):
    """la junta se puede soldar por ese lado? Se mira si el rayo escapa del
       ensamble o si choca contra otra pieza (el interior de un cajon)."""
    return not _choca(tri, origen + direccion * salto, direccion)


def _espesor(mesh, n):
    """cuanto mide la pieza en la direccion n (perpendicular al contacto)"""
    v = mesh.vertices @ n
    return float(v.max() - v.min())


def _menor(mesh):
    """la dimension mas pequena de la pieza: su espesor de placa"""
    lo, hi = mesh.bounds
    return float(np.min(hi - lo))


def _ancho_minimo(poly):
    """el lado corto de la huella. Es el espesor de la pieza que se apoya de
       canto, y a diferencia de _ancho no depende de que arista se mire: por el
       lado corto de la huella daba el lado largo y salia un PL16 de 100 mm."""
    c = np.asarray(poly.exterior.coords)[:-1]
    if len(c) < 3:
        return float('inf')
    mejor = float('inf')
    for i in range(len(c)):
        e = c[(i + 1) % len(c)] - c[i]
        L = np.hypot(*e)
        if L < 1e-9:
            continue
        nr = np.array([-e[1], e[0]]) / L
        pr = c @ nr
        mejor = min(mejor, float(pr.max() - pr.min()))
    return mejor


def _espesor_local(patches, nc, d_contacto):
    """espesor de la pieza que hace de base, medido desde la cara de contacto
       hasta la siguiente cara paralela. En una placa es su espesor; en un
       perfil, el del ala, y no el canto entero del perfil."""
    mejor = None
    for p in patches:
        if p['n'] @ nc < 0.999:            # cara del otro lado de la pieza
            continue
        t = p['d'] - d_contacto
        if t > PLANE_TOL and (mejor is None or t < mejor):
            mejor = t
    return mejor


def _cordones_del_par(pa, pb, ma, mb, tol, min_len, ang_min, ang_max, costuras):
    """cordones candidatos entre dos piezas, a partir de sus parches planos"""
    res = []
    for fa in pa:
        for fb in pb:
            if fa['n'] @ fb['n'] > -0.999:            # deben ser antiparalelos
                continue
            if abs(fa['d'] + fb['d']) > tol:          # y estar en contacto
                continue
            poly_b = _reproject(fb, fa)
            huella = fa['poly'].intersection(poly_b)
            if huella.is_empty or huella.area < 1e-6:
                continue

            nc = fa['n']                              # de A hacia B
            up_a, up_b = -nc, nc
            ext_a, ext_b = _espesor(ma, nc) * 1000, _espesor(mb, nc) * 1000
            # espesor local de cada pieza en la junta; si no se encuentra la
            # cara paralela se cae a la extension total, que es lo de antes
            loc_a = _espesor_local(pa, -nc, -fa['d'])
            loc_b = _espesor_local(pb, nc, fa['d'])
            ext_a = min(ext_a, loc_a * 1000) if loc_a else ext_a
            ext_b = min(ext_b, loc_b * 1000) if loc_b else ext_b
            # si el contacto cae en el canto de la pieza no hay cara paralela
            # que medir y saldria su largo. Se acota por su dimension menor,
            # que es lo que en obra se llama el espesor de esa placa
            ext_a = min(ext_a, _menor(ma) * 1000)
            ext_b = min(ext_b, _menor(mb) * 1000)
            geoms = huella.geoms if isinstance(huella, MultiPolygon) else [huella]
            for gi, g in enumerate(geoms):
                # solo el contorno exterior: los agujeros de la huella son el
                # interior de un tubo o un taladro, y ahi no se puede soldar
                for p, q in _merge_collinear(list(g.exterior.coords)):
                    largo = float(np.linalg.norm(q - p))
                    if largo < min_len:
                        continue
                    mid2 = (p + q) / 2
                    pm = Point(mid2)
                    on_a = fa['poly'].boundary.distance(pm) < EDGE_TOL
                    on_b = poly_b.boundary.distance(pm) < EDGE_TOL
                    if on_a and on_b and not costuras:
                        continue
                    if not on_a and not on_b:
                        continue
                    # normal 2D saliendo de la huella
                    e = q - p
                    nr = np.array([-e[1], e[0]])
                    nr /= np.linalg.norm(nr)
                    if g.contains(Point(mid2 + nr * 1e-4)):
                        nr = -nr
                    outw = nr[0] * fa['e1'] + nr[1] * fa['e2']
                    t3 = (_to3(fa, *q) - _to3(fa, *p)) / largo
                    mid3 = _to3(fa, *mid2)
                    anc = _ancho(g, mid2, nr)
                    anc_min = _ancho_minimo(g) * 1000
                    cordon = {
                        'line': np.array([_to3(fa, *p), _to3(fa, *q)]),
                        'largo_m': largo,
                        'ancho_m': anc,
                        'centro': mid3,
                    }
                    # espesor de cada parte EN LA JUNTA. Para la que se apoya,
                    # lo limita la huella: un alma de 600 mm apoyada de canto
                    # tiene 600 de fondo pero solo 4.8 de espesor
                    anc_mm = min(anc * 1000, anc_min)

                    if on_a and on_b:
                        # las dos acaban aqui: solo es junta soldable si sus
                        # caras siguen coplanares, y entonces va un unico
                        # liston por el lado accesible (lo filtra detect_welds)
                        _, mflanco_a = _flanco(pa, nc, mid3, t3, outw, up_a)
                        _, mflanco_b = _flanco(pb, nc, mid3, t3, outw, up_b)
                        if (mflanco_a is None or mflanco_b is None
                                or mflanco_a @ mflanco_b < 0.999):
                            continue        # esquina en angulo, no costura
                        t1 = min(anc_mm, ext_a)
                        cordon.update(tipo='costura', angulo=180.0, sube='A',
                                      acceso=mflanco_a, junta='tope',
                                      plano=(round(float(fa['d']), 4), gi),
                                      t1_mm=round(t1, 2), t2_mm=round(ext_b, 2),
                                      **_aisc('costura', 'tope', largo * 1000,
                                              180.0, t1, ext_b))
                        res.append(cordon)
                        continue

                    # el flanco es de la pieza que se acaba en esa arista;
                    # la otra es la que sigue y hace de base
                    if on_a:
                        ang, _ = _flanco(pa, nc, mid3, t3, outw, up_a)
                        sube, e_sube, e_base = 'A', ext_a, ext_b
                    else:
                        ang, _ = _flanco(pb, nc, mid3, t3, outw, up_b)
                        sube, e_sube, e_base = 'B', ext_b, ext_a
                    if ang is None or not (ang_min <= ang <= ang_max):
                        continue
                    t1 = min(anc_mm, e_sube)
                    # si la pieza que se apoya es mas delgada que lo que dura el
                    # contacto, esta tumbada sobre la otra: es un solape
                    junta = 'solape' if e_sube <= anc_mm * 1.2 else 'T'
                    cordon.update(tipo='filete', angulo=ang, sube=sube,
                                  acceso=None, junta=junta, plano=None,
                                  t1_mm=round(t1, 2), t2_mm=round(e_base, 2),
                                  **_aisc('filete', junta, largo * 1000, ang,
                                          t1, e_base))
                    res.append(cordon)
    return res


# ---------------------------------------------------------------------------
def detect_welds(meshes, tol=TOL_M, min_len=MIN_LEN_M, ang_min=ANG_MIN,
                 ang_max=ANG_MAX, costuras=True, verbose=True):
    """
    meshes: dict node -> trimesh.Trimesh (todas en el mismo sistema de coordenadas)
    costuras: incluir las juntas a 180 grados (un liston por el lado accesible)
    devuelve: lista de dicts con la geometria y datos de cada cordon posible
    """
    nodes = list(meshes.keys())
    bounds = {n: meshes[n].bounds for n in nodes}
    patches = {}

    bruto, pares = [], 0
    for i in range(len(nodes)):
        for j in range(i + 1, len(nodes)):
            na, nb = nodes[i], nodes[j]
            if not _boxes_overlap(bounds[na], bounds[nb], tol):
                continue
            for n in (na, nb):
                if n not in patches:
                    patches[n] = _planar_patches(meshes[n])
            try:
                ws = _cordones_del_par(patches[na], patches[nb],
                                       meshes[na], meshes[nb], tol, min_len,
                                       ang_min, ang_max, costuras)
            except Exception as e:
                if verbose:
                    print(f"  [!] {na}-{nb}: {type(e).__name__}: {e}")
                continue
            if ws:
                pares += 1
            for w in ws:
                # primero la pieza que se apoya, despues la que hace de base
                w['piezas'] = [na, nb] if w['sube'] == 'A' else [nb, na]
                bruto.append(w)

    # de las dos aristas de una costura solo se suelda la de fuera: se tira un
    # rayo desde cada una y se queda la que escapa del ensamble
    def clave(w):
        return (tuple(sorted(w['piezas'])), w['plano'])

    candidatas = collections.Counter(clave(w) for w in bruto if w['tipo'] == 'costura')
    if candidatas:
        tri = np.concatenate([meshes[n].triangles for n in nodes])
        bruto = [w for w in bruto
                 if w['tipo'] != 'costura'
                 or _accesible(tri, w['centro'], w['acceso'])]

    # cuantas caras de cada junta a tope quedaron accesibles: es lo que decide
    # si la preparacion va por un lado o por los dos. Se agrupa por HUELLA y no
    # por plano: dos CE punta con punta tienen dos juntas (ala de arriba y de
    # abajo) en el mismo plano, y contarlas juntas daba "2 caras" en falso.
    accesibles = collections.Counter(clave(w) for w in bruto if w["tipo"] == "costura")
    for w in bruto:
        if w["tipo"] != "costura":
            lados, perimetro = 1, False
        else:
            k = clave(w)
            # mas de dos aristas soldables en la misma huella no son "los dos
            # lados" de una junta: es el contorno de un perfil cortado a inglete
            perimetro = candidatas[k] > 2
            lados = 1 if perimetro else accesibles[k]
        w["preparacion"] = _preparacion(w["tipo"], w["angulo"],
                                        min(w["t1_mm"], w["t2_mm"]), lados)
        if perimetro:
            w["preparacion"]["nota"] = ("junta a tope siguiendo el contorno del "
                                        "perfil (corte a inglete)")

    # numeracion estable: de abajo hacia arriba y, a igual altura, el mas largo primero
    bruto.sort(key=lambda w: (round(float(w["centro"][2]), 3), -w["largo_m"]))
    welds = []
    for k, w in enumerate(bruto, 1):
        welds.append({
            "id": k,
            "piezas": w["piezas"],
            "tipo": w["tipo"],
            "junta": w["junta"],
            "largo_mm": round(w["largo_m"] * 1000, 1),
            "ancho_mm": round(w["ancho_m"] * 1000, 1),
            "angulo": round(w["angulo"], 1),
            "t1_mm": w["t1_mm"],
            "t2_mm": w["t2_mm"],
            "cateto_min_mm": w["cateto_min_mm"],
            "cateto_max_mm": w["cateto_max_mm"],
            "garganta_min_mm": w["garganta_min_mm"],
            "cateto_mm": None,        # lo rellena quien calcula la conexion
            "preparacion": w["preparacion"],
            "avisos": w["avisos"],
            "centro": [round(float(x), 4) for x in w["centro"]],
            "puntos": [[round(float(v), 4) for v in p] for p in w["line"]],
        })
    if verbose:
        tot = sum(w["largo_mm"] for w in welds)
        nc = sum(1 for w in welds if w["tipo"] == "costura")
        print(f"Soldaduras posibles: {len(welds)}  "
              f"({len(welds) - nc} de filete, {nc} de costura; "
              f"{pares} pares de piezas en contacto, {tot:.0f} mm de cordon)")
    return welds


# ---------------------------------------------------------------------------
def meshes_from_glb(path):
    scene = trimesh.load(str(path), force='scene')
    out = {}
    for name, geom in scene.geometry.items():
        m = geom.copy()
        node = name
        # aplicar la transformada del nodo si la hubiera
        for nn in scene.graph.nodes_geometry:
            if scene.graph[nn][1] == name:
                T = scene.graph[nn][0]
                m.apply_transform(T)
                node = nn
                break
        out[node] = m
    return out


if __name__ == "__main__":
    mark = sys.argv[1] if len(sys.argv) > 1 else "SX-CM2-11"
    base = Path(__file__).parent / "out" / "marks"
    meshes = meshes_from_glb(base / f"{mark}.glb")
    print(f"{mark}: {len(meshes)} piezas")
    welds = detect_welds(meshes)
    for w in welds[:15]:
        print(f"  S{w['id']:<3} {w['piezas'][0]}-{w['piezas'][1]:<6} "
              f"largo={w['largo_mm']:>7.1f} mm  ancho={w['ancho_mm']:>6.1f} mm  "
              f"ang={w['angulo']:>5.1f}")

    f = base / f"{mark}.json"
    if f.exists():
        d = json.loads(f.read_text(encoding="utf-8"))
        d["soldaduras"] = welds
        d["totales"]["soldaduras"] = len(welds)
        d["totales"]["soldadura_mm"] = round(sum(w["largo_mm"] for w in welds), 1)
        f.write_text(json.dumps(d, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"Actualizado {f}")

        idxf = base / "index.json"
        if idxf.exists():
            idx = json.loads(idxf.read_text(encoding="utf-8"))
            if mark in idx:
                idx[mark]["soldaduras"] = len(welds)
                idxf.write_text(json.dumps(idx, ensure_ascii=False, indent=1),
                                encoding="utf-8")
