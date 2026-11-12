// Photo cropper ([data-photo-crop], the student's profile photo window).
// Pick a photo, then drag / zoom / rotate it under a round mask. On send, the square crop is turned into a
// 600 × 600 JPG and put back in the file field, so the server receives exactly what the student framed.
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const OUTPUT = 600;
const sizeText = (bytes) => (bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

document.querySelectorAll('[data-photo-crop]').forEach((root) => {
    const form = root.closest('form');
    const input = root.querySelector('input[type="file"]');
    const drop = root.querySelector('[data-file-drop]');
    const stage = root.querySelector('[data-crop-stage]');
    const image = root.querySelector('[data-crop-image]');
    const zoom = root.querySelector('[data-crop-zoom]');
    const name = root.querySelector('[data-crop-name]');
    const submit = document.querySelector(`[form="${form.id}"][type="submit"]`);
    let cropper = null;
    let baseRatio = 1;
    let source = null;   // the file the student picked (kept so "Change photo" and re-sending work)

    const reset = () => {
        cropper?.destroy();
        cropper = null;
        source = null;
        stage.hidden = true;
        drop.hidden = false;
        if (submit) submit.disabled = true;
    };

    const open = (file) => {
        source = file;
        cropper?.destroy();
        image.src = URL.createObjectURL(file);
        name.textContent = `${file.name} · ${sizeText(file.size)}`;
        drop.hidden = true;
        stage.hidden = false;
        zoom.value = 1;
        cropper = new Cropper(image, {
            aspectRatio: 1,
            viewMode: 1,            // the photo always covers the circle
            dragMode: 'move',       // drag the photo, not the frame
            autoCropArea: 0.9,
            cropBoxMovable: false,
            cropBoxResizable: false,
            toggleDragModeOnDblclick: false,
            guides: false,
            center: false,
            background: false,
            zoomOnWheel: true,
            ready() { baseRatio = cropper.getCanvasData().width / cropper.getImageData().naturalWidth; },
            zoom(event) {
                // Keep the slider in step with wheel / pinch zoom (1× = fitted, up to 3×).
                const value = event.detail.ratio / baseRatio;
                if (value < 1 || value > 3) { event.preventDefault(); return; }
                zoom.value = value.toFixed(2);
            },
        });
        if (submit) submit.disabled = false;
    };

    // A file chosen in the drop box (browse or drag) goes straight into the cropper.
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (file && file.type.startsWith('image/')) open(file);
    });

    zoom.addEventListener('input', () => cropper?.zoomTo(baseRatio * Number(zoom.value)));
    root.querySelector('[data-crop-zoom-out]').addEventListener('click', () => { zoom.value = Math.max(1, Number(zoom.value) - 0.25); zoom.dispatchEvent(new Event('input')); });
    root.querySelector('[data-crop-zoom-in]').addEventListener('click', () => { zoom.value = Math.min(3, Number(zoom.value) + 0.25); zoom.dispatchEvent(new Event('input')); });
    root.querySelector('[data-crop-rotate]').addEventListener('click', () => cropper?.rotate(90));
    root.querySelector('[data-crop-change]').addEventListener('click', () => { input.value = ''; input.click(); });
    const clear = () => { input.value = ''; input.dispatchEvent(new Event('change')); reset(); };   // the drop box resets too
    root.querySelector('[data-crop-remove]').addEventListener('click', clear);

    form.addEventListener('submit', (event) => {
        if (!cropper || form.dataset.cropped === '1') return;   // nothing to crop, or already done
        event.preventDefault();
        const canvas = cropper.getCroppedCanvas({ width: OUTPUT, height: OUTPUT, fillColor: '#ffffff', imageSmoothingQuality: 'high' });
        canvas.toBlob((blob) => {
            const list = new DataTransfer();
            list.items.add(new File([blob], (source?.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
            input.files = list.files;
            form.dataset.cropped = '1';
            form.requestSubmit(submit || undefined);
        }, 'image/jpeg', 0.9);
    });

    // Closing the window throws the unsent crop away.
    root.closest('.modal')?.addEventListener('hidden.bs.modal', () => { clear(); delete form.dataset.cropped; });
    reset();
});
