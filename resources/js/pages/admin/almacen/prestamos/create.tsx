import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { activosPrestables, ALMACENES_DEMO, DEPARTAMENTOS_DEMO, USUARIOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { InfoIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
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
    const [activoId, setActivoId] = useState('');
    const [responsable, setResponsable] = useState('');
    const [destino, setDestino] = useState<Destino>('obra');
    const [obra, setObra] = useState('');
    const [departamento, setDepartamento] = useState('');
    const [fechaSalida, setFechaSalida] = useState('');
    const [fechaRetorno, setFechaRetorno] = useState('');
    const [condicion, setCondicion] = useState('');
    const [observaciones, setObservaciones] = useState('');

    const claveAlmacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId)?.clave;
    const prestables = activosPrestables(claveAlmacen);
    const activo = prestables.find((a) => String(a.id) === activoId);

    /** Cambiar de pañol invalida la pieza: ya no es de ese almacén. */
    const elegirAlmacen = (valor: string) => {
        setAlmacenId(valor);
        setActivoId('');
        setCondicion('');
    };

    /** La condición arranca con la que trae registrada la pieza. */
    const elegirActivo = (valor: string) => {
        setActivoId(valor);
        setCondicion(prestables.find((a) => String(a.id) === valor)?.condicion ?? '');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Prestar herramienta" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Prestar herramienta</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Se presta una pieza concreta, con su número de serie, a una persona que responde por ella.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Pañol" htmlFor="almacen" required>
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
                                label="Pieza"
                                htmlFor="activo"
                                description={
                                    claveAlmacen
                                        ? 'Sólo aparecen las que están en el pañol y disponibles.'
                                        : 'Elige primero el pañol.'
                                }
                                className="md:col-span-2"
                                required
                            >
                                <Select
                                    id="activo"
                                    value={activoId}
                                    onValueChange={elegirActivo}
                                    placeholder={
                                        claveAlmacen && prestables.length === 0
                                            ? 'No hay piezas disponibles en este pañol'
                                            : 'Selecciona la pieza'
                                    }
                                    disabled={!claveAlmacen || prestables.length === 0}
                                >
                                    {prestables.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.no_serie} — {a.descripcion}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Quién responde"
                                htmlFor="responsable"
                                description="A esta persona se le reclama si no vuelve."
                                required
                            >
                                <Select
                                    id="responsable"
                                    value={responsable}
                                    onValueChange={setResponsable}
                                    placeholder="¿Quién se la lleva?"
                                >
                                    {USUARIOS_DEMO.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {u.nombre} — {u.puesto}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Salida" htmlFor="fecha_salida" required>
                                <Input
                                    id="fecha_salida"
                                    type="date"
                                    value={fechaSalida}
                                    onChange={(e) => setFechaSalida(e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Debe volver"
                                htmlFor="fecha_retorno"
                                description="Sin fecha no hay forma de saber qué está vencido."
                                required
                            >
                                <Input
                                    id="fecha_retorno"
                                    type="date"
                                    value={fechaRetorno}
                                    onChange={(e) => setFechaRetorno(e.target.value)}
                                />
                            </FormField>
                        </div>

                        <fieldset className="mt-4">
                            <legend className="mb-2 text-sm font-medium">¿A dónde se la lleva?</legend>
                            <div className="flex flex-wrap gap-4">
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="destino"
                                        className="radio radio-sm"
                                        checked={destino === 'obra'}
                                        onChange={() => setDestino('obra')}
                                    />
                                    <span className="text-sm">A una obra</span>
                                </label>
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="destino"
                                        className="radio radio-sm"
                                        checked={destino === 'planta'}
                                        onChange={() => setDestino('planta')}
                                    />
                                    <span className="text-sm">Se queda en planta</span>
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
                                <FormField label="Área" htmlFor="departamento" required>
                                    <Select
                                        id="departamento"
                                        value={departamento}
                                        onValueChange={setDepartamento}
                                        placeholder="¿Qué área la usa?"
                                    >
                                        {DEPARTAMENTOS_DEMO.map((d) => (
                                            <SelectItem key={d.id} value={String(d.id)}>
                                                {d.nombre}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            )}

                            <FormField
                                label="Condición de salida"
                                htmlFor="condicion"
                                description="Contra esto se compara cuando vuelva."
                                required
                            >
                                <Input
                                    id="condicion"
                                    value={condicion}
                                    onChange={(e) => setCondicion(e.target.value)}
                                    placeholder="Buena, disco gastado, sin guarda..."
                                />
                            </FormField>

                            <FormField label="Observaciones" htmlFor="observaciones">
                                <Input
                                    id="observaciones"
                                    value={observaciones}
                                    onChange={(e) => setObservaciones(e.target.value)}
                                    placeholder="Con extensión, en maletín..."
                                />
                            </FormField>
                        </div>

                        {activo && (
                            <div className="alert alert-info mt-4">
                                <InfoIcon className="size-5" />
                                <span>
                                    Prestar <strong>{activo.no_serie}</strong> no baja la existencia de{' '}
                                    {activo.codigo}: la pieza sigue siendo del pañol {activo.almacen} y sólo deja de
                                    estar disponible.
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/prestamos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled title="La maqueta todavía no guarda">
                            Registrar préstamo
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
