import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

export function UserInfo({ user, showEmail = true }: Props) {
    // Obtener iniciales del nombre
    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    return (
        <div className="flex items-center gap-3">
            <div className="avatar placeholder">
                <div className="bg-neutral text-neutral-content w-8 rounded-full">
                    <span className="text-xs">{getInitials(user.name)}</span>
                </div>
            </div>
            <div className="flex flex-col text-left">
                <span className="text-sm font-medium leading-tight">{user.name}</span>
                {showEmail && (
                    <span className="text-xs text-base-content/60 leading-tight">{user.email}</span>
                )}
            </div>
        </div>
    );
}
