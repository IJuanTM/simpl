import {acceptCookies, cookies} from './cookies.js'

// -------------------------------------------------------------------------------------------------------------------------------- //

// On click, accept the cookies
cookies.querySelector('button').addEventListener('click', acceptCookies);

// -------------------------------------------------------------------------------------------------------------------------------- //

// On load, if the cookies have not been accepted, remove the invisible class from the cookies section
window.addEventListener('load', () => {
  if (!localStorage.getItem('cookiesAccepted')) cookies.classList.remove('invisible');
});
