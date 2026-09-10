"""
Los trabajos del servicio: cada IFC subido es un trabajo con su carpeta
temporal, que pasa por pendiente -> procesando -> listo | error.

Se procesan de uno en uno: mallar un IFC de 50 MB y buscar cordones se come la
CPU, y dos a la vez solo tardarian el doble cada uno. El registro vive en
memoria; si el servicio se reinicia, Laravel ve un 404 al preguntar por el
trabajo y lo marca como error para que se vuelva a procesar.
"""
import shutil
import tempfile
import threading
import uuid
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass
from pathlib import Path
from typing import Optional

from exportador import exportar_marcas
from welds import WELDS_VERSION


@dataclass
class Trabajo:
    id: str
    carpeta: Path
    estado: str = "pendiente"          # pendiente | procesando | listo | error
    marcas_hechas: int = 0
    marcas_total: int = 0
    error: Optional[str] = None
    zip: Optional[Path] = None

    @property
    def ifc(self) -> Path:
        return self.carpeta / "modelo.ifc"

    def como_dict(self) -> dict:
        return {
            "id": self.id,
            "estado": self.estado,
            "progreso": {"marcas_hechas": self.marcas_hechas, "marcas_total": self.marcas_total},
            "welds_version": WELDS_VERSION,
            "error": self.error,
        }


class Trabajos:
    def __init__(self, raiz: Optional[Path] = None, workers: int = 1):
        self.raiz = Path(raiz) if raiz else Path(tempfile.gettempdir()) / "ifc-service"
        self._todos: dict[str, Trabajo] = {}
        self._lock = threading.Lock()
        self._pool = ThreadPoolExecutor(max_workers=workers)

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
        self._pool.submit(self._correr, trabajo, welds, only)

    def borrar(self, id_: str) -> bool:
        with self._lock:
            trabajo = self._todos.pop(id_, None)
        if trabajo is None:
            return False
        shutil.rmtree(trabajo.carpeta, ignore_errors=True)
        return True

    def _correr(self, trabajo: Trabajo, welds: bool, only: Optional[str]) -> None:
        trabajo.estado = "procesando"

        def avance(hechas: int, total: int) -> None:
            trabajo.marcas_hechas, trabajo.marcas_total = hechas, total

        try:
            salida = trabajo.carpeta / "salida"
            exportar_marcas(trabajo.ifc, salida, welds=welds, only=only, progreso=avance)
            trabajo.zip = Path(shutil.make_archive(str(trabajo.carpeta / "resultado"), "zip", salida))
            trabajo.estado = "listo"
        except Exception as e:                 # el trabajo cuenta el error; el servicio sigue
            trabajo.error = f"{type(e).__name__}: {e}"
            trabajo.estado = "error"
