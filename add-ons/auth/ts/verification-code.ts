export const handleVerificationCode = (): void => {
  const
    codeInput = document.getElementById('code') as HTMLInputElement | null,
    digitInputs = document.querySelectorAll('input.digit') as NodeListOf<HTMLInputElement>;

  if (!codeInput || !digitInputs.length) return;

  // Pre-fill from existing value
  if (codeInput.value) [...codeInput.value].forEach((char, i) => i < digitInputs.length && (digitInputs[i]!.value = char));

  // Update hidden input
  const updateHiddenInput = () => codeInput.value = Array.from(digitInputs).map(input => input.value).join('');

  // Handle input in digit fields
  digitInputs.forEach((input, index) => {
    // Handle input and focus next field
    input.addEventListener('input', () => {
      input.value = input.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase();

      if (input.value.length === 1 && index < digitInputs.length - 1) digitInputs[index + 1].focus();

      updateHiddenInput();
    });

    // Handle backspace
    input.addEventListener('keydown', (e: KeyboardEvent) => {
      if (e.key === 'Backspace' && input.value === '' && index > 0) digitInputs[index - 1]!.focus();
    });
  });

  // Handle paste event
  digitInputs.forEach(input => {
    input.addEventListener('paste', (e: ClipboardEvent) => {
      e.preventDefault();
      const pasteData = (e.clipboardData || (window as any).clipboardData).getData('text');

      if (!pasteData) return;

      // Process pasted content
      const alphanumericData = pasteData.replace(/[^0-9A-Za-z]/g, '').toUpperCase();
      [...alphanumericData].forEach((char, i) => i < digitInputs.length && (digitInputs[i]!.value = char));

      updateHiddenInput();

      // Focus next empty input or last input
      const nextEmpty = Array.from(digitInputs).findIndex(input => !input.value);
      nextEmpty !== -1 ? digitInputs[nextEmpty]!.focus() : digitInputs[digitInputs.length - 1]!.focus();
    });
  });
};
