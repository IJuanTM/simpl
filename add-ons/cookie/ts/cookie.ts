export const cookie = document.querySelector('section.cookie') as HTMLElement | null;

/**
 * Function to accept the cookies
 *
 * @returns {void}
 */
export const acceptCookies = (): void => {
  // Add the invisible class to the cookies section
  cookie?.classList.add('invisible');

  // Set the cookiesAccepted item in localStorage to true
  localStorage.setItem('cookiesAccepted', 'true');
}
