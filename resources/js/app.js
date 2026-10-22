import axios from 'axios';
import './cms/crud-common';
import './cms/user-profiles';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-axios-delete]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();

            const url = button.dataset.axiosDelete;
            const confirmText = button.dataset.confirmText || 'Are you sure?';

            if (!window.confirm(confirmText)) {
                return;
            }

            try {
                const response = await axios.delete(url);
                if (response.data?.redirect) {
                    window.location.href = response.data.redirect;
                    return;
                }

                window.location.reload();
            } catch (error) {
                console.error(error);
                window.alert('Something went wrong.');
            }
        });
    });
});
