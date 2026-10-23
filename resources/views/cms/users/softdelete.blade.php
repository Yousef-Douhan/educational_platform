@extends('cms.parent')

@section('title', 'Trashed Users')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/cms/users.css') }}">
@endpush

@section('main-content')
<div class="space-y-6 bg-white p-6 rounded-2xl">
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-5">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-slate-900">Trashed Users</h2>
            <p class="text-sm text-slate-500 mt-1">Manage, restore, or permanently remove deleted user accounts.</p>
        </div>
        <div>
            <a href="{{ route('cms.admin.users.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">
                <i class="fa-solid fa-arrow-left text-slate-400"></i> Back to Users
            </a>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="crud-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/80 text-xs uppercase font-bold tracking-wider text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-6">#</th>
                        <th class="py-3.5 px-6">{{ __('users.name') }}</th>
                        <th class="py-3.5 px-6">{{ __('users.email') }}</th>
                        <th class="py-3.5 px-6">Deleted At</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="trashed-users-table-body" class="divide-y divide-slate-100 bg-white">
                    <!-- Dynamically Loaded via JS -->
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Wrapper -->
        <div class="flex items-center justify-between border-t border-slate-100 p-4 bg-white" id="pagination-wrapper">
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/cms/crud-common.js') }}"></script>
    @php
        $can = [
            'restore'     => auth()->user()->can('users.restore'),
            'forceDelete' => auth()->user()->can('users.force-delete'),
        ];
    @endphp
    <script>
        const CAN = @json($can);
        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        document.addEventListener('DOMContentLoaded', function () {
            loadTrashedUsers();
        });

        function loadTrashedUsers(page = 1) {
            axios.get(`/cms/admin/users/trashed?page=${page}`).then(response => {
                let data = response.data;
                let tbody = document.getElementById('trashed-users-table-body');
                tbody.innerHTML = '';

                if (data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-12 text-slate-400 font-medium">No trashed users found.</td></tr>`;
                    return;
                }

                data.data.forEach((user, index) => {
                    tbody.innerHTML += `
                        <tr class="crud-table-row">
                            <td class="py-4 px-6 font-medium text-slate-500">${(data.current_page - 1) * data.per_page + index + 1}</td>
                            <td class="py-4 px-6 font-bold text-slate-900">${esc(user.name)}</td>
                            <td class="py-4 px-6 text-slate-600 font-medium">${esc(user.email)}</td>
                            <td class="py-4 px-6 text-slate-500 text-xs">${new Date(user.deleted_at).toLocaleDateString()}</td>
                            <td class="py-4 px-6 text-right space-x-1">
                                ${CAN.restore ? `<button onclick="restoreUser(${user.id})" class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-emerald-600 hover:bg-emerald-50 transition" title="Restore"><i class="fa-solid fa-rotate-left"></i></button>` : ''}
                                ${CAN.forceDelete ? `<button onclick="forceDeleteUser(${user.id})" class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-rose-500 hover:bg-rose-50 transition" title="Delete Permanently"><i class="fa-solid fa-trash-can"></i></button>` : ''}
                            </td>
                        </tr>
                    `;
                });
                renderPagination(data, 'loadTrashedUsers');
            });
        }

        function restoreUser(id) {
            axios.post(`/cms/admin/users/${id}/restore`).then(response => {
                if (response.data.success) {
                    loadTrashedUsers();
                    showToast(response.data.message, 'success');
                }
            }).catch(err => showToast(err.response?.data?.message || 'Action failed.', 'error'));
        }

        function forceDeleteUser(id) {
            Swal.fire({
                title: 'Permanent Delete?',
                text: "This action cannot be undone and will erase data forever!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete forever!'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.delete(`/cms/admin/users/${id}/force-delete`).then(response => {
                        if (response.data.success) {
                            loadTrashedUsers();
                            showToast(response.data.message, 'success');
                        }
                    }).catch(err => showToast(err.response?.data?.message || 'Action failed.', 'error'));
                }
            });
        }
    </script>
@endpush