// Keep the native selector available when JavaScript is disabled.
document.querySelectorAll('[data-license-search]').forEach((select) => {
    const choices = Array.from(select.options).filter((option) => option.value);
    const wrapper = document.createElement('div');
    wrapper.className = 'license-search';
    const input = document.createElement('input');
    input.type = 'text';
    input.id = `${select.id}-search`;
    document.querySelector(`label[for="${select.id}"]`).htmlFor = input.id;
    input.placeholder = 'Search themes or extensions…';
    input.autocomplete = 'off';
    input.required = true;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    const list = document.createElement('ul');
    list.id = `${select.id}-results`;
    list.className = 'license-search-results';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Matching themes and extensions');
    list.hidden = true;
    input.setAttribute('aria-controls', list.id);
    const status = document.createElement('div');
    status.className = 'license-search-status';
    status.setAttribute('role', 'status');
    status.hidden = true;
    wrapper.append(input, list, status);
    select.after(wrapper);
    select.hidden = true;
    select.required = false;
    let matches = [];
    let active = -1;

    function close() {
        list.hidden = true;
        status.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }

    function sync() {
        input.value = select.value ? select.selectedOptions[0].textContent : '';
        input.setCustomValidity('');
    }

    function choose(option) {
        select.value = option.value;
        sync();
        close();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function render(query = '') {
        matches = choices.filter((option) =>
            `${option.textContent} ${option.value}`.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase()),
        );
        active = -1;
        input.removeAttribute('aria-activedescendant');
        list.replaceChildren();
        matches.forEach((option, index) => {
            const item = document.createElement('li');
            item.id = `${list.id}-${index}`;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.value === select.value));
            item.textContent = option.textContent;
            // Retain input focus while choosing with a pointer.
            item.addEventListener('mousedown', (event) => event.preventDefault());
            item.addEventListener('click', () => choose(option));
            list.append(item);
        });
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        status.textContent = matches.length ? `${matches.length} packages available.` : 'No matching packages. Try another name.';
        status.hidden = matches.length > 0;
    }

    input.addEventListener('focus', () => render(select.value ? '' : input.value));
    input.addEventListener('click', () => {
        if (list.hidden) render(select.value ? '' : input.value);
    });
    input.addEventListener('input', () => {
        select.value = '';
        input.setCustomValidity(input.value ? 'Choose a package from the search results.' : '');
        render(input.value);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) render(select.value ? '' : input.value);
            if (!matches.length) return;
            active = (active + (event.key === 'ArrowDown' ? 1 : (active < 0 ? 0 : -1)) + matches.length) % matches.length;
            Array.from(list.children).forEach((item, index) => {
                item.classList.toggle('is-active', index === active);
            });
            const item = list.children[active];
            input.setAttribute('aria-activedescendant', item.id);
            item.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            if (active >= 0) choose(matches[active]);
            else if (matches.length === 1) choose(matches[0]);
            else if (select.value) close();
        }
    });
    wrapper.addEventListener('focusout', (event) => {
        if (!wrapper.contains(event.relatedTarget)) close();
    });
    select.addEventListener('change', sync);
    select.form.addEventListener('reset', () => queueMicrotask(() => { sync(); close(); }));
    sync();
});

document.addEventListener('click', async (event) => {
    const toggle = event.target.closest('[data-password-toggle]');
    if (toggle) {
        const input = document.getElementById(toggle.dataset.passwordToggle);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Hide' : 'Show';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute(
            'aria-label',
            `${show ? 'Hide' : 'Show'} ${input.name === 'password_confirmation' ? 'confirmation password' : 'password'}`,
        );
    }
    const copy = event.target.closest('[data-copy-target]');
    if (copy) {
        const input = document.getElementById(copy.dataset.copyTarget);
        const feedback = document.querySelector('.copy-feedback');
        if (!input) return;
        try {
            await navigator.clipboard.writeText(input.value);
            copy.textContent = 'Copied';
            if (feedback) feedback.textContent = 'License key copied.';
        } catch {
            input.focus();
            input.select();
            if (feedback)
                feedback.textContent =
                    'Key selected. Press Ctrl+C or copy it manually.';
        }
    }
});
