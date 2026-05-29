const base = window.location.pathname.split('/').slice(0, 3).join('/');

function openModal(modal: HTMLElement): void {
  modal.classList.remove('invisible');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal: HTMLElement): void {
  modal.classList.add('invisible');
  document.body.style.overflow = '';
}

function bindClose(modal: HTMLElement): void {
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal(modal);
  });
  modal.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', () => closeModal(modal)));
}

function initUserDeleteModal(): void {
  const modal = document.querySelector<HTMLElement>('[data-user-delete-modal]');
  if (!modal) return;

  const softDeleteForm = modal.querySelector<HTMLFormElement>('.modal-soft-delete-form');
  const userIdEl = modal.querySelector<HTMLElement>('.modal-user-id');
  const usernameEl = modal.querySelector<HTMLElement>('.modal-user-username');
  const emailEl = modal.querySelector<HTMLElement>('.modal-user-email');

  document.querySelectorAll<HTMLElement>('[data-modal-delete]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (userIdEl) userIdEl.textContent = btn.dataset.userId ?? '';
      if (usernameEl) usernameEl.textContent = btn.dataset.userUsername ?? '';
      if (emailEl) emailEl.textContent = btn.dataset.userEmail ?? '';
      if (softDeleteForm) softDeleteForm.action = `${base}/delete?id=${btn.dataset.userId}`;
      openModal(modal);
    });
  });

  bindClose(modal);
}

function initUserPurgeModal(): void {
  const modal = document.querySelector<HTMLElement>('[data-user-purge-modal]');
  if (!modal) return;

  const purgeForm = modal.querySelector<HTMLFormElement>('.modal-purge-form');
  const userIdEl = modal.querySelector<HTMLElement>('.modal-user-id');
  const usernameEl = modal.querySelector<HTMLElement>('.modal-user-username');
  const emailEl = modal.querySelector<HTMLElement>('.modal-user-email');

  document.querySelectorAll<HTMLElement>('[data-modal-purge]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (userIdEl) userIdEl.textContent = btn.dataset.userId ?? '';
      if (usernameEl) usernameEl.textContent = btn.dataset.userUsername ?? '';
      if (emailEl) emailEl.textContent = btn.dataset.userEmail ?? '';
      if (purgeForm) purgeForm.action = `${base}/purge?id=${btn.dataset.userId}`;
      openModal(modal);
    });
  });

  bindClose(modal);
}

function initUserRestoreModal(): void {
  const modal = document.querySelector<HTMLElement>('[data-user-restore-modal]');
  if (!modal) return;

  const restoreForm = modal.querySelector<HTMLFormElement>('.modal-restore-form');
  const userIdEl = modal.querySelector<HTMLElement>('.modal-user-id');
  const usernameEl = modal.querySelector<HTMLElement>('.modal-user-username');
  const emailEl = modal.querySelector<HTMLElement>('.modal-user-email');

  document.querySelectorAll<HTMLElement>('[data-modal-restore]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (userIdEl) userIdEl.textContent = btn.dataset.userId ?? '';
      if (usernameEl) usernameEl.textContent = btn.dataset.userUsername ?? '';
      if (emailEl) emailEl.textContent = btn.dataset.userEmail ?? '';
      if (restoreForm) restoreForm.action = `${base}/restore?id=${btn.dataset.userId}`;
      openModal(modal);
    });
  });

  bindClose(modal);
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

  document.querySelectorAll<HTMLElement>('[data-modal-role-delete]').forEach(btn => {
    btn.addEventListener('click', () => {
      const userCount = parseInt(btn.dataset.roleUserCount ?? '0', 10);
      const blocked = userCount > 0;

      if (roleIdEl) roleIdEl.textContent = btn.dataset.roleId ?? '';
      if (roleNameEl) roleNameEl.textContent = btn.dataset.roleName ?? '';
      if (userCountEl) userCountEl.textContent = String(userCount);
      if (deleteForm) deleteForm.action = `${base}/delete?id=${btn.dataset.roleId}`;
      if (deleteBtn) deleteBtn.inert = blocked;
      if (warningEl) warningEl.classList.toggle('hidden', !blocked);
      if (warningTextEl) warningTextEl.textContent = `${userCount} user${userCount !== 1 ? 's are' : ' is'} assigned to this role. Reassign or remove them before deleting.`;
      openModal(modal);
    });
  });

  bindClose(modal);
}

export const modalModule = {
  init(): void {
    initUserDeleteModal();
    initUserPurgeModal();
    initUserRestoreModal();
    initRoleDeleteModal();
  }
};
