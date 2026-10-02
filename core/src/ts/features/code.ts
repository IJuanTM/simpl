export const codeModule = {
  init(): void {
    document.querySelectorAll<HTMLElement>('.code-block').forEach(container => {
      const codeElement = container.querySelector('code');
      if (!codeElement) return;

      codeElement.addEventListener('click', () => {
        const selection = window.getSelection();
        if (!selection) return;

        const range = document.createRange();
        range.selectNodeContents(codeElement);
        selection.removeAllRanges();
        selection.addRange(range);
      });

      const copyButton = container.querySelector<HTMLElement>('.copy-code');
      const copyIcon = copyButton?.querySelector('i');
      if (!copyButton || !copyIcon) return;

      copyButton.addEventListener('click', e => {
        e.stopPropagation();

        navigator.clipboard.writeText(codeElement.textContent ?? '').then(() => {
          copyIcon.classList.replace('fa-copy', 'fa-check');
          setTimeout(() => copyIcon.classList.replace('fa-check', 'fa-copy'), 2000);
        }).catch(() => {
        });
      });
    });
  }
};
