"""
ORIFICIOS de cada pieza, deducidos de los tornillos del IFC.

Tekla no exporta los barrenos: ni IfcOpeningElement ni booleanas, y las placas
salen macizas. Lo unico que hay son los IfcMechanicalFastener, y cada uno es un
GRUPO: un IfcMappedItem por tornillo. Vienen de dos formas:

  * tornillo real   cabeza, vastago, rondanas y tuercas como cascarones sueltos
                    y, casi siempre, un DISCO por cada placa que atraviesa: ese
                    disco es el barreno, con su forma exacta (redondo u ovalado)
                    y el espesor de la placa.
  * solo orificio   unicamente los discos, sin herraje (NominalLength suele ser
                    el espesor de la placa).

Manda el disco. Solo si un tornillo no trae ninguno se deduce el barreno del
vastago: diametro + holgura estandar (AISC 360 tabla J3.3).

Un tornillo no esta ligado a las placas que atraviesa (no hay
IfcRelConnectsWithRealizingElements) y los de montaje cuelgan de UNO solo de
los dos ensambles que unen, asi que se prueban TODOS los tornillos del modelo
contra cada pieza: se tira una recta por el eje y se mira si entra y sale de la
pieza dentro del tramo del disco (o del vastago).

Uso como libreria:
    from holes import load_bolts, find_holes, cut_holes
    bolts = load_bolts(model)                       # una vez por IFC
    holes = find_holes(bolts, {guid: trimesh})      # coordenadas de mundo, metros
    mesh2, n = cut_holes(mesh, holes[guid])

Uso directo (censo de tornillos del IFC, no malla nada):
    python holes.py [archivo.ifc]
"""
import sys
import collections
from pathlib import Path

import numpy as np
import trimesh
import ifcopenshell
import ifcopenshell.util.placement as up
import ifcopenshell.util.unit as uu
from shapely.geometry import MultiPoint

SEGMENTOS  = 24        # lados del barreno redondo en la malla
TOL_D      = 0.02      # el vastago mide el diametro nominal +-2 %; un barreno, mas
MARGEN_M   = 0.001     # 1 mm: cuanto puede sobresalir la pieza del tramo del vastago
SOBRA_M    = 0.001     # 1 mm: el cortador sobresale de la pieza por cada cara
MIN_ESP_M  = 0.0005    # entradas y salidas mas juntas que esto son la misma arista
DUP_M      = 0.001     # dos orificios a menos de 1 mm en la misma pieza son uno


def holgura_mm(d_mm):
    """Orificio estandar AISC 360-16 tabla J3.3: +1/16" hasta 7/8", +1/8" desde 1"."""
    return 1.5875 if d_mm < 25.0 else 3.175


# ---------------------------------------------------------------------------
# 1) leer los tornillos
# ---------------------------------------------------------------------------
def _cascarones(rep):
    """Cascarones sueltos de una representacion: [(puntos, tapa)] en unidades del IFC.
    La tapa es la cara con mas vertices; en un prisma, su normal es el eje."""
    caras = []
    for item in rep.Items:
        if not item.is_a("IfcFacetedBrep"):
            continue
        for f in item.Outer.CfsFaces:
            for b in f.Bounds:
                if b.is_a("IfcFaceOuterBound") or len(f.Bounds) == 1:
                    caras.append([tuple(round(c, 3) for c in p.Coordinates)
                                  for p in b.Bound.Polygon])
    padre = {}

    def raiz(a):
        while padre[a] != a:
            padre[a] = padre[padre[a]]
            a = padre[a]
        return a

    for cara in caras:
        for p in cara:
            padre.setdefault(p, p)
        for p in cara[1:]:
            padre[raiz(p)] = raiz(cara[0])
    puntos, tapas = collections.defaultdict(set), {}
    for cara in caras:
        r = raiz(cara[0])
        puntos[r].update(cara)
        if len(cara) > len(tapas.get(r, ())):
            tapas[r] = cara
    return [(np.array(sorted(puntos[r]), dtype=np.float64),
             np.array(tapas[r], dtype=np.float64)) for r in puntos]


def _ejes(P):
    """Centro, ejes principales y extension de una nube de puntos."""
    c = (P.max(axis=0) + P.min(axis=0)) / 2.0
    _, _, vt = np.linalg.svd(P - P.mean(axis=0), full_matrices=False)
    q = (P - c) @ vt.T
    c = c + ((q.max(axis=0) + q.min(axis=0)) / 2.0) @ vt
    return c, vt, q.max(axis=0) - q.min(axis=0)


def _base(e):
    """Dos vectores perpendiculares al eje."""
    ref = np.array([1.0, 0, 0]) if abs(e[0]) < 0.9 else np.array([0, 1.0, 0])
    u = np.cross(e, ref)
    u /= np.linalg.norm(u)
    return u, np.cross(e, u)


def _leer_mapa(rep, d_nom):
    """
    Que trae este IfcRepresentationMap, en coordenadas locales y unidades del IFC:
        ("tornillo", p0, eje, largo, None)      vastago de p0 a p0 + eje*largo
        ("orificio", p0, eje, largo, puntos)    barreno, con su forma exacta

    Si hay herraje hexagonal (cabeza, tuercas) es un tornillo real: el prisma del
    diametro nominal es el vastago, los algo mayores son barrenos y los mucho
    mayores, rondanas. Si no hay herraje es un grupo de solo orificios, y vale
    cualquier diametro (los de anclas son muy holgados) mientras todos los
    discos sean iguales; asi no se confunde con un conector de cortante.
    """
    prismas, herraje = [], False
    for P, tapa in _cascarones(rep):
        if len(tapa) < 8:                       # hexagonos: cabeza y tuercas
            herraje = True
            continue
        n = np.zeros(3)                         # normal de la tapa (Newell)
        for a, b in zip(tapa, np.roll(tapa, -1, axis=0)):
            n += np.cross(a, b)
        if np.linalg.norm(n) < 1e-9:
            continue
        eje = n / np.linalg.norm(n)
        u, v = _base(eje)
        xy = np.c_[P @ u, P @ v]
        rect = np.asarray(MultiPoint(xy).minimum_rotated_rectangle.exterior.coords)
        ancho, largo_sec = sorted([np.linalg.norm(rect[1] - rect[0]),
                                   np.linalg.norm(rect[2] - rect[1])])
        c2 = rect[:4].mean(axis=0)
        if largo_sec < 1.08 * ancho:            # redondo: los vertices estan en el circulo
            ancho = 2.0 * float(np.linalg.norm(xy - c2, axis=1).max())
        t = P @ eje
        prismas.append({"p0": c2[0] * u + c2[1] * v + eje * t.min(), "eje": eje,
                        "alto": float(t.max() - t.min()), "ancho": ancho,
                        "redondo": largo_sec < 1.08 * ancho, "P": P})

    def orificio(q):
        return ("orificio", q["p0"], q["eje"], q["alto"], q["P"])

    if not herraje:
        anchos = [q["ancho"] for q in prismas]
        if prismas and max(anchos) - min(anchos) <= 0.02 * max(anchos):
            return [orificio(q) for q in prismas]
        return []

    out, vastago = [], None
    for q in prismas:
        if q["redondo"] and abs(q["ancho"] - d_nom) <= TOL_D * d_nom:
            if vastago is None or q["alto"] > vastago["alto"]:
                vastago = q
        elif (1 + TOL_D) * d_nom < q["ancho"] <= 1.45 * d_nom:
            out.append(orificio(q))
    if vastago is not None:
        out.append(("tornillo", vastago["p0"], vastago["eje"], vastago["alto"], None))
    return out


def load_bolts(model, verbose=True):
    """
    Todos los tornillos del modelo, uno por IfcMappedItem, en METROS y mundo:
        {"p0", "eje", "largo", "tipo", "d_perno_mm", "contorno", "guid"}
    """
    escala = uu.calculate_unit_scale(model)            # unidades del IFC -> metros
    mapas, bolts, raros = {}, [], collections.Counter()

    for f in model.by_type("IfcMechanicalFastener"):
        if not f.Representation:
            continue
        d_nom = float(f.NominalDiameter or 0.0)
        l_nom = float(f.NominalLength or 0.0)
        if d_nom <= 0:
            raros["sin diametro"] += 1
            continue
        M = up.get_local_placement(f.ObjectPlacement)
        for rep in f.Representation.Representations:
            for item in rep.Items:
                if not item.is_a("IfcMappedItem"):
                    continue
                src = item.MappingSource
                clave = (src.id(), round(d_nom, 2))
                if clave not in mapas:
                    mapas[clave] = _leer_mapa(src.MappedRepresentation, d_nom)
                if not mapas[clave]:
                    raros[f"no reconocido {d_nom:.1f}x{l_nom:.1f}"] += 1
                    continue
                T = M @ up.get_mappeditem_transformation(item)
                R, t = T[:3, :3], T[:3, 3]
                con_discos = any(x[0] == "orificio" for x in mapas[clave])
                for tipo, p0, eje, largo, contorno in mapas[clave]:
                    if tipo == "tornillo" and con_discos:
                        continue        # sus barrenos ya vienen; el vastago no aporta
                    e = R @ eje
                    e /= np.linalg.norm(e)
                    bolts.append({
                        "guid": f.GlobalId,
                        "tipo": tipo,
                        "p0": (R @ p0 + t) * escala,
                        "eje": e,
                        "largo": largo * escala,
                        "d_perno_mm": round(d_nom * escala * 1000.0, 2),
                        "contorno": None if contorno is None
                                    else (contorno @ R.T + t) * escala,
                    })
    if verbose:
        n = collections.Counter(b["tipo"] for b in bolts)
        print(f"Barrenos en el IFC: {n['orificio']}   tornillos sin barreno "
              f"(se deduce del vastago): {n['tornillo']}   "
              f"[{len(model.by_type('IfcMechanicalFastener'))} grupos]")
        for k, v in raros.most_common(8):
            print(f"   sin usar: {k}  x{v}")
    return bolts


# ---------------------------------------------------------------------------
# 2) que piezas atraviesa cada tornillo
# ---------------------------------------------------------------------------
def _cruces(tri, o, d):
    """Parametros t donde la recta o + t*d cruza los triangulos (Moller-Trumbore)."""
    a, b, c = tri[:, 0], tri[:, 1], tri[:, 2]
    e1, e2 = b - a, c - a
    h = np.cross(d, e2)
    det = np.einsum("ij,ij->i", e1, h)
    ok = np.abs(det) > 1e-14
    inv = np.zeros_like(det)
    inv[ok] = 1.0 / det[ok]
    s = o - a
    u = np.einsum("ij,ij->i", s, h) * inv
    q = np.cross(s, e1)
    v = (q @ d) * inv
    t = np.einsum("ij,ij->i", e2, q) * inv
    eps = 1e-9
    ok &= (u >= -eps) & (v >= -eps) & (u + v <= 1 + eps)
    return np.sort(t[ok])


def _forma(bolt):
    """Seccion del orificio: (tipo, diam_mm, largo_mm, eje_largo, u, poligono 2D en m)."""
    u, v = _base(bolt["eje"])

    if bolt["tipo"] == "tornillo":
        d = bolt["d_perno_mm"] + holgura_mm(bolt["d_perno_mm"])
        ang = np.linspace(0, 2 * np.pi, SEGMENTOS, endpoint=False)
        r = d / 2000.0
        return "redondo", round(d, 2), None, None, u, np.c_[np.cos(ang), np.sin(ang)] * r

    # solo orificio: la forma es la del disco exportado
    C = bolt["contorno"] - bolt["p0"]
    xy = np.c_[C @ u, C @ v]
    xy -= (xy.max(axis=0) + xy.min(axis=0)) / 2.0
    _, vt, ext = _ejes(np.c_[xy, np.zeros(len(xy))])
    largo, ancho = sorted(ext[:2], reverse=True)
    if largo - ancho < 0.05 * ancho:                       # redondo
        r = float(np.linalg.norm(xy, axis=1).max())       # vertices sobre el circulo
        ang = np.linspace(0, 2 * np.pi, SEGMENTOS, endpoint=False)
        return ("redondo", round(2000.0 * r, 2), None, None, u,
                np.c_[np.cos(ang), np.sin(ang)] * r)
    # ovalado: se corta con el contorno tal cual (es convexo)
    hull = np.asarray(MultiPoint(xy).convex_hull.exterior.coords)[:-1]
    eje_largo = vt[0][0] * u + vt[0][1] * v
    return ("ovalado", round(ancho * 1000.0, 2), round(largo * 1000.0, 2),
            eje_largo, u, hull)


def find_holes(bolts, meshes, verbose=False):
    """
    meshes: {id: trimesh} en coordenadas de mundo (metros).
    Devuelve {id: [orificio, ...]} solo para las piezas atravesadas.
    """
    if not bolts or not meshes:
        return {}
    P0 = np.array([b["p0"] for b in bolts])
    P1 = P0 + np.array([b["eje"] * b["largo"] for b in bolts])
    blo, bhi = np.minimum(P0, P1), np.maximum(P0, P1)

    out = {}
    for pid, mesh in meshes.items():
        lo, hi = mesh.bounds[0] - MARGEN_M, mesh.bounds[1] + MARGEN_M
        cerca = np.nonzero(np.all((bhi >= lo) & (blo <= hi), axis=1))[0]
        if not len(cerca):
            continue
        tri = mesh.triangles
        lista = []
        for i in cerca:
            b = bolts[i]
            ts = _cruces(tri, b["p0"], b["eje"])
            if len(ts) < 2:
                continue
            ts = ts[np.r_[True, np.diff(ts) > MIN_ESP_M]]   # aristas compartidas
            if len(ts) % 2:
                continue                                    # roza un borde: dudoso
            # un disco es el barreno de UNA placa: tiene que coincidir con ella, o
            # se le cuelga el de la placa de al lado. El vastago las atraviesa todas.
            m = 0.0 if b["tipo"] == "orificio" else MARGEN_M
            for t_in, t_out in zip(ts[0::2], ts[1::2]):
                dentro = min(t_out, b["largo"] + m) - max(t_in, -m)
                if dentro < 0.5 * min(t_out - t_in, b["largo"] + 2 * m):
                    continue
                # el disco mide lo que la placa; algo mucho mas largo es el tensor o
                # el ancla que PASA por el barreno, no la pieza barrenada
                if b["tipo"] == "orificio" and t_out - t_in > 1.5 * b["largo"] + 1e-3:
                    continue
                tipo, diam, largo, eje_largo, u, poly = _forma(b)
                lista.append({
                    "tipo": tipo,
                    "diam_mm": diam,
                    "largo_mm": largo,
                    "eje_largo": eje_largo,
                    "espesor_mm": round(float(t_out - t_in) * 1000.0, 1),
                    "origen": b["tipo"],
                    "perno_mm": b["d_perno_mm"],
                    "fastener": b["guid"],
                    "centro": b["p0"] + b["eje"] * (t_in + t_out) / 2.0,
                    "eje": b["eje"].copy(),
                    "_u": u, "_poly": poly, "_t": float(t_out - t_in),
                })
        # un mismo barreno puede venir dos veces (tornillo + solo orificio, o los
        # dos ensambles de una union): manda el "orificio", que trae la forma real
        lista.sort(key=lambda h: (h["origen"] != "orificio", -h["diam_mm"]))
        unicos = []
        for h in lista:
            if any(np.linalg.norm(np.cross(h["centro"] - k["centro"], k["eje"])) < DUP_M
                   and abs((h["centro"] - k["centro"]) @ k["eje"]) < DUP_M + k["_t"]
                   for k in unicos):
                continue
            unicos.append(h)
        if unicos:
            out[pid] = unicos
    if verbose:
        print(f"Orificios: {sum(len(v) for v in out.values())} en {len(out)} piezas")
    return out


# ---------------------------------------------------------------------------
# 3) cortar la malla
# ---------------------------------------------------------------------------
def _prisma(h):
    """Cortador: la seccion del orificio extruida a lo largo del eje."""
    e, u = h["eje"], h["_u"]
    v = np.cross(e, u)
    poly = h["_poly"]
    n = len(poly)
    # sentido antihorario visto desde +eje, para que las normales salgan hacia fuera
    area = 0.5 * np.sum(poly[:, 0] * np.roll(poly[:, 1], -1)
                        - np.roll(poly[:, 0], -1) * poly[:, 1])
    if area < 0:
        poly = poly[::-1]
    m = h["_t"] / 2.0 + SOBRA_M
    base = h["centro"] + np.outer(poly[:, 0], u) + np.outer(poly[:, 1], v)
    V = np.vstack([base - e * m, base + e * m,
                   h["centro"] - e * m, h["centro"] + e * m])
    F = []
    for i in range(n):
        j = (i + 1) % n
        F += [[i, j, n + j], [i, n + j, n + i],          # pared
              [2 * n, j, i], [2 * n + 1, n + i, n + j]]  # tapas
    return trimesh.Trimesh(vertices=V, faces=np.array(F), process=False)


def cut_holes(mesh, holes):
    """Devuelve (malla con los barrenos, cuantos se cortaron). No toca `mesh`."""
    if not holes:
        return mesh, 0
    try:
        solido = trimesh.Trimesh(vertices=mesh.vertices, faces=mesh.faces, process=True)
        if not solido.is_volume:
            return mesh, 0
        cortador = trimesh.boolean.union([_prisma(h) for h in holes], engine="manifold") \
            if len(holes) > 1 else _prisma(holes[0])
        res = trimesh.boolean.difference([solido, cortador], engine="manifold")
        if res.is_empty or not len(res.faces):
            return mesh, 0
        return res, len(holes)
    except Exception:
        return mesh, 0


def publicar(holes, origen):
    """Orificios listos para el .json: centrados como la marca y sin campos internos."""
    out = []
    for h in holes:
        d = {k: v for k, v in h.items() if not k.startswith("_")}
        d["centro"] = [round(float(x), 4) for x in h["centro"] - origen]
        d["eje"] = [round(float(x), 4) for x in h["eje"]]
        if d["largo_mm"] is None:
            del d["largo_mm"], d["eje_largo"]
        else:
            d["eje_largo"] = [round(float(x), 4) for x in h["eje_largo"]]
        out.append(d)
    return out


def mover(holes, origen):
    """Los mismos orificios con el centro desplazado (para cortar la malla centrada)."""
    return [dict(h, centro=h["centro"] - origen) for h in holes]


if __name__ == "__main__":
    ruta = sys.argv[1] if len(sys.argv) > 1 else \
        str(Path(__file__).parent / "NAVE SIX-PRODUCCION-22-07-26.ifc")
    print(f"Abriendo {Path(ruta).name} ...")
    bolts = load_bolts(ifcopenshell.open(ruta))
    censo = collections.Counter((b["tipo"], b["d_perno_mm"], round(b["largo"] * 1000, 1))
                                for b in bolts)
    print("\n  tipo       perno_mm  largo_mm   cantidad")
    for (tipo, d, l), n in censo.most_common(25):
        print(f"  {tipo:<10} {d:>8} {l:>9} {n:>10}")
