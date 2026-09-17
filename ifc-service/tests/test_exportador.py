"""
El exportador contra un IFC real (minimo): una marca, dos placas, dos filetes.
"""
import json

from exportador import exportar_marcas
from welds import WELDS_VERSION


def test_exporta_la_marca_con_su_glb_su_ficha_y_sus_cordones(ifc_minimo, tmp_path):
    resultado = exportar_marcas(ifc_minimo, tmp_path)

    assert list(resultado["marcas"]) == ["SX-T1-1"]
    assert resultado["welds_version"] == WELDS_VERSION

    ficha = json.loads((tmp_path / "marks" / "SX-T1-1.json").read_text(encoding="utf-8"))
    assert ficha["welds_version"] == WELDS_VERSION
    assert len(ficha["piezas"]) == 2
    assert [w["tipo"] for w in ficha["soldaduras"]] == ["filete", "filete"]
    assert (tmp_path / "marks" / "SX-T1-1.glb").stat().st_size > 0

    # los dos rincones de la placa son UNA soldadura para calidad
    assert [j["id"] for j in ficha["juntas"]] == [1]
    assert ficha["juntas"][0]["tramos"] == sorted(ficha["juntas"][0]["tramos"])
    assert ficha["totales"]["juntas"] == 1
    assert [w["junta_id"] for w in ficha["soldaduras"]] == [1, 1]

    indice = json.loads((tmp_path / "index.json").read_text(encoding="utf-8"))
    assert indice["marcas"]["SX-T1-1"]["soldaduras"] == 2
    assert indice["marcas"]["SX-T1-1"]["juntas"] == 1
    assert indice["marcas"]["SX-T1-1"]["file"] == "SX-T1-1"
    # La estructura entera, sin cordones, para verla de un vistazo.
    assert indice["completo"] is True
    assert indice["modelo"] == "modelo.glb"
    assert (tmp_path / "modelo.glb").stat().st_size > 0


def test_sin_soldaduras_no_calcula_cordones(ifc_minimo, tmp_path):
    resultado = exportar_marcas(ifc_minimo, tmp_path, welds=False)

    ficha = json.loads((tmp_path / "marks" / "SX-T1-1.json").read_text(encoding="utf-8"))
    assert ficha["soldaduras"] == []
    assert ficha["juntas"] == []
    assert resultado["welds_version"] is None


def test_las_piezas_traen_sus_orificios_aunque_el_ifc_no_tenga_tornillos(ifc_minimo, tmp_path):
    """
    El IFC minimo no trae IfcMechanicalFastener, asi que no hay barrenos que
    cortar: lo que se comprueba es que la ficha lleva la clave y el conteo en
    cero, que es lo que lee el importador de Laravel.
    """
    resultado = exportar_marcas(ifc_minimo, tmp_path)

    ficha = json.loads((tmp_path / "marks" / "SX-T1-1.json").read_text(encoding="utf-8"))
    assert [p["orificios"] for p in ficha["piezas"]] == [[], []]
    assert ficha["totales"]["orificios"] == 0
    assert resultado["totales"]["orificios"] == 0


def test_sin_barrenos_no_se_leen_los_tornillos(ifc_minimo, tmp_path):
    exportar_marcas(ifc_minimo, tmp_path, barrenos=False)

    ficha = json.loads((tmp_path / "marks" / "SX-T1-1.json").read_text(encoding="utf-8"))
    assert [p["orificios_cortados"] for p in ficha["piezas"]] == [0, 0]


def test_only_filtra_por_prefijo_y_no_malla_nada_si_no_hay(ifc_minimo, tmp_path):
    resultado = exportar_marcas(ifc_minimo, tmp_path, only="XX")

    assert resultado["marcas"] == {}
    assert resultado["modelo"] is None


def test_reporta_el_avance(ifc_minimo, tmp_path):
    avance = []
    exportar_marcas(ifc_minimo, tmp_path, welds=False, progreso=lambda h, t: avance.append((h, t)))

    assert avance == [(0, 1), (1, 1)]
