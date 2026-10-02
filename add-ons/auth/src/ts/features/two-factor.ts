import {bindBackdropClose, openModal} from './modal.ts';

export const twoFactorModule = {
  init(): void {
    // Scoped per .two-fa-card, so a click in one card never affects the other's tab or panel.
    document.querySelectorAll<HTMLElement>('.two-fa-card').forEach(card => {
      const param = card.dataset.tabParam;
      if (!param) return;

      const tabs = card.querySelectorAll<HTMLButtonElement>('[data-tab]');
      const panels = card.querySelectorAll<HTMLElement>('[data-tab-panel]');

      tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.toggle('active', t === tab));
        panels.forEach(panel => panel.hidden = panel.dataset.tabPanel !== tab.dataset.tab);

        // replaceState (not pushState) avoids adding a history entry per click.
        const url = new URL(window.location.href);
        url.searchParams.set(param, tab.dataset.tab ?? '');
        history.replaceState(null, '', url);
      }));
    });

    const recoveryModal = document.querySelector<HTMLDialogElement>('[data-recovery-modal]');
    if (recoveryModal) {
      openModal(recoveryModal);
      bindBackdropClose(recoveryModal);
    }

    const downloadButton = document.querySelector<HTMLButtonElement>('[data-download-codes]');
    if (downloadButton) {
      downloadButton.addEventListener('click', () => {
        const codes = JSON.parse(downloadButton.dataset.codes || '[]') as string[];
        const body = `${downloadButton.dataset.header || ''}\n\n${codes.join('\n')}\n`;
        const url = URL.createObjectURL(new Blob([body], {type: 'text/plain'}));

        const link = document.createElement('a');
        link.href = url;
        link.download = downloadButton.dataset.filename || 'recovery-codes.txt';
        link.click();

        URL.revokeObjectURL(url);
      });
    }

    // A [data-autosave] form has no submit button; each field change POSTs it in the background.
    // form.action is unusable here: a control named "action" clobbers it, so read the attribute.
    document.querySelectorAll<HTMLFormElement>('form[data-autosave]').forEach(form =>
      form.addEventListener('change', () => {
        fetch(form.getAttribute('action') || window.location.href, {method: 'POST', body: new FormData(form)});
      }));

    // An unconfirmed authenticator secret is discarded by any navigation away from the page.
    // This warns before that happens; confirming or cancelling the setup removes the guard again.
    if (document.querySelector('[data-totp-setup]')) {
      const warn = (event: BeforeUnloadEvent): void => {
        event.preventDefault();
        event.returnValue = '';
      };

      window.addEventListener('beforeunload', warn);

      // Any deliberate in-app navigation (a link, the setup buttons, the logout control) drops the guard.
      document.querySelectorAll('a[href], [data-logout], [data-totp-setup] button').forEach(el =>
        el.addEventListener('click', () => window.removeEventListener('beforeunload', warn), {once: true}));
    }
  }
};
