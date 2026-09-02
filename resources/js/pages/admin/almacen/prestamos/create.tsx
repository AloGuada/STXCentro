import { CapturadorPiezasPrestamo, PIEZA_VACIA, piezasSinCondicion } from '@/components/alm/capturador-piezas-prestamo';
import { FechasMovimiento } from '@/components/alm/fechas-movimiento';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { activosPrestables, ALMACENES_DEMO, GRUPOS_TRABAJO_DEMO, USUARIOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmPrestamoPiezaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { InfoIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Préstamos', href: '/admin/almacen/prestamos' },
    { title: 'Nuevo', href: '/admin/almacen/prestamos/create' },
];

const OBRAS_DEMO = [
    { id: 1, etiqueta: 'T4 — Torre 4' },
    { id: 2, etiqueta: 'MBP — Museo Bellas Artes' },
];

type Destino = 'obra' | 'planta';

export default function PrestamoCreate() {
    const [almacenId, setAlmacenId] = useState('');
    const [responsable, setResponsable] = useState('');
    const [destino, setDestino] = useState<Destino>('obra');
    const [obra, setObra] = useState('');
    const [grupoTrabajo, setGrupoTrabajo] = useState('');
    const [fechaSalida, setFechaSalida] = useState('');
    const [fechaRetorno, setFechaRetorno] = useState('');
    const [sinRetorno, setSinRetorno] = useState(false);
    const [observaciones, setObservaciones] = useState('');
    const [piezas, setPiezas] = useState<AlmPrestamoPiezaBorrador[]>([{ ...PIEZA_VACIA }]);

    const claveAlmacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId)?.clave;
    const prestables = activosPrestables(claveAlmacen);

    const grupo = GRUPOS_TRABAJO_DEMO.find((g) => String(g.id) === grupoTrabajo);

    const elegidas = piezas.filter((p) => p.activo_id !== '');
    const sinCondicion = piezasSinCondicion(piezas).length;

    /** Cambiar de almacén invalida las piezas: ya no salen de ahí. */
    const elegirAlmacen = (valor: string) => {
        setAlmacenId(valor);
        setPiezas([{ ...PIEZA_VACIA }]);
    };

    /** El destino que se descarta no deja su valor puesto. */
    const elegirDestino = (valor: Destino) => {
        setDestino(valor);
        setObra('');
        setGrupoTrabajo('');
    };

    /**
     * Herramienta que se queda con la cuadrilla hasta que termine la obra: se
     * presta sin fecha. Se limpia la que hubiera para no guardar un plazo que
     * ya no aplica.
     */
    const elegirSinRetorno = (valor: boolean) => {
        setSinRetorno(valor);

        if (valor) {
            setFechaRetorno('');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Prestar herramienta" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Prestar herramienta</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Se prestan piezas concretas, con su número de serie, a una persona que responde por todas. Cada
                        pieza se va con su propio folio, así se puede devolver por separado.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Almacén" htmlFor="almacen" required>
                                <Select
                                    id="almacen"
                                    value={almacenId}
                                    onValueChange={elegirAlmacen}
                                    placeholder="¿De dónde sale?"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Quién responde"
                                htmlFor="responsable"
                                description="A esta persona se le reclaman todas las piezas del vale."
                                required
                            >
                                <Select
                                    id="responsable"
                                    value={responsable}
                                    onValueChange={setResponsable}
                                    placeholder="¿Quién se las lleva?"
                                >
                                    {USUARIOS_DEMO.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {u.nombre} — {u.puesto}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Observaciones"
                                htmlFor="observaciones"
                                description="Lo que aplica a todo el vale; lo de cada pieza va en su renglón."
                            >
                                <Input
                                    id="observaciones"
                                    value={observaciones}
                                    onChange={(e) => setObservaciones(e.target.value)}
                                    placeholder="Se van juntas en la camioneta..."
                                />
                            </FormField>

                            <FechasMovimiento
                                fecha={fechaSalida}
                                onChange={setFechaSalida}
                                label="Salida"
                                id="fecha_salida"
                                descripcion="Cuándo se llevó las piezas. Puede ser antes de hoy, nunca después."
                            />

                            <FormField
                                label="Deben volver"
                                htmlFor="fecha_retorno"
                                description={
                                    sinRetorno
                                        ? 'Préstamo abierto: nunca aparecerá como vencido, sólo como afuera.'
                                        : 'Sin fecha no hay forma de saber qué está vencido.'
                                }
                                required={!sinRetorno}
                            >
                                <div className="space-y-2">
                                    <Input
                                        id="fecha_retorno"
                                        type="date"
                                        value={fechaRetorno}
                                        onChange={(e) => setFechaRetorno(e.target.value)}
                                        disabled={sinRetorno}
                                    />
                                    <label className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            className="checkbox checkbox-sm"
                                            checked={sinRetorno}
                                            onChange={(e) => elegirSinRetorno(e.target.checked)}
                                        />
                                        <span className="text-sm">Sin fecha de retorno</span>
                                    </label>
                                </div>
                            </FormField>
                        </div>

                        <fieldset className="mt-4">
                            <legend className="mb-2 text-sm font-medium">¿A dónde se las lleva?</legend>
                            <div className="flex flex-wrap gap-4">
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="destino"
                                        className="radio radio-sm"
                                        checked={destino === 'obra'}
                                        onChange={() => elegirDestino('obra')}
                                    />
                                    <span className="text-sm">A una obra</span>
                                </label>
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="destino"
                                        className="radio radio-sm"
                                        checked={destino === 'planta'}
                                        onChange={() => elegirDestino('planta')}
                                    />
                                    <span className="text-sm">Se quedan en planta</span>
                                </label>
                            </div>
                        </fieldset>

                        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                            {destino === 'obra' ? (
                                <FormField label="Obra" htmlFor="obra" required>
                                    <Select id="obra" value={obra} onValueChange={setObra} placeholder="¿A qué obra?">
                                        {OBRAS_DEMO.map((o) => (
                                            <SelectItem key={o.id} value={String(o.id)}>
                                                {o.etiqueta}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            ) : (
                                <FormField
                                    label="Grupo de trabajo"
                                    htmlFor="grupo_trabajo"
                                    description={
                                        grupo
                                            ? `${grupo.empleados} empleados · ${grupo.ubicaciones.join(', ')}`
                                            : 'Las cuadrillas vienen de Producción; el almacén no las da de alta.'
                                    }
                                    className="md:col-span-2"
                                    required
                                >
                                    <Select
                                        id="grupo_trabajo"
                                        value={grupoTrabajo}
                                        onValueChange={setGrupoTrabajo}
                                        placeholder="¿Qué cuadrilla las usa?"
                                    >
                                        {GRUPOS_TRABAJO_DEMO.map((g) => (
                                            <SelectItem key={g.id} value={String(g.id)}>
                                                {g.descripcion}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            )}
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Piezas</h2>
                        {!claveAlmacen && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el almacén para ver qué piezas están disponibles para prestar.
                            </p>
                        )}
                        {claveAlmacen && prestables.length === 0 && (
                            <div className="alert alert-warning mb-3">
                                <TriangleAlertIcon className="size-5" />
                                <span>
                                    Este almacén no tiene ninguna pieza disponible: están prestadas o en reparación.
                                </span>
                            </div>
                        )}
                        <CapturadorPiezasPrestamo
                            piezas={piezas}
                            onChange={setPiezas}
                            prestables={prestables}
                            almacenElegido={claveAlmacen !== undefined}
                        />

                        {sinCondicion > 0 && (
                            <div className="alert alert-warning mt-3">
                                <TriangleAlertIcon className="size-5" />
                                <span>
                                    {sinCondicion === 1
                                        ? 'Una pieza no dice en qué condición sale.'
                                        : `${sinCondicion} piezas no dicen en qué condición salen.`}
                                </span>
                            </div>
                        )}

                        {elegidas.length > 0 && (
                            <div className="alert alert-info mt-3">
                                <InfoIcon className="size-5" />
                                <span>
                                    Se registran <strong>{elegidas.length}</strong>{' '}
                                    {elegidas.length === 1 ? 'resguardo' : 'resguardos'}, uno por pieza. Nada de esto
                                    baja la existencia: las piezas siguen siendo del almacén {claveAlmacen} y sólo dejan
                                    de estar disponibles.
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/prestamos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled title="La maqueta todavía no guarda">
                            {elegidas.length > 1 ? `Registrar ${elegidas.length} préstamos` : 'Registrar préstamo'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
