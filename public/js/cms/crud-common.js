function showToast(message, icon = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    Toast.fire({
        icon: icon,
        title: message
    });
}

function handleFormErrors(error, formId) {
    if (error.response && error.response.status === 422) {
        let errors = error.response.data.errors;
        let form = document.getElementById(formId);
        
        // Clear previous errors
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.innerText = '');

        // Show new errors
        for (let field in errors) {
            let input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.classList.add('is-invalid');
                let feedback = form.querySelector(`.invalid-feedback[data-field="${field}"]`) || input.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.innerText = errors[field][0];
                }
            }
        }
    } else {
        showToast('Something went wrong!', 'error');
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function renderPagination(data, callbackName) {
    let wrapper = document.getElementById('pagination-wrapper');
    if (!wrapper) return;

    let linksHtml = `<span class="text-muted small">Showing ${data.from || 0} to ${data.to || 0} of ${data.total} entries</span>`;
    linksHtml += `<ul class="pagination pagination-sm mb-0">`;

    data.links.forEach(link => {
        let active = link.active ? 'active' : '';
        let disabled = !link.url ? 'disabled' : '';
        let pageNum = link.url ? new URL(link.url).searchParams.get('page') : 1;
        
        linksHtml += `
            <li class="page-item ${active} ${disabled}">
                <a class="page-link" href="#" onclick="${callbackName}(${pageNum}); return false;">${link.label}</a>
            </li>
        `;
    });

    linksHtml += `</ul>`;
    wrapper.innerHTML = linksHtml;
}