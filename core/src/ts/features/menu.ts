export const menuModule = {
  init(): void {
    const navMenu = document.querySelector<HTMLElement>('nav.menu');
    const menuHamburger = document.querySelector<HTMLElement>('button.hamburger');
    if (!navMenu || !menuHamburger) return;

    const navItems = navMenu.querySelectorAll<HTMLElement>('.nav-item');

    const setMenuState = (isOpen: boolean): void => {
      menuHamburger.classList.toggle('is-active', isOpen);
      navMenu.classList.toggle('extended', isOpen);

      if (isOpen) menuHamburger.setAttribute('aria-expanded', 'true');
      else menuHamburger.removeAttribute('aria-expanded');

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
    document.querySelectorAll('a.nav-item').forEach(link => link.addEventListener('click', toggle));
    window.addEventListener('resize', syncTabIndex);

    setActive();
    syncTabIndex();
  }
};
