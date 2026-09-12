import {prefersReducedMotion} from '../helpers/motion.ts';

function collapseAlert(item: HTMLElement): void {
  if (prefersReducedMotion()) return item.remove();

  item.style.maxHeight = `${item.scrollHeight}px`;
  void item.offsetHeight;
  item.classList.add('collapsing');
  item.addEventListener('transitionend', e => {
    if (e.propertyName === 'max-height') item.remove();
  }, {once: true});
}

function dismissGlobalAlert(item: HTMLElement): void {
  if (prefersReducedMotion()) return item.remove();

  item.classList.add('invisible');
  item.addEventListener('transitionend', () => item.remove(), {once: true});
}

function unlock(item: HTMLElement): void {
  if (!item.classList.contains('alert')) item.removeAttribute('inert');
  else if (item.classList.contains('global')) dismissGlobalAlert(item);
  else collapseAlert(item);
}

function lock(item: HTMLElement, ms?: number): void {
  item.setAttribute('inert', '');
  if (ms) setTimeout(() => unlock(item), ms);
}

function runCountdown(el: HTMLElement): void {
  const end = Date.now() + parseInt(el.dataset.countdown ?? '0');

  const tick = (): void => {
    const secondsLeft = Math.max(0, Math.ceil((end - Date.now()) / 1000));
    el.textContent = String(secondsLeft);

    if (secondsLeft > 0) setTimeout(tick, 250);
    else (el.closest<HTMLElement>('[data-countdown-wrap]') ?? el).classList.add('hidden');
  };

  tick();
}

export const timeoutModule = {
  init(): void {
    document.querySelectorAll<HTMLElement>('.alert.global[popover]').forEach(alert => {
      try {
        alert.showPopover();
      } catch {
        // Already open, or the browser has no popover support - the CSS fallback still shows it.
      }
    });

    document.querySelectorAll<HTMLElement>('[data-timeout]').forEach(item =>
      setTimeout(() => unlock(item), parseInt(item.getAttribute('data-timeout') ?? '0'))
    );

    document.querySelectorAll<HTMLElement>('[data-countdown]').forEach(runCountdown);

    document.querySelectorAll<HTMLButtonElement>('button[data-cooldown]').forEach(button => {
      button.addEventListener('click', () => {
        const ms = parseInt(button.dataset.cooldown ?? '0');
        if (ms > 0) lock(button, ms);
      });
    });

    document.querySelectorAll<HTMLFormElement>('form').forEach(form =>
      form.addEventListener('submit', event => {
        const button = form.querySelector<HTMLButtonElement>('button[type="submit"]');
        if (!button) return;
        button.setAttribute('inert', '');
        // A cancelled submit (a script-driven form that never navigates) would otherwise leave the button dead.
        setTimeout(() => event.defaultPrevented && button.removeAttribute('inert'));
      })
    );
  }
};
