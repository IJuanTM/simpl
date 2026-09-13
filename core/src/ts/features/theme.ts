import {storage} from '../helpers/storage.ts';

const themes = [
  {name: 'light', icon: 'fa-sun'},
  {name: 'dark', icon: 'fa-moon'}
] as const;

type Theme = typeof themes[number];

function currentTheme(): Theme {
  const stored = storage.get('theme');
  if (stored) return themes.find(t => t.name === stored) ?? themes[1];

  return matchMedia('(prefers-color-scheme: light)').matches ? themes[0] : themes[1];
}

function nextTheme(current: Theme): Theme {
  return themes[(themes.findIndex(t => t.name === current.name) + 1) % themes.length]!;
}

export const themeModule = {
  init(): void {
    const themeSwitch = document.querySelector<HTMLElement>('div.theme-switch');
    if (!themeSwitch) return;

    const render = (theme: Theme): void => {
      themeSwitch.innerHTML = `<i class="fas ${theme.icon}"></i>`;
      document.documentElement.setAttribute('data-theme', theme.name);
    };

    themeSwitch.addEventListener('click', () => {
      const theme = nextTheme(currentTheme());
      storage.set('theme', theme.name);
      render(theme);
    });

    render(currentTheme());
  }
};
