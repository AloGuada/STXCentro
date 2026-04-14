import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, Loader2 } from 'lucide-react';

type Props = {
    onClose: () => void;
};

export default function CambiarPasswordModal({ onClose }: Props) {
    const [current, setCurrent] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [verCurrent, setVerCurrent] = useState(false);
    const [verPassword, setVerPassword] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [procesando, setProcesando] = useState(false);
    const [exito, setExito] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (password !== confirmation) {
            setErrores({ password_confirmation: 'Las contraseñas no coinciden.' });
            return;
        }
        setProcesando(true);
        setErrores({});
        setExito(false);
        router.put(
            '/user/password',
            {
                current_password: current,
                password,
                password_confirmation: confirmation,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setExito(true);
                    setCurrent('');
                    setPassword('');
                    setConfirmation('');
                    setTimeout(onClose, 1200);
                },
                onError: (errs) => setErrores(errs as Record<string, string>),
                onFinish: () => setProcesando(false),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/50" onClick={procesando ? undefined : onClose} />
            <div className="relative bg-base-100 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                <div className="flex items-center gap-3 px-5 py-4 border-b border-base-300 bg-base-200/40">
                    <div className="p-2 rounded-lg bg-primary/10 text-primary">
                        <KeyRound className="size-5" />
                    </div>
                    <div>
                        <h3 className="font-semibold">Cambiar contraseña</h3>
                        <p className="text-xs text-base-content/60">Actualiza tu contraseña personal.</p>
                    </div>
                </div>

                <form onSubmit={submit} className="p-5 space-y-4">
                    <CampoPassword
                        id="curr"
                        label="Contraseña actual"
                        value={current}
                        onChange={setCurrent}
                        ver={verCurrent}
                        setVer={setVerCurrent}
                        error={errores.current_password}
                        autoFocus
                    />
                    <CampoPassword
                        id="new"
                        label="Nueva contraseña"
                        value={password}
                        onChange={setPassword}
                        ver={verPassword}
                        setVer={setVerPassword}
                        error={errores.password}
                        help="Mínimo 8 caracteres."
                    />
                    <CampoPassword
                        id="confirm"
                        label="Confirmar nueva contraseña"
                        value={confirmation}
                        onChange={setConfirmation}
                        ver={verPassword}
                        setVer={setVerPassword}
                        error={errores.password_confirmation}
                        noToggle
                    />

                    {exito && (
                        <div className="alert alert-success py-2 text-sm">
                            <span>Contraseña actualizada.</span>
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2 border-t border-base-300 -mx-5 px-5 -mb-5 pb-4">
                        <button type="button" onClick={onClose} disabled={procesando} className="btn btn-ghost btn-sm">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={procesando || !current || !password || !confirmation}
                            className="btn btn-primary btn-sm"
                        >
                            {procesando ? <Loader2 className="size-4 animate-spin" /> : null}
                            Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function CampoPassword({
    id,
    label,
    value,
    onChange,
    ver,
    setVer,
    error,
    help,
    autoFocus,
    noToggle,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    ver: boolean;
    setVer: (v: boolean) => void;
    error?: string;
    help?: string;
    autoFocus?: boolean;
    noToggle?: boolean;
}) {
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium mb-1.5">
                {label}
            </label>
            <div className="relative">
                <input
                    id={id}
                    type={ver ? 'text' : 'password'}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className={`input input-bordered w-full pr-10 ${error ? 'input-error' : ''}`}
                    required
                    autoFocus={autoFocus}
                    autoComplete={id === 'curr' ? 'current-password' : 'new-password'}
                />
                {!noToggle && (
                    <button
                        type="button"
                        onClick={() => setVer(!ver)}
                        className="absolute right-2 top-1/2 -translate-y-1/2 btn btn-xs btn-ghost btn-circle"
                        title={ver ? 'Ocultar' : 'Mostrar'}
                    >
                        {ver ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                    </button>
                )}
            </div>
            {error ? (
                <p className="text-error text-xs mt-1">{error}</p>
            ) : help ? (
                <p className="text-xs text-base-content/60 mt-1">{help}</p>
            ) : null}
        </div>
    );
}
