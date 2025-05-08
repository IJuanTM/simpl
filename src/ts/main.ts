import {menuHamburger, navMenu, setActiveLink, setNavItems, toggleMenu} from './menu';
import {acceptCookies, cookie} from './cookie'

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
  const nextTheme = themes[((themes.findIndex(theme => theme.name === (localStorage.getItem('theme') || 'dark'))) + 1) % themes.length];

  // Set the theme in localStorage
  localStorage.setItem('theme', nextTheme.name);

  // Set the theme icon
  themeSwitch.innerHTML = `<i class="fas ${nextTheme.icon}"></i>`;

  // Set the theme
  document.documentElement.dataset.theme = localStorage.getItem('theme')!;
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
    if (item.classList.contains('alert')) item.classList.add('invisible');
    else item.removeAttribute('inert');
  }, parseInt(item.dataset.timeout || '0')));
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
