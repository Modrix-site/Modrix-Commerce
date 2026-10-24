// Offer only categories that apply to the selected package type. Server-side
// validation remains authoritative, including when JavaScript is unavailable.
document.querySelectorAll('.listing-discovery').forEach((section) => {
    const form = section.closest('form');
    const type = form?.querySelector('select[name="type"]');
    if (!type) return;
    const refresh = () => {
        section.querySelectorAll('[data-category-type]').forEach((label) => {
            const scope = label.dataset.categoryType;
            const allowed = scope === 'all' || scope === type.value;
            label.hidden = !allowed;
            label.querySelector('input[type="checkbox"]').disabled = !allowed;
        });
    };
    type.addEventListener('change', refresh);
    refresh();
});
