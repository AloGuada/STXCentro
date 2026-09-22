"""
Sidecar de OCR (reemplaza Tesseract).

Mantiene el modelo de PaddleOCR cargado en memoria y expone un único endpoint
HTTP que recibe una imagen o un PDF y devuelve el texto reconocido. Laravel lo
consume vía App\\Services\\Ocr\\OcrClient (config services.paddle_ocr.url).

El modelo tarda en cargar y come memoria: por eso es un proceso aparte que se
levanta una vez, y no algo que PHP arranque por petición.

Arranque:
    pip install -r requirements.txt
    uvicorn main:app --host 0.0.0.0 --port 8800
"""

import os
from io import BytesIO

import fitz
import numpy as np
from fastapi import FastAPI, File, HTTPException, UploadFile
from paddleocr import PaddleOCR
from PIL import Image

app = FastAPI(title="OCR Service", version="1.0.0")

# El lado mayor al que se reduce una imagen antes de reconocerla. Una foto de
# 4000 px no reconoce mejor que una de 1600 y multiplica el tiempo por página.
MAX_LADO = 1600

# Un PDF con menos texto embebido que esto se considera escaneado y se OCR-ea.
PDF_MIN_TEXTO = 100

# A cuánto se rasteriza una página de PDF: 2x sobre 72 dpi son ~144 dpi, que es
# donde PaddleOCR deja de ganar precisión con este modelo.
PDF_ZOOM = 2.0

# oneDNN acelera el reconocimiento, pero con paddlepaddle 3.3.1 en Windows
# revienta al ejecutar el modelo («ConvertPirAttribute2RuntimeAttribute not
# support»). Se deja apagado y se enciende por entorno donde sí funcione.
_MKLDNN = os.getenv("OCR_MKLDNN", "0") == "1"

_ocr = PaddleOCR(
    lang="latin",
    text_detection_model_name="PP-OCRv5_mobile_det",
    text_recognition_model_name="latin_PP-OCRv5_mobile_rec",
    use_doc_orientation_classify=False,
    use_doc_unwarping=False,
    use_textline_orientation=False,
    enable_mkldnn=_MKLDNN,
)


def _reducir(imagen: Image.Image) -> Image.Image:
    """La imagen con su lado mayor en MAX_LADO, o tal cual si ya es menor."""
    lado = max(imagen.size)
    if lado <= MAX_LADO:
        return imagen

    escala = MAX_LADO / lado
    nuevos = (int(imagen.width * escala), int(imagen.height * escala))

    return imagen.resize(nuevos, Image.LANCZOS)


def _ocr_imagenes(imagenes: list[Image.Image]) -> list[str]:
    """Las líneas reconocidas en cada imagen, en el orden en que se leyeron."""
    lineas: list[str] = []

    for imagen in imagenes:
        array = np.array(_reducir(imagen).convert("RGB"))

        for pagina in _ocr.predict(input=array):
            textos = pagina["rec_texts"] if "rec_texts" in pagina else getattr(pagina, "rec_texts", [])
            lineas.extend(texto.strip() for texto in textos if texto and texto.strip())

    return lineas


def _lineas_de_pdf(contenido: bytes) -> list[str]:
    """
    PDF nativo (con capa de texto): se extrae el texto directo, sin OCR (rápido).
    PDF escaneado (sin texto): se rasteriza con PyMuPDF y se OCR-ea cada página.
    """
    doc = fitz.open(stream=contenido, filetype="pdf")

    embebido = "\n".join(pagina.get_text() for pagina in doc)
    if len(embebido.strip()) >= PDF_MIN_TEXTO:
        return [linea.strip() for linea in embebido.splitlines() if linea.strip()]

    imagenes = []
    for pagina in doc:
        pix = pagina.get_pixmap(matrix=fitz.Matrix(PDF_ZOOM, PDF_ZOOM))
        imagenes.append(Image.frombytes("RGB", (pix.width, pix.height), pix.samples))

    return _ocr_imagenes(imagenes)


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/ocr")
async def ocr_endpoint(file: UploadFile = File(...)) -> dict:
    """El texto de una imagen o un PDF, como cadena entera y como líneas."""
    contenido = await file.read()

    if not contenido:
        raise HTTPException(status_code=422, detail="Archivo vacío")

    try:
        if contenido[:5] == b"%PDF-":
            lineas = _lineas_de_pdf(contenido)
        else:
            lineas = _ocr_imagenes([Image.open(BytesIO(contenido))])
    except HTTPException:
        raise
    except Exception as exc:
        raise HTTPException(status_code=422, detail=f"Archivo inválido: {exc}") from exc

    return {"text": "\n".join(lineas), "lines": lineas}
