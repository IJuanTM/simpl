function togglePassword(input: HTMLInputElement, icon: HTMLElement): void {
  input.type = input.type === 'password' ? 'text' : 'password';
  icon.classList.toggle('fa-eye');
  icon.classList.toggle('fa-eye-slash');
}

function capsLockWarning(event: KeyboardEvent): void {
  const input = event.currentTarget as HTMLElement;
  const warning = input.closest('.form-group')?.querySelector<HTMLElement>('.password-warning');
  warning?.classList.toggle('hidden', !event.getModifierState('CapsLock'));
}

export const inputModule = {
  // Called from message.ts, so it stays on the public surface.
  checkMessageLength(target: HTMLTextAreaElement): void {
    const messageWarning = document.querySelector<HTMLElement>('p.message-warning');
    if (!messageWarning) return;

    const lengthSpan = document.querySelector('span.message-length');
    if (lengthSpan) lengthSpan.textContent = String(target.value.length);

    messageWarning.classList.toggle('warning', target.value.length >= target.maxLength - 50);
    messageWarning.classList.toggle('error', target.value.length === target.maxLength);
  },

  init(): void {
    document.querySelectorAll('input, textarea, select').forEach(field =>
      field.addEventListener('keydown', () => field.closest('div.input-group')?.classList.remove('error'))
    );

    const password = document.querySelector<HTMLInputElement>('input.input-password');
    const toggleIcon = document.querySelector<HTMLElement>('i.password-toggle');
    if (password && toggleIcon) toggleIcon.addEventListener('click', () => togglePassword(password, toggleIcon));

    document.querySelectorAll<HTMLInputElement>('input[type="password"]').forEach(input => input.addEventListener('keydown', capsLockWarning));
  }
};
