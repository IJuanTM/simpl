const codeInput = document.getElementById('code') as HTMLInputElement | null;
const digitInputs = document.querySelectorAll('input.digit') as NodeListOf<HTMLInputElement>;

export const verificationModule = {
  updateHiddenInput: (): void => {
    if (!codeInput) return;
    codeInput.value = Array.from(digitInputs).map(input => input.value).join('');
  },

  prefillDigits: (): void => {
    if (!codeInput || !codeInput.value) return;
    [...codeInput.value].forEach((char, i) => {
      if (i < digitInputs.length) digitInputs[i].value = char;
    });
  },

  handleInput: (input: HTMLInputElement, index: number): void => {
    input.value = input.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase();

    if (input.value.length === 1 && index < digitInputs.length - 1) {
      digitInputs[index + 1].focus();
    }

    verificationModule.updateHiddenInput();
  },

  handleBackspace: (e: KeyboardEvent, input: HTMLInputElement, index: number): void => {
    if (e.key === 'Backspace' && input.value === '' && index > 0) {
      digitInputs[index - 1].focus();
    }
  },

  handlePaste: (e: ClipboardEvent): void => {
    e.preventDefault();

    const pasteData = e.clipboardData?.getData('text');
    if (!pasteData) return;

    const alphanumericData = pasteData.replace(/[^0-9A-Za-z]/g, '').toUpperCase();

    [...alphanumericData].forEach((char, i) => {
      if (i < digitInputs.length) digitInputs[i].value = char;
    });

    verificationModule.updateHiddenInput();

    const nextEmpty = Array.from(digitInputs).findIndex(input => !input.value);
    if (nextEmpty !== -1) digitInputs[nextEmpty].focus();
    else digitInputs[digitInputs.length - 1].focus();
  },

  init: (): void => {
    if (!codeInput || !digitInputs.length) return;

    verificationModule.prefillDigits();

    digitInputs.forEach((input, index) => {
      input.addEventListener('input', () => verificationModule.handleInput(input, index));
      input.addEventListener('keydown', (e) => verificationModule.handleBackspace(e, input, index));
      input.addEventListener('paste', verificationModule.handlePaste);
    });
  }
};