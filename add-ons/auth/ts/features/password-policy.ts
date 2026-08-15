function showQuote(quote: HTMLElement, quoteText: HTMLElement, message: string): void {
  quoteText.textContent = message;
  quote.classList.remove('hidden');
}

function hideQuote(quote: HTMLElement): void {
  quote.classList.add('hidden');
}

function initValidatedField(input: HTMLInputElement, quote: HTMLElement, quoteText: HTMLElement, isValid: (value: string) => boolean, message: string): void {
  input.addEventListener('blur', () => {
    if (input.value === '' || isValid(input.value)) hideQuote(quote);
    else showQuote(quote, quoteText, message);
  });

  input.addEventListener('focus', () => hideQuote(quote));
}

function initPasswordField(passwordInput: HTMLInputElement, quote: HTMLElement, quoteText: HTMLElement): void {
  const pattern = passwordInput.dataset.passwordPattern;
  const message = passwordInput.dataset.passwordMessage;
  if (!pattern || !message) return;

  const regex = new RegExp(pattern);
  initValidatedField(passwordInput, quote, quoteText, value => regex.test(value), message);
}

function initPasswordCheckField(checkInput: HTMLInputElement, quote: HTMLElement, quoteText: HTMLElement): void {
  const matchId = checkInput.dataset.match;
  const passwordInput = matchId ? document.getElementById(matchId) as HTMLInputElement | null : null;
  if (!passwordInput) return;

  initValidatedField(checkInput, quote, quoteText, value => value === passwordInput.value, 'The entered passwords do not match.');
}

export const passwordPolicyModule = {
  init: (): void => {
    document.querySelectorAll<HTMLElement>('[data-password-quote]').forEach(quote => {
      const quoteText = quote.querySelector<HTMLElement>('.quote-text');
      const form = quote.closest('form');
      if (!quoteText || !form) return;

      const passwordInput = form.querySelector<HTMLInputElement>('input[data-password-pattern]');
      const checkInput = form.querySelector<HTMLInputElement>('input[data-match]');

      if (passwordInput) initPasswordField(passwordInput, quote, quoteText);
      if (checkInput) initPasswordCheckField(checkInput, quote, quoteText);
    });
  }
};
