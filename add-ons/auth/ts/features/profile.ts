const profileImage = document.querySelector('form.profile-image') as HTMLFormElement | null;
const editProfileImage = document.querySelector('button.profile-action.edit') as HTMLButtonElement | null;
const profileImageInput = profileImage?.querySelector('input[type="file"]') as HTMLInputElement | null;

export const profileModule = {
  processImage: async (file: File): Promise<Blob | null> => {
    return new Promise((resolve) => {
      const reader = new FileReader();

      reader.onload = () => {
        const image = new Image();
        image.src = reader.result as string;

        image.onload = () => {
          const size = Math.min(image.width, image.height);
          const x = (image.width - size) / 2;
          const y = (image.height - size) / 2;

          const canvas = document.createElement('canvas');
          canvas.width = size;
          canvas.height = size;

          const context = canvas.getContext('2d');

          if (context) {
            context.drawImage(image, x, y, size, size, 0, 0, size, size);
            canvas.toBlob(blob => resolve(blob));
          } else resolve(null);
        };
      };

      reader.readAsDataURL(file);
    });
  },

  uploadImage: async (): Promise<void> => {
    if (!profileImage || !profileImageInput) return;

    profileImage.classList.add('loading');

    const file = profileImageInput.files?.[0];

    if (!file) {
      profileImage.classList.remove('loading');
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      profileImage.classList.remove('loading');
      alert('The image size is too large. Please choose an image that is less than 2MB.');
      return;
    }

    if (!file.type.startsWith('image/')) {
      profileImage.classList.remove('loading');
      alert('The file you selected is not an image. Please select an image file.');
      return;
    }

    const blob = await profileModule.processImage(file);

    if (!blob) {
      profileImage.classList.remove('loading');
      alert('Failed to process the image. Please try again.');
      return;
    }

    const formData = new FormData(profileImage);
    formData.append('new_img', blob, `${formData.get('id')}-${Date.now()}.png`);

    try {
      const response = await fetch('/api/profile/update-profile-image', {
        method: 'POST',
        body: formData
      });

      if (response.ok) window.location.reload();
      else alert('An error occurred while uploading the image. Please try again.');
    } catch {
      profileImage.classList.remove('loading');
      alert('An error occurred while uploading the image. Please try again.');
    }
  },

  init: (): void => {
    if (!editProfileImage || !profileImageInput) return;

    editProfileImage.addEventListener('click', () => profileImageInput.click());
    profileImageInput.addEventListener('change', profileModule.uploadImage);
  }
};