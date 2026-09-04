type AlertType = 'success' | 'warning' | 'error' | 'info';

export function showAlert(message: string, type: AlertType = 'info', timeoutMs = 6000): void {
  const el = document.createElement('div');
  el.className = `alert ${type} global`;
  el.setAttribute('role', 'alert');
  el.textContent = message;
  document.body.appendChild(el);

  setTimeout(() => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return el.remove();

    el.classList.add('invisible');
    el.addEventListener('transitionend', () => el.remove(), {once: true});
  }, timeoutMs);
}
