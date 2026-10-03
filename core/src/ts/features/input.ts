function togglePassword(input: HTMLInputElement, icon: HTMLElement): void {
  input.type = input.type === 'password' ? 'text' : 'password';
  icon.classList.toggle('fa-eye');
  icon.classList.toggle('fa-eye-slash');
}

function capsLockWarning(event: KeyboardEvent): void {
  const input = event.currentTarget as HTMLElement;
  input.closest('.form-group')?.querySelector<HTMLElement>('.password-warning')?.classList.toggle('hidden', !event.getModifierState('CapsLock'));
}

export const inputModule = {
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
