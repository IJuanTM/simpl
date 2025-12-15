const navMenu = document.querySelector('nav.menu') as HTMLElement | null;
const navItems = navMenu?.querySelectorAll('*.nav-item') as NodeListOf<HTMLElement> | undefined;
const menuHamburger = document.querySelector('button.hamburger') as HTMLElement | null;

export const menuModule = {
  toggle: (): void => {
    if (!navMenu || !navItems || !menuHamburger) return;

    let menuHeight = 0;
    const isActive = menuHamburger.classList.contains('is-active');

    navItems.forEach(item => {
      menuHeight += item.offsetHeight;
      item.setAttribute('tabindex', isActive ? '-1' : '0');
    });

    navMenu.style.maxHeight = navMenu.style.maxHeight ? '' : `${menuHeight}px`;
    menuHamburger.classList.toggle('is-active');
    menuHamburger.toggleAttribute('aria-expanded');
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
    if (!navItems) return;

    const tabindex = window.innerWidth > 768 ? '0' : '-1';
    navItems.forEach(item => item.setAttribute('tabindex', tabindex));
  },

  init: (): void => {
    if (!navMenu || !menuHamburger) return;

    menuHamburger.addEventListener('click', menuModule.toggle);
    document.querySelectorAll('a.nav-link').forEach(link => link.addEventListener('click', menuModule.toggle));
    window.addEventListener('resize', menuModule.setTabIndex);
  }
};
