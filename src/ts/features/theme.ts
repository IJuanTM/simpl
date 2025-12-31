import {storage} from '../utils/storage.ts';

const themeSwitch = document.querySelector('div.theme-switch') as HTMLElement | null;
const themes = [
  {name: 'light', icon: 'fa-sun'},
  {name: 'dark', icon: 'fa-moon'}
] as const;

export const themeModule = {
  getCurrent: (): typeof themes[number] => {
    const currentName = storage.get('theme') || 'dark';
    return themes.find(t => t.name === currentName) || themes[1];
  },

  getNext: (): typeof themes[number] => {
    const currentIndex = themes.findIndex(t => t.name === themeModule.getCurrent().name);
    return themes[(currentIndex + 1) % themes.length];
  },

  set: (theme: typeof themes[number]): void => {
    storage.set('theme', theme.name);
    if (themeSwitch) themeSwitch.innerHTML = `<i class="fas ${theme.icon}"></i>`;
    document.documentElement.setAttribute('data-theme', theme.name);
  },

  toggle: (): void => {
    themeModule.set(themeModule.getNext());
  },

  init: (): void => {
    if (!themeSwitch) return;

    themeSwitch.addEventListener('click', themeModule.toggle);
    themeModule.set(themeModule.getCurrent());
  }
};
