"""
La agrupacion de cordones en juntas: lo que calidad cuenta como UNA soldadura.

detect_welds da un cordon por cada arista recta de la huella de contacto, asi
que una placa centrada sobre otra da dos. En el plano esa union lleva UN solo
simbolo, y es lo que el inspector referencia: los dos cordones son tramos de la
misma junta. Aqui se comprueba sobre los mismos casos sinteticos de
test_welds_casos.py.
"""
import pytest

from test_welds_casos import A_RAS, ALMA_L, BASE_T, BASE_XY, DER, IZQ, alma, caja, placa_base
from welds import agrupar_juntas, detect_welds

CASOS = [
    # los dos rincones de la placa son la misma soldadura (ambos lados)
    ("centrada", lambda: {"base": placa_base(), "alma": alma()}, 2, 1),
    ("a ras del borde", lambda: {"base": placa_base(), "alma": alma(dx=A_RAS)}, 2, 1),
    # todo el contorno de la placa apoyada: una sola junta alrededor
    ("apoyada entera", lambda: {"base": placa_base(),
                                "tapa": caja((0.100, ALMA_L, BASE_T), (0.050, 0, BASE_T / 2))}, 4, 1),
    ("solape", lambda: {"base": placa_base(),
                        "sol": caja((0.300, BASE_XY[1], BASE_T), (0.250, 0, BASE_T / 2))}, 4, 1),
    # dos placas canto con canto: los dos lados son la misma junta a tope
    ("a tope, al aire", lambda: {"izq": caja(*IZQ), "der": caja(*DER)}, 2, 1),
]


@pytest.mark.parametrize("nombre, piezas, cordones, juntas", CASOS, ids=[c[0] for c in CASOS])
def test_cuantas_juntas_lleva_cada_union(nombre, piezas, cordones, juntas):
    welds = detect_welds(piezas(), verbose=False)
    agrupadas = agrupar_juntas(welds)

    assert len(welds) == cordones
    assert len(agrupadas) == juntas


def test_cada_cordon_queda_como_tramo_de_su_junta():
    welds = detect_welds({"base": placa_base(), "alma": alma()}, verbose=False)

    juntas = agrupar_juntas(welds)

    junta = juntas[0]
    assert junta["id"] == 1
    assert sorted(junta["tramos"]) == sorted(w["id"] for w in welds)
    assert junta["principal"] in junta["tramos"]
    # el largo de la junta es el de sus tramos sumados, no el del principal
    assert junta["largo_mm"] == pytest.approx(sum(w["largo_mm"] for w in welds), abs=0.1)
    assert all(w["junta_id"] == 1 for w in welds)
    # el auxiliar de geometria no se queda en la ficha
    assert all("_P" not in w for w in welds)


def test_dos_uniones_separadas_del_mismo_par_son_dos_juntas():
    """El mismo par de piezas unido en dos sitios lejanos lleva dos simbolos."""
    piezas = {
        "base": placa_base(),
        "izq": caja((0.030, 0.030, 0.100), (-0.150, 0, 0.050)),
        "der": caja((0.030, 0.030, 0.100), (0.150, 0, 0.050)),
    }

    juntas = agrupar_juntas(detect_welds(piezas, verbose=False))

    # una junta por cada montante: son pares de piezas distintos
    assert {tuple(sorted(j["piezas"])) for j in juntas} == {("base", "izq"), ("base", "der")}
    assert len(juntas) == 2


def test_las_juntas_se_numeran_de_abajo_hacia_arriba():
    piezas = {
        "base": placa_base(),
        "alma": alma(),
        "tapa": caja((0.100, ALMA_L, BASE_T), (0.000, 0, 0.150 + BASE_T / 2)),
    }

    juntas = agrupar_juntas(detect_welds(piezas, verbose=False))

    alturas = [j["centro"][2] for j in juntas]
    assert alturas == sorted(alturas)
    assert [j["id"] for j in juntas] == list(range(1, len(juntas) + 1))
