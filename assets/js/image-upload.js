// Shrinks chosen photos in the browser before they are uploaded, and shows previews.
//
// Why: phone photos are often 3 to 8 MB, but the server accepts at most 2 MB each.
// Drawing a photo onto a smaller <canvas> and saving that as a JPEG usually gives a
// file of a few hundred KB or less. Uploads are faster on mobile data, the size
// limit is not hit, and details stored inside the original photo, such as the GPS
// location where it was taken, are left behind.
//
// How to use it on a file input:
//   <input type="file" data-resize-max="400" data-preview="#some-img">
//   <input type="file" multiple data-resize-max="1200" data-max-files="5"
//          data-preview-list="#some-div">
//
//   data-resize-max     longest side, in pixels, of each image that gets uploaded
//   data-max-files      how many photos to keep (default 1)
//   data-preview        selector of one <img> that shows the chosen photo
//   data-preview-hides  (optional) selector of an element to hide once that preview shows
//   data-preview-list   selector of a container that gets one small <img> per photo
//
// The server checks every upload again (includes/uploads.php). This script makes
// uploading nicer for the user; it is not a security check.

document.querySelectorAll('input[type="file"][data-resize-max]').forEach((input) => {
    input.addEventListener('change', () => handleChosenFiles(input));
});

async function handleChosenFiles(input) {
    const chosen = Array.from(input.files);
    const maxFiles = Number(input.dataset.maxFiles || 1);
    const maxSide = Number(input.dataset.resizeMax);

    // Keep the form from being submitted while the photos are still being processed.
    const submitButton = input.form.querySelector('button[type="submit"]');
    submitButton.disabled = true;

    // For security, JavaScript may not write to an input's file list directly.
    // A DataTransfer object is the supported way to build a new list.
    const transfer = new DataTransfer();
    const previewable = [];

    for (const file of chosen.slice(0, maxFiles)) {
        try {
            const smaller = await resizeImage(file, maxSide);
            transfer.items.add(smaller);
            previewable.push(smaller);
        } catch (error) {
            // The browser could not read this file as an image. Send it unchanged:
            // the server will reject it and tell the user why.
            transfer.items.add(file);
        }
    }

    input.files = transfer.files;
    showPreviews(input, previewable, chosen.length > maxFiles);
    submitButton.disabled = false;
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

function showPreviews(input, files, someWereDropped) {
    // One fixed <img>, used for the profile photo.
    const single = input.dataset.preview ? document.querySelector(input.dataset.preview) : null;
    if (single && files.length > 0) {
        single.src = URL.createObjectURL(files[0]);
        single.hidden = false;

        if (input.dataset.previewHides) {
            document.querySelector(input.dataset.previewHides).hidden = true;
        }
    }

    // A container that gets one small <img> per photo, used for listing photos.
    const list = input.dataset.previewList ? document.querySelector(input.dataset.previewList) : null;
    if (list) {
        list.replaceChildren();

        files.forEach((file, index) => {
            const thumb = document.createElement('img');
            thumb.src = URL.createObjectURL(file);
            thumb.alt = 'Photo ' + (index + 1);
            thumb.className = 'photo-preview';
            list.append(thumb);
        });

        if (someWereDropped) {
            const note = document.createElement('p');
            note.className = 'hint';
            note.textContent = 'Only the first ' + input.dataset.maxFiles + ' photos were kept.';
            list.append(note);
        }
    }
}
