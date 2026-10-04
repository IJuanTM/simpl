import {csrfToken} from '../helpers/csrf.ts';

async function logout(): Promise<void> {
  try {
    // Not following the redirect keeps the target page from rendering unseen and using up the "logged out" toast.
    await fetch('/api/logout', {method: 'POST', headers: {'X-CSRF-Token': csrfToken()}, redirect: 'manual'});
  } catch {
    // Ignore network errors and navigate away regardless.
  }

  window.location.href = '/';
}

export const logoutModule = {
  init(): void {
    document.querySelectorAll<HTMLButtonElement>('[data-logout]').forEach(button => button.addEventListener('click', logout));
  }
};
