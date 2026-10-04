import {showToast} from '../helpers/toast.ts';

type ContactResponse = { message?: string; alerts?: string; cooldown?: number };

function lockFor(button: HTMLButtonElement, ms: number): void {
  if (ms <= 0) return;
  button.inert = true;
  setTimeout(() => button.inert = false, ms);
}

export const contactModule = {
  init(): void {
    const modal = document.querySelector<HTMLDialogElement>('#contact-modal');
    const form = modal?.querySelector<HTMLFormElement>('#contact-form');
    const intro = modal?.querySelector<HTMLElement>('[data-contact-intro]');
    if (!modal || !form || !intro) return;

    const showAlerts = (html: string): void => {
      modal.querySelector('.form-alerts')?.remove();
      if (html) intro.insertAdjacentHTML('afterend', html);
    };

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = event.submitter as HTMLButtonElement | null;
      // Read before disabling the button, since a disabled submitter is left out of the form data.
      const body = new FormData(form, button);
      // disabled rather than inert: timeout.ts clears inert from the submit button of any cancelled submit.
      if (button) button.disabled = true;

      try {
        // A followed redirect would render a page unseen, using up its queued toasts and passing as a successful send.
        const response = await fetch(form.action, {method: 'POST', body, redirect: 'error'});
        const data: ContactResponse = await response.json().catch(() => ({}));

        if (response.ok) {
          showAlerts('');
          form.reset();
          modal.close();
          showToast(data.message ?? 'Your message has been sent!', 'success');
        } else if (response.status === 403) {
          showToast('Your session has expired. Reload the page and try again.', 'warning');
        } else if (data.alerts) {
          showAlerts(data.alerts);
        } else {
          showToast('Your message could not be sent. Please try again.', 'error');
        }

        if (button) lockFor(button, data.cooldown ?? 0);
      } catch {
        showToast('Your message could not be sent. Please check your connection and try again.', 'error');
      } finally {
        if (button) button.disabled = false;
      }
    });

    // The message counter only updates on input, which a reset doesn't fire.
    form.addEventListener('reset', () => setTimeout(() => modal.querySelector('textarea')?.dispatchEvent(new Event('input'))));
  }
};
