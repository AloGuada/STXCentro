"""
Los trabajos del servicio: cada IFC subido es un trabajo con su carpeta,
que pasa por pendiente -> procesando -> listo | error.

Se procesan de uno en uno: mallar un IFC de 50 MB y buscar cordones se come la
CPU, y dos a la vez solo tardarian el doble cada uno.

El estado de cada trabajo vive en su carpeta (`estado.json`), y cada marca
queda en disco en cuanto termina. Asi, si el servicio se reinicia a media
conversion, al arrancar retoma los trabajos que estaban a medias sin repetir
las marcas ya hechas, y los terminados se siguen pudiendo descargar.
"""
import json
import shutil
import tempfile
import threading
import uuid
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional

from exportador import exportar_marcas
from welds import WELDS_VERSION

ESTADO = "estado.json"


@dataclass
class Trabajo:
    id: str
    carpeta: Path
    estado: str = "pendiente"          # pendiente | procesando | listo | error
    marcas_hechas: int = 0
    marcas_total: int = 0
    error: Optional[str] = None
    welds: bool = True
    only: Optional[str] = None
    _lock: threading.Lock = field(default_factory=threading.Lock, repr=False, compare=False)

    @property
    def ifc(self) -> Path:
        return self.carpeta / "modelo.ifc"

    @property
    def salida(self) -> Path:
        return self.carpeta / "salida"

    @property
    def zip(self) -> Optional[Path]:
        ruta = self.carpeta / "resultado.zip"
        return ruta if ruta.is_file() else None

    def como_dict(self) -> dict:
        return {
            "id": self.id,
            "estado": self.estado,
            "progreso": {"marcas_hechas": self.marcas_hechas, "marcas_total": self.marcas_total},
            "welds_version": WELDS_VERSION,
            "error": self.error,
        }

    def index(self) -> dict:
        """Lo que va del index.json: vacio si todavia no se escribio ninguna marca."""
        ruta = self.salida / "index.json"
        if not ruta.is_file():
            return {"completo": False, "welds_version": WELDS_VERSION if self.welds else None, "marcas": {},
                    "totales": {"marcas": 0, "soldaduras": 0, "soldadura_mm": 0}}
        return json.loads(ruta.read_text(encoding="utf-8"))

    def modelo_completo(self) -> Optional[Path]:
        """El .glb de la estructura entera; solo existe cuando el trabajo termino."""
        ruta = self.salida / "modelo.glb"
        return ruta if ruta.is_file() else None

    def archivo_de_marca(self, nombre: str) -> Optional[Path]:
        """El .glb o .json de una marca ya escrita; nada si no existe o si el nombre sale de la carpeta."""
        if "/" in nombre or "\\" in nombre or ".." in nombre or not nombre.endswith((".glb", ".json")):
            return None
        ruta = self.salida / "marks" / nombre
        return ruta if ruta.is_file() else None

    def guardar(self) -> None:
        with self._lock:
            datos = {"estado": self.estado, "marcas_hechas": self.marcas_hechas, "marcas_total": self.marcas_total,
                     "error": self.error, "welds": self.welds, "only": self.only}
            tmp = self.carpeta / (ESTADO + ".tmp")
            tmp.write_text(json.dumps(datos), encoding="utf-8")
            tmp.replace(self.carpeta / ESTADO)

    @classmethod
    def desde_carpeta(cls, carpeta: Path) -> Optional["Trabajo"]:
        if not (carpeta / "modelo.ifc").is_file():
            return None
        trabajo = cls(id=carpeta.name, carpeta=carpeta)
        ruta = carpeta / ESTADO
        if ruta.is_file():
            datos = json.loads(ruta.read_text(encoding="utf-8"))
            for clave in ("estado", "marcas_hechas", "marcas_total", "error", "welds", "only"):
                if clave in datos:
                    setattr(trabajo, clave, datos[clave])
        return trabajo


class Trabajos:
    def __init__(self, raiz: Optional[Path] = None, workers: int = 1):
        self.raiz = Path(raiz) if raiz else Path(tempfile.gettempdir()) / "ifc-service"
        self.raiz.mkdir(parents=True, exist_ok=True)
        self._todos: dict[str, Trabajo] = {}
        self._lock = threading.Lock()
        self._pool = ThreadPoolExecutor(max_workers=workers)
        self._retomar()

    def _retomar(self) -> None:
        """Al arrancar: lo terminado se conserva y lo que quedo a medias se relanza."""
        for carpeta in sorted(self.raiz.iterdir()):
            if not carpeta.is_dir():
                continue
            trabajo = Trabajo.desde_carpeta(carpeta)
            if trabajo is None:
                continue
            self._todos[trabajo.id] = trabajo
            if trabajo.estado == "listo" and trabajo.zip is None:
                trabajo.estado = "procesando"
            if trabajo.estado in ("pendiente", "procesando"):
                self.lanzar(trabajo, welds=trabajo.welds, only=trabajo.only)

    def nuevo(self) -> Trabajo:
        id_ = uuid.uuid4().hex
        carpeta = self.raiz / id_
        carpeta.mkdir(parents=True, exist_ok=True)
        trabajo = Trabajo(id=id_, carpeta=carpeta)
        with self._lock:
            self._todos[id_] = trabajo
        return trabajo

    def get(self, id_: str) -> Optional[Trabajo]:
        with self._lock:
            return self._todos.get(id_)

    def lanzar(self, trabajo: Trabajo, *, welds: bool = True, only: Optional[str] = None) -> None:
        trabajo.welds, trabajo.only = welds, only
        trabajo.estado = "pendiente"
        trabajo.error = None
        trabajo.guardar()
        self._pool.submit(self._correr, trabajo)

    def borrar(self, id_: str) -> bool:
        with self._lock:
            trabajo = self._todos.pop(id_, None)
        if trabajo is None:
            return False
        shutil.rmtree(trabajo.carpeta, ignore_errors=True)
        return True

    def _correr(self, trabajo: Trabajo) -> None:
        trabajo.estado = "procesando"
        trabajo.guardar()

        def avance(hechas: int, total: int) -> None:
            trabajo.marcas_hechas, trabajo.marcas_total = hechas, total
            trabajo.guardar()

        try:
            exportar_marcas(trabajo.ifc, trabajo.salida, welds=trabajo.welds, only=trabajo.only, progreso=avance)
            shutil.make_archive(str(trabajo.carpeta / "resultado"), "zip", trabajo.salida)
            trabajo.estado = "listo"
        except Exception as e:                 # el trabajo cuenta el error; el servicio sigue
            trabajo.error = f"{type(e).__name__}: {e}"
            trabajo.estado = "error"
        trabajo.guardar()
