type FormField = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

function fieldState(field: FormField): string {
  return field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')
    ? String(field.checked)
    : field.value;
}

function trackChanges(): void {
  document.querySelectorAll<HTMLFormElement>('form[data-track-form]').forEach(form => {
    const inputFields = Array.from(form.querySelectorAll<FormField>('input:not([type="submit"]), textarea, select'));
    const submitButton = form.querySelector<HTMLButtonElement>('button[type="submit"], input[type="submit"]');
    if (!submitButton) return;

    const initialState = inputFields.map(fieldState);

    const checkChanges = (): void => {
      const changed = inputFields.some((field, index) => fieldState(field) !== initialState[index]);
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
