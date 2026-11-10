// Shrinks a chosen photo in the browser before it is uploaded, and shows a preview.
//
// Why: phone photos are often 3 to 8 MB, but the server accepts at most 2 MB.
// Drawing the photo onto a smaller <canvas> and saving that as a JPEG usually gives
// a file under 200 KB. Uploads are faster on mobile data, the size limit is not hit,
// and details stored inside the original photo, such as the GPS location where it
// was taken, are left behind.
//
// How to use it on a file input:
//   <input type="file" data-resize-max="400" data-preview="#some-img">
//   data-resize-max     longest side, in pixels, of the image that gets uploaded
//   data-preview        CSS selector of the <img> that shows the chosen photo
//   data-preview-hides  (optional) selector of an element to hide once the preview shows
//
// The server checks every upload again (includes/uploads.php). This script makes
// uploading nicer for the user; it is not a security check.

document.querySelectorAll('input[type="file"][data-resize-max]').forEach((input) => {
    input.addEventListener('change', () => handleChosenFile(input));
});

async function handleChosenFile(input) {
    const file = input.files[0];
    if (!file) {
        return;
    }

    // Keep the form from being submitted while the photo is still being processed.
    const submitButton = input.form.querySelector('button[type="submit"]');
    submitButton.disabled = true;

    try {
        const smaller = await resizeImage(file, Number(input.dataset.resizeMax));

        // Swap the chosen file for the smaller one. For security, JavaScript may not
        // write to an input's file list directly; a DataTransfer object is the
        // supported way to build a new list.
        const transfer = new DataTransfer();
        transfer.items.add(smaller);
        input.files = transfer.files;

        showPreview(input, smaller);
    } catch (error) {
        // The browser could not read the file as an image. Leave it as chosen:
        // the server will reject it and tell the user why.
    } finally {
        submitButton.disabled = false;
    }
}

// Returns a JPEG copy of the image whose longest side is at most maxSide pixels.
async function resizeImage(file, maxSide) {
    const image = await loadImage(file);

    // Scale so the longest side becomes maxSide. Math.min(1, ...) means a small
    // image is never enlarged.
    const scale = Math.min(1, maxSide / Math.max(image.naturalWidth, image.naturalHeight));

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(image.naturalWidth * scale);
    canvas.height = Math.round(image.naturalHeight * scale);

    const context = canvas.getContext('2d');
    // JPEG has no transparency, so paint white behind see-through PNGs first.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    URL.revokeObjectURL(image.src);

    // 0.85 is the JPEG quality: a good balance between file size and sharpness.
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
    if (!blob) {
        throw new Error('The browser could not create the smaller image.');
    }

    return new File([blob], 'photo.jpg', { type: 'image/jpeg' });
}

// Loads a file into an <img> element, so the browser decodes it for us.
function loadImage(file) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('The browser cannot read this file as an image.'));
        image.src = URL.createObjectURL(file);
    });
}

function showPreview(input, file) {
    const preview = document.querySelector(input.dataset.preview);
    if (!preview) {
        return;
    }

    preview.src = URL.createObjectURL(file);
    preview.hidden = false;

    if (input.dataset.previewHides) {
        document.querySelector(input.dataset.previewHides).hidden = true;
    }
}
