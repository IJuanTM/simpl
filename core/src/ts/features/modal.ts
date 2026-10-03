import {raiseToasts} from '../helpers/toast.ts';

export function openModal(modal: HTMLDialogElement): void {
  modal.showModal();
  raiseToasts();
}

// Close buttons use command="close" declaratively; this only covers the backdrop-click case.
export function bindBackdropClose(modal: HTMLDialogElement): void {
  modal.addEventListener('click', e => {
    if (e.target === modal) modal.close();
  });
}

export const modalModule = {
  init(): void {
    document.querySelectorAll<HTMLDialogElement>('dialog.modal-overlay').forEach(bindBackdropClose);
  }
};
