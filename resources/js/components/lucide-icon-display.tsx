import { icons } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { createElement } from 'react';
import type { ReactNode } from 'react';

type LucideIconDisplayProps = {
    icon?: string | null;
    className?: string;
    fallback?: ReactNode;
};

function resolveIcon(icon?: string | null): LucideIcon | null {
    if (!icon) {
        return null;
    }

    return (icons[icon as keyof typeof icons] as LucideIcon | undefined) ?? null;
}

export default function LucideIconDisplay({
    icon,
    className,
    fallback = null,
}: LucideIconDisplayProps) {
    const iconComponent = resolveIcon(icon);

    if (!iconComponent) {
        return <>{fallback}</>;
    }

    return createElement(iconComponent, { className, 'aria-hidden': 'true' });
}