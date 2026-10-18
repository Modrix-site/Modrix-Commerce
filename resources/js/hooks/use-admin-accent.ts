import { useSyncExternalStore } from 'react';

export const adminAccents = [
    { value: 'green', label: 'Emerald', color: '#00843d' },
    { value: 'blue', label: 'Blue', color: '#2563eb' },
    { value: 'violet', label: 'Violet', color: '#7c3aed' },
    { value: 'orange', label: 'Orange', color: '#c45b08' },
] as const;

type AdminAccent = (typeof adminAccents)[number]['value'];
const listeners = new Set<() => void>();
let currentAccent: AdminAccent = 'green';

function subscribe(callback: () => void) {
    listeners.add(callback);
    return () => {
        listeners.delete(callback);
    };
}

export function initializeAdminAccent() {
    if (typeof window === 'undefined') return;
    const saved = localStorage.getItem('admin-accent');
    currentAccent =
        adminAccents.find((accent) => accent.value === saved)?.value ?? 'green';
    document.documentElement.dataset.adminAccent = currentAccent;
}

export function useAdminAccent() {
    const accent = useSyncExternalStore(
        subscribe,
        () => currentAccent,
        () => 'green' as AdminAccent,
    );

    const updateAccent = (value: AdminAccent) => {
        currentAccent = value;
        localStorage.setItem('admin-accent', value);
        document.documentElement.dataset.adminAccent = value;
        listeners.forEach((listener) => listener());
    };

    return { accent, updateAccent };
}
