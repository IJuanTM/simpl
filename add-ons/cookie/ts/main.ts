import {acceptCookies, cookie} from './cookie'

// -------------------------------------------------------------------------------------------------------------------------------- //

if (cookie) {
  // On click, accept the cookies
  cookie?.querySelector('button')!.addEventListener('click', acceptCookies);

  // On load, if the cookies have not been accepted, remove the invisible class from the cookies section
  window.addEventListener('load', () => {
    if (!localStorage.getItem('cookiesAccepted')) cookie?.classList.remove('invisible');
  });
}
