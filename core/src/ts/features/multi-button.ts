const ICONS = {
  radio: {off: ['fa-regular', 'fa-circle'], on: ['fas', 'fa-circle-dot']},
  checkbox: {off: ['fa-regular', 'fa-square'], on: ['fas', 'fa-square-check']}
};

export const multiButtonModule = {
  init(): void {
    document.querySelectorAll<HTMLElement>('.multi-button.radio, .multi-button.checkbox').forEach(container => {
      const icon = ICONS[container.classList.contains('radio') ? 'radio' : 'checkbox'];

      // A radio's siblings don't fire their own change event when deselected, so resync every input on any change.
      const sync = (): void => {
        container.querySelectorAll<HTMLInputElement>('input').forEach(input => {
          const iconElement = input.closest('label')?.querySelector('i');
          if (!iconElement) return;

          iconElement.classList.remove(...icon.off, ...icon.on);
          iconElement.classList.add(...(input.checked ? icon.on : icon.off));
        });
      };

      container.addEventListener('change', sync);
      sync();
    });
  }
};
