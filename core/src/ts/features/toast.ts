import {carryToasts, showToast, type ToastType} from '../helpers/toast.ts';

export const toastModule = {
  init(): void {
    carryToasts();

    const stack = document.querySelector<HTMLElement>('section.toasts');
    if (!stack?.dataset.toasts) return;

    const toasts = JSON.parse(stack.dataset.toasts) as { message: string; type: ToastType; timeout: number }[];
    toasts.forEach(toast => showToast(toast.message, toast.type, toast.timeout * 1000));
  }
};
