import {prefersReducedMotion} from './motion.ts';

export type ToastType = 'success' | 'warning' | 'error' | 'info';

type Toast = { message: string; type: ToastType; timeoutMs: number; elapsedMs?: number };

const CARRY_KEY = 'toasts';

function toastStack(): HTMLElement | null {
  return document.querySelector<HTMLElement>('section.toasts');
}

// Animates the toasts that stay from their old position to their new one, so they slide into place instead of jumping.
function slide(stack: HTMLElement, change: () => void): void {
  const before = new Map([...stack.children].map(toast => [toast, toast.getBoundingClientRect().top]));
  change();
  if (prefersReducedMotion()) return;

  before.forEach((top, toast) => {
    const offset = top - toast.getBoundingClientRect().top;
    if (toast.isConnected && offset) toast.animate([{translate: `0 ${offset}px`}, {translate: '0 0'}], {duration: 300, easing: 'ease-in-out'});
  });
}

function dismissToast(toast: HTMLElement): void {
  const stack = toastStack();
  if (!stack || toast.classList.contains('leaving')) return;

  toast.classList.add('leaving');
  // Closing the stack while the last toast is still fading lets its shadow fade out at the same time.
  if ([...stack.children].every(child => child.classList.contains('leaving'))) stack.hidePopover();
  if (prefersReducedMotion()) return slide(stack, () => toast.remove());
  // transitioncancel covers browsers without allow-discrete, where hiding the stack cuts the fade short.
  for (const event of ['transitionend', 'transitioncancel'] as const) toast.addEventListener(event, e => {
    if (e.target === toast && e.propertyName === 'opacity' && toast.isConnected) slide(stack, () => toast.remove());
  });
}

// The top layer stacks by insertion order, so a modal opened after the toasts would cover them.
// Re-showing the stack popover moves it back to the front, above any open dialog.
export function raiseToasts(): void {
  const stack = toastStack();
  if (!stack?.matches(':popover-open')) return;

  stack.hidePopover();
  stack.showPopover();
}

function addToast({message, type, timeoutMs, elapsedMs = 0}: Toast, carried = false): void {
  const stack = toastStack();
  const toast = document.querySelector<HTMLTemplateElement>('template#toast-template')?.content.firstElementChild?.cloneNode(true);
  if (!stack || !(toast instanceof HTMLElement)) return;

  toast.classList.add(type);
  toast.classList.toggle('carried', carried);
  toast.dataset.type = type;
  toast.querySelector('.toast-message')!.textContent = message;
  toast.querySelector('button.toast-close')!.addEventListener('click', () => dismissToast(toast));

  if (!stack.matches(':popover-open')) stack.showPopover();
  slide(stack, () => stack.prepend(toast));

  const timer = toast.querySelector('svg.toast-timer')!;
  if (timeoutMs <= 0) return timer.remove();

  const countdown = timer.firstElementChild!.animate([{strokeDashoffset: 0}, {strokeDashoffset: 1}], {duration: timeoutMs, fill: 'forwards'});
  countdown.currentTime = elapsedMs;
  countdown.finished.then(() => dismissToast(toast));

  // Deferred so :hover and :focus-within already reflect where the pointer and focus ended up.
  const sync = (): void => {
    if (toast.matches(':hover, :focus-within')) countdown.pause();
    else countdown.play();
  };
  for (const event of ['pointerenter', 'pointerleave', 'focusin', 'focusout']) toast.addEventListener(event, () => setTimeout(sync));
}

export function showToast(message: string, type: ToastType = 'info', timeoutMs = 6000): void {
  addToast({message, type, timeoutMs});
}

// Toasts still on screen when the user leaves the page continue on the next one with the time they had left.
export function carryToasts(): void {
  const carried = JSON.parse(sessionStorage.getItem(CARRY_KEY) ?? '[]') as Toast[];
  sessionStorage.removeItem(CARRY_KEY);

  const stack = toastStack();
  if (stack && carried.length) {
    stack.classList.add('carried');
    stack.addEventListener('toggle', e => {
      if ((e as ToggleEvent).newState === 'closed') stack.classList.remove('carried');
    });
  }
  carried.forEach(toast => addToast(toast, true));

  addEventListener('pagehide', () => {
    const open = [...document.querySelectorAll<HTMLElement>('section.toasts > div.toast:not(.leaving)')].reverse().map((toast): Toast => {
      const countdown = toast.querySelector('svg.toast-timer > circle')?.getAnimations()[0];
      return {
        message: toast.querySelector('.toast-message')!.textContent ?? '',
        type: toast.dataset.type as ToastType,
        timeoutMs: Number(countdown?.effect?.getTiming().duration ?? 0),
        elapsedMs: Number(countdown?.currentTime ?? 0)
      };
    });
    if (open.length) sessionStorage.setItem(CARRY_KEY, JSON.stringify(open));
  });

  // A page restored from the back/forward cache still shows its own toasts, so the copy saved when leaving it would duplicate them.
  addEventListener('pageshow', e => {
    if (e.persisted) sessionStorage.removeItem(CARRY_KEY);
  });
}
