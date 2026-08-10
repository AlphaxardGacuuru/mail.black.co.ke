import type { RouteHref } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: RouteHref;
};

export type NavItem = {
    title: string;
    href: RouteHref;
    icon?: LucideIcon | null;
    isActive?: boolean;
};
