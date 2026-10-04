function checkMessageLength(target: HTMLTextAreaElement): void {
  const messageWarning = document.querySelector<HTMLElement>('p.message-warning');
  if (!messageWarning) return;

  const lengthSpan = document.querySelector('span.message-length');
  if (lengthSpan) lengthSpan.textContent = String(target.value.length);

  messageWarning.classList.toggle('warning', target.value.length >= target.maxLength - 50);
  messageWarning.classList.toggle('error', target.value.length === target.maxLength);
}

export const messageModule = {
  init(): void {
    const messageTextarea = document.querySelector<HTMLTextAreaElement>('textarea.message-field');
    const clearButton = document.querySelector<HTMLElement>('p.clear-message');
    if (!messageTextarea || !clearButton) return;

    const syncClearButton = (): void => {
      clearButton.toggleAttribute('inert', messageTextarea.value.length === 0);
    };

    // 'input' catches paste/drag-drop/IME changes that 'keyup' misses.
    messageTextarea.addEventListener('input', () => {
      checkMessageLength(messageTextarea);
      syncClearButton();
    });

    clearButton.addEventListener('click', () => {
      messageTextarea.value = '';
      checkMessageLength(messageTextarea);
      syncClearButton();
    });
  }
};
