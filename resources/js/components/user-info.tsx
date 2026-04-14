import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    roles?: string[];
};

export function UserInfo({ user, showEmail = true, roles }: Props) {
    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const rolPrincipal = roles && roles.length > 0 ? roles[0] : null;

    return (
        <div className="flex items-center gap-3">
            <div className="avatar placeholder">
                <div className="bg-neutral text-neutral-content w-8 rounded-full">
                    <span className="text-xs">{getInitials(user.name)}</span>
                </div>
            </div>
            <div className="flex flex-col text-left min-w-0">
                <span className="text-sm font-medium leading-tight truncate">{user.name}</span>
                {rolPrincipal && (
                    <span className="badge badge-sm badge-primary badge-outline mt-0.5 self-start">{rolPrincipal}</span>
                )}
                {showEmail && !rolPrincipal && (
                    <span className="text-xs text-base-content/60 leading-tight truncate">{user.email}</span>
                )}
            </div>
        </div>
    );
}
