export const
  navMenu = document.querySelector('nav.menu') as HTMLElement,
  navItems = navMenu.querySelectorAll('*.nav-item') as NodeListOf<HTMLElement>,
  menuHamburger = document.querySelector('button.hamburger') as HTMLElement;

/**
 * Function to toggle the navigation menu.
 *
 * @returns {void}
 */
export const toggleMenu = (): void => {
  let menuHeight = 0;

  navItems.forEach((item: HTMLElement) => {
    // Get the height of the menu items.
    menuHeight += item.offsetHeight;

    // Set the tabindex of the menu items.
    if (menuHamburger.classList.contains('is-active')) item.setAttribute('tabindex', '-1');
    else item.setAttribute('tabindex', '0');
  });

  // Set the height of the navigation menu.
  navMenu.style.maxHeight = navMenu.style.maxHeight ? '' : `${menuHeight}px`;

  // Toggle the hamburger menu.
  menuHamburger.classList.toggle('is-active');
  menuHamburger.toggleAttribute('aria-expanded');
}

/**
 * Function to set the active link in the navigation menu.
 *
 * @returns {void}
 */
export const setActiveLink = (): void => {
  const currentPath = window.location.pathname.replace(/\/+$/, '') || '/home';

  navItems.forEach(item => {
    const itemPath = new URL(item.getAttribute('href') || '', location.origin).pathname.replace(/\/+$/, '') || '/home';

    item.classList.toggle('active', itemPath === currentPath);
  });
};

/**
 * Function to set the tabindex of the navigation menu items.
 *
 * @returns {void}
 */
export const setNavItems = (): void => {
  // Set the tabindex of the menu items.
  if (window.innerWidth > 768) navItems.forEach(item => item.setAttribute('tabindex', '0'));
  else navItems.forEach(item => item.setAttribute('tabindex', '-1'));
}
