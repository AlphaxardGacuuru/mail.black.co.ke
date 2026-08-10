import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type RouteHref = string | { url: string };

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: RouteHref): string {
    return typeof url === 'string' ? url : url.url;
}
