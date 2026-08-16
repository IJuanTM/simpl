export const formTrackingModule = {
  trackChanges: (): void => {
    const trackedForms = document.querySelectorAll('form[data-track-form]') as NodeListOf<HTMLFormElement>;

    trackedForms.forEach(form => {
      const inputFields = form.querySelectorAll('input:not([type="submit"]), textarea, select') as NodeListOf<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>;
      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]') as HTMLButtonElement | null;

      if (!submitButton) return;

      const currentValues = new Map<string, string>();
      inputFields.forEach(field => currentValues.set(field.name, field.value));

      const checkChanges = () => {
        const changed = Array.from(inputFields).some(field => field.value !== currentValues.get(field.name));

        if (changed) submitButton.removeAttribute('inert');
        else submitButton.setAttribute('inert', '');
      };

      inputFields.forEach(field =>
        ['keyup', 'change'].forEach(event => field.addEventListener(event, checkChanges))
      );
    });
  },

  trackCheckbox: (): void => {
    const trackedCheckboxes = document.querySelectorAll('[data-track-checkbox]') as NodeListOf<HTMLInputElement>;

    trackedCheckboxes.forEach(checkbox => {
      const form = checkbox.closest('form');
      if (!form) return;

      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]') as HTMLButtonElement | null;
      if (!submitButton) return;

      checkbox.addEventListener('change', () => {
        if (checkbox.checked) submitButton.removeAttribute('inert');
        else submitButton.setAttribute('inert', '');
      });
    });
  },

  init: (): void => {
    formTrackingModule.trackChanges();
    formTrackingModule.trackCheckbox();
  }
};
