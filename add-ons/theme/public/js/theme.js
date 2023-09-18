const themes = [
  'light',
  'dark'
];

// On load, set the theme
document.addEventListener('DOMContentLoaded', () => {
  if (!localStorage.getItem('theme')) localStorage.setItem('theme', themes[0]);

  document.documentElement.dataset.theme = localStorage.getItem('theme');
});
