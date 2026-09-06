export const menuModule = {
  init(): void {
    const navMenu = document.querySelector<HTMLElement>('nav.menu');
    const hamburger = document.querySelector<HTMLElement>('button.hamburger');
    if (!navMenu || !hamburger) return;

    const navItems = navMenu.querySelectorAll<HTMLElement>('.nav-item');
    const desktop = matchMedia('(min-width: 64rem)');

    const setOpen = (open: boolean): void => {
      hamburger.classList.toggle('is-active', open);
      hamburger.setAttribute('aria-expanded', String(open));
      navMenu.classList.toggle('extended', open);
      navMenu.toggleAttribute('inert', !open && !desktop.matches);
    };

    const normalizePath = (url: string): string => new URL(url, location.origin).pathname.replace(/\/+$/, '') || '/home';

    const current = normalizePath(location.href);
    navItems.forEach(item => {
      const href = item.getAttribute('href');
      if (href !== null) item.classList.toggle('active', normalizePath(href) === current);
    });

    hamburger.addEventListener('click', () => setOpen(!hamburger.classList.contains('is-active')));
    navItems.forEach(item => item.addEventListener('click', () => {
      if (!desktop.matches) setOpen(false);
    }));
    desktop.addEventListener('change', () => setOpen(false));

    setOpen(false);
  }
};
