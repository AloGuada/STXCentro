"""
El corte de los barrenos en la malla y como salen a la ficha.

El IFC minimo de las pruebas no trae IfcMechanicalFastener, asi que aqui no se
leen tornillos: se arma a mano un orificio con la misma forma que devuelve
find_holes y se comprueba lo que hacen cut_holes, mover y publicar, que es de
donde salen el .glb barrenado y los "orificios" de cada pieza.
"""
import numpy as np
import pytest
import trimesh

from holes import cut_holes, mover, publicar

DIAM_M = 0.022          # barreno de 22 mm
ESPESOR_M = 0.012       # placa de 12 mm


def placa():
    """Placa de 200 x 200 x 12, centrada en el origen."""
    return trimesh.creation.box(extents=(0.200, 0.200, ESPESOR_M))


def orificio(centro=(0.0, 0.0, 0.0), lados=24):
    """Un orificio redondo como el que arma find_holes, con su seccion y su eje."""
    angulos = np.linspace(0.0, 2.0 * np.pi, lados, endpoint=False)
    poly = np.column_stack([np.cos(angulos), np.sin(angulos)]) * (DIAM_M / 2.0)
    return {
        "tipo": "redondo",
        "diam_mm": round(DIAM_M * 1000.0, 1),
        "largo_mm": None,
        "eje_largo": None,
        "espesor_mm": round(ESPESOR_M * 1000.0, 1),
        "origen": "orificio",
        "perno_mm": 19.0,
        "fastener": "0fake",
        "centro": np.array(centro, dtype=np.float64),
        "eje": np.array([0.0, 0.0, 1.0]),
        "_u": np.array([1.0, 0.0, 0.0]),
        "_poly": poly,
        "_t": ESPESOR_M,
    }


def test_el_barreno_se_corta_en_la_malla_y_le_quita_volumen():
    maciza = placa()

    barrenada, cortados = cut_holes(maciza, [orificio()])

    assert cortados == 1
    esperado = np.pi * (DIAM_M / 2.0) ** 2 * ESPESOR_M
    assert barrenada.volume < maciza.volume
    assert maciza.volume - barrenada.volume == pytest.approx(esperado, rel=0.02)


def test_sin_orificios_la_malla_se_devuelve_igual():
    maciza = placa()

    misma, cortados = cut_holes(maciza, [])

    assert cortados == 0
    assert misma is maciza


def test_dos_barrenos_se_cortan_los_dos():
    maciza = placa()

    barrenada, cortados = cut_holes(maciza, [orificio((-0.050, 0, 0)), orificio((0.050, 0, 0))])

    assert cortados == 2
    esperado = 2 * np.pi * (DIAM_M / 2.0) ** 2 * ESPESOR_M
    assert maciza.volume - barrenada.volume == pytest.approx(esperado, rel=0.02)


def test_mover_centra_el_orificio_como_la_marca_sin_tocar_el_original():
    origen = np.array([1.0, 2.0, 3.0])
    h = orificio((1.0, 2.0, 3.0))

    movido = mover([h], origen)[0]

    assert movido["centro"].tolist() == [0.0, 0.0, 0.0]
    assert h["centro"].tolist() == [1.0, 2.0, 3.0]


def test_publicar_deja_el_orificio_listo_para_el_json():
    publicado = publicar([orificio((0.010, 0.020, 0.0))], np.zeros(3))[0]

    # nada de campos internos de geometria: el json lo lee el importador
    assert not [k for k in publicado if k.startswith("_")]
    assert publicado["tipo"] == "redondo"
    assert publicado["diam_mm"] == 22.0
    assert publicado["espesor_mm"] == 12.0
    assert publicado["centro"] == [0.01, 0.02, 0.0]
    assert publicado["eje"] == [0.0, 0.0, 1.0]
    # un barreno redondo no lleva largo ni eje del ovalo
    assert "largo_mm" not in publicado
    assert "eje_largo" not in publicado
