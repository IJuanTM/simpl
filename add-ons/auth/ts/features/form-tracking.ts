const editProfileButton = document.querySelector('button.edit-profile') as HTMLButtonElement | null;
const editUserButton = document.querySelector('button.edit-user') as HTMLButtonElement | null;
const deleteCheckbox = document.querySelector('input.delete-checkbox') as HTMLInputElement | null;
const deleteUserButton = document.querySelector('button.delete-user') as HTMLButtonElement | null;

export const formTrackingModule = {
  trackChanges: (): void => {
    if (!editProfileButton && !editUserButton) return;

    const inputFields = document.querySelectorAll('input, textarea, select') as NodeListOf<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>;
    const currentValues = new Map<string, string>();
    const targetButton = editUserButton || editProfileButton;

    if (!targetButton) return;

    inputFields.forEach(field => currentValues.set(field.name, field.value));

    const checkChanges = () => {
      const changed = Array.from(inputFields).some(field => field.value !== currentValues.get(field.name));

      if (changed) targetButton.removeAttribute('inert');
      else targetButton.setAttribute('inert', '');
    };

    inputFields.forEach(field =>
      ['keyup', 'change'].forEach(event => field.addEventListener(event, checkChanges))
    );
  },

  trackDeleteCheckbox: (): void => {
    if (!deleteCheckbox || !deleteUserButton) return;

    deleteCheckbox.addEventListener('change', () => {
      if (deleteCheckbox.checked) deleteUserButton.removeAttribute('inert');
      else deleteUserButton.setAttribute('inert', '');
    });
  },

  init: (): void => {
    formTrackingModule.trackChanges();
    formTrackingModule.trackDeleteCheckbox();
  }
};
