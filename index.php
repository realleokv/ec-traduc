<?php
session_start();
require_once 'conexao.php';

$mensagem = $_SESSION['mensagem'] ?? '';
unset($_SESSION['mensagem']);

$HORA_INICIO_AGENDA = '08:00';
$HORA_FIM_AGENDA    = '18:00';
$DURACAO_SLOT       = 300; // minutos

$stmt_agenda = $pdo->query("
    SELECT id, data_inicio, data_fim, status, observacao
    FROM agenda_disponibilidade
    WHERE status IN ('ocupado', 'bloqueado')
      AND data_fim >= NOW()
    ORDER BY data_inicio ASC
");

$indisponibilidades = $stmt_agenda->fetchAll(PDO::FETCH_ASSOC);

$horarios_livres = [];

$hoje = new DateTime('today');
$data_final = new DateTime('+365 days');

while ($hoje <= $data_final) {

    $dia_semana = (int)$hoje->format('N');

    if ($dia_semana <= 5) {

        $data = $hoje->format('Y-m-d');

        $inicio_dia = new DateTime($data . ' ' . $HORA_INICIO_AGENDA);
        $fim_dia    = new DateTime($data . ' ' . $HORA_FIM_AGENDA);

        $slot_inicio = clone $inicio_dia;

        while ($slot_inicio < $fim_dia) {

            $slot_fim = clone $slot_inicio;
            $slot_fim->modify("+{$DURACAO_SLOT} minutes");

            if ($slot_fim > $fim_dia) {
                break;
            }

            $inicio_str = $slot_inicio->format('Y-m-d H:i:s');
            $fim_str    = $slot_fim->format('Y-m-d H:i:s');

            $livre = true;

            foreach ($indisponibilidades as $evento) {

                $evento_inicio = strtotime($evento['data_inicio']);
                $evento_fim    = strtotime($evento['data_fim']);

                $slot_inicio_ts = strtotime($inicio_str);
                $slot_fim_ts    = strtotime($fim_str);

                if (
                    $evento_inicio < $slot_fim_ts &&
                    $evento_fim > $slot_inicio_ts
                ) {
                    $livre = false;
                    break;
                }
            }

            if ($livre) {
                $horarios_livres[] = [
                    'data_inicio' => $inicio_str,
                    'data_fim'    => $fim_str,
                    'status'      => 'disponivel',
                    'observacao'  => ''
                ];
            }

            $slot_inicio = $slot_fim;
        }
    }

    $hoje->modify('+1 day');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_agendamento'])) {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $servico = trim($_POST['servico'] ?? '');
    $data = $_POST['data'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fim = $_POST['hora_fim'] ?? '';
    $detalhes = trim($_POST['detalhes'] ?? '');

    if (
        empty($nome) ||
        empty($email) ||
        empty($telefone) ||
        empty($servico) ||
        empty($data) ||
        empty($hora_inicio) ||
        empty($hora_fim)
    ) {

        $_SESSION['mensagem'] = "Preencha todos os campos obrigatórios.";

    } else {

        $inicio_solicitado = $data . ' ' . $hora_inicio . ':00';
        $fim_solicitado = $data . ' ' . $hora_fim . ':00';

        $inicio_ts = strtotime($inicio_solicitado);
        $fim_ts = strtotime($fim_solicitado);

        $hora_inicio_config = strtotime($data . ' ' . $HORA_INICIO_AGENDA . ':00');
        $hora_fim_config = strtotime($data . ' ' . $HORA_FIM_AGENDA . ':00');

        $dia_semana = (int)date('N', $inicio_ts);

        if ($fim_ts <= $inicio_ts) {

            $_SESSION['mensagem'] = "O horário de término deve ser depois do horário de início.";

        } elseif ($inicio_ts < $hora_inicio_config || $fim_ts > $hora_fim_config) {

            $_SESSION['mensagem'] = "O horário escolhido está fora do horário de atendimento.";

        } elseif ($dia_semana > 5) {

            $_SESSION['mensagem'] = "A agenda não está disponível aos finais de semana.";

        } elseif ($inicio_ts < time()) {

            $_SESSION['mensagem'] = "Não é possível solicitar um horário que já passou.";

        } else {

            $stmt_conflito = $pdo->prepare("
                SELECT COUNT(*) 
                FROM agenda_disponibilidade
                WHERE status IN ('ocupado', 'bloqueado')
                  AND data_inicio < :fim
                  AND data_fim > :inicio
            ");

            $stmt_conflito->execute([
                ':inicio' => $inicio_solicitado,
                ':fim' => $fim_solicitado
            ]);

            $conflito = (int)$stmt_conflito->fetchColumn();

            if ($conflito > 0) {

                $_SESSION['mensagem'] = "Esse horário acabou de ficar indisponível. Escolha outro.";

            } else {

                $stmt_pedido = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM solicitacoes_agendamento
                    WHERE status IN ('pendente', 'aceito')
                      AND data_solicitada = :data
                      AND horario_inicio < :hora_fim
                      AND horario_fim > :hora_inicio
                ");

                $stmt_pedido->execute([
                    ':data' => $data,
                    ':hora_inicio' => $hora_inicio,
                    ':hora_fim' => $hora_fim
                ]);

                $pedido_existente = (int)$stmt_pedido->fetchColumn();

                if ($pedido_existente > 0) {

                    $_SESSION['mensagem'] = "Esse horário já possui uma solicitação. Escolha outro.";

                } else {

                    $sql = "
                        INSERT INTO solicitacoes_agendamento
                        (
                            nome_cliente,
                            email_cliente,
                            telefone_cliente,
                            tipo_servico,
                            data_solicitada,
                            horario_inicio,
                            horario_fim,
                            detalhes
                        )
                        VALUES
                        (
                            :nome,
                            :email,
                            :telefone,
                            :servico,
                            :data,
                            :hora_inicio,
                            :hora_fim,
                            :detalhes
                        )
                    ";

                    $stmt = $pdo->prepare($sql);

                    $executado = $stmt->execute([
                        ':nome' => $nome,
                        ':email' => $email,
                        ':telefone' => $telefone,
                        ':servico' => $servico,
                        ':data' => $data,
                        ':hora_inicio' => $hora_inicio,
                        ':hora_fim' => $hora_fim,
                        ':detalhes' => $detalhes
                    ]);

                    if ($executado) {

                        $_SESSION['mensagem'] = "Solicitação enviada com sucesso! Aguarde a confirmação de Elisa.";

                    } else {

                        $_SESSION['mensagem'] = "Erro ao enviar solicitação. Tente novamente.";
                    }
                }
            }
        }
    }

    header("Location: index.php#agenda");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>EC Traduções - Intérprete de Libras</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,340..600;1,9..144,340..600&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="style.css">

  <meta name="description" content="Intérprete de Libras especializada em promover acessibilidade, inclusão e comunicação entre pessoas surdas e ouvintes.">
  <meta name="keywords" content="libras, intérprete, acessibilidade, inclusão, comunicação">

  <meta property="og:title" content="EC Traduções - Intérprete de Libras">
  <meta property="og:description" content="Intérprete de Libras especializada em promover acessibilidade, inclusão e comunicação entre pessoas surdas e ouvintes.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.example.com">
  <meta property="og:image" content="https://www.example.com/image.jpg">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="EC Traduções - Intérprete de Libras">
  <meta name="twitter:description" content="Intérprete de Libras especializada em promover acessibilidade, inclusão e comunicação entre pessoas surdas e ouvintes.">
  <meta name="twitter:image" content="https://www.example.com/image.jpg">

  <link rel="icon" type="image" src="./img/logo_sun.png">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>

<body>

<div id="bee-cursor" aria-hidden="true">
    <svg viewBox="0 0 512 512" width="48" height="48" xmlns="http://www.w3.org/2000/svg">
        <path class="hand" style="fill:#F2F4F6;stroke:#8A9099;stroke-width:10;stroke-linejoin:round;stroke-linecap:round;" d="
            M180,280
            C180,250 180,210 180,190
            C180,175 190,165 205,165
            C220,165 230,175 230,190
            L230,260
            L410,150
            C420,143 433,145 440,155
            C447,165 445,178 435,185
            L300,275
            L340,260
            C352,255 366,260 371,272
            C376,284 371,297 359,302
            L330,314
            L362,304
            C374,300 387,306 391,318
            C395,330 389,343 377,347
            L345,358
            L365,353
            C377,350 389,357 392,368
            C395,380 388,392 376,395
            L300,415
            C290,440 265,460 235,462
            C210,464 185,458 168,442
            C150,425 145,405 145,385
            L145,340
            C145,320 155,300 180,285
            Z"></path>

        <path class="thumb-crease" style="fill:none;stroke:#8A9099;stroke-width:7;stroke-linecap:round;" d="M200,340 C195,365 200,390 220,405"></path>

        <path class="palm-crease" style="fill:none;stroke:#8A9099;stroke-width:6;stroke-linecap:round;" d="M235,425 C255,435 280,432 300,418"></path>

        <g class="knuckles" style="fill:#D9DDE2;">
            <circle cx="300" cy="330" r="14"></circle>
            <circle cx="330" cy="360" r="14"></circle>
            <circle cx="358" cy="390" r="14"></circle>
        </g>

        <g class="click-lines" style="stroke:#D0D6DC;stroke-width:8;stroke-linecap:round;opacity:0.6;">
            <line x1="400" y1="410" x2="440" y2="395"></line>
            <line x1="395" y1="440" x2="435" y2="440"></line>
            <line x1="385" y1="465" x2="415" y2="490"></line>
        </g>
    </svg>
</div>

<header id="hd">

    <div class="logo">
        <img src="./img/logo_sun.png" alt="EC Traduções Logo" id="logoid">
    </div>

    <!-- Atualização dos botões e links de navegação no header -->
<nav>
    <ul>
        <li><a href="#hero">Início</a></li>
        <li><a href="#services">Serviços</a></li>
        <li><a href="#projects">Projetos</a></li>
        <li><a href="#agenda">Agenda</a></li>
        <li><a href="#testimonials">Depoimentos</a></li>
        <li><a href="#contact">Contato</a></li>
        <li class="nav-cta-item"><a href="#agenda">Agendar Serviço</a></li>
    </ul>
</nav>

<div class="hd-actions">
    <button type="button" id="theme-toggle" class="btn-theme-toggle" aria-label="Alternar tema claro/escuro">☾</button>
    <a href="#agenda" class="btn-agendar">Agendar Serviço</a>
    <button type="button" class="hd-burger" aria-label="Abrir menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</div>

</header>

<main>

<?php if ($mensagem): ?>

    <div class="alerta" role="status"><?= htmlspecialchars($mensagem) ?></div>

<?php endif; ?>

<section id="hero" class="sec">

    <svg class="hero-bg-curve" viewBox="0 0 1200 800" preserveAspectRatio="none" aria-hidden="true">
        <path d="M-50,620 C 200,520 350,700 620,560 S 1050,380 1260,480" />
        <path d="M-50,180 C 250,260 420,80 700,160 S 1100,300 1260,220" />
    </svg>

    <div class="hero-txt">

        <span class="eyebrow">Intérprete de Libras</span>

        <h1>Conectando palavras, <span class="accent-line">transformando realidades</span></h1>

        <p class="lede">
            Sou Elisa, intérprete de Libras especializada em promover
            acessibilidade, inclusão e comunicação entre pessoas surdas
            e ouvintes.
        </p>

        <div class="hero-btns">
            <a href="#services" class="btn-p">Meus serviços</a>
            <a href="#contact" class="btn-s">Fale comigo</a>
        </div>

    </div>

    <div class="hero-img">
        <div class="frame">
            <img src="img/elisa.png" alt="Elisa, intérprete de Libras">
        </div>
    </div>

    <div class="hero-stats">

        <article class="st">
            <h3>5+</h3>
            <p>Anos de experiência</p>
        </article>

        <article class="st">
            <h3>100+</h3>
            <p>Clientes atendidos</p>
        </article>

        <article class="st">
            <h3>100%</h3>
            <p>Acessibilidade</p>
        </article>

    </div>

</section>

<section id="services" class="sec sec-alt">
    <div class="sec-inner">

        <div class="sec-hd" data-reveal="up">
            <span class="eyebrow">Serviços</span>
            <h2>O que eu faço</h2>
        </div>

        <div class="svc-list" data-reveal-group>

            <article class="svc-row">
                <h3>Interpretação em Libras</h3>
                <p>Atendimento presencial e remoto para reuniões, palestras, eventos e conferências.</p>
            </article>

            <article class="svc-row">
                <h3>Consultoria em Acessibilidade</h3>
                <p>Orientação para empresas implementarem comunicação inclusiva.</p>
            </article>

            <article class="svc-row">
                <h3>Capacitação em Libras</h3>
                <p>Treinamentos para equipes desenvolverem atendimento acessível.</p>
            </article>

        </div>

    </div>
</section>


<section id="projects" class="sec">
    <div class="sec-inner">

        <div class="sec-hd" data-reveal="up">
            <span class="eyebrow">Projetos</span>
            <h2>Trabalhos realizados</h2>
        </div>

        <div class="grid-3" data-reveal-group>

            <article class="crd-prj">
                <div class="prj-media"><img src="img/projeto1.jpg" alt="Congresso Empresarial"></div>
                <h3>Congresso Empresarial</h3>
                <p>Interpretação simultânea durante evento corporativo.</p>
            </article>

            <article class="crd-prj">
                <div class="prj-media"><img src="img/projeto2.jpg" alt="Treinamento Corporativo"></div>
                <h3>Treinamento Corporativo</h3>
                <p>Capacitação em Libras para colaboradores.</p>
            </article>

            <article class="crd-prj">
                <div class="prj-media"><img src="img/projeto3.jpg" alt="Consultoria"></div>
                <h3>Consultoria</h3>
                <p>Adequação da comunicação para acessibilidade.</p>
            </article>

        </div>

    </div>
</section>

<section id="agenda" class="sec sec-alt">
    <div class="sec-inner">

        <div class="sec-hd center" data-reveal="up">
            <span class="eyebrow">Agenda</span>
            <h2>Disponibilidade</h2>
        </div>

        <div class="status" data-reveal="fade">

            <?php if (!empty($horarios_livres)): ?>
                <span class="on">Disponível para novos atendimentos</span>
            <?php else: ?>
                <span class="off">Agenda temporariamente lotada</span>
            <?php endif; ?>

        </div>

        <div class="calendar-wrapper" data-reveal="scale">

            <div class="calendar-header">
                <button id="cal-prev" class="btn-s" aria-label="Mês anterior">&lt;</button>
                <h3 id="cal-month-year"></h3>
                <button id="cal-next" class="btn-s" aria-label="Próximo mês">&gt;</button>
            </div>

            <div class="calendar-weekdays">
                <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
            </div>

            <div id="calendar-days" class="calendar-days-grid"></div>

            <div id="slots-container" class="slots-container" style="display: none;">
                <h4 id="selected-date-text">Horários livres para o dia selecionado:</h4>
                <div id="slots-list" class="slots-list"></div>
            </div>

        </div>

        <div style="text-align: center; margin-top: 2.5rem;" data-reveal="up">
            <a href="#solicitar-agendamento" class="btn-p">Solicitar agendamento</a>
        </div>

    </div>
</section>



<section id="certs" class="sec">
    <div class="sec-inner">

        <div class="sec-hd" data-reveal="up">
            <span class="eyebrow">Certificações</span>
            <h2>Formação</h2>
        </div>

        <div class="cert-list" data-reveal-group>

            <article class="cert-row">
                <h3>Certificação 01</h3>
                <p>Instituição</p>
            </article>

            <article class="cert-row">
                <h3>Certificação 02</h3>
                <p>Instituição</p>
            </article>

            <article class="cert-row">
                <h3>Curso de Especialização</h3>
                <p>Instituição</p>
            </article>

        </div>

    </div>
</section>

<section id="testimonials" class="sec sec-alt">
    <div class="sec-inner">

        <div class="sec-hd center" data-reveal="up">
            <span class="eyebrow">Depoimentos</span>
            <h2>O que dizem sobre meu trabalho</h2>
        </div>

        <div class="test-list" data-reveal-group>

            <article class="crd-test">
                <span class="quote-mark">"</span>
                <p>Excelente profissional...</p>
                <strong>Cliente</strong>
            </article>

            <article class="crd-test">
                <span class="quote-mark">"</span>
                <p>Muito competente...</p>
                <strong>Cliente</strong>
            </article>

            <article class="crd-test">
                <span class="quote-mark">"</span>
                <p>Recomendo...</p>
                <strong>Cliente</strong>
            </article>

        </div>

    </div>
</section>

<section id="about" class="sec heritage-about" aria-labelledby="about-title">
    <div class="about-media" aria-hidden="true">
        <video class="about-bg" autoplay muted loop playsinline preload="auto"
            poster="https://d2ol7oe51mr4n.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/4f690bd1-881a-4192-82f2-d714d34c8fb9.png">
            <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260901_122529_931c22c8-8d2d-47c0-ad51-b97f56a91e42.mp4" type="video/mp4">
        </video>
    </div>

    <div class="about-inner">
        <div class="about-grid">

            <div class="about-brand">
                <div class="about-brand-lockup about-animate">
                    <img src="img/logo-classic.png" alt="" class="about-brand-logo">
                    <h2 id="about-title" class="about-brand-name">EC Traduções</h2>
                </div>

                <p class="about-blurb about-animate">
                    A EC Traduções promove acessibilidade e inclusão por meio da tradução e interpretação em Libras.
                </p>

                <ul class="about-contact-list about-animate">
                    <li>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h13A2.5 2.5 0 0 1 21 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 18.5v-13Zm2 .2v.3l7 4.8 7-4.8v-.3a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5Zm14 2.7-6.43 4.41a1 1 0 0 1-1.14 0L5 8.4v10.1a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5V8.4Z"/>
                        </svg>
                        <a href="mailto:elisa.ectraducoes@gmail.com">elisa.ectraducoes@gmail.com</a>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M6.62 2.99 9.2 2.4c.65-.15 1.3.2 1.55.81l1.2 2.92c.22.54.06 1.16-.38 1.52L10.1 8.8a15.5 15.5 0 0 0 5.1 5.1l1.15-1.47c.36-.44.98-.6 1.52-.38l2.92 1.2c.61.25.96.9.81 1.55l-.59 2.58a2.5 2.5 0 0 1-2.43 1.94C10.53 19.32 4.68 13.47 4.68 6.42A2.5 2.5 0 0 1 6.62 2.99Z"/>
                        </svg>
                        <a>(11) 97837-9737</a>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 21s7-6.02 7-12a7 7 0 1 0-14 0c0 5.98 7 12 7 12Zm0-9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/>
                        </svg>
                        <a href="https://instagram.com/ec_traducoes" target="_blank" rel="noopener noreferrer">@ec_traducoes</a>
                    </li>
                </ul>
            </div>

            <nav class="about-col about-animate" aria-label="Heritage">
                <h3 class="about-col-title">Elisa Cristina</h3>
                <ul class="about-link-list">
                    <li><a href="#">Inicial</a></li>
                    <li><a href="#">Serviços</a></li>
                    <li><a href="#">Projetos</a></li>
                    <li><a href="#">Agendamento</a></li>
                    <li><a href="#">Social</a></li>
                </ul>
            </nav>
            <div class="about-letter about-animate">
                <div class="about-letter-header">
                    <h3 class="about-col-title">Entre em Contato</h3>
                    <p>
                        Tem alguma dúvida, precisa de uma interpretação ou quer solicitar um orçamento?
                        Envie uma mensagem e fale comigo.
                    </p>
                </div>
                <form class="about-contact-form" action="mailto:elisa.ectraducoes@gmail.com" method="post" enctype="text/plain">
                    <label class="about-sr-only" for="about-nome">Nome</label>
                    <input
                        id="about-nome"
                        type="text"
                        name="Nome"
                        placeholder="Seu nome"
                        autocomplete="name"
                        required
                    >
                    <label class="about-sr-only" for="about-email">E-mail</label>
                    <input
                        id="about-email"
                        type="email"
                        name="E-mail"
                        placeholder="Seu e-mail"
                        autocomplete="email"
                        required
                    >
                    <label class="about-sr-only" for="about-mensagem">Mensagem</label>
                    <textarea
                        id="about-mensagem"
                        name="Mensagem"
                        placeholder="Escreva sua mensagem..."
                        rows="4"
                        required
                    ></textarea>
                    <button type="submit" class="about-contact-submit">
                        <span>Enviar mensagem</span>
                        <svg viewBox="0 0 24 24" fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true">
                            <path d="M4 12h15M13 6l6 6-6 6"/>
                        </svg>
                    </button>
                </form>
            </div>

        </div>
    </div>
</section>

</main>


<!-- Remova a antiga <section id="solicitar-agendamento"> e adicione este Modal antes do final da tag </body> -->
<div id="modal-agendamento" class="modal-overlay" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-labelledby="modal-title">
        <button type="button" class="modal-close" id="btn-close-modal" aria-label="Fechar modal">&times;</button>
        
        <div class="modal-header">
            <span class="eyebrow">Reserva Instantânea</span>
            <h3 id="modal-title">Confirmar Agendamento</h3>
            <div id="modal-slot-info" class="modal-slot-badge"></div>
        </div>

        <form action="index.php#agenda" method="POST" class="modal-form">
            <!-- Campos ocultos preenchidos dinamicamente ao clicar no horário -->
            <input type="hidden" id="form-data" name="data" required>
            <input type="hidden" id="form-hora-inicio" name="hora_inicio" required>
            <input type="hidden" id="form-hora-fim" name="hora_fim" required>

            <div>
                <label for="modal-nome">Seu Nome:</label>
                <input type="text" id="modal-nome" name="nome" placeholder="Digite seu nome completo" required>
            </div>

            <div>
                <label for="modal-email">Seu E-mail:</label>
                <input type="email" id="modal-email" name="email" placeholder="exemplo@email.com" required>
            </div>

            <div>
                <label for="modal-telefone">Telefone / WhatsApp:</label>
                <input type="tel" id="modal-telefone" name="telefone" placeholder="(11) 99999-9999" required>
            </div>

            <div>
                <label for="modal-servico">Tipo de Serviço:</label>
                <select id="modal-servico" name="servico" required>
                    <option value="Interpretação em Libras">Interpretação em Libras</option>
                    <option value="Consultoria em Acessibilidade">Consultoria em Acessibilidade</option>
                    <option value="Capacitação em Libras">Capacitação em Libras</option>
                </select>
            </div>

            <div>
                <label for="modal-detalhes">Detalhes da solicitação:</label>
                <textarea id="modal-detalhes" name="detalhes" rows="3" placeholder="Informe local, tema do evento ou especificações..."></textarea>
            </div>

            <button type="submit" name="solicitar_agendamento" class="btn-p" style="width: 100%; justify-content: center; margin-top: 0.5rem;">
                Enviar Solicitação
            </button>
        </form>
    </div>
</div>

<button id="btn-max-access" class="btn-max-access" aria-label="Segure por 2 segundos para ativar Máxima Acessibilidade">
    <svg class="ring-svg" viewBox="0 0 52 52">
        <circle class="ring-bg" cx="26" cy="26" r="22" />
        <circle class="ring-fill" cx="26" cy="26" r="22" />
    </svg>
    <span class="access-icon">♿</span>
</button>

<script>
    window.dbHorariosLivres = <?= json_encode(
        $horarios_livres,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const alerta = document.querySelector('.alerta');
        if (alerta) {
            setTimeout(() => {
                alerta.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                alerta.style.opacity = '0';
                alerta.style.transform = 'translateY(-10px)';
                
                setTimeout(() => alerta.remove(), 600); // Remove do DOM após a animação
            }, 4000); // 4 segundos visível
        }
    });
</script>

<script type="module" src="./js/main.js"></script>

</body>
</html>