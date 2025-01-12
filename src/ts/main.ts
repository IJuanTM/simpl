import {menuHamburger, navMenu, setActiveLink, setNavItems, toggleMenu} from './menu';

// -------------------------------------------------------------------------------------------------------------------------------- //

// Simpl attribution
console.info('This website is made using the Simpl framework. Read more about Simpl here: https://www.github.com/IJuanTM/simpl');

// -------------------------------------------------------------------------------------------------------------------------------- //

// Navigation menu event listeners
if (navMenu) {
  // Hamburger menu button event listener
  menuHamburger.addEventListener('click', toggleMenu);

  // Menu item event listeners
  document.querySelectorAll('a.nav-link').forEach(link => link.addEventListener('click', toggleMenu));
}

// -------------------------------------------------------------------------------------------------------------------------------- //

// Set navigation items on resize
window.addEventListener('resize', setNavItems);

const timeoutItems = document.querySelectorAll('[data-timeout]') as NodeListOf<HTMLElement>;

window.addEventListener('load', () => {
  // Set active link on load
  setActiveLink();

  // Set navigation items on load
  setNavItems();

  if (timeoutItems) timeoutItems.forEach(item => setTimeout(() => {
    // If item is an alert, hide it
    // Else, remove the inert attribute from the item
    if (item.classList.contains('alert')) item.classList.add('invisible');
    else item.removeAttribute('inert');
  }, parseInt(item.dataset.timeout || '0')));
});
