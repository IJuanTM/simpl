const navMenu = document.querySelector('nav.menu') as HTMLElement | null;
const navItems = navMenu?.querySelectorAll('*.nav-item') as NodeListOf<HTMLElement> | undefined;
const menuHamburger = document.querySelector('button.hamburger') as HTMLElement | null;

export const menuModule = {
  updateHeight: (): void => {
    if (!navMenu || !navItems || !menuHamburger) return;

    if (menuHamburger.classList.contains('is-active')) {
      let menuHeight = 0;

      navItems.forEach(item => menuHeight += item.offsetHeight);
      navMenu.style.maxHeight = `${menuHeight}px`;
    } else navMenu.style.maxHeight = '';
  },

  setMenuState: (isOpen: boolean): void => {
    if (!navMenu || !navItems || !menuHamburger) return;

    menuHamburger.classList.toggle('is-active', isOpen);
    navMenu.classList.toggle('extended', isOpen);

    if (isOpen) menuHamburger.setAttribute('aria-expanded', 'true');
    else menuHamburger.removeAttribute('aria-expanded');

    navItems.forEach(item => item.setAttribute('tabindex', isOpen ? '0' : '-1'));
    menuModule.updateHeight();
  },

  toggle: (): void => {
    if (!menuHamburger) return;

    menuModule.setMenuState(!menuHamburger.classList.contains('is-active'));
  },

  setActive: (): void => {
    if (!navItems) return;

    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/home';

    navItems.forEach(item => {
      const itemPath = new URL(item.getAttribute('href') || '', location.origin).pathname.replace(/\/+$/, '') || '/home';
      item.classList.toggle('active', itemPath === currentPath);
    });
  },

  setTabIndex: (): void => {
    if (!navItems || !menuHamburger) return;

    if (window.innerWidth > 1024) {
      menuModule.setMenuState(false);
      navItems.forEach(item => item.setAttribute('tabindex', '0'));
    } else menuModule.updateHeight();
  },

  init: (): void => {
    if (!navMenu || !menuHamburger) return;

    menuHamburger.addEventListener('click', menuModule.toggle);
    document.querySelectorAll('a.nav-link').forEach(link => link.addEventListener('click', menuModule.toggle));
    window.addEventListener('resize', menuModule.setTabIndex);
  }
};
