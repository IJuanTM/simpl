import {prefersReducedMotion} from './motion.ts';

type AlertType = 'success' | 'warning' | 'error' | 'info';

// The top layer stacks by insertion order, so a modal opened after the alert would cover it.
// Re-showing the alert popover moves it back to the front, above any open dialog.
export function raiseGlobalAlert(): void {
  document.querySelectorAll<HTMLElement>('.alert.global[popover]').forEach(alert => {
    try {
      if (!alert.matches(':popover-open')) return;
      alert.hidePopover();
      alert.showPopover();
    } catch {
      // No popover support, so nothing to re-stack.
    }
  });
}

export function dismissGlobalAlert(el: HTMLElement): void {
  if (prefersReducedMotion()) return el.remove();

  el.classList.add('invisible');
  el.addEventListener('transitionend', () => el.remove(), {once: true});
}

export function showAlert(message: string, type: AlertType = 'info', timeoutMs = 6000): void {
  const el = document.createElement('div');
  el.className = `alert ${type} global`;
  el.setAttribute('role', 'alert');
  el.setAttribute('popover', 'manual');
  el.textContent = message;
  document.body.appendChild(el);

  try {
    el.showPopover();
  } catch {
    // No popover support; the CSS fallback still shows it.
  }

  setTimeout(() => dismissGlobalAlert(el), timeoutMs);
}
