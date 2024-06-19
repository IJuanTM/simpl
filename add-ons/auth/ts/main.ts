import {capsLockWarning, checkMessageLength, inputPassword, passwordToggleIcon, togglePassword} from './input.js';

// -------------------------------------------------------------------------------------------------------------------------------- //

const inputFields = document.querySelectorAll('input, textarea, select') as NodeListOf<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>;

if (inputFields) inputFields.forEach(field => field.addEventListener('keydown', () => {
  const inputGroup = field.closest('div.input-group');

  // Remove error class from input group
  if (inputGroup) inputGroup.classList.remove('error');
}));

if (inputPassword) {
  // On click, toggle the password visibility
  passwordToggleIcon.addEventListener('click', togglePassword);

  // On keydown, check if the caps lock key is on
  inputPassword.addEventListener('keydown', (event: KeyboardEvent) => capsLockWarning(event));
}

const
  messageTextarea = document.querySelector('textarea.message-field') as HTMLTextAreaElement,
  clearMessageButton = document.querySelector('p.clear-message');

if (messageTextarea && clearMessageButton) {
  messageTextarea.addEventListener('keyup', event => {
    // Check the message length
    checkMessageLength({target: event.target as HTMLTextAreaElement});

    // If the message length is greater than 0, remove the inert attribute from the clear message button
    // Else, add the inert attribute to the clear message button
    if (messageTextarea.value.length > 0) clearMessageButton.removeAttribute('inert');
    else clearMessageButton.setAttribute('inert', '');
  });

  clearMessageButton.addEventListener('click', () => {
    // Clear the message textarea
    messageTextarea.value = '';

    // Check the message length
    checkMessageLength({target: messageTextarea});

    // Add the inert attribute to the clear message button
    clearMessageButton.setAttribute('inert', '');
  });
}

// -------------------------------------------------------------------------------------------------------------------------------- //

const
  editProfileButton = document.querySelector('button.edit-profile'),
  editUserButton = document.querySelector('button.edit-user');

if (editProfileButton || editUserButton) {
  const currentValues: { [key: string]: string } = {};

  inputFields.forEach(field => {
    // Set the current values of the input fields
    currentValues[field.name] = field.value;

    ['keyup', 'change'].forEach(event => field.addEventListener(event, () => {
      let changed = false;

      // Check if the values of the input fields have changed
      inputFields.forEach(field => {
        if (field.value !== currentValues[field.name]) changed = true;
      });

      // If the values have changed, remove the inert attribute from the edit profile button or the edit user button
      // Else, add the inert attribute to the edit profile button or the edit user button
      if (changed) {
        if (editUserButton) editUserButton.removeAttribute('inert');
        else if (editProfileButton) editProfileButton.removeAttribute('inert');
      } else {
        if (editUserButton) editUserButton.setAttribute('inert', '');
        else if (editProfileButton) editProfileButton.setAttribute('inert', '');
      }
    }));
  });
}

const
  deleteCheckbox = document.querySelector('input.delete-checkbox') as HTMLInputElement,
  deleteUserButton = document.querySelector('button.delete-user') as HTMLButtonElement;

// On change, set or remove the inert attribute from the delete user button depending on whether the checkbox is checked
if (deleteCheckbox) deleteCheckbox.addEventListener('change', () => deleteCheckbox.checked
  ? deleteUserButton.removeAttribute('inert')
  : deleteUserButton.setAttribute('inert', ''));
