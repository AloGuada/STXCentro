"""
Servicio que convierte el IFC de una obra en una marca por archivo, con sus
cordones de soldadura detectados por geometria.

Corre aparte de Laravel, como el OCR de RH: procesar un IFC tarda minutos y no
cabe en una peticion web. Laravel sube el archivo, pregunta cada tanto por el
estado y, cuando esta listo, descarga el resultado en un zip.

    POST   /procesar          multipart: archivo (.ifc), welds, only -> 202 {id}
    GET    /estado/{id}       {estado, progreso{marcas_hechas, marcas_total}, welds_version, error}
    GET    /resultado/{id}    zip con index.json y marks/*.glb|*.json
    DELETE /trabajos/{id}     borra la carpeta temporal
    GET    /salud             {ok, welds_version}

    uvicorn main:app --port 8011
"""
import shutil
from typing import Optional

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse, Response

from trabajos import Trabajos
from welds import WELDS_VERSION

app = FastAPI(title="ifc-service", version="1.0")
trabajos = Trabajos()


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
