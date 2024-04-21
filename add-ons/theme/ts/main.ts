const
  themeSwitch = document.querySelector('div.theme-switch'),
  themes = [
    'light',
    'dark'
  ];

themeSwitch.addEventListener('click', () => {
  // Set the theme in localStorage
  localStorage.setItem('theme', themes[themes.indexOf(localStorage.getItem('theme')) + 1] || themes[0]);

  // Set the theme icon
  setThemeIcon();

  // Set the theme
  document.documentElement.dataset.theme = localStorage.getItem('theme');
});

const setThemeIcon = () => {
  // Remove the hidden class from all icons
  themeSwitch.querySelectorAll('i').forEach(icon => icon.classList.remove('hidden'));

  // Add the hidden class to the icon of the current theme
  switch (localStorage.getItem('theme')) {
    case themes[0]:
      themeSwitch.querySelector('i.fa-sun').classList.add('hidden');
      break;
    case themes[1]:
      themeSwitch.querySelector('i.fa-moon').classList.add('hidden');
      break;
  }
}

// -------------------------------------------------------------------------------------------------------------------------------- //

// On load, set the theme
window.addEventListener('load', setThemeIcon);
