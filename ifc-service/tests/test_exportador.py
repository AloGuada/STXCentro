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

    indice = json.loads((tmp_path / "index.json").read_text(encoding="utf-8"))
    assert indice["marcas"]["SX-T1-1"]["soldaduras"] == 2
    assert indice["marcas"]["SX-T1-1"]["file"] == "SX-T1-1"


def test_sin_soldaduras_no_calcula_cordones(ifc_minimo, tmp_path):
    resultado = exportar_marcas(ifc_minimo, tmp_path, welds=False)

    ficha = json.loads((tmp_path / "marks" / "SX-T1-1.json").read_text(encoding="utf-8"))
    assert ficha["soldaduras"] == []
    assert resultado["welds_version"] is None


def test_only_filtra_por_prefijo_y_no_malla_nada_si_no_hay(ifc_minimo, tmp_path):
    assert exportar_marcas(ifc_minimo, tmp_path, only="XX")["marcas"] == {}


def test_reporta_el_avance(ifc_minimo, tmp_path):
    avance = []
    exportar_marcas(ifc_minimo, tmp_path, welds=False, progreso=lambda h, t: avance.append((h, t)))

    assert avance == [(0, 1), (1, 1)]
