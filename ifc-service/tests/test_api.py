"""
El servicio de punta a punta: subir, preguntar, descargar el zip y borrar.
"""
import io
import time
import zipfile

from fastapi.testclient import TestClient

import main

cliente = TestClient(main.app)


def _esperar(id_, segundos=60):
    fin = time.time() + segundos
    while time.time() < fin:
        estado = cliente.get(f"/estado/{id_}").json()
        if estado["estado"] in ("listo", "error"):
            return estado
        time.sleep(0.1)
    raise AssertionError("el trabajo no termino a tiempo")


def test_procesa_el_ifc_y_entrega_el_zip(ifc_minimo):
    with open(ifc_minimo, "rb") as archivo:
        respuesta = cliente.post("/procesar", files={"archivo": ("modelo.ifc", archivo, "application/octet-stream")})

    assert respuesta.status_code == 202
    id_ = respuesta.json()["id"]

    estado = _esperar(id_)
    assert estado["estado"] == "listo", estado
    assert estado["progreso"] == {"marcas_hechas": 1, "marcas_total": 1}

    zip_ = cliente.get(f"/resultado/{id_}")
    assert zip_.status_code == 200
    nombres = zipfile.ZipFile(io.BytesIO(zip_.content)).namelist()
    assert "index.json" in nombres
    assert any(n.endswith("SX-T1-1.glb") for n in nombres)

    assert cliente.delete(f"/trabajos/{id_}").status_code == 204
    assert cliente.get(f"/estado/{id_}").status_code == 404


def test_rechaza_lo_que_no_es_ifc():
    respuesta = cliente.post("/procesar", files={"archivo": ("planos.pdf", b"%PDF", "application/pdf")})

    assert respuesta.status_code == 422


def test_un_trabajo_que_no_existe_es_404():
    assert cliente.get("/estado/no-existe").status_code == 404
    assert cliente.get("/resultado/no-existe").status_code == 404
    assert cliente.delete("/trabajos/no-existe").status_code == 404


def test_salud_dice_que_version_de_welds_corre():
    assert cliente.get("/salud").json() == {"ok": True, "welds_version": main.WELDS_VERSION}
