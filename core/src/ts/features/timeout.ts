export const timeoutModule = {
  lock: (item: HTMLElement, ms?: number): void => {
    item.setAttribute('inert', '');
    if (ms) setTimeout(() => timeoutModule.unlock(item), ms);
  },

  unlock: (item: HTMLElement): void => {
    if (item.classList.contains('alert')) {
      if (item.classList.contains('global')) item.classList.add('invisible');
      else timeoutModule.collapseAlert(item);
    } else {
      item.removeAttribute('inert');
    }
  },

  collapseAlert: (item: HTMLElement): void => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return item.remove();

    item.style.maxHeight = `${item.scrollHeight}px`;
    void item.offsetHeight;
    item.classList.add('collapsing');
    item.addEventListener('transitionend', e => {
      if (e.propertyName === 'max-height') item.remove();
    }, {once: true});
  },

  onLoad: (): void => {
    document.querySelectorAll<HTMLElement>('[data-timeout]').forEach(item => {
      const ms = parseInt(item.getAttribute('data-timeout') ?? '0');
      setTimeout(() => timeoutModule.unlock(item), ms);
    });

    document.querySelectorAll<HTMLButtonElement>('button[data-cooldown]').forEach(button => {
      button.addEventListener('click', () => {
        const ms = parseInt(button.dataset.cooldown ?? '0');
        if (ms > 0) timeoutModule.lock(button, ms);
      });
    });

    document.querySelectorAll<HTMLFormElement>('form').forEach(form => form.addEventListener('submit', () => form.querySelector<HTMLButtonElement>('button[type="submit"]')?.setAttribute('inert', '')));
  }
};
