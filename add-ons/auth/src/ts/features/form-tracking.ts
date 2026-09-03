function trackChanges(): void {
  document.querySelectorAll<HTMLFormElement>('form[data-track-form]').forEach(form => {
    const inputFields = form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>('input:not([type="submit"]), textarea, select');
    const submitButton = form.querySelector<HTMLButtonElement>('button[type="submit"], input[type="submit"]');
    if (!submitButton) return;

    const initialValues = new Map<string, string>();
    inputFields.forEach(field => initialValues.set(field.name, field.value));

    const checkChanges = (): void => {
      const changed = Array.from(inputFields).some(field => field.value !== initialValues.get(field.name));
      submitButton.toggleAttribute('inert', !changed);
    };

    inputFields.forEach(field => ['keyup', 'change'].forEach(event => field.addEventListener(event, checkChanges)));
  });
}

function trackCheckbox(): void {
  document.querySelectorAll<HTMLInputElement>('[data-track-checkbox]').forEach(checkbox => {
    const submitButton = checkbox.closest('form')?.querySelector<HTMLButtonElement>('button[type="submit"], input[type="submit"]');
    if (!submitButton) return;

    checkbox.addEventListener('change', () => submitButton.toggleAttribute('inert', !checkbox.checked));
  });
}

export const formTrackingModule = {
  init(): void {
    trackChanges();
    trackCheckbox();
  }
};
