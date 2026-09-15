const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------
   Reveal-on-scroll: elementos marcados com data-reveal ou
   data-reveal-group recebem .is-visible quando entram na tela.
   ------------------------------------------------------------ */
export function initRevealAnimations() {
    const targets = document.querySelectorAll('[data-reveal], [data-reveal-group]');
    if (!targets.length) return;

    if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
        targets.forEach(el => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });

    targets.forEach(el => observer.observe(el));
}

/* ------------------------------------------------------------
   Sequência cinematográfica do Hero: separa o H1 em palavras
   para revelar em cascata, depois libera o restante do timeline
   via a classe .hero-loaded no <body> (controlada por CSS).
   ------------------------------------------------------------ */
export function initHeroSequence() {
    const heading = document.querySelector('.hero-txt h1');

    if (heading && !heading.dataset.split) {
        const text = heading.innerHTML;
        // Preserva a quebra manual (<span class="accent-line">...</span>) e separa por palavra.
        const wordify = (str) => str
            .split(' ')
            .filter(Boolean)
            .map(word => `<span class="word"><span>${word}</span></span>`)
            .join(' ');

        const wrapped = text
            .split(/(<span[^>]*>.*?<\/span>)/g)
            .map(chunk => {
                const match = chunk.match(/^(<span[^>]*>)(.*?)(<\/span>)$/);
                if (match) {
                    return `${match[1]}${wordify(match[2])}${match[3]}`;
                }
                return wordify(chunk);
            })
            .join(' ');

        heading.innerHTML = wrapped;
        heading.dataset.split = 'true';

        heading.querySelectorAll('.word > span').forEach((span, i) => {
            span.style.transitionDelay = `${0.14 + i * 0.055}s`;
        });
    }

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.body.classList.add('hero-loaded');
        });
    });
}

/* ------------------------------------------------------------
   Header: estado ao rolar (fundo + esconder/mostrar) e menu
   mobile com abertura/fechamento animados.
   ------------------------------------------------------------ */
export function initHeader() {
    const header = document.getElementById('hd');
    if (!header) return;

    const nav = header.querySelector('nav');
    const burger = header.querySelector('.hd-burger');
    let lastScroll = window.scrollY;

    function onScroll() {
        const y = window.scrollY;
        header.classList.toggle('is-scrolled', y > 40);

        if (!header.classList.contains('menu-open')) {
            if (y > lastScroll && y > 200) {
                header.classList.add('is-hidden');
            } else {
                header.classList.remove('is-hidden');
            }
        }
        lastScroll = y;
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (burger && nav) {
        burger.addEventListener('click', () => {
            const isOpen = nav.classList.toggle('is-open');
            burger.classList.toggle('is-open', isOpen);
            burger.setAttribute('aria-expanded', String(isOpen));
            header.classList.toggle('menu-open', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        });

        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                nav.classList.remove('is-open');
                burger.classList.remove('is-open');
                burger.setAttribute('aria-expanded', 'false');
                header.classList.remove('menu-open');
                document.body.style.overflow = '';
            });
        });

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                burger.classList.remove('is-open');
                burger.setAttribute('aria-expanded', 'false');
                header.classList.remove('menu-open');
                document.body.style.overflow = '';
            }
        });
    }
}

/* ------------------------------------------------------------
   Vídeo de fundo da seção #about: troca a fonte conforme o
   tema ativo (padrão / noturno / máxima acessibilidade).
   Chamada sempre que um dos dois toggles de tema muda de
   estado, e também na carga inicial de cada um deles.
   ------------------------------------------------------------ */
const ABOUT_BG_VIDEOS = {
    default: 'https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260901_122529_931c22c8-8d2d-47c0-ad51-b97f56a91e42.mp4',
    night: './video/about-bg-night.mp4',
    access: './video/about-bg-access.mp4'
};

function updateAboutBgVideo() {
    const video = document.querySelector('.about-bg');
    if (!video) return;

    const isAccess = document.body.classList.contains('theme-max-access');
    const isNight = document.documentElement.getAttribute('data-theme') === 'night-purple';

    // Máxima acessibilidade tem prioridade se os dois estiverem ativos.
    const targetSrc = isAccess
        ? ABOUT_BG_VIDEOS.access
        : (isNight ? ABOUT_BG_VIDEOS.night : ABOUT_BG_VIDEOS.default);

    const source = video.querySelector('source');
    if (!source || source.getAttribute('src') === targetSrc) return;

    const wasPlaying = !video.paused;

    // Trocar o src e chamar load() zera o frame atual do <video>, então ele
    // fica preto até baixar dados suficientes do novo arquivo. Para evitar
    // esse flash, escondemos o vídeo (revelando o fundo da seção) e só o
    // exibimos de novo quando o novo arquivo já tiver um frame pronto.
    video.classList.add('is-switching');

    const reveal = () => {
        video.removeEventListener('loadeddata', reveal);
        video.removeEventListener('error', handleError);
        video.classList.remove('is-switching');
    };

    // Se o arquivo do tema falhar ao carregar (rede, caminho incorreto etc.),
    // volta para o vídeo padrão em vez de deixar o fundo travado sem vídeo.
    const handleError = () => {
        video.removeEventListener('loadeddata', reveal);
        video.removeEventListener('error', handleError);
        if (targetSrc !== ABOUT_BG_VIDEOS.default && source.getAttribute('src') === targetSrc) {
            source.setAttribute('src', ABOUT_BG_VIDEOS.default);
            video.load();
            video.addEventListener('loadeddata', reveal, { once: true });
            if (wasPlaying) {
                video.play().catch(() => {});
            }
        } else {
            video.classList.remove('is-switching');
        }
    };

    video.addEventListener('loadeddata', reveal, { once: true });
    video.addEventListener('error', handleError, { once: true });

    source.setAttribute('src', targetSrc);
    video.load();
    if (wasPlaying) {
        video.play().catch(() => {});
    }
}

/* ------------------------------------------------------------
   Alternância de tema claro/escuro.
   ------------------------------------------------------------ */
export function initThemeToggle() {
    const btn = document.getElementById('theme-toggle');
    const logo = document.getElementById('logoid');
    const logo2 = document.getElementById('logoid2');
    if (!btn) return;

    function applyTheme(theme) {
        if (theme === 'night-purple') {
            document.documentElement.setAttribute('data-theme', 'night-purple');
            if (logo) logo.src = './img/logo.png';
            if (logo2) logo2.src = './img/logo.png';
            btn.textContent = '☀';
        } else {
            document.documentElement.removeAttribute('data-theme');
            if (logo) logo.src = './img/logo_sun.png';
            if (logo2) logo2.src = './img/logo_sun.png';
            btn.textContent = '☾';
        }
        updateAboutBgVideo();
    }

    const saved = localStorage.getItem('ec_theme') || 'default';
    applyTheme(saved);

    btn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'night-purple' ? 'default' : 'night-purple';
        localStorage.setItem('ec_theme', next);
        applyTheme(next);
    });
}

/* ------------------------------------------------------------
   Botões magnéticos: leve atração ao cursor, apenas em
   dispositivos com mouse de verdade.
   ------------------------------------------------------------ */
export function initMagneticButtons() {
    if (prefersReducedMotion() || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return;
    }

    const buttons = document.querySelectorAll('.btn-p, .btn-s, .btn-agendar');

    buttons.forEach(btn => {
        let rect = null;

        btn.addEventListener('mouseenter', () => {
            rect = btn.getBoundingClientRect();
        });

        btn.addEventListener('mousemove', (e) => {
            if (!rect) rect = btn.getBoundingClientRect();
            const relX = e.clientX - rect.left - rect.width / 2;
            const relY = e.clientY - rect.top - rect.height / 2;
            btn.style.transform = `translate(${relX * 0.18}px, ${relY * 0.35}px)`;
        });

        btn.addEventListener('mouseleave', () => {
            btn.style.transform = '';
            rect = null;
        });
    });
}

/* ------------------------------------------------------------
   Parallax sutil da imagem do hero em resposta ao cursor.
   ------------------------------------------------------------ */
export function initHeroParallax() {
    if (prefersReducedMotion() || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return;
    }

    const frame = document.querySelector('.hero-img .frame');
    const heroSection = document.getElementById('hero');
    if (!frame || !heroSection) return;

    heroSection.addEventListener('mousemove', (e) => {
        const rect = heroSection.getBoundingClientRect();
        const relX = (e.clientX - rect.left) / rect.width - 0.5;
        const relY = (e.clientY - rect.top) / rect.height - 0.5;
        frame.style.transform = `translate(${relX * 14}px, ${relY * 14}px)`;
    });

    heroSection.addEventListener('mouseleave', () => {
        frame.style.transform = '';
    });
}

/* ------------------------------------------------------------
   Tema de Máxima Acessibilidade (Botão Fixo com Clique Longo - 2s)
   ------------------------------------------------------------ */
export function initMaxAccessibility() {
    const btn = document.getElementById('btn-max-access');
    if (!btn) return;

    let holdTimer = null;
    const HOLD_DURATION = 1000;

    // Restaura preferência salva
    if (localStorage.getItem('theme-max-access') === 'true') {
        document.body.classList.add('theme-max-access');
    }
    updateAboutBgVideo();

    function startHold(e) {
        e.preventDefault();
        btn.classList.add('is-holding');

        holdTimer = setTimeout(() => {
            const active = document.body.classList.toggle('theme-max-access');
            localStorage.setItem('theme-max-access', active);
            updateAboutBgVideo();

            cancelHold();

            if (navigator.vibrate) {
                navigator.vibrate(100);
            }
        }, HOLD_DURATION);
    }

    function cancelHold() {
        btn.classList.remove('is-holding');
        if (holdTimer) {
            clearTimeout(holdTimer);
            holdTimer = null;
        }
    }

    btn.addEventListener('pointerdown', startHold);
    btn.addEventListener('pointerup', cancelHold);
    btn.addEventListener('pointerleave', cancelHold);
    btn.addEventListener('pointercancel', cancelHold);
}