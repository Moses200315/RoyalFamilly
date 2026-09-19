(function () {
    const navigation = performance.getEntriesByType('navigation')[0];
    const currentPage = window.location.pathname.split('/').pop();

    if (navigation && navigation.type === 'reload' && currentPage !== 'dashboard.php') {
        window.location.replace('dashboard.php');
        return;
    }

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
})();
