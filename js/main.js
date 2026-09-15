import { initCursor } from './cursor.js';
import { initCalendar } from './calendar.js';
import {
    initRevealAnimations,
    initHeroSequence,
    initHeader,
    initThemeToggle,
    initMagneticButtons,
    initHeroParallax,
    initMaxAccessibility
} from './animations.js';

// Ativa as animações no CSS
document.documentElement.classList.add('ec-anim');

function init() {
    /* Luz sutil que acompanha o cursor (apenas com mouse de verdade) */
    const glowEl = document.createElement('div');
    glowEl.classList.add('mouse-glow');
    glowEl.setAttribute('aria-hidden', 'true');
    document.body.appendChild(glowEl);

    window.addEventListener('mousemove', (e) => {
        glowEl.style.setProperty('--mouse-x', `${e.clientX}px`);
        glowEl.style.setProperty('--mouse-y', `${e.clientY}px`);
    }, { passive: true });

    initCursor();
    initHeader();
    initThemeToggle();
    initHeroSequence();
    initHeroParallax();
    initMagneticButtons();
    initRevealAnimations();
    initCalendar();
    initMaxAccessibility();
}

// Executa a inicialização garantindo que o DOM esteja pronto, 
// mesmo se o evento DOMContentLoaded já tiver disparado.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}