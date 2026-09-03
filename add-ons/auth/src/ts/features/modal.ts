const base = window.location.pathname.split('/').slice(0, 3).join('/');

function openModal(modal: HTMLDialogElement): void {
  modal.showModal();
}

function closeModal(modal: HTMLDialogElement): void {
  modal.close();
}

function bindClose(modal: HTMLDialogElement): void {
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal(modal);
  });
  modal.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', () => closeModal(modal)));
}

interface UserActionModalConfig {
  modalSelector: string;
  triggerAttr: string;
  formSelector: string;
  urlSegment: string;
}

// Delegated on document, not the trigger buttons, so rows added after an AJAX table refresh still work.
function initUserActionModal(config: UserActionModalConfig): void {
  const modal = document.querySelector<HTMLDialogElement>(config.modalSelector);
  if (!modal) return;

  const form = modal.querySelector<HTMLFormElement>(config.formSelector);
  const userIdEl = modal.querySelector<HTMLElement>('.modal-user-id');
  const usernameEl = modal.querySelector<HTMLElement>('.modal-user-username');
  const emailEl = modal.querySelector<HTMLElement>('.modal-user-email');

  document.addEventListener('click', e => {
    const btn = (e.target as HTMLElement).closest<HTMLElement>(`[${config.triggerAttr}]`);
    if (!btn) return;

    if (userIdEl) userIdEl.textContent = btn.dataset.userId ?? '';
    if (usernameEl) usernameEl.textContent = btn.dataset.userUsername ?? '';
    if (emailEl) emailEl.textContent = btn.dataset.userEmail ?? '';
    if (form) form.action = `${base}/${config.urlSegment}?id=${btn.dataset.userId}`;
    openModal(modal);
  });

  bindClose(modal);
}

function initRoleDeleteModal(): void {
  const modal = document.querySelector<HTMLDialogElement>('[data-role-delete-modal]');
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
    initUserActionModal({modalSelector: '[data-user-delete-modal]', triggerAttr: 'data-modal-delete', formSelector: '.modal-soft-delete-form', urlSegment: 'delete'});
    initUserActionModal({modalSelector: '[data-user-purge-modal]', triggerAttr: 'data-modal-purge', formSelector: '.modal-purge-form', urlSegment: 'purge'});
    initUserActionModal({modalSelector: '[data-user-restore-modal]', triggerAttr: 'data-modal-restore', formSelector: '.modal-restore-form', urlSegment: 'restore'});
    initRoleDeleteModal();
  }
};
