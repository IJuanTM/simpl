import {storage} from '../helpers/storage.ts';

const prefersLight = matchMedia('(prefers-color-scheme: light)');

export const themeModule = {
  init(): void {
    const themeSwitch = document.querySelector<HTMLElement>('div.theme-switch');
    if (!themeSwitch) return;

    const button = themeSwitch.querySelector<HTMLButtonElement>('button.theme-switch-btn')!;
    const menu = themeSwitch.querySelector<HTMLElement>('div.theme-menu')!;
    const options = [...themeSwitch.querySelectorAll<HTMLButtonElement>('button[data-theme-option]')];

    const render = (): void => {
      const choice = storage.get('theme') ?? 'system';
      const selected = options.find(option => option.dataset.themeOption === choice) ?? options[0]!;
      const theme = selected.dataset.themeOption === 'system' ? (prefersLight.matches ? 'light' : 'dark') : selected.dataset.themeOption!;

      document.documentElement.setAttribute('data-theme', theme);
      button.querySelector('i')!.className = selected.querySelector('i')!.className;
      options.forEach(option => option.setAttribute('aria-pressed', String(option === selected)));
    };

    options.forEach(option => option.addEventListener('click', () => {
      if (option.dataset.themeOption === 'system') storage.remove('theme');
      else storage.set('theme', option.dataset.themeOption!);

      render();
      menu.hidePopover();
    }));

    menu.addEventListener('toggle', e => button.setAttribute('aria-expanded', String((e as ToggleEvent).newState === 'open')));
    prefersLight.addEventListener('change', render);

    render();
  }
};
