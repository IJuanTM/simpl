export const
  profileImage = document.querySelector('form.profile-image') as HTMLFormElement | null,
  editProfileImage = document.querySelector('button.profile-action.edit') as HTMLFormElement | null,
  profileImageInput = profileImage?.querySelector('input[type="file"]') as HTMLInputElement | null;

export const setProfileImage = () => {
  profileImage?.classList.add('loading');

  const file = profileImageInput?.files![0] as File;

  if (!file) return profileImage?.classList.remove('loading');

  if (file.size > 2 * 1024 * 1024) {
    profileImage?.classList.remove('loading');

    return alert('The image size is too large. Please choose an image that is less than 2MB.');
  }

  if (file.type.startsWith('image/')) {
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

          canvas.toBlob(blob => {
            const formData = new FormData(profileImage!);

            formData.append('new_img', blob!, `${formData.get('id')}-${Date.now()}.png`);

            fetch('/api/profile/update-profile-image', {
              method: 'POST',
              body: formData
            }).then(response => {
              if (response.ok) window.location.reload();
              else alert('An error occurred while uploading the image. Please try again.');
            });
          });
        }
      }
    }

    reader.readAsDataURL(file);
  } else {
    alert('The file you selected is not an image. Please select an image file.');

    profileImage?.classList.remove('loading');
  }
}
