function initUserDeleteModal(): void {
    const modal = document.querySelector<HTMLElement>('[data-user-delete-modal]');
    if (!modal) return;

    const softDeleteForm = modal.querySelector<HTMLFormElement>('.modal-soft-delete-form');
    const purgeForm = modal.querySelector<HTMLFormElement>('.modal-purge-form');
    const titleEl = modal.querySelector<HTMLElement>('.modal-title');
    const descriptionEl = modal.querySelector<HTMLElement>('.modal-description');
    const userIdEl = modal.querySelector<HTMLElement>('.modal-user-id');
    const usernameEl = modal.querySelector<HTMLElement>('.modal-user-username');
    const emailEl = modal.querySelector<HTMLElement>('.modal-user-email');

    const base = window.location.pathname.split('/').slice(0, 3).join('/');

    function openModal(id: string, username: string, email: string, isActive: boolean): void {
        if (userIdEl) userIdEl.textContent = '#' + id;
        if (usernameEl) usernameEl.textContent = username;
        if (emailEl) emailEl.textContent = email;

        if (softDeleteForm) softDeleteForm.action = base + '/delete?id=' + id;
        if (purgeForm) purgeForm.action = base + '/purge?id=' + id;

        if (softDeleteForm) softDeleteForm.style.display = isActive ? '' : 'none';

        const title = isActive ? 'Delete user' : 'Purge user';
        if (titleEl) titleEl.textContent = title;
        modal.setAttribute('aria-label', title);

        if (descriptionEl) descriptionEl.textContent = isActive
            ? 'Choose how to remove this user:'
            : 'This user is already deactivated. Permanently remove them?';

        modal.classList.remove('invisible');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(): void {
        modal.classList.add('invisible');
        document.body.style.overflow = '';
    }

    document.querySelectorAll<HTMLElement>('[data-modal-delete]').forEach(btn => {
        btn.addEventListener('click', () => openModal(
            btn.dataset.userId ?? '',
            btn.dataset.userUsername ?? '',
            btn.dataset.userEmail ?? '',
            btn.dataset.userActive === '1'
        ));
    });

    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal();
    });
    modal.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', closeModal));
}

function initUserRestoreModal(): void {
    const modal = document.querySelector<HTMLElement>('[data-user-restore-modal]');
    if (!modal) return;

    const restoreForm = modal.querySelector<HTMLFormElement>('.modal-restore-form');
    const userIdEl = modal.querySelector<HTMLElement>('.modal-user-id');
    const usernameEl = modal.querySelector<HTMLElement>('.modal-user-username');
    const emailEl = modal.querySelector<HTMLElement>('.modal-user-email');

    const base = window.location.pathname.split('/').slice(0, 3).join('/');

    function openModal(id: string, username: string, email: string): void {
        if (userIdEl) userIdEl.textContent = '#' + id;
        if (usernameEl) usernameEl.textContent = username;
        if (emailEl) emailEl.textContent = email;
        if (restoreForm) restoreForm.action = base + '/restore?id=' + id;

        modal.classList.remove('invisible');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(): void {
        modal.classList.add('invisible');
        document.body.style.overflow = '';
    }

    document.querySelectorAll<HTMLElement>('[data-modal-restore]').forEach(btn => {
        btn.addEventListener('click', () => openModal(
            btn.dataset.userId ?? '',
            btn.dataset.userUsername ?? '',
            btn.dataset.userEmail ?? ''
        ));
    });

    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal();
    });
    modal.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', closeModal));
}

function initRoleDeleteModal(): void {
    const modal = document.querySelector<HTMLElement>('[data-role-delete-modal]');
    if (!modal) return;

    const deleteForm = modal.querySelector<HTMLFormElement>('.modal-delete-role-form');
    const deleteBtn = deleteForm?.querySelector<HTMLButtonElement>('button');
    const roleIdEl = modal.querySelector<HTMLElement>('.modal-role-id');
    const roleNameEl = modal.querySelector<HTMLElement>('.modal-role-name');
    const userCountEl = modal.querySelector<HTMLElement>('.modal-role-user-count');
    const warningEl = modal.querySelector<HTMLElement>('.modal-warning');
    const warningTextEl = modal.querySelector<HTMLElement>('.modal-warning-text');

    const base = window.location.pathname.split('/').slice(0, 3).join('/');

    function openModal(id: string, name: string, userCount: number): void {
        if (roleIdEl) roleIdEl.textContent = '#' + id;
        if (roleNameEl) roleNameEl.textContent = name;
        if (userCountEl) userCountEl.textContent = String(userCount);
        if (deleteForm) deleteForm.action = base + '/delete?id=' + id;

        const blocked = userCount > 0;
        if (deleteBtn) deleteBtn.inert = blocked;
        if (warningEl) warningEl.style.display = blocked ? '' : 'none';
        if (warningTextEl) warningTextEl.textContent = userCount + ' user' + (userCount !== 1 ? 's are' : ' is') + ' assigned to this role. Reassign or remove them before deleting.';

        modal.classList.remove('invisible');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(): void {
        modal.classList.add('invisible');
        document.body.style.overflow = '';
    }

    document.querySelectorAll<HTMLElement>('[data-modal-role-delete]').forEach(btn => {
        btn.addEventListener('click', () => openModal(
            btn.dataset.roleId ?? '',
            btn.dataset.roleName ?? '',
            parseInt(btn.dataset.roleUserCount ?? '0', 10)
        ));
    });

    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal();
    });
    modal.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', closeModal));
}

export const modalModule = {
    init(): void {
        initUserDeleteModal();
        initUserRestoreModal();
        initRoleDeleteModal();
    }
};
