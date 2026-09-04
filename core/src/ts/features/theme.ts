import {storage} from '../helpers/storage.ts';

const themes = [
  {name: 'light', icon: 'fa-sun'},
  {name: 'dark', icon: 'fa-moon'}
] as const;

type Theme = typeof themes[number];

function currentTheme(): Theme {
  return themes.find(t => t.name === storage.get('theme')) ?? themes[1];
}

function nextTheme(current: Theme): Theme {
  return themes[(themes.findIndex(t => t.name === current.name) + 1) % themes.length]!;
}

export const themeModule = {
  init(): void {
    const themeSwitch = document.querySelector<HTMLElement>('div.theme-switch');
    if (!themeSwitch) return;

    const apply = (theme: Theme): void => {
      storage.set('theme', theme.name);
      themeSwitch.innerHTML = `<i class="fas ${theme.icon}"></i>`;
      document.documentElement.setAttribute('data-theme', theme.name);
    };

    themeSwitch.addEventListener('click', () => apply(nextTheme(currentTheme())));
    apply(currentTheme());
  }
};
