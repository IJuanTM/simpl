import {inputModule} from './input';

const messageTextarea = document.querySelector('textarea.message-field') as HTMLTextAreaElement | null;
const clearMessageButton = document.querySelector('p.clear-message') as HTMLElement | null;

export const messageModule = {
  updateClearButton: (): void => {
    if (!messageTextarea || !clearMessageButton) return;

    if (messageTextarea.value.length > 0) clearMessageButton.removeAttribute('inert');
    else clearMessageButton.setAttribute('inert', '');
  },

  clear: (): void => {
    if (!messageTextarea || !clearMessageButton) return;

    messageTextarea.value = '';
    inputModule.checkMessageLength(messageTextarea);
    clearMessageButton.setAttribute('inert', '');
  },

  init: (): void => {
    if (!messageTextarea || !clearMessageButton) return;

    messageTextarea.addEventListener('keyup', () => {
      inputModule.checkMessageLength(messageTextarea);
      messageModule.updateClearButton();
    });

    clearMessageButton.addEventListener('click', messageModule.clear);
  }
};
