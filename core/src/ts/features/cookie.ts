import {storage} from '../utils/storage.ts';

export const cookieModule = {
  init(): void {
    const cookie = document.querySelector<HTMLElement>('section.cookie');
    if (!cookie) return;

    cookie.querySelector('button')?.addEventListener('click', () => {
      cookie.classList.add('invisible');
      storage.set('cookiesAccepted', 'true');
    });

    if (!storage.has('cookiesAccepted')) cookie.classList.remove('invisible');
  }
};
