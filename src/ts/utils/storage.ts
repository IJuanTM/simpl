export const storage = {
  get: (key: string): string | null => localStorage.getItem(key),
  set: (key: string, value: string): void => localStorage.setItem(key, value),
  has: (key: string): boolean => localStorage.getItem(key) !== null
};