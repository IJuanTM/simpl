type FormField = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

function fieldState(field: FormField): string {
  return field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')
    ? String(field.checked)
    : field.value;
}

// A field or submit button can live outside the form and target it via the `form` attribute (e.g. to sit in a shared button row, or a form with no visible fields of its own), so it won't turn up as a descendant; look it up by owner form too.
function ownedElements<T extends Element>(form: HTMLFormElement, selector: string): T[] {
  const descendants = Array.from(form.querySelectorAll<T>(selector));
  if (!form.id) return descendants;

  const externalSelector = selector.split(',').map(part => `${part.trim()}[form="${form.id}"]`).join(', ');
  const external = Array.from(document.querySelectorAll<T>(externalSelector));
  return [...descendants, ...external];
}

function trackChanges(): void {
  document.querySelectorAll<HTMLFormElement>('form[data-track-form]').forEach(form => {
    const inputFields = ownedElements<FormField>(form, 'input:not([type="submit"]), textarea, select');
    const trackedButtons = ownedElements<HTMLButtonElement>(form, 'button[type="submit"], button[type="reset"], input[type="submit"]')
      .filter(button => !button.hasAttribute('data-track-ignore'));
    if (!trackedButtons.length) return;

    const initialState = inputFields.map(fieldState);

    const checkChanges = (): void => {
      const changed = inputFields.some((field, index) => fieldState(field) !== initialState[index]);
      trackedButtons.forEach(button => button.toggleAttribute('inert', !changed));
    };

    // 'input' catches paste/drag-drop/IME changes that 'keyup' misses; 'change' still covers select/checkbox/radio.
    inputFields.forEach(field => ['input', 'change'].forEach(event => field.addEventListener(event, checkChanges)));

    // A native reset button reverts the fields without firing input/change, so re-check once it has.
    form.addEventListener('reset', () => setTimeout(checkChanges));
  });
}

function trackCheckbox(): void {
  document.querySelectorAll<HTMLInputElement>('[data-track-checkbox]').forEach(checkbox => {
    // The checkbox sits in the modal body, outside the submit's <form>, so widen the scope to the dialog.
    const scope = checkbox.closest('form') ?? checkbox.closest('dialog');
    const submitButton = scope?.querySelector<HTMLButtonElement>('button[type="submit"], input[type="submit"]');
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
