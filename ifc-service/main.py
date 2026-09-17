"""
Servicio que convierte el IFC de una obra en una marca por archivo, con sus
cordones de soldadura detectados por geometria.

Corre aparte de Laravel, como el OCR de RH: procesar un IFC tarda minutos y no
cabe en una peticion web. Laravel sube el archivo, pregunta cada tanto por el
estado y se va trayendo cada marca en cuanto esta escrita, sin esperar a que
termine el modelo entero.

    POST   /procesar          multipart: archivo (.ifc), welds, only -> 202 {id}
    GET    /estado/{id}       {estado, progreso{marcas_hechas, marcas_total}, welds_version, error}
    GET    /resultado/{id}    zip con index.json y marks/*.glb|*.json (cuando esta listo)
    GET    /resultado/{id}/marcas            el index.json tal como va: las marcas ya escritas
    GET    /resultado/{id}/marcas/{archivo}  el .glb o .json de una marca ya escrita
    GET    /resultado/{id}/modelo            modelo.glb: la estructura entera, sin cordones
    DELETE /trabajos/{id}     borra la carpeta temporal
    GET    /salud             {ok, welds_version}

    uvicorn main:app --port 8011
"""
import os
import shutil
from typing import Optional

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse, Response

from trabajos import Trabajos
from welds import WELDS_VERSION

app = FastAPI(title="ifc-service", version="1.0")
# La carpeta de trabajos se puede fijar por entorno (las pruebas usan una temporal).
trabajos = Trabajos(os.environ.get("IFC_SERVICE_RAIZ") or None)


@app.get("/salud")
def salud():
    return {"ok": True, "welds_version": WELDS_VERSION}


@app.post("/procesar", status_code=202)
def procesar(archivo: UploadFile = File(...), welds: bool = Form(True), only: Optional[str] = Form(None)):
    if not (archivo.filename or "").lower().endswith(".ifc"):
        raise HTTPException(status_code=422, detail="Se espera un archivo .ifc")

    trabajo = trabajos.nuevo()
    with open(trabajo.ifc, "wb") as destino:
        shutil.copyfileobj(archivo.file, destino)

    trabajos.lanzar(trabajo, welds=welds, only=only or None)
    return {"id": trabajo.id}


@app.get("/estado/{id_}")
def estado(id_: str):
    return _trabajo(id_).como_dict()


@app.get("/resultado/{id_}")
def resultado(id_: str):
    trabajo = _trabajo(id_)
    if trabajo.estado != "listo" or trabajo.zip is None:
        raise HTTPException(status_code=409, detail=f"El trabajo esta {trabajo.estado}")
    return FileResponse(trabajo.zip, media_type="application/zip", filename=f"{id_}.zip")


@app.get("/resultado/{id_}/marcas")
def marcas(id_: str):
    return _trabajo(id_).index()


@app.get("/resultado/{id_}/marcas/{archivo}")
def archivo_de_marca(id_: str, archivo: str):
    ruta = _trabajo(id_).archivo_de_marca(archivo)
    if ruta is None:
        raise HTTPException(status_code=404, detail="Esa marca no esta escrita todavia")
    tipo = "model/gltf-binary" if archivo.endswith(".glb") else "application/json"
    return FileResponse(ruta, media_type=tipo, filename=archivo)


@app.get("/resultado/{id_}/modelo")
def modelo_completo(id_: str):
    ruta = _trabajo(id_).modelo_completo()
    if ruta is None:
        raise HTTPException(status_code=404, detail="El modelo completo no esta escrito todavia")
    return FileResponse(ruta, media_type="model/gltf-binary", filename="modelo.glb")


@app.delete("/trabajos/{id_}", status_code=204)
def borrar(id_: str):
    if not trabajos.borrar(id_):
        raise HTTPException(status_code=404, detail="No existe ese trabajo")
    return Response(status_code=204)


def _trabajo(id_: str):
    trabajo = trabajos.get(id_)
    if trabajo is None:
        raise HTTPException(status_code=404, detail="No existe ese trabajo")
    return trabajo
