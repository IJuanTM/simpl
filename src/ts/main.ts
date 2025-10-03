import {menuHamburger, navMenu, setActiveLink, setNavItems, toggleMenu} from './menu.ts';
import {acceptCookies, cookie} from './cookie.ts'

// Import external stylesheets for Vite to bundle them, local stylesheets are handled by sass
import "the-new-css-reset/css/reset.css";
import "@fortawesome/fontawesome-free/css/all.min.css";
import "hamburgers/dist/hamburgers.min.css";

// -------------------------------------------------------------------------------------------------------------------------------- //

// Simpl attribution
console.info('This website is made using the Simpl framework. Read more about Simpl here: https://www.github.com/IJuanTM/simpl');

// -------------------------------------------------------------------------------------------------------------------------------- //

if (navMenu) {
  // Hamburger menu button event listener
  menuHamburger.addEventListener('click', toggleMenu);

  // Menu item event listeners
  document.querySelectorAll('a.nav-link').forEach(link => link.addEventListener('click', toggleMenu));
}

// -------------------------------------------------------------------------------------------------------------------------------- //

const
  themeSwitch = document.querySelector('div.theme-switch') as HTMLElement,
  themes: { name: string, icon: string }[] = [
    {
      name: 'light',
      icon: 'fa-sun'
    },
    {
      name: 'dark',
      icon: 'fa-moon'
    }
  ];

themeSwitch.addEventListener('click', () => {
  const nextTheme = themes[((themes.findIndex(theme => theme.name === (localStorage.getItem('theme') || 'dark'))) + 1) % themes.length]!;

  // Set the theme in localStorage
  localStorage.setItem('theme', nextTheme.name);

  // Set the theme icon
  themeSwitch.innerHTML = `<i class="fas ${nextTheme.icon}"></i>`;

  // Set the theme
  document.documentElement.setAttribute('data-theme', localStorage.getItem('theme')!.toString());
});

// -------------------------------------------------------------------------------------------------------------------------------- //

// Set navigation items on resize
window.addEventListener('resize', setNavItems);

window.addEventListener('load', () => {
  // Set the theme icon
  themeSwitch.innerHTML = `<i class="fas ${themes[themes.findIndex(theme => theme.name === (localStorage.getItem('theme') || 'dark'))]!.icon}"></i>`;

  // Set active link and navigation items
  setActiveLink();
  setNavItems();

  // Handle elements with the data-timeout attribute
  (document.querySelectorAll('[data-timeout]') as NodeListOf<HTMLElement>).forEach(item => setTimeout(() => {
    if (item.classList.contains('alert')) {
      if (item.classList.contains('global')) item.classList.add('invisible');
      else {
        const height = item.scrollHeight;
        item.style.maxHeight = `${height}px`;

        void item.offsetHeight;

        item.classList.add('collapsing');

        item.addEventListener('transitionend', function handler(e) {
          if (e.propertyName === 'max-height') {
            item.remove();
            item.removeEventListener('transitionend', handler);
          }
        });
      }
    } else item.removeAttribute('inert');
  }, parseInt(item.getAttribute('data-timeout') || '0')));
});

// -------------------------------------------------------------------------------------------------------------------------------- //

if (cookie) {
  // On click, accept the cookies
  cookie?.querySelector('button')!.addEventListener('click', acceptCookies);

  // On load, if the cookies have not been accepted, remove the invisible class from the cookies section
  window.addEventListener('load', () => {
    if (!localStorage.getItem('cookiesAccepted')) cookie?.classList.remove('invisible');
  });
}
