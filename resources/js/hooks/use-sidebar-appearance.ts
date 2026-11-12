import { useSyncExternalStore } from 'react';

export type SidebarAppearance = 'dark' | 'light';
const listeners = new Set<() => void>();
let current: SidebarAppearance = 'dark';

function subscribe(callback: () => void) {
    listeners.add(callback);
    return () => {
        listeners.delete(callback);
    };
}

export function initializeSidebarAppearance() {
    if (typeof window === 'undefined') return;
    current =
        localStorage.getItem('sidebar-appearance') === 'light'
            ? 'light'
            : 'dark';
    document.documentElement.dataset.sidebarAppearance = current;
}

export function useSidebarAppearance() {
    const sidebarAppearance = useSyncExternalStore(
        subscribe,
        () => current,
        () => 'dark' as SidebarAppearance,
    );
    const updateSidebarAppearance = (value: SidebarAppearance) => {
        current = value;
        localStorage.setItem('sidebar-appearance', value);
        document.documentElement.dataset.sidebarAppearance = value;
        listeners.forEach((listener) => listener());
    };
    return { sidebarAppearance, updateSidebarAppearance };
}
