(function () {
    const iconForField = (field) => {
        const name = field.name || '';
        if (name.includes('phone')) return 'bi-telephone';
        if (name.includes('email')) return 'bi-envelope';
        if (name.includes('date')) return 'bi-calendar-event';
        if (name.includes('address')) return 'bi-geo-alt';
        if (name.includes('price') || name.includes('amount') || name.includes('gallons')) return 'bi-cash-stack';
        if (name.includes('code')) return 'bi-upc-scan';
        if (name.includes('status')) return 'bi-toggle-on';
        if (field.tagName === 'SELECT') return 'bi-list-check';
        return 'bi-pencil-square';
    };

    document.querySelectorAll('.modal-body .mb-3').forEach((fieldGroup) => {
        const label = fieldGroup.querySelector(':scope > label');
        const field = fieldGroup.querySelector(':scope > input, :scope > select, :scope > textarea');

        if (!label || !field || field.type === 'hidden' || field.classList.contains('form-floating')) return;

        const floatingField = document.createElement('div');
        floatingField.className = 'form-floating input-with-icon';
        field.placeholder = field.placeholder || label.textContent.trim();

        const icon = document.createElement('i');
        icon.className = `bi ${iconForField(field)} input-icon`;
        icon.setAttribute('aria-hidden', 'true');

        fieldGroup.insertBefore(floatingField, fieldGroup.firstChild);
        floatingField.append(icon, field, label);
    });

    const bindLiveSearch = (input, items) => {
        if (!input || !items.length) {
            return;
        }

        const applyFilter = () => {
            const query = input.value.trim().toLowerCase();
            items.forEach((item) => {
                const haystack = (item.getAttribute('data-search') || item.textContent || '').toLowerCase();
                const matches = query === '' || haystack.includes(query);
                item.classList.toggle('is-search-hidden', !matches);
                if (item.tagName === 'TR') {
                    item.style.display = matches ? '' : 'none';
                } else {
                    item.style.display = matches ? '' : 'none';
                }
            });
        };

        input.addEventListener('input', applyFilter);
        applyFilter();
    };

    bindLiveSearch(
        document.querySelector('[data-live-search="customers"]'),
        document.querySelectorAll('[data-search-row="customer"]')
    );
    bindLiveSearch(
        document.querySelector('[data-live-search="staff"]'),
        document.querySelectorAll('[data-search-row="staff"]')
    );
    bindLiveSearch(
        document.querySelector('[data-live-search="deliveries"]'),
        document.querySelectorAll('[data-search-row="delivery"]')
    );
    bindLiveSearch(
        document.querySelector('[data-live-search="delivered"]'),
        document.querySelectorAll('[data-search-row="delivered"]')
    );
})();
