export const verificationModule = {
  init(): void {
    const codeInput = document.querySelector<HTMLInputElement>('#code');
    const digitInputs = document.querySelectorAll<HTMLInputElement>('input.digit');
    if (!codeInput || !digitInputs.length) return;

    const syncHidden = (): void => {
      codeInput.value = Array.from(digitInputs, input => input.value).join('');
    };

    const fill = (chars: string): void => {
      Array.from(chars).forEach((char, i) => {
        const digit = digitInputs.item(i);
        if (digit) digit.value = char;
      });
      syncHidden();
    };

    // #code carries a server-rendered value after a failed submit.
    if (codeInput.value) fill(codeInput.value);

    digitInputs.forEach((input, index) => {
      input.addEventListener('input', () => {
        input.value = input.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase();
        if (input.value.length === 1) digitInputs.item(index + 1)?.focus();
        syncHidden();
      });

      input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && input.value === '') digitInputs.item(index - 1)?.focus();
      });

      input.addEventListener('paste', e => {
        e.preventDefault();

        const pasted = e.clipboardData?.getData('text');
        if (!pasted) return;

        fill(pasted.replace(/[^0-9A-Za-z]/g, '').toUpperCase());

        const firstEmpty = Array.from(digitInputs).find(digit => !digit.value);
        if (firstEmpty) {
          firstEmpty.focus();
          return;
        }

        digitInputs.item(digitInputs.length - 1)?.focus();

        const submitButton = codeInput.form?.querySelector<HTMLButtonElement>('button[type="submit"]');
        if (codeInput.form && submitButton) codeInput.form.requestSubmit(submitButton);
      });
    });
  }
};
