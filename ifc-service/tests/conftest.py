"""
Un IFC minimo para las pruebas: una marca con una placa base y un alma
centrada encima, que es el caso 1 de los casos basicos (2 filetes).

Se arma con la API de ifcopenshell en vez de guardar un archivo en el repo:
asi se ve de un vistazo que geometria lleva y por que da lo que da.
"""
import sys
from pathlib import Path

import numpy as np
import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

# En 0.8 los submodulos de la API no se cargan solos con `import ifcopenshell.api`.
import ifcopenshell.api  # noqa: E402
import ifcopenshell.api.aggregate  # noqa: E402,F401
import ifcopenshell.api.context  # noqa: E402,F401
import ifcopenshell.api.geometry  # noqa: E402,F401
import ifcopenshell.api.project  # noqa: E402,F401
import ifcopenshell.api.root  # noqa: E402,F401
import ifcopenshell.api.spatial  # noqa: E402,F401
import ifcopenshell.api.unit  # noqa: E402,F401


def _ifc_minimo(ruta: Path) -> Path:
    api = ifcopenshell.api
    f = api.project.create_file(version="IFC4")
    proyecto = api.root.create_entity(f, ifc_class="IfcProject", name="Prueba")
    api.unit.assign_unit(f)
    modelo = api.context.add_context(f, context_type="Model")
    cuerpo = api.context.add_context(f, context_type="Model", context_identifier="Body",
                                     target_view="MODEL_VIEW", parent=modelo)
    sitio = api.root.create_entity(f, ifc_class="IfcSite", name="Sitio")
    api.aggregate.assign_object(f, products=[sitio], relating_object=proyecto)

    ensamble = api.root.create_entity(f, ifc_class="IfcElementAssembly", name="Trabe")
    ensamble.Tag = "SX-T1-1"
    api.spatial.assign_container(f, products=[ensamble], relating_structure=sitio)

    def placa(nombre, largo, fondo, alto, x, y, z):
        # add_wall_representation: rectangulo largo (x) por fondo (y), extruido alto (z)
        p = api.root.create_entity(f, ifc_class="IfcPlate", name=nombre)
        rep = api.geometry.add_wall_representation(f, context=cuerpo, length=largo, height=alto, thickness=fondo)
        api.geometry.assign_representation(f, product=p, representation=rep)
        m = np.eye(4)
        m[:3, 3] = (x, y, z)
        api.geometry.edit_object_placement(f, product=p, matrix=m)
        return p

    base = placa("PL12", 0.400, 0.400, 0.012, -0.200, -0.200, -0.012)
    alma = placa("PL8", 0.008, 0.300, 0.150, -0.004, -0.150, 0.0)
    api.aggregate.assign_object(f, products=[base, alma], relating_object=ensamble)

    f.write(str(ruta))
    return ruta


@pytest.fixture(scope="session")
def ifc_minimo(tmp_path_factory) -> Path:
    return _ifc_minimo(tmp_path_factory.mktemp("ifc") / "minimo.ifc")
