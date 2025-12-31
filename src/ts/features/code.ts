export const codeModule = {
  onLoad: (): void => {
    document.querySelectorAll('.code-block').forEach(container => {
      const codeElement = container.querySelector('code');
      const copyButton = container.querySelector('.copy-code') as HTMLElement | null;
      const copyIcon = copyButton?.querySelector('i') as HTMLElement | null;

      if (codeElement) {
        codeElement.addEventListener('click', () => {
          const selection = window.getSelection();
          if (!selection) return;

          const range = document.createRange();
          range.selectNodeContents(codeElement);
          selection.removeAllRanges();
          selection.addRange(range);
        });
      }

      if (copyButton && copyIcon && codeElement) {
        copyButton.addEventListener('click', (e) => {
          e.stopPropagation();

          const text = codeElement.textContent || '';
          const updateIcon = () => {
            copyIcon.classList.replace('fa-copy', 'fa-check');
            setTimeout(() => copyIcon.classList.replace('fa-check', 'fa-copy'), 2000);
          };

          if (navigator.clipboard?.writeText) navigator.clipboard.writeText(text).then(updateIcon);
          else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.setAttribute('readonly', '');

            document.body.appendChild(textarea);

            textarea.select();
            document.execCommand('copy');

            document.body.removeChild(textarea);

            updateIcon();
          }
        });
      }
    });
  }
};
