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

function unlock(item: HTMLElement): void {
  if (!item.classList.contains('alert')) item.removeAttribute('inert');
  else if (item.classList.contains('global')) item.classList.add('invisible');
  else collapseAlert(item);
}

function lock(item: HTMLElement, ms?: number): void {
  item.setAttribute('inert', '');
  if (ms) setTimeout(() => unlock(item), ms);
}

export const timeoutModule = {
  init(): void {
    document.querySelectorAll<HTMLElement>('[data-timeout]').forEach(item =>
      setTimeout(() => unlock(item), parseInt(item.getAttribute('data-timeout') ?? '0'))
    );

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
