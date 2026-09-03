function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function logout(): Promise<void> {
  try {
    await fetch('/api/logout', {method: 'POST', headers: {'X-CSRF-Token': csrfToken()}});
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
