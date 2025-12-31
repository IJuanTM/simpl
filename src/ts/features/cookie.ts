import {storage} from '../utils/storage.ts';

const cookie = document.querySelector('section.cookie') as HTMLElement | null;

export const cookieModule = {
  accept: (): void => {
    cookie?.classList.add('invisible');
    storage.set('cookiesAccepted', 'true');
  },

  init: (): void => {
    if (!cookie) return;

    cookie.querySelector('button')?.addEventListener('click', cookieModule.accept);

    window.addEventListener('load', () => {
      if (!storage.has('cookiesAccepted')) cookie.classList.remove('invisible');
    });
  }
};
