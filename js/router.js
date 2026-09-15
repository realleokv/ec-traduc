export function initRouter() {
    // Delegação de eventos: muito mais robusto e captura cliques em ícones/SVGs internos
    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-page]');
        if (link) {
            e.preventDefault();
            const pageId = link.getAttribute('data-page');
            if (pageId) goToPage(pageId);
        }
    });

    // Suporte ao botão voltar do navegador e links diretos
    window.addEventListener('popstate', () => {
        const hash = window.location.hash.substring(1) || 'home';
        renderPage(hash);
    });

    // Inicialização: carrega a página correta baseada no hash ou padrão para home
    const initialPage = window.location.hash.substring(1) || 'home';
    renderPage(initialPage);
}

export function goToPage(pageId) {
    // Atualiza o histórico apenas se for uma nova página
    if (window.location.hash !== `#${pageId}`) {
        window.history.pushState(null, '', `#${pageId}`);
    }
    renderPage(pageId);
}

// Função interna que apenas cuida da troca visual
function renderPage(pageId) {
    const pages = document.querySelectorAll('.page');
    const navLinks = document.querySelectorAll('[data-page]');

    // 1. Troca a visibilidade das páginas
    let found = false;
    pages.forEach(p => {
        if (p.id === `page-${pageId}`) {
            p.classList.add('active');
            found = true;
        } else {
            p.classList.remove('active');
        }
    });

    // Se a página não existir (ex: erro de hash), volta pra home
    if (!found && pageId !== 'home') {
        return goToPage('home');
    }

    // 2. Atualiza o estado visual dos links (underline)
    navLinks.forEach(l => {
        if (l.getAttribute('data-page') === pageId) {
            l.classList.add('active');
            l.setAttribute('aria-current', 'page');
        } else {
            l.classList.remove('active');
            l.removeAttribute('aria-current');
        }
    });

    // 3. Scroll suave para o topo
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
