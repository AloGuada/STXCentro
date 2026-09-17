# ifc-service

Servicio aparte que convierte el IFC de una obra en una marca por archivo, con
sus cordones de soldadura detectados por geometría. Lo consume Calidad
(`App\Services\Qal\Ifc\IfcClient`) para los modelos 3D.

Corre aparte de Laravel, como el OCR de RH: procesar un IFC tarda minutos y no
cabe en una petición web. Laravel sube el archivo desde un job de la cola
`ifc`, pregunta por el estado cada tanto y se va trayendo cada marca (su .glb,
su plantilla .json y sus cordones) en cuanto el servicio la escribe; al final
baja `modelo.glb`, la estructura entera sin cordones.

## Qué hace

- `exportador.py`: `build_all_marks.py` de demo3d sin rutas fijas. Malla una
  instancia por marca y escribe `index.json` y `marks/<MARCA>.glb|json`.
- `welds.py`: copia literal de `demo3d/welds.py` más `WELDS_VERSION`. Tekla no
  exporta soldaduras, así que se deducen de la geometría. **Si se cambia el
  algoritmo, se sube `WELDS_VERSION`**: cada modelo guarda con qué versión se
  calcularon sus cordones.
- `trabajos.py`: los trabajos, de uno en uno. El estado de cada uno vive en
  su carpeta (`estado.json`) y al arrancar el servicio retoma los que quedaron
  a medias sin repetir las marcas ya escritas.
- `main.py`: la API.

## Instalación (Windows)

Python 3.12 (ifcopenshell no tiene ruedas para 3.14):

```
cd ifc-service
py -3.12 -m venv .venv
.venv\Scripts\pip install -r requirements.txt
```

Si el Python del sistema ya trae ifcopenshell, trimesh y shapely, se puede
crear el venv con `--system-site-packages` e instalar sólo lo que falte.

## Arranque

```
.venv\Scripts\uvicorn main:app --port 8011
```

En Laravel (`.env`): `IFC_SERVICE_URL=http://127.0.0.1:8011`, y un worker de la
cola: `php artisan queue:work --queue=ifc`.

## Pruebas

```
.venv\Scripts\python -m pytest
```

## API

| Método | Ruta | Respuesta |
|---|---|---|
| POST | `/procesar` | multipart `archivo` (.ifc), `welds`, `only` → 202 `{id}` |
| GET | `/estado/{id}` | `{estado, progreso{marcas_hechas, marcas_total}, welds_version, error}` |
| GET | `/resultado/{id}/marcas` | el `index.json` tal como va (`completo` cuando están todas) |
| GET | `/resultado/{id}/marcas/{archivo}` | el `.glb` o `.json` de una marca ya escrita |
| GET | `/resultado/{id}/modelo` | `modelo.glb`, la estructura entera sin cordones (404 hasta terminar) |
| GET | `/resultado/{id}` | zip con `index.json` y `marks/*` (409 si no está listo) |
| DELETE | `/trabajos/{id}` | 204 |
| GET | `/salud` | `{ok, welds_version}` |

Los trabajos viven en `%TEMP%/ifc-service/<id>/` (o en `IFC_SERVICE_RAIZ`).
Laravel borra la carpeta al terminar de importar; si el servicio se reinicia a
media conversión, la retoma solo al arrancar.
