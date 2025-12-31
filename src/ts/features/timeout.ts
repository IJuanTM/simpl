export const timeoutModule = {
  handleAlertCollapse: (item: HTMLElement): void => {
    const height = item.scrollHeight;
    item.style.maxHeight = `${height}px`;

    void item.offsetHeight;

    item.classList.add('collapsing');

    item.addEventListener('transitionend', function handler(e) {
      if (e.propertyName === 'max-height') {
        item.remove();
        item.removeEventListener('transitionend', handler);
      }
    });
  },

  process: (item: HTMLElement): void => {
    if (item.classList.contains('alert')) {
      if (item.classList.contains('global')) item.classList.add('invisible');
      else timeoutModule.handleAlertCollapse(item);
    } else item.removeAttribute('inert');
  },

  onLoad: (): void => {
    document.querySelectorAll('[data-timeout]').forEach((item: Element) => {
      const timeout = parseInt((item as HTMLElement).getAttribute('data-timeout') || '0');
      setTimeout(() => timeoutModule.process(item as HTMLElement), timeout);
    });
  }
};
