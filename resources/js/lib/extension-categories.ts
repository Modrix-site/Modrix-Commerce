import type { ExtensionSummary } from '@/types';
export const extensionCategories = [
    'Payments',
    'Shipping & delivery',
    'AI & content',
    'Orders & fulfillment',
    'Catalog & merchandising',
    'Marketing & customer experience',
    'Analytics & reporting',
    'Store tools',
] as const;
export function categoriesForExtension(extension: ExtensionSummary): string[] {
    const capabilities = extension.capabilities.map((c) => c.key);
    const name = `${extension.name} ${extension.display_name}`.toLowerCase();
    const categories: string[] = [];
    if (capabilities.includes('payment.gateway')) categories.push('Payments');
    if (capabilities.includes('shipping.rates'))
        categories.push('Shipping & delivery');
    if (/ai-assistant|translation|content|\bai\b/.test(name))
        categories.push('AI & content');
    if (
        /catalog|product|inventory|search|wishlist/.test(name) ||
        capabilities.includes('search.provider')
    )
        categories.push('Catalog & merchandising');
    if (
        /review|loyalty|coupon|discount|marketing|wishlist|newsletter/.test(
            name,
        )
    )
        categories.push('Marketing & customer experience');
    if (/order-alert|fulfillment|packing/.test(name))
        categories.push('Orders & fulfillment');
    if (
        capabilities.includes('dashboard.widgets') ||
        /analytics|report/.test(name)
    )
        categories.push('Analytics & reporting');
    return categories.length ? categories : ['Store tools'];
}
