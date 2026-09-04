export const menuModule = {
  init(): void {
    const navMenu = document.querySelector<HTMLElement>('nav.menu');
    const menuHamburger = document.querySelector<HTMLElement>('button.hamburger');
    if (!navMenu || !menuHamburger) return;

    const navItems = navMenu.querySelectorAll<HTMLElement>('.nav-item');

    const setMenuState = (isOpen: boolean): void => {
      menuHamburger.classList.toggle('is-active', isOpen);
      navMenu.classList.toggle('extended', isOpen);

      menuHamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

      navItems.forEach(item => item.setAttribute('tabindex', isOpen ? '0' : '-1'));
    };

    const toggle = (): void => setMenuState(!menuHamburger.classList.contains('is-active'));

    const setActive = (): void => {
      const currentPath = window.location.pathname.replace(/\/+$/, '') || '/home';

      navItems.forEach(item => {
        const itemPath = new URL(item.getAttribute('href') || '', location.origin).pathname.replace(/\/+$/, '') || '/home';
        item.classList.toggle('active', itemPath === currentPath);
      });
    };

    const syncTabIndex = (): void => {
      if (window.innerWidth > 1024) {
        setMenuState(false);
        navItems.forEach(item => item.setAttribute('tabindex', '0'));
      } else setMenuState(menuHamburger.classList.contains('is-active'));
    };

    menuHamburger.addEventListener('click', toggle);
    document.querySelectorAll('a.nav-item').forEach(link => link.addEventListener('click', () => {
      if (window.innerWidth <= 1024) setMenuState(false);
    }));
    window.addEventListener('resize', syncTabIndex);

    setActive();
    syncTabIndex();
  }
};
