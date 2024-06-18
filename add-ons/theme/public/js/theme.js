// On load, set the theme
document.addEventListener('DOMContentLoaded', () => document.documentElement.dataset['theme'] = localStorage.getItem('theme') || 'light');
