export const verificationModule = {
  init(): void {
    const codeInput = document.querySelector<HTMLInputElement>('#code');
    if (!codeInput) return;

    const submitButton = codeInput.form?.querySelector<HTMLButtonElement>('button[type="submit"]');
    const gateSubmit = (): void => submitButton?.toggleAttribute('inert', !codeInput.value.trim());

    const digitInputs = document.querySelectorAll<HTMLInputElement>('input.digit');

    if (!digitInputs.length) {
      // Recovery mode: one visible field is the code, no digit boxes to mirror.
      codeInput.addEventListener('input', gateSubmit);
      gateSubmit();
      return;
    }

    const syncHidden = (): void => {
      codeInput.value = Array.from(digitInputs, input => input.value).join('');
      gateSubmit();
    };

    const fill = (chars: string): void => {
      digitInputs.forEach((digit, i) => {
        digit.value = chars[i] ?? '';
      });
      syncHidden();
    };

    // #code carries a server-rendered value after a failed submit.
    if (codeInput.value) fill(codeInput.value);
    gateSubmit();

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

        if (codeInput.form && submitButton) codeInput.form.requestSubmit(submitButton);
      });
    });
  }
};
