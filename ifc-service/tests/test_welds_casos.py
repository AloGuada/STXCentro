"""
Casos basicos de union entre dos piezas, con geometria sintetica.

Son los de demo3d/test_welds_casos.py, pasados a pytest. Comprueban la regla
que decide cuantos cordones lleva cada union y de que tipo:

  FILETE   hay rincon: una pieza acaba en esa arista y la otra sigue.
           centrada  -> 90 y 90                        -> 2 filetes
           a ras     -> por el lado a ras no hay rincon -> 1 filete
           inclinada -> agudo y obtuso, las dos van     -> 2 filetes

  COSTURA  las dos piezas acaban en la arista y sus caras siguen coplanares
           (180 grados). Un liston por cada lado al que se pueda llegar; el
           lado que da al interior de un cajon no cuenta.
"""
import numpy as np
import pytest
import trimesh

from welds import detect_welds

BASE_XY = (0.400, 0.400)      # placa base 400 x 400
BASE_T = 0.012                # 12 mm de espesor
ALMA_T = 0.008                # 8 mm de espesor
ALMA_L = 0.300                # 300 mm de largo
ALMA_H = 0.150                # 150 mm de alto


def placa_base():
    m = trimesh.creation.box(extents=(BASE_XY[0], BASE_XY[1], BASE_T))
    m.apply_translation((0, 0, -BASE_T / 2))       # cara superior en z = 0
    return m


def alma(dx=0.0, inclinacion_deg=0.0):
    """placa apoyada sobre z=0, desplazada dx en x e inclinada (cizalla sobre
    los vertices: con un transform no rigido trimesh deja las normales mal)"""
    m = trimesh.creation.box(extents=(ALMA_T, ALMA_L, ALMA_H))
    m.apply_translation((dx, 0, ALMA_H / 2))
    v = m.vertices.copy()
    if inclinacion_deg:
        v[:, 0] += np.tan(np.radians(inclinacion_deg)) * v[:, 2]
    return trimesh.Trimesh(vertices=v, faces=m.faces, process=False)


def caja(extents, centro):
    m = trimesh.creation.box(extents=extents)
    m.apply_translation(centro)
    return m


A_RAS = BASE_XY[0] / 2 - ALMA_T / 2
IZQ = ((0.200, ALMA_L, BASE_T), (-0.100, 0, 0))
DER = ((0.200, ALMA_L, BASE_T), (0.100, 0, 0))

CASOS = [
    ("centrada", lambda: {"base": placa_base(), "alma": alma()}, 2, 0),
    # por un lado rincon de 90; por el otro la cara de la placa y el canto de
    # la base quedan coplanares -> filete + costura
    ("a ras del borde", lambda: {"base": placa_base(), "alma": alma(dx=A_RAS)}, 1, 1),
    ("inclinada 15 grados", lambda: {"base": placa_base(), "alma": alma(inclinacion_deg=15.0)}, 2, 0),
    ("inclinada 32 grados", lambda: {"base": placa_base(), "alma": alma(inclinacion_deg=32.0)}, 2, 0),
    ("inclinada 55 grados", lambda: {"base": placa_base(), "alma": alma(inclinacion_deg=55.0)}, 2, 0),
    # inclinada y a ras: al ir inclinada su cara ya no queda coplanar con el
    # canto de la base, no llegan a 180 grados -> ninguna costura
    ("inclinada y a ras", lambda: {"base": placa_base(), "alma": alma(dx=A_RAS, inclinacion_deg=15.0)}, 1, 0),
    # placa que cae entera sobre la otra: se suelda todo su contorno
    ("apoyada entera", lambda: {"base": placa_base(),
                                "tapa": caja((0.100, ALMA_L, BASE_T), (0.050, 0, BASE_T / 2))}, 4, 0),
    # solape: filete en el canto de cada una y costura en los dos laterales
    ("solape", lambda: {"base": placa_base(),
                        "sol": caja((0.300, BASE_XY[1], BASE_T), (0.250, 0, BASE_T / 2))}, 2, 2),
    # dos placas coplanares canto con canto, los dos lados accesibles
    ("a tope, al aire", lambda: {"izq": caja(*IZQ), "der": caja(*DER)}, 0, 2),
    # la misma junta con el lado de abajo tapado por otra pieza separada 2 mm
    ("a tope, un lado tapado", lambda: {"izq": caja(*IZQ), "der": caja(*DER),
                                        "tapon": caja((0.200, ALMA_L, 0.050),
                                                      (0, 0, -BASE_T / 2 - 0.002 - 0.025))}, 0, 1),
]


@pytest.mark.parametrize("nombre, piezas, filetes, costuras", CASOS, ids=[c[0] for c in CASOS])
def test_cuantos_cordones_lleva_cada_union(nombre, piezas, filetes, costuras):
    welds = detect_welds(piezas(), verbose=False)

    assert (sum(w["tipo"] == "filete" for w in welds), sum(w["tipo"] == "costura" for w in welds)) == (filetes, costuras)
