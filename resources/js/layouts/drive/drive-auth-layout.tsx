import AppLogoIcon from '@/components/app-logo-icon';
import type { PropsWithChildren } from 'react';

type Props = PropsWithChildren<{
    title: string;
    description?: string;
}>;

export default function DriveAuthLayout({ children, title, description }: Props) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <div className="flex flex-col items-center gap-2 font-medium">
                            <div className="mb-1 flex h-9 items-center justify-center">
                                <AppLogoIcon className="h-9 text-[var(--foreground)] dark:text-white" />
                            </div>
                        </div>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            {description && (
                                <p className="text-center text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
