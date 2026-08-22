const inputPassword = document.querySelector('input.input-password') as HTMLInputElement | null;
const passwordToggleIcon = document.querySelector('i.password-toggle') as HTMLElement | null;
const messageWarning = document.querySelector('p.message-warning') as HTMLElement | null;

export const inputModule = {
  togglePassword: (): void => {
    if (!inputPassword || !passwordToggleIcon) return;

    inputPassword.setAttribute('type', inputPassword.getAttribute('type') === 'password' ? 'text' : 'password');
    passwordToggleIcon.classList.toggle('fa-eye');
    passwordToggleIcon.classList.toggle('fa-eye-slash');
  },

  capsLockWarning: (event: KeyboardEvent): void => {
    const input = event.currentTarget as HTMLElement;
    const passwordWarning = input.closest('.form-group')?.querySelector<HTMLElement>('.password-warning');
    if (!passwordWarning) return;

    if (event.getModifierState('CapsLock')) passwordWarning.classList.remove('hidden');
    else passwordWarning.classList.add('hidden');
  },

  checkMessageLength: (target: HTMLTextAreaElement): void => {
    if (!messageWarning) return;

    const lengthSpan = document.querySelector('span.message-length');
    if (lengthSpan) lengthSpan.textContent = String(target.value.length);

    const threshold = target.maxLength - 50;

    messageWarning.classList.toggle('warning', target.value.length >= threshold);
    messageWarning.classList.toggle('error', target.value.length === target.maxLength);
  },

  removeError: (field: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement): void => {
    field.closest('div.input-group')?.classList.remove('error');
  },

  init: (): void => {
    document.querySelectorAll('input, textarea, select').forEach(field =>
      field.addEventListener('keydown', () => inputModule.removeError(field as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement))
    );

    if (inputPassword) passwordToggleIcon?.addEventListener('click', inputModule.togglePassword);

    document.querySelectorAll<HTMLInputElement>('input[type="password"]').forEach(input => input.addEventListener('keydown', inputModule.capsLockWarning));
  }
};
