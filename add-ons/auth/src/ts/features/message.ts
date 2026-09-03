import {inputModule} from './input.ts';

export const messageModule = {
  init(): void {
    const messageTextarea = document.querySelector<HTMLTextAreaElement>('textarea.message-field');
    const clearButton = document.querySelector<HTMLElement>('p.clear-message');
    if (!messageTextarea || !clearButton) return;

    const syncClearButton = (): void => {
      clearButton.toggleAttribute('inert', messageTextarea.value.length === 0);
    };

    messageTextarea.addEventListener('keyup', () => {
      inputModule.checkMessageLength(messageTextarea);
      syncClearButton();
    });

    clearButton.addEventListener('click', () => {
      messageTextarea.value = '';
      inputModule.checkMessageLength(messageTextarea);
      syncClearButton();
    });
  }
};
