let beeCursor = null;

let mouseX = 0;
let mouseY = 0;

let currentX = 0;
let currentY = 0;

let rotation = 0;
let targetRotation = 0;

let rafId = null;

export function initCursor() {
    // Cursor customizado só faz sentido com mouse de verdade.
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return;
    }

    beeCursor = document.getElementById('bee-cursor');
    if (!beeCursor) return;

    document.body.classList.add('has-custom-cursor');

    window.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;

        const dx = mouseX - currentX;
        const dy = mouseY - currentY;

        if (Math.abs(dx) > 1 || Math.abs(dy) > 1) {
            targetRotation = Math.atan2(dy, dx) * 0 / Math.PI + 330;
        }
    }, { passive: true });

    window.addEventListener('mousedown', () => beeCursor.classList.add('flapping'));
    window.addEventListener('mouseup', () => beeCursor.classList.remove('flapping'));

    document.addEventListener('mouseleave', () => setCursorVisibility(false));
    document.addEventListener('mouseenter', () => setCursorVisibility(true));

    if (!rafId) animateCursor();
}

function animateCursor() {
    currentX += (mouseX - currentX) * 0.58;
    currentY += (mouseY - currentY) * 0.58;

    let difference = targetRotation - rotation;
    while (difference > 180) difference -= 360;
    while (difference < -180) difference += 360;
    rotation += difference * 0.15;

    if (beeCursor) {
        beeCursor.style.transform =
            `translate3d(${currentX}px, ${currentY}px, 0) translate(-50%, -50%) rotate(${rotation}deg)`;
    }

    rafId = requestAnimationFrame(animateCursor);
}

export function setCursorVisibility(visible) {
    if (!beeCursor) return;
    beeCursor.style.opacity = visible ? '1' : '0';
    beeCursor.style.visibility = visible ? 'visible' : 'hidden';
}