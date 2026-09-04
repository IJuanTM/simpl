import {showAlert} from '../helpers/alert.ts';

function cropToSquarePng(file: File): Promise<Blob | null> {
  return new Promise(resolve => {
    const reader = new FileReader();

    reader.onload = () => {
      const image = new Image();

      image.onload = () => {
        const size = Math.min(image.width, image.height);
        const x = (image.width - size) / 2;
        const y = (image.height - size) / 2;

        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = size;

        const context = canvas.getContext('2d');
        if (!context) return resolve(null);

        context.drawImage(image, x, y, size, size, 0, 0, size, size);
        canvas.toBlob(blob => resolve(blob));
      };

      image.onerror = () => resolve(null);
      image.src = reader.result as string;
    };

    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}

export const profileImageModule = {
  init(): void {
    const form = document.querySelector<HTMLFormElement>('form.profile-image');
    const editButton = document.querySelector<HTMLButtonElement>('button.profile-action.edit');
    const fileInput = form?.querySelector<HTMLInputElement>('input[type="file"]');
    if (!form || !editButton || !fileInput) return;

    const fail = (message: string): void => {
      form.classList.remove('loading');
      showAlert(message, 'error');
    };

    const upload = async (): Promise<void> => {
      form.classList.add('loading');

      const file = fileInput.files?.[0];
      if (!file) {
        form.classList.remove('loading');
        return;
      }

      const maxSizeMb = Number(fileInput.dataset.maxSizeMb ?? 2);
      if (file.size > maxSizeMb * 1024 * 1024) return fail(`The image size is too large. Please choose an image that is less than ${maxSizeMb}MB.`);
      if (!file.type.startsWith('image/')) return fail('The file you selected is not an image. Please select an image file.');

      const blob = await cropToSquarePng(file);
      if (!blob) return fail('Failed to process the image. Please try again.');

      const formData = new FormData(form);
      formData.append('new_img', blob, `${formData.get('id')}-${Date.now()}.png`);

      try {
        const response = await fetch(`/api/user/${formData.get('id')}/update-profile-image`, {method: 'POST', body: formData});
        if (response.ok) window.location.reload();
        else fail('An error occurred while uploading the image. Please try again.');
      } catch {
        fail('An error occurred while uploading the image. Please try again.');
      }
    };

    editButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', upload);
  }
};
