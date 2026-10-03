import {formValidationModule, initialValue} from './form-validation.ts';

const LOOKALIKES: Record<string, string> = {'0': 'o', '1': 'i', '3': 'e', '4': 'a', '5': 's', '7': 't', 'l': 'i'};

// Must stay in sync with AuthController::normalizeUsername().
function normalizeUsername(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9]/g, '').replace(/[013457l]/g, char => LOOKALIKES[char] ?? char);
}

const reservedSets = new WeakMap<HTMLInputElement, Set<string>>();

function reservedSet(input: HTMLInputElement, json: string): Set<string> {
  let set = reservedSets.get(input);
  if (!set) {
    set = new Set((JSON.parse(json) as string[]).map(normalizeUsername));
    reservedSets.set(input, set);
  }
  return set;
}

function reservedError(input: HTMLInputElement): string {
  const {reserved, reservedMessage} = input.dataset;

  // The server only checks a changed value, so a reserved name an admin assigned can still be saved unchanged.
  if (!reserved || !reservedMessage || input.value === initialValue(input)) return '';

  return reservedSet(input, reserved).has(normalizeUsername(input.value)) ? reservedMessage : '';
}

export const reservedUsernameModule = {
  init(): void {
    formValidationModule.addRule(reservedError);
  }
};
