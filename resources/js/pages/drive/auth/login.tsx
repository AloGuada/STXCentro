import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import DriveAuthLayout from '@/layouts/drive/drive-auth-layout';
import { useEffect } from 'react';

export default function DriveLogin() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    useEffect(() => {
        const saved = localStorage.getItem('last_drive_email');
        if (saved) {
            setData('email', saved);
        }
    }, []);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        localStorage.setItem('last_drive_email', data.email);
        post('/drive/login');
    }

    return (
        <DriveAuthLayout
            title="Drive"
            description="Ingrese sus credenciales para acceder a sus archivos"
        >
            <Head title="Drive - Iniciar Sesión" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoFocus
                            autoComplete="email"
                            placeholder="correo@empresa.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Contraseña</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            autoComplete="current-password"
                            placeholder="Contraseña"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Iniciar Sesión
                    </Button>
                </div>
            </form>
        </DriveAuthLayout>
    );
}
