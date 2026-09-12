export const multiSelectModule = {
  init(): void {
    document.querySelectorAll<HTMLSelectElement>('[data-multi-select-input]').forEach(select => {
      const container = select.closest<HTMLElement>('[data-multi-select]');
      const pills = container?.querySelector<HTMLElement>('[data-multi-select-pills]');
      if (!pills) return;

      const addPill = (option: HTMLOptionElement): void => {
        option.hidden = true;

        const label = option.textContent?.trim() ?? option.value;

        const pill = document.createElement('button');
        pill.type = 'button';
        pill.className = 'multi-select-pill';
        pill.append(label);
        pill.setAttribute('aria-label', `Remove ${label}`);

        pill.addEventListener('click', () => {
          option.hidden = false;
          option.selected = false;
          pill.remove();
        });

        pills.append(pill);
      };

      const options = (): HTMLOptionElement[] => Array.from(select.options);

      options().filter(option => option.hidden).forEach(addPill);

      // A plain click/keyboard move on a native multi-select clears every other selection, so already-picked (hidden) options must be reselected after every change or the browser would silently drop them from the submission.
      select.addEventListener('change', () => {
        options().filter(option => option.selected && !option.hidden).forEach(addPill);
        options().filter(option => option.hidden).forEach(option => (option.selected = true));
      });
    });
  }
};
