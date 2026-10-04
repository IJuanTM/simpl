import {openModal} from './modal.ts';

type FormField = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

// Returns an error message for the input, or '' when it passes.
export type ValidationRule = (input: HTMLInputElement) => string;

const shown = new WeakSet<FormField>();

// A page re-rendered after a rejected save shows the submitted value, so data-initial-value carries the saved one to compare against.
export function initialValue(field: HTMLInputElement | HTMLTextAreaElement): string {
  return field.dataset.initialValue ?? field.defaultValue;
}

const rules: ValidationRule[] = [];
const refreshers: (() => void)[] = [];

function isFormField(element: Element): element is FormField {
  return element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement;
}

function fieldLabel(field: FormField): string {
  return (field.labels?.[0]?.firstChild?.textContent ?? field.name).replace(':', '').trim().toLowerCase();
}

function requiredMessage(field: FormField): string {
  return `Please enter an input in the ${fieldLabel(field)} field!`;
}

function customError(field: FormField): string {
  if (!(field instanceof HTMLInputElement)) return '';

  const {requiredWith, match, matchMessage, pattern, patternMessage} = field.dataset;

  if (field.value === '') {
    const trigger = requiredWith ? document.getElementById(requiredWith) as HTMLInputElement | null : null;
    return trigger && trigger.value !== initialValue(trigger) ? requiredMessage(field) : '';
  }

  const matchField = match ? document.getElementById(match) as HTMLInputElement | null : null;
  if (matchField && field.value !== matchField.value) return matchMessage ?? `The input in the ${fieldLabel(field)} field does not match!`;

  if (pattern && patternMessage && !new RegExp(pattern).test(field.value)) return patternMessage;

  for (const rule of rules) {
    const error = rule(field);
    if (error !== '') return error;
  }

  return '';
}

function message(field: FormField): string {
  if (field.validity.valid) return '';
  if (field.validity.valueMissing) return requiredMessage(field);
  if (field.validity.typeMismatch) return `The input in the ${fieldLabel(field)} field is not a valid email address!`;
  if (field.validity.tooLong) return `The input of the ${fieldLabel(field)} field is too long!`;

  return field.validationMessage;
}

function errorFor(field: FormField): HTMLElement | null {
  const group = field.closest('.input-group');
  if (!group) return null;

  if (group.nextElementSibling instanceof HTMLElement && group.nextElementSibling.matches('p.field-error')) return group.nextElementSibling;

  const error = document.createElement('p');
  error.className = 'row field-error f-nowrap g-col-0.5 hidden';
  error.setAttribute('role', 'alert');
  group.after(error);
  return error;
}

function report(field: FormField): void {
  const text = message(field);
  if (text === '' && !shown.has(field)) return;

  const error = errorFor(field);
  if (!error) return;

  error.textContent = text;
  error.classList.toggle('hidden', text === '');
  field.closest('.input-group')?.classList.toggle('error', text !== '');

  if (text === '') {
    field.removeAttribute('aria-invalid');
    shown.delete(field);
  } else {
    field.setAttribute('aria-invalid', 'true');
    shown.add(field);
  }
}

function initForm(form: HTMLFormElement): void {
  // form.elements also covers fields outside the form that target it via the `form` attribute.
  const fields = Array.from(form.elements).filter(isFormField);

  const refresh = (): void => fields.forEach(field => {
    field.setCustomValidity(customError(field));
    if (shown.has(field)) report(field);
  });

  fields.forEach(field => {
    ['input', 'change'].forEach(event => field.addEventListener(event, refresh));

    // A field left empty is only flagged on submit, so tabbing through a blank form doesn't light it up; clearing a prefilled value is flagged right away.
    field.addEventListener('blur', () => {
      if (field.value !== '' || (!(field instanceof HTMLSelectElement) && initialValue(field) !== '')) report(field);
    });

    // Cancelling the event swaps the browser's native bubble for the field's own error message; the browser then skips focusing it, so focus the first invalid field here.
    field.addEventListener('invalid', event => {
      event.preventDefault();
      const first = fields.find(other => !other.validity.valid) === field;
      const dialog = field.closest('dialog');

      // A field in a closed dialog (e.g. a password confirmation) is asked for by opening it, not flagged as an error the user hasn't seen yet.
      if (dialog && !dialog.open) {
        if (first) {
          openModal(dialog);
          field.focus();
        }
        return;
      }

      report(field);
      if (first) field.focus();
    });
  });

  // A native reset button reverts the fields without firing input/change, so re-check once it has.
  form.addEventListener('reset', () => setTimeout(refresh));

  refreshers.push(refresh);
  refresh();
}

export const formValidationModule = {
  // Add-ons register extra rules here; forms already initialised are re-checked, so the call order relative to init() doesn't matter.
  addRule(rule: ValidationRule): void {
    rules.push(rule);
    refreshers.forEach(refresh => refresh());
  },

  init(): void {
    document.querySelectorAll<HTMLFormElement>('form:not([novalidate])').forEach(initForm);
  }
};
