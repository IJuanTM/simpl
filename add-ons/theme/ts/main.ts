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
  const
    currentTheme = localStorage.getItem('theme') || 'light',
    nextTheme = themes[themes.findIndex((theme: { name: string; }) => theme.name === currentTheme) + 1] || themes[0]!;

  // Set the theme in localStorage
  localStorage.setItem('theme', nextTheme.name);

  // Set the theme icon
  themeSwitch.innerHTML = `<i class="fas ${nextTheme.icon}"></i>`;

  // Set the theme
  document.documentElement.dataset.theme = localStorage.getItem('theme')!;
});

// -------------------------------------------------------------------------------------------------------------------------------- //

// On load, set the theme
window.addEventListener('load', () => {
  const currentTheme = localStorage.getItem('theme') || 'light';

  // Set the theme icon
  themeSwitch.innerHTML = `<i class="fas ${themes[themes.findIndex(theme => theme.name === currentTheme)]!.icon}"></i>`;
});
