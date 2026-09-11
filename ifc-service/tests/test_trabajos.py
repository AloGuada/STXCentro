"""
Los trabajos sobreviven al reinicio del servicio: el estado va a disco y lo
que quedo a medias se retoma sin repetir las marcas ya escritas.
"""
import json
import shutil
import time

from trabajos import Trabajos


def _esperar(trabajo, segundos=60):
    fin = time.time() + segundos
    while time.time() < fin and trabajo.estado not in ("listo", "error"):
        time.sleep(0.1)
    return trabajo.estado


def test_el_estado_va_a_disco_y_se_recupera_al_arrancar(ifc_minimo, tmp_path):
    trabajos = Trabajos(tmp_path)
    trabajo = trabajos.nuevo()
    shutil.copy(ifc_minimo, trabajo.ifc)
    trabajos.lanzar(trabajo, welds=False)
    assert _esperar(trabajo) == "listo"

    otra_vez = Trabajos(tmp_path)
    recuperado = otra_vez.get(trabajo.id)
    assert recuperado is not None
    assert recuperado.estado == "listo"
    assert recuperado.zip is not None
    assert recuperado.index()["completo"] is True
    assert recuperado.archivo_de_marca("SX-T1-1.glb") is not None


def test_un_trabajo_a_medias_se_retoma_sin_repetir_las_marcas_hechas(ifc_minimo, tmp_path):
    carpeta = tmp_path / "abc"
    carpeta.mkdir()
    shutil.copy(ifc_minimo, carpeta / "modelo.ifc")
    (carpeta / "estado.json").write_text(json.dumps({"estado": "procesando", "marcas_total": 1, "welds": False}))
    # La unica marca ya quedo escrita en la corrida anterior.
    marks = carpeta / "salida" / "marks"
    marks.mkdir(parents=True)
    (marks / "SX-T1-1.glb").write_bytes(b"glTF")
    (marks / "SX-T1-1.json").write_text(json.dumps({"marca": "SX-T1-1", "nombre": "Trabe", "piezas": [], "soldaduras": [],
                                                    "totales": {"peso_kg": 1, "soldadura_mm": 0, "bbox_mm": [1, 1, 1]}}))

    trabajos = Trabajos(tmp_path)
    trabajo = trabajos.get("abc")
    assert _esperar(trabajo) == "listo"
    # No se volvio a mallar: el glb sigue siendo el de mentira.
    assert (marks / "SX-T1-1.glb").read_bytes() == b"glTF"
    assert trabajo.index()["marcas"]["SX-T1-1"]["nombre"] == "Trabe"
