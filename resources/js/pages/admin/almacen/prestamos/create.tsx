import { Head, Link, router, useForm } from '@inertiajs/react';
import { InfoIcon, PlusIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Préstamos', href: '/admin/almacen/prestamos' },
    { title: 'Nuevo', href: '/admin/almacen/prestamos/create' },
];

const hoy = () => new Date().toISOString().slice(0, 10);
const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/** Una pieza con serie que hoy está disponible en el almacén elegido. */
type Pieza = {
    id: number;
    articulo_id: number;
    codigo: string | null;
    descripcion: string | null;
    no_serie: string;
    marca: string | null;
    modelo: string | null;
    condicion: string | null;
    ubicacion: string | null;
};

/** Un activo por cantidad con algo libre de resguardo en el almacén elegido. */
type PorCantidad = {
    articulo_id: number;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    cantidad: number;
    prestado: number;
    disponible: number;
};

type Prestables = { piezas: Pieza[]; por_cantidad: PorCantidad[] };

/**
 * Un renglón del vale. Con `activo_id` es una pieza; sin él es un activo por
 * cantidad y `cantidad` dice cuántas. `clave` identifica la opción elegida en
 * el buscador: `p:<activo_id>` o `c:<articulo_id>`.
 */
type Renglon = {
    clave: string;
    articulo_id: string;
    activo_id: string;
    cantidad: string;
    condicion_salida: string;
    observaciones: string;
    /** Amarre al renglón del pedido que surte; vacío en el préstamo directo. */
    pedido_detalle_id: string;
};

const RENGLON_VACIO: Renglon = {
    clave: '',
    articulo_id: '',
    activo_id: '',
    cantidad: '',
    condicion_salida: '',
    observaciones: '',
    pedido_detalle_id: '',
};

/** Un pedido que debe herramienta, con lo que le falta de cada activo. */
type PedidoSurtible = {
    id: number;
    folio: string | null;
    almacen_id: number;
    departamento: string | null;
    obra_id: number | null;
    grupo_trabajo_id: number | null;
    solicitante_id: string | null;
    /** El supervisor a cuyo nombre quedó el pedido: responde él por lo prestado. */
    solicitante: string | null;
    recibe: string | null;
    fecha_requerida: string | null;
    detalles: {
        id: number;
        articulo_id: number;
        codigo: string | null;
        descripcion: string | null;
        unidad: string | null;
        por_pieza: boolean;
        pendiente: number;
    }[];
};

type Destino = 'obra' | 'grupo' | 'planta';

/** El destino que dice el pedido, o el que se elige a mano. */
function destinoDe(pedido: PedidoSurtible | undefined): Destino {
    if (pedido?.obra_id) return 'obra';
    if (pedido?.grupo_trabajo_id) return 'grupo';
    return pedido ? 'planta' : 'obra';
}

/**
 * Los renglones con que arranca un préstamo que surte un pedido. El activo por
 * cantidad ya viene elegido con lo que falta; la pieza con serie viene
 * amarrada al artículo y espera a que se elija cuál.
 */
function renglonesDe(pedido: PedidoSurtible): Renglon[] {
    return pedido.detalles.map((d) => ({
        ...RENGLON_VACIO,
        clave: d.por_pieza ? '' : `c:${d.articulo_id}`,
        articulo_id: String(d.articulo_id),
        cantidad: d.por_pieza ? '1' : String(d.pendiente),
        pedido_detalle_id: String(d.id),
    }));
}

type Props = {
    almacenes: AlmAlmacenOpcion[];
    /** Quien puede responder por el vale: tiene `alm.pedidos.supervisar`. */
    supervisores: { id: string; name: string }[];
    usuarios: { id: string; name: string }[];
    obras: { id: number; no: string; descripcion: string | null }[];
    gruposTrabajo: { id: number; descripcion: string }[];
    pedidosSurtibles: PedidoSurtible[];
    pedidoSeleccionado: number | null;
};

/**
 * Prestar activos: piezas concretas con su serie, o N de un activo sin serie,
 * a una persona que responde por todo el vale.
 *
 * Nada de esto baja la existencia: lo prestado sigue siendo del almacén y sólo
 * deja de estar disponible.
 */
export default function PrestamoCreate({
    almacenes,
    supervisores,
    usuarios,
    obras,
    gruposTrabajo,
    pedidosSurtibles,
    pedidoSeleccionado,
}: Props) {
    // Si llegan desde el pedido, el almacén viene en la URL y el pedido se
    // precarga en cuanto la pantalla lo tiene.
    const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
    const pedidoInicial = pedidoSeleccionado === null ? undefined : pedidosSurtibles.find((p) => p.id === pedidoSeleccionado);

    const form = useForm({
        almacen_id: pedidoInicial ? String(pedidoInicial.almacen_id) : (params.get('almacen_id') ?? ''),
        pedido_id: pedidoInicial ? String(pedidoInicial.id) : '',
        responsable_id: pedidoInicial?.solicitante_id ?? '',
        obra_id: '',
        grupo_trabajo_id: '',
        fecha_salida: hoy(),
        fecha_retorno_esperada: '',
        autorizado_por: '',
        observaciones: '',
        renglones: (pedidoInicial ? renglonesDe(pedidoInicial) : [{ ...RENGLON_VACIO }]) as Renglon[],
    });

    const [destino, setDestino] = useState<Destino>(destinoDe(pedidoInicial));
    const [sinRetorno, setSinRetorno] = useState(false);
    const [prestables, setPrestables] = useState<Prestables>({ piezas: [], por_cantidad: [] });

    // Lo prestable se pide al elegir el almacén: no se sabe cuál hasta que lo
    // eligen, y cambiarlo tira los renglones porque ya no salen de ahí.
    useEffect(() => {
        if (form.data.almacen_id === '') {
            return;
        }

        let vigente = true;

        fetch(`/admin/almacen/prestamos/prestables/${form.data.almacen_id}`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : { piezas: [], por_cantidad: [] }))
            .then((datos: Prestables) => {
                if (vigente) {
                    setPrestables(datos);
                }
            })
            .catch(() => setPrestables({ piezas: [], por_cantidad: [] }));

        return () => {
            vigente = false;
        };
    }, [form.data.almacen_id]);

    const elegirAlmacen = (valor: string) => {
        setPrestables({ piezas: [], por_cantidad: [] });
        form.setData((d) => ({ ...d, almacen_id: valor, pedido_id: '', renglones: [{ ...RENGLON_VACIO }] }));
        // Los pedidos surtibles son de un almacén: se vuelven a pedir al servidor.
        router.get('/admin/almacen/prestamos/create', { almacen_id: valor }, { preserveState: true, replace: true, only: ['pedidosSurtibles'] });
    };

    /**
     * Al elegir un pedido, el préstamo arranca con lo que le falta de
     * herramienta: responsable, destino y renglones. Las piezas con serie
     * quedan amarradas al artículo pero sin serie: eso lo elige el almacenista,
     * que es quien sabe cuál pulidora se va.
     */
    const cargarPedido = (id: string) => {
        const elegido = pedidosSurtibles.find((p) => String(p.id) === id);

        if (elegido === undefined) {
            form.setData((d) => ({ ...d, pedido_id: '', responsable_id: '', obra_id: '', grupo_trabajo_id: '', renglones: [{ ...RENGLON_VACIO }] }));
            setDestino('obra');

            return;
        }

        setDestino(destinoDe(elegido));
        form.setData((d) => ({
            ...d,
            pedido_id: id,
            responsable_id: elegido.solicitante_id ?? d.responsable_id,
            obra_id: elegido.obra_id === null ? '' : String(elegido.obra_id),
            grupo_trabajo_id: elegido.grupo_trabajo_id === null ? '' : String(elegido.grupo_trabajo_id),
            observaciones: d.observaciones || (elegido.recibe ? `Recibe ${elegido.recibe}` : ''),
            renglones: renglonesDe(elegido),
        }));
    };

    const pedido = pedidosSurtibles.find((p) => String(p.id) === form.data.pedido_id);

    /** El destino que se descarta no deja su valor puesto. */
    const elegirDestino = (valor: Destino) => {
        setDestino(valor);
        form.setData((d) => ({ ...d, obra_id: '', grupo_trabajo_id: '' }));
    };

    const elegirSinRetorno = (valor: boolean) => {
        setSinRetorno(valor);

        if (valor) {
            form.setData('fecha_retorno_esperada', '');
        }
    };

    const yaElegidas = new Set(form.data.renglones.map((r) => r.clave).filter(Boolean));

    /**
     * Todo lo prestable, en una sola lista para buscar por descripción o serie.
     * Un renglón que viene del pedido sólo ofrece lo de su artículo: el pedido
     * dijo qué, el almacenista dice cuál.
     */
    const opcionesPara = (renglon: Renglon) => [
        ...prestables.piezas
            .filter((p) => renglon.pedido_detalle_id === '' || String(p.articulo_id) === renglon.articulo_id)
            .filter((p) => !yaElegidas.has(`p:${p.id}`) || renglon.clave === `p:${p.id}`)
            .map((p) => ({
                value: `p:${p.id}`,
                label: `${p.no_serie} · ${p.descripcion ?? ''}${p.marca || p.modelo ? ` (${[p.marca, p.modelo].filter(Boolean).join(' ')})` : ''}`,
            })),
        ...prestables.por_cantidad
            .filter((c) => renglon.pedido_detalle_id === '' || String(c.articulo_id) === renglon.articulo_id)
            .filter((c) => !yaElegidas.has(`c:${c.articulo_id}`) || renglon.clave === `c:${c.articulo_id}`)
            .map((c) => ({
                value: `c:${c.articulo_id}`,
                label: `${c.codigo ?? ''} · ${c.descripcion ?? ''} — ${numero(c.disponible)} ${c.unidad ?? ''} libres`,
            })),
    ];

    const piezaDe = (clave: string) => prestables.piezas.find((p) => `p:${p.id}` === clave);
    const cantidadDe = (clave: string) => prestables.por_cantidad.find((c) => `c:${c.articulo_id}` === clave);

    const editar = (i: number, cambio: Partial<Renglon>) =>
        form.setData(
            'renglones',
            form.data.renglones.map((r, k) => (k === i ? { ...r, ...cambio } : r)),
        );

    /** Al elegir, el renglón toma la forma de lo elegido: pieza (cantidad 1) o cantidad. */
    const elegir = (i: number, clave: string) => {
        const pieza = piezaDe(clave);
        const porCantidad = cantidadDe(clave);

        const actual = form.data.renglones[i];

        editar(i, {
            clave,
            articulo_id: pieza ? String(pieza.articulo_id) : porCantidad ? String(porCantidad.articulo_id) : actual.articulo_id,
            activo_id: pieza ? String(pieza.id) : '',
            // Del pedido ya trae la cantidad que falta; en directo se teclea.
            cantidad: pieza ? '1' : actual.pedido_detalle_id !== '' ? actual.cantidad : '',
            condicion_salida: pieza?.condicion ?? '',
        });
    };

    const agregar = () => form.setData('renglones', [...form.data.renglones, { ...RENGLON_VACIO }]);

    const quitar = (i: number) =>
        form.setData(
            'renglones',
            form.data.renglones.length === 1 ? [{ ...RENGLON_VACIO }] : form.data.renglones.filter((_, k) => k !== i),
        );

    const errorDe = (i: number, campo: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`renglones.${i}.${campo}`];

    const elegidos = form.data.renglones.filter((r) => r.clave !== '');
    const nadaPrestable =
        form.data.almacen_id !== '' && prestables.piezas.length === 0 && prestables.por_cantidad.length === 0;

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((d) => ({
            ...d,
            obra_id: d.obra_id || null,
            grupo_trabajo_id: d.grupo_trabajo_id || null,
            fecha_retorno_esperada: d.fecha_retorno_esperada || null,
            autorizado_por: d.autorizado_por || null,
            renglones: d.renglones
                .filter((r) => r.clave !== '')
                .map((r) => ({
                    articulo_id: r.articulo_id,
                    activo_id: r.activo_id || null,
                    cantidad: r.activo_id ? 1 : r.cantidad,
                    condicion_salida: r.condicion_salida || null,
                    observaciones: r.observaciones || null,
                    pedido_detalle_id: r.pedido_detalle_id || null,
                })),
            pedido_id: d.pedido_id || null,
        }));

        form.post('/admin/almacen/prestamos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Prestar" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Prestar activos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Piezas con su serie, o N de un activo sin serie, a una persona que responde por todo el vale.
                        Nada baja la existencia: lo prestado sigue siendo del almacén.
                    </p>
                </div>

                <form onSubmit={enviar} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Almacén" htmlFor="almacen_id" error={form.errors.almacen_id} required>
                                <Select
                                    id="almacen_id"
                                    value={form.data.almacen_id}
                                    onValueChange={elegirAlmacen}
                                    placeholder="¿De dónde sale?"
                                >
                                    {almacenes.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {etiquetaDeAlmacen(a)} — {a.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Surte el pedido"
                                htmlFor="pedido_id"
                                error={form.errors.pedido_id}
                                description="Opcional: sólo salen los pedidos que piden herramienta."
                            >
                                <Select
                                    id="pedido_id"
                                    value={form.data.pedido_id}
                                    onValueChange={cargarPedido}
                                    disabled={form.data.almacen_id === ''}
                                    placeholder="Préstamo directo, sin pedido"
                                >
                                    <SelectItem value="">Préstamo directo, sin pedido</SelectItem>
                                    {pedidosSurtibles.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.folio} — {p.departamento}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Quién responde"
                                htmlFor="responsable_id"
                                error={form.errors.responsable_id}
                                description={
                                    pedido
                                        ? 'Lo fija el pedido: responde el supervisor que pidió la herramienta.'
                                        : 'Un supervisor de almacén: a él se le reclama todo el vale.'
                                }
                                required
                            >
                                {pedido ? (
                                    <Input id="responsable_id" value={pedido.solicitante ?? '—'} readOnly />
                                ) : (
                                    <SearchSelect
                                        options={supervisores.map((u) => ({ value: u.id, label: u.name }))}
                                        value={form.data.responsable_id}
                                        onValueChange={(v) => form.setData('responsable_id', v)}
                                        placeholder="Escribe un nombre..."
                                        maxOptions={30}
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Autorizó"
                                htmlFor="autorizado_por"
                                error={form.errors.autorizado_por}
                                description="Opcional. Quién autoriza que salga del almacén; firma el resguardo."
                            >
                                <SearchSelect
                                    options={usuarios.map((u) => ({ value: u.id, label: u.name }))}
                                    value={form.data.autorizado_por}
                                    onValueChange={(v) => form.setData('autorizado_por', v)}
                                    placeholder="Escribe un nombre..."
                                    maxOptions={30}
                                />
                            </FormField>

                            <FormField
                                label="Salida"
                                htmlFor="fecha_salida"
                                error={form.errors.fecha_salida}
                                description="Cuándo se lo llevó. Puede ser antes de hoy, nunca después."
                                required
                            >
                                <Input
                                    id="fecha_salida"
                                    type="date"
                                    max={hoy()}
                                    value={form.data.fecha_salida}
                                    onChange={(e) => form.setData('fecha_salida', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Debe volver"
                                htmlFor="fecha_retorno_esperada"
                                error={form.errors.fecha_retorno_esperada}
                                description={
                                    sinRetorno
                                        ? 'Sin fecha nunca aparece como vencido, sólo como afuera.'
                                        : 'Sin fecha no hay forma de saber qué está vencido.'
                                }
                            >
                                <div className="space-y-2">
                                    <Input
                                        id="fecha_retorno_esperada"
                                        type="date"
                                        value={form.data.fecha_retorno_esperada}
                                        onChange={(e) => form.setData('fecha_retorno_esperada', e.target.value)}
                                        disabled={sinRetorno}
                                    />
                                    <label className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            className="checkbox checkbox-sm"
                                            checked={sinRetorno}
                                            onChange={(e) => elegirSinRetorno(e.target.checked)}
                                        />
                                        <span className="text-sm">Sin fecha de retorno (mientras dure la obra)</span>
                                    </label>
                                </div>
                            </FormField>

                            <FormField
                                label="Observaciones"
                                htmlFor="observaciones"
                                error={form.errors.observaciones}
                                description="Lo que aplica a todo el vale; lo de cada renglón va en su renglón."
                            >
                                <Input
                                    id="observaciones"
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                    placeholder="Se van juntas en la camioneta..."
                                />
                            </FormField>
                        </div>

                        <fieldset className="mt-4">
                            <legend className="mb-2 text-sm font-medium">¿A dónde se lo lleva?</legend>
                            <div className="flex flex-wrap gap-4">
                                {(
                                    [
                                        ['obra', 'A una obra'],
                                        ['grupo', 'A una cuadrilla en planta'],
                                        ['planta', 'Se queda en planta'],
                                    ] as [Destino, string][]
                                ).map(([valor, etiqueta]) => (
                                    <label key={valor} className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="radio"
                                            name="destino"
                                            className="radio radio-sm"
                                            checked={destino === valor}
                                            onChange={() => elegirDestino(valor)}
                                        />
                                        <span className="text-sm">{etiqueta}</span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                            {destino === 'obra' && (
                                <FormField label="Obra" htmlFor="obra_id" error={form.errors.obra_id}>
                                    <SearchSelect
                                        options={obras.map((o) => ({
                                            value: String(o.id),
                                            label: `${o.no}${o.descripcion ? ` — ${o.descripcion}` : ''}`,
                                        }))}
                                        value={form.data.obra_id}
                                        onValueChange={(v) => form.setData('obra_id', v)}
                                        placeholder="¿A qué obra?"
                                        maxOptions={30}
                                    />
                                </FormField>
                            )}
                            {destino === 'grupo' && (
                                <FormField
                                    label="Grupo de trabajo"
                                    htmlFor="grupo_trabajo_id"
                                    error={form.errors.grupo_trabajo_id}
                                    description="Las cuadrillas vienen de Producción; el almacén no las da de alta."
                                >
                                    <Select
                                        id="grupo_trabajo_id"
                                        value={form.data.grupo_trabajo_id}
                                        onValueChange={(v) => form.setData('grupo_trabajo_id', v)}
                                        placeholder="¿Qué cuadrilla lo usa?"
                                    >
                                        {gruposTrabajo.map((g) => (
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
                        <div className="mb-2 flex items-center justify-between">
                            <h2 className="text-lg font-semibold">Qué se lleva</h2>
                            <Button type="button" variant="outline" onClick={agregar} disabled={form.data.almacen_id === ''}>
                                <PlusIcon className="size-4" />
                                Agregar renglón
                            </Button>
                        </div>

                        {form.data.almacen_id === '' && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el almacén para ver qué se puede prestar.
                            </p>
                        )}
                        {pedido && (
                            <div className="alert alert-info mb-3">
                                <InfoIcon className="size-5" />
                                <span>
                                    Se cargó la herramienta que le falta a {pedido.folio}: {pedido.detalles.length}{' '}
                                    renglón(es), se necesitaba el {pedido.fecha_requerida}. En las piezas con serie
                                    elige cuál se va; puedes prestar menos, no más de lo pedido.
                                </span>
                            </div>
                        )}
                        {nadaPrestable && (
                            <div className="alert alert-warning mb-3">
                                <TriangleAlertIcon className="size-5" />
                                <span>
                                    Este almacén no tiene nada prestable: sus piezas están afuera o en reparación y sus
                                    activos por cantidad ya están todos en resguardo.
                                </span>
                            </div>
                        )}
                        {typeof form.errors.renglones === 'string' && (
                            <p className="text-error mb-2 text-sm">{form.errors.renglones}</p>
                        )}

                        <div className="rounded-box border-base-300 overflow-x-auto border">
                            <table className="table table-sm">
                                <thead className="bg-base-200">
                                    <tr>
                                        <th className="w-[36%]">Pieza o activo</th>
                                        <th className="w-28 text-right">Cantidad</th>
                                        <th>Condición al salir</th>
                                        <th>Observaciones</th>
                                        <th className="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.renglones.map((r, i) => {
                                        const pieza = piezaDe(r.clave);
                                        const porCantidad = cantidadDe(r.clave);
                                        const error = errorDe(i, 'activo_id') ?? errorDe(i, 'articulo_id');

                                        return (
                                            <tr key={i} className="hover">
                                                <td>
                                                    <SearchSelect
                                                        options={opcionesPara(r)}
                                                        value={r.clave}
                                                        onValueChange={(v) => elegir(i, v)}
                                                        placeholder="Serie, código o descripción..."
                                                        inputClassName="input-sm"
                                                        maxOptions={25}
                                                        disabled={form.data.almacen_id === ''}
                                                    />
                                                    {r.pedido_detalle_id !== '' && r.clave === '' && (
                                                        <span className="text-base-content/50 block text-xs">
                                                            Pide {pedido?.detalles.find((d) => String(d.id) === r.pedido_detalle_id)?.descripcion}: elige cuál se va.
                                                        </span>
                                                    )}
                                                    {pieza?.ubicacion && (
                                                        <span className="text-base-content/50 block text-xs">
                                                            En {pieza.ubicacion}
                                                        </span>
                                                    )}
                                                    {error && <span className="text-error text-xs">{error}</span>}
                                                </td>
                                                <td>
                                                    {porCantidad ? (
                                                        <>
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                max={porCantidad.disponible}
                                                                step="0.0001"
                                                                className="input-sm text-right"
                                                                value={r.cantidad}
                                                                onChange={(e) => editar(i, { cantidad: e.target.value })}
                                                                error={Boolean(errorDe(i, 'cantidad'))}
                                                            />
                                                            <span className="text-base-content/50 block text-right text-xs">
                                                                de {numero(porCantidad.disponible)} {porCantidad.unidad}
                                                            </span>
                                                            {errorDe(i, 'cantidad') && (
                                                                <span className="text-error text-xs">{errorDe(i, 'cantidad')}</span>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <span className="text-base-content/50 block text-right font-mono text-xs">
                                                            {pieza ? '1 pieza' : '—'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td>
                                                    <Input
                                                        className="input-sm"
                                                        value={r.condicion_salida}
                                                        onChange={(e) => editar(i, { condicion_salida: e.target.value })}
                                                        placeholder="Buena, con desgaste..."
                                                        disabled={r.clave === ''}
                                                    />
                                                </td>
                                                <td>
                                                    <Input
                                                        className="input-sm"
                                                        value={r.observaciones}
                                                        onChange={(e) => editar(i, { observaciones: e.target.value })}
                                                        disabled={r.clave === ''}
                                                    />
                                                </td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        onClick={() => quitar(i)}
                                                        aria-label="Quitar renglón"
                                                    >
                                                        <Trash2Icon className="size-3.5" />
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {elegidos.length > 0 && (
                            <div className="alert alert-info mt-3">
                                <InfoIcon className="size-5" />
                                <span>
                                    Se registra un resguardo con <strong>{elegidos.length}</strong> renglón(es). Nada de
                                    esto baja la existencia: lo prestado sigue siendo del almacén y sólo deja de estar
                                    disponible.
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/prestamos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing || elegidos.length === 0}>
                            Registrar resguardo
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
