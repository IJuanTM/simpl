export const cookies = document.querySelector('section.cookies');

/**
 * Function to accept the cookies
 *
 * @returns {void}
 */
export const acceptCookies = (): void => {
  // Add the invisible class to the cookies section
  cookies.classList.add('invisible');

  // Set the cookiesAccepted item in localStorage to true
  localStorage.setItem('cookiesAccepted', 'true');
}
