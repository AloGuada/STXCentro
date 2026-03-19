import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import IntraLayout from '@/layouts/intra-layout';
import type { RhPermisoAusencia } from '@/types/models';
import { Head, router, usePage } from '@inertiajs/react';
import { FileText, PlusIcon, Trash2Icon } from 'lucide-react';
import { useEffect, useState } from 'react';

const STORAGE_KEY = 'permisos';

const DEPARTAMENTOS = [
    { nombre: 'Almacén y Logística', gerente: 'Juan Carlos Iturralde Patrón' },
    { nombre: 'Calidad', gerente: 'Juan Carlos Iturralde Patrón' },
    { nombre: 'Construcción', gerente: 'Edgar Adriel Serna Riverra' },
    { nombre: 'Producción', gerente: 'Juan Carlos Iturralde Patrón' },
    { nombre: 'Ingeniería y Proyectos', gerente: 'José Ricardo Briceño Tun' },
    { nombre: 'Infraestructura y Mantenimiento', gerente: 'Juan Carlos Iturralde Patrón' },
    { nombre: 'Capital Humano', gerente: 'Martha Eugenia de Llano Rodríguez' },
    { nombre: 'Administración y Finanzas', gerente: 'Rosana Adolfina Salas Tah' },
    { nombre: 'Compras', gerente: 'Alejandro Boeta Barrera' },
    { nombre: 'Dirección', gerente: 'Sin asignar' },
    { nombre: 'Ventas', gerente: 'Ricardo Arturo González Mier y Terán' },
    { nombre: 'Sistemas', gerente: 'Juan Carlos Iturralde Patrón' },
    { nombre: 'Sistemas de gestión', gerente: 'Juan Carlos Iturralde Patrón' },
];

type PermisoForm = {
    nombres: string;
    apellidos: string;
    numero_empleado: string;
    departamento: string;
    gerente: string;
    tipo: string;
    modalidad: string;
    condicion: string;
    razon: string;
    fecha_permiso: string;
};

const emptyForm: PermisoForm = {
    nombres: '',
    apellidos: '',
    numero_empleado: '',
    departamento: '',
    gerente: '',
    tipo: '',
    modalidad: '',
    condicion: '',
    razon: '',
    fecha_permiso: '',
};

function formatFecha(fechaStr: string): string {
    if (!fechaStr) return '—';
    const fecha = new Date(fechaStr);
    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const anio = fecha.getFullYear();
    const hora = String(fecha.getHours()).padStart(2, '0');
    const min = String(fecha.getMinutes()).padStart(2, '0');
    return `${dia}/${mes}/${anio} ${hora}:${min}`;
}

export default function PermisosPublico() {
    const { flash } = usePage<{ flash: { permiso?: RhPermisoAusencia } }>().props;
    const permisoCreado = flash?.permiso;

    const [permisos, setPermisos] = useState<RhPermisoAusencia[]>([]);
    const [form, setForm] = useState<PermisoForm>(emptyForm);
    const [modalOpen, setModalOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            setPermisos(JSON.parse(stored));
        }
    }, []);

    useEffect(() => {
        if (permisoCreado) {
            setPermisos((prev) => {
                const updated = [...prev, permisoCreado];
                localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
                return updated;
            });
        }
    }, [permisoCreado]);

    const handleDepartamentoChange = (nombre: string) => {
        const dep = DEPARTAMENTOS.find((d) => d.nombre === nombre);
        setForm({ ...form, departamento: nombre, gerente: dep?.gerente ?? '' });
    };

    const handleGuardar = () => {
        setProcessing(true);
        router.post('/rh/permisos', form as Record<string, string>, {
            onSuccess: () => {
                setModalOpen(false);
                setForm(emptyForm);
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    };

    const handleEliminar = (id: number) => {
        if (!confirm('¿Eliminar este permiso de esta lista?')) return;
        setPermisos((prev) => {
            const updated = prev.filter((p) => p.id !== id);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
            return updated;
        });
    };

    return (
        <IntraLayout>
            <Head title="Permisos de Ausencia" />

            <div className="mx-auto w-11/12 max-w-5xl py-10">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Permisos de Ausencia</h1>
                        <p className="mt-1 text-sm text-slate-400">
                            Los permisos registrados en este dispositivo aparecen en la lista.
                        </p>
                    </div>

                    <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                        <DialogTrigger className="btn btn-success gap-2">
                            <PlusIcon className="size-4" />
                            Nuevo permiso
                        </DialogTrigger>

                        <DialogContent className="w-max">
                            <DialogHeader>
                                <DialogTitle>Nuevo Permiso de Ausencia</DialogTitle>
                            </DialogHeader>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <input
                                    className="input input-bordered"
                                    placeholder="Número de empleado"
                                    value={form.numero_empleado}
                                    onChange={(e) => setForm({ ...form, numero_empleado: e.target.value })}
                                />
                                <input
                                    className="input input-bordered"
                                    placeholder="Nombres *"
                                    value={form.nombres}
                                    onChange={(e) => setForm({ ...form, nombres: e.target.value })}
                                />
                                <input
                                    className="input input-bordered"
                                    placeholder="Apellidos *"
                                    value={form.apellidos}
                                    onChange={(e) => setForm({ ...form, apellidos: e.target.value })}
                                />

                                <select
                                    className="select select-bordered"
                                    value={form.departamento}
                                    onChange={(e) => handleDepartamentoChange(e.target.value)}
                                >
                                    <option value="">Departamento</option>
                                    {DEPARTAMENTOS.map((d) => (
                                        <option key={d.nombre} value={d.nombre}>
                                            {d.nombre}
                                        </option>
                                    ))}
                                </select>

                                <input
                                    className="input input-bordered col-span-2"
                                    placeholder="Gerente (se llena automático)"
                                    value={form.gerente}
                                    readOnly
                                />

                                <input
                                    type="datetime-local"
                                    className="input input-bordered col-span-full w-full"
                                    value={form.fecha_permiso}
                                    onChange={(e) => setForm({ ...form, fecha_permiso: e.target.value })}
                                />

                                <select
                                    className="select select-bordered"
                                    value={form.tipo}
                                    onChange={(e) => setForm({ ...form, tipo: e.target.value })}
                                >
                                    <option value="">Tipo de permiso</option>
                                    <option value="CON GOCE">Con goce de sueldo</option>
                                    <option value="SIN GOCE">Sin goce de sueldo</option>
                                    <option value="DEVOLUCION HORAS">Devolución de horas</option>
                                </select>

                                <select
                                    className="select select-bordered"
                                    value={form.modalidad}
                                    onChange={(e) => setForm({ ...form, modalidad: e.target.value })}
                                >
                                    <option value="">Permiso por</option>
                                    <option value="DIA COMPLETO">Día completo</option>
                                    <option value="ENTRADA TARDIA">Entrada tardía</option>
                                    <option value="SALIDA ANTICIPADA">Salida anticipada</option>
                                </select>

                                <select
                                    className="select select-bordered"
                                    value={form.condicion}
                                    onChange={(e) => setForm({ ...form, condicion: e.target.value })}
                                >
                                    <option value="">Condición</option>
                                    <option value="ENFERMEDAD">Enfermedad</option>
                                    <option value="ESTUDIOS">Estudios</option>
                                    <option value="DEFUNCION FAMILIAR">Defunción familiar</option>
                                    <option value="MATRIMONIO">Matrimonio</option>
                                    <option value="MATERNIDAD/PATERNIDAD">Maternidad / Paternidad</option>
                                    <option value="TRAMITE">Trámite</option>
                                    <option value="PERSONAL">Personal</option>
                                    <option value="OTRO">Otro</option>
                                </select>

                                <textarea
                                    className="textarea textarea-bordered w-full col-span-full"
                                    placeholder="Razón del permiso"
                                    rows={3}
                                    value={form.razon}
                                    onChange={(e) => setForm({ ...form, razon: e.target.value })}
                                />
                            </div>

                            <DialogFooter>
                                <DialogClose>Cancelar</DialogClose>
                                <button className="btn btn-success" onClick={handleGuardar} disabled={processing}>
                                    {processing && <span className="loading loading-spinner loading-sm" />}
                                    Guardar
                                </button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Tabla */}
                <div className="overflow-x-auto rounded-xl border border-slate-700 bg-slate-800/40">
                    <table className="table">
                        <thead>
                            <tr className="border-slate-700 text-slate-300">
                                <th>Folio</th>
                                <th>Empleado</th>
                                <th>Tipo</th>
                                <th>Modalidad</th>
                                <th>Fecha permiso</th>
                                <th>Fecha elaboración</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {permisos.length > 0 ? (
                                permisos.map((p) => (
                                    <tr key={p.id} className="border-slate-700 text-slate-200">
                                        <td className="font-mono text-xs">{p.folio}</td>
                                        <td>
                                            {p.nombres} {p.apellidos}
                                            {p.numero_empleado && (
                                                <span className="ml-1 text-xs text-slate-400">({p.numero_empleado})</span>
                                            )}
                                        </td>
                                        <td>{p.tipo ?? '—'}</td>
                                        <td>{p.modalidad ?? '—'}</td>
                                        <td className="text-sm">{formatFecha(p.fecha_permiso ?? '')}</td>
                                        <td className="text-sm">{formatFecha(p.created_at)}</td>
                                        <td>
                                            <div className="flex gap-2">
                                                <a
                                                    href={`/rh/permisos/${p.id}/pdf`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="btn btn-sm btn-outline gap-1"
                                                >
                                                    <FileText className="size-3.5" />
                                                    PDF
                                                </a>
                                                <button
                                                    className="btn btn-sm btn-error btn-outline"
                                                    onClick={() => handleEliminar(p.id)}
                                                >
                                                    <Trash2Icon className="size-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={7} className="py-12 text-center text-slate-500">
                                        No hay permisos registrados en este dispositivo.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="mt-4 text-center text-xs text-slate-600">
                    Los permisos se almacenan localmente en este navegador. Para consultar el historial completo, contacta a Capital Humano.
                </p>
            </div>
        </IntraLayout>
    );
}
