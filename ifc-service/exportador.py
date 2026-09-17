"""
Exporta cada marca de un IFC a su propio glb y su ficha, abriendo el IFC una sola vez.

Es build_all_marks.py de demo3d sin rutas fijas ni argumentos de consola: lo
llama el servicio con el archivo que subio Laravel y una carpeta de salida.
Malla UNA instancia representativa de cada marca (no las 45 mil piezas del
modelo) y detecta sus cordones con welds.py.

Salida:
    <salida>/index.json           indice de marcas, con la version de welds
    <salida>/marks/<MARCA>.glb    un mesh por pieza, ya barrenado y centrado
    <salida>/marks/<MARCA>.json   ficha de piezas (con sus orificios), cordones
                                  y juntas
"""
import json
import re
from collections import defaultdict
from pathlib import Path
from typing import Callable, Optional

import numpy as np
import trimesh
import ifcopenshell
import ifcopenshell.geom as geom
import ifcopenshell.util.element as ue

from holes import cut_holes, find_holes, load_bolts, mover, publicar
from welds import WELDS_VERSION, agrupar_juntas, detect_welds

DENSIDAD = 7850.0
RE_ID = re.compile(r"^ID[0-9a-f-]{30,}$", re.I)


def type_name(elem):
    for rel in getattr(elem, "IsTypedBy", []) or []:
        if rel.RelatingType and rel.RelatingType.Name:
            return rel.RelatingType.Name
    return None


def material_name(elem):
    try:
        m = ue.get_material(elem, should_inherit=True)
    except Exception:
        return None
    if m is None:
        return None
    if m.is_a("IfcMaterial"):
        return m.Name
    if hasattr(m, "Materials") and m.Materials:
        return m.Materials[0].Name
    if hasattr(m, "ForLayerSet") and m.ForLayerSet:
        for capa in m.ForLayerSet.MaterialLayers or []:
            if capa.Material:
                return capa.Material.Name
    return None


def safe_name(mark):
    return re.sub(r"[^A-Za-z0-9_.-]", "_", mark)


def exportar_marcas(ifc, salida, *, welds=True, only=None, bolts=False, completo=True,
                    barrenos=True,
                    progreso: Optional[Callable[[int, int], None]] = None) -> dict:
    """
    ifc:      ruta del .ifc
    salida:   carpeta donde se escriben index.json, marks/ y modelo.glb
    welds:    calcular los cordones (lo lento)
    only:     prefijo de marca, para exportar solo algunas
    bolts:    incluir los pernos como piezas
    barrenos: cortar los orificios en la malla (salen de los tornillos del IFC)
    completo: escribir ademas modelo.glb con la estructura entera (todas las
              instancias, sin cordones), para verla de un vistazo
    progreso: se llama con (marcas_hechas, marcas_total) conforme avanza
    devuelve: el contenido de index.json
    """
    ifc, salida = Path(ifc), Path(salida)
    marks = salida / "marks"
    marks.mkdir(parents=True, exist_ok=True)

    model = ifcopenshell.open(str(ifc))

    # 1) una instancia representativa por marca
    asm_by_mark = defaultdict(list)
    for a in model.by_type("IfcElementAssembly"):
        if a.Tag and a.Tag.strip():
            asm_by_mark[a.Tag.strip().upper()].append(a)

    marcas = sorted(asm_by_mark)
    if only:
        marcas = [m for m in marcas if m.startswith(only.strip().upper())]
    if progreso:
        progreso(0, len(marcas))

    parts, info_by_guid, mark_by_guid = [], {}, {}
    for mark in marcas:
        rep = asm_by_mark[mark][0]
        for rel in getattr(rep, "IsDecomposedBy", []) or []:
            for c in rel.RelatedObjects:
                if not bolts and c.is_a("IfcMechanicalFastener"):
                    continue
                parts.append(c)
                mark_by_guid[c.GlobalId] = mark
                info_by_guid[c.GlobalId] = {
                    "guid": c.GlobalId,
                    "marca_pieza": "" if RE_ID.match(c.Tag or "") else (c.Tag or ""),
                    "clase": c.is_a(),
                    "nombre": c.Name or "",
                    "perfil": type_name(c) or c.Description or "",
                    "material": material_name(c) or "",
                    "desc": c.Description or "",
                }

    # 2) mallado en una sola pasada. Sin piezas no se llama al iterador: con
    # include vacio mallaria el modelo entero.
    meshes_by_mark = defaultdict(dict)          # mark -> {guid: Trimesh}
    if parts:
        settings = geom.settings()
        settings.set(settings.USE_WORLD_COORDS, True)
        it = geom.iterator(settings, model, include=parts)
        if it.initialize():
            while True:
                shape = it.get()
                g = shape.geometry
                V = np.asarray(g.verts, dtype=np.float64).reshape(-1, 3)   # ya en metros
                F = np.asarray(g.faces, dtype=np.uint32).reshape(-1, 3)
                mark = mark_by_guid.get(shape.guid)
                if mark and len(V) and len(F):
                    meshes_by_mark[mark][shape.guid] = trimesh.Trimesh(vertices=V, faces=F, process=False)
                if not it.next():
                    break

    # 3) un archivo por marca. Cada marca queda en disco en cuanto termina, y
    # el index.json se reescribe con lo que va: quien lo lea a media conversion
    # ve las marcas listas (`completo` dice si ya estan todas). Una marca que ya
    # tiene su .glb y su .json de una corrida anterior no se vuelve a calcular:
    # asi una conversion interrumpida se retoma donde iba.
    # Los orificios no vienen en el IFC: salen de los tornillos (ver holes.py).
    # Se leen una sola vez para todo el modelo.
    tornillos = load_bolts(model, verbose=False) if barrenos else []

    index = {}
    for k, mark in enumerate(marcas, 1):
        safe = safe_name(mark)
        if (marks / f"{safe}.glb").is_file() and (marks / f"{safe}.json").is_file():
            index[mark] = entrada_de_ficha(json.loads((marks / f"{safe}.json").read_text(encoding="utf-8")), safe)
        else:
            piezas_mesh = meshes_by_mark.get(mark) or {}
            if piezas_mesh:
                index[mark] = _exportar_una(mark, asm_by_mark[mark], piezas_mesh, info_by_guid, marks, welds, bolts, tornillos)
        _escribir_index(salida, index, welds, completo=(k == len(marcas)))
        if progreso:
            progreso(k, len(marcas))

    modelo = None
    if completo and not only and marcas:
        modelo = _exportar_modelo_completo(model, asm_by_mark, marcas, bolts, salida)

    return _escribir_index(salida, index, welds, completo=True, modelo=modelo)


def _exportar_modelo_completo(model, asm_by_mark, marcas, bolts, salida):
    """
    La estructura entera en un solo .glb: todas las instancias de todas las
    marcas, en coordenadas de obra, sin cordones. Cada malla lleva por nombre
    la marca y el numero de instancia, para poder resaltar una marca desde el
    visor. Si un modelo no tiene nada que mallar, no se escribe.
    """
    partes, marca_de = [], {}
    for mark in marcas:
        for n, ensamble in enumerate(asm_by_mark[mark], 1):
            for rel in getattr(ensamble, "IsDecomposedBy", []) or []:
                for c in rel.RelatedObjects:
                    if not bolts and c.is_a("IfcMechanicalFastener"):
                        continue
                    partes.append(c)
                    marca_de[c.GlobalId] = (mark, n)
    if not partes:
        return None

    settings = geom.settings()
    settings.set(settings.USE_WORLD_COORDS, True)
    it = geom.iterator(settings, model, include=partes)
    if not it.initialize():
        return None

    scene, k = trimesh.Scene(), 0
    while True:
        shape = it.get()
        g = shape.geometry
        V = np.asarray(g.verts, dtype=np.float64).reshape(-1, 3)
        F = np.asarray(g.faces, dtype=np.uint32).reshape(-1, 3)
        if shape.guid in marca_de and len(V) and len(F):
            mark, n = marca_de[shape.guid]
            k += 1
            scene.add_geometry(trimesh.Trimesh(vertices=V, faces=F, process=False),
                               node_name=f"{safe_name(mark)}#{n}#{k}", geom_name=f"{safe_name(mark)}#{n}#{k}")
        if not it.next():
            break
    if scene.is_empty:
        return None
    scene.export(salida / "modelo.glb")
    return "modelo.glb"


def _escribir_index(salida, index, welds, *, completo, modelo=None):
    resultado = {
        "completo": completo,
        "modelo": modelo,
        "welds_version": WELDS_VERSION if welds else None,
        "marcas": index,
        "totales": {
            "marcas": len(index),
            "juntas": sum(m.get("juntas") or 0 for m in index.values()),
            "soldaduras": sum(m["soldaduras"] for m in index.values()),
            "soldadura_mm": round(sum(m["soldadura_mm"] for m in index.values()), 1),
            "orificios": sum(m.get("orificios") or 0 for m in index.values()),
        },
    }
    tmp = salida / "index.json.tmp"
    tmp.write_text(json.dumps(resultado, ensure_ascii=False, indent=1), encoding="utf-8")
    tmp.replace(salida / "index.json")
    return resultado


def entrada_de_ficha(ficha, safe):
    """La linea del index.json de una marca, sacada de su ficha ya escrita."""
    return {
        "file": safe,
        "nombre": ficha.get("nombre", ""),
        "piezas": len(ficha.get("piezas", [])),
        "peso_kg": ficha["totales"]["peso_kg"],
        "ensambles": ficha.get("ensambles_en_modelo", 1),
        "juntas": len(ficha.get("juntas", [])),
        "soldaduras": len(ficha.get("soldaduras", [])),
        "soldadura_mm": ficha["totales"]["soldadura_mm"],
        "orificios": ficha["totales"].get("orificios", 0),
        "bbox_mm": ficha["totales"]["bbox_mm"],
    }


def _exportar_una(mark, ensambles, piezas_mesh, info_by_guid, marks, welds, bolts, tornillos=()):
    allv = np.concatenate([m.vertices for m in piezas_mesh.values()], axis=0)
    lo, hi = allv.min(axis=0), allv.max(axis=0)
    origen = np.array([(lo[0] + hi[0]) / 2, (lo[1] + hi[1]) / 2, lo[2]])

    # Los orificios se buscan en coordenadas de obra, antes de centrar la marca.
    orificios = find_holes(tornillos, {g: m for g, m in piezas_mesh.items()
                                       if info_by_guid[g]["clase"] != "IfcMechanicalFastener"}) if tornillos else {}

    scene, piezas, centradas = trimesh.Scene(), [], {}
    for i, (guid, mesh) in enumerate(piezas_mesh.items()):
        mesh.vertices -= origen
        node = f"P{i:03d}"
        # Los cordones se calculan sobre la pieza maciza: el barreno no es
        # rincon soldable, y una malla cortada ensucia los parches planos.
        centradas[node] = mesh
        hs = orificios.get(guid, [])
        mesh, cortados = cut_holes(mesh, mover(hs, origen))
        scene.add_geometry(mesh, node_name=node, geom_name=node)
        dims = (mesh.bounds[1] - mesh.bounds[0]) * 1000.0
        try:
            vol = abs(float(mesh.volume))
        except Exception:
            vol = 0.0
        d = dict(info_by_guid[guid])
        d.update({
            "node": node,
            "bbox_mm": [round(float(x), 1) for x in dims],
            "largo_mm": round(float(max(dims)), 1),
            "peso_kg": round(vol * DENSIDAD, 2),
            "centro": [round(float(x), 4) for x in mesh.bounds.mean(axis=0)],
            "orificios": publicar(hs, origen),
            "orificios_cortados": cortados,
        })
        piezas.append(d)
    piezas.sort(key=lambda p: (-p["peso_kg"], p["node"]))

    solds = detect_welds(centradas, verbose=False) if welds else []
    # Lo que calidad cuenta como UNA soldadura: los cordones quedan como tramos
    # de su junta, con "junta_id" y "remate" puestos por agrupar_juntas.
    juntas = agrupar_juntas(solds)

    safe = safe_name(mark)
    scene.export(marks / f"{safe}.glb")
    ficha = {
        "marca": mark,
        "nombre": ensambles[0].Name or "",
        "ensambles_en_modelo": len(ensambles),
        "instancias_exportadas": 1,
        "incluye_pernos": bool(bolts),
        "welds_version": WELDS_VERSION if welds else None,
        "piezas": piezas,
        "soldaduras": solds,
        "juntas": juntas,
        "totales": {
            "piezas": len(piezas),
            "peso_kg": round(sum(p["peso_kg"] for p in piezas), 2),
            "bbox_mm": [round(float(x), 1) for x in (hi - lo) * 1000.0],
            "juntas": len(juntas),
            "soldaduras": len(solds),
            "soldadura_mm": round(sum(w["largo_mm"] for w in solds), 1),
            "orificios": sum(len(p["orificios"]) for p in piezas),
        },
    }
    # El json va al final y por un temporal: si el proceso muere a medio
    # escribir, no queda una marca "lista" con la ficha truncada.
    tmp = marks / f"{safe}.json.tmp"
    tmp.write_text(json.dumps(ficha, ensure_ascii=False), encoding="utf-8")
    tmp.replace(marks / f"{safe}.json")

    return entrada_de_ficha(ficha, safe)
