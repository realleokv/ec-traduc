
<?php

session_start();

require_once 'conexao.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_logado'])) {

    header("Location: login.php");
    exit;

}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_solicitacao'])) {

    $id_solicitacao = intval($_POST['id']);

    $acao = $_POST['acao_solicitacao'];


    if ($acao === 'aceitar') {

        $valor = filter_input(
            INPUT_POST,
            'valor',
            FILTER_VALIDATE_FLOAT
        );

        $valor = $valor !== false ? $valor : 0.00;

        $stmt_get = $pdo->prepare("
            SELECT *
            FROM solicitacoes_agendamento
            WHERE id = :id
            LIMIT 1
        ");

        $stmt_get->execute([
            ':id' => $id_solicitacao
        ]);

        $solicitacao = $stmt_get->fetch(PDO::FETCH_ASSOC);


        if ($solicitacao) {

            $inicio =
                $solicitacao['data_solicitada']
                . ' '
                . $solicitacao['horario_inicio']
                . ':00';

            $fim =
                $solicitacao['data_solicitada']
                . ' '
                . $solicitacao['horario_fim']
                . ':00';

            $stmt_conflito = $pdo->prepare("
                SELECT *
                FROM agenda_disponibilidade
                WHERE status IN ('ocupado', 'bloqueado')
                  AND data_inicio < :fim
                  AND data_fim > :inicio
                LIMIT 1
            ");

            $stmt_conflito->execute([
                ':inicio' => $inicio,
                ':fim' => $fim
            ]);

            $conflito = $stmt_conflito->fetch(PDO::FETCH_ASSOC);


            if ($conflito) {


                $mensagem_erro =
                    "Não foi possível aceitar. "
                    . "Esse horário já está ocupado ou bloqueado.";

                $_SESSION['agenda_erro'] = $mensagem_erro;

            } else {

                try {

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        UPDATE solicitacoes_agendamento
                        SET
                            status = 'aceito',
                            valor = :valor
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':valor' => $valor,
                        ':id' => $id_solicitacao
                    ]);

                    $obs =
                        "Serviço: "
                        . $solicitacao['tipo_servico']
                        . " | Cliente: "
                        . $solicitacao['nome_cliente']
                        . " (R$ "
                        . number_format(
                            $valor,
                            2,
                            ',',
                            '.'
                        )
                        . ")";

                    $stmt_ins = $pdo->prepare("
                        INSERT INTO agenda_disponibilidade
                        (
                            data_inicio,
                            data_fim,
                            status,
                            observacao
                        )
                        VALUES
                        (
                            :inicio,
                            :fim,
                            'ocupado',
                            :obs
                        )
                    ");

                    $stmt_ins->execute([
                        ':inicio' => $inicio,
                        ':fim' => $fim,
                        ':obs' => $obs
                    ]);


                    $pdo->commit();


                } catch (Exception $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $_SESSION['agenda_erro'] =
                        "Erro ao confirmar o agendamento.";

                }
            }
        }


    } elseif ($acao === 'recusar') {

        $stmt = $pdo->prepare("
            UPDATE solicitacoes_agendamento
            SET status = 'recusado'
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id_solicitacao
        ]);
    }


    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_evento'])) {

    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?? '';
    $status = $_POST['status'] ?? 'bloqueado';
    $observacao = trim($_POST['observacao'] ?? '');

    if (!in_array($status, ['ocupado', 'bloqueado'], true)) {

        $status = 'bloqueado';
    }

    $inicio_ts = strtotime($data_inicio);
    $fim_ts = strtotime($data_fim);


    if (!$inicio_ts || !$fim_ts || $fim_ts <= $inicio_ts) {

        $_SESSION['agenda_erro'] =
            "Data ou horário inválido.";

    } else {

        $stmt_conflito = $pdo->prepare("
            SELECT *
            FROM agenda_disponibilidade
            WHERE status IN ('ocupado', 'bloqueado')
              AND data_inicio < :fim
              AND data_fim > :inicio
            LIMIT 1
        ");

        $stmt_conflito->execute([
            ':inicio' => date('Y-m-d H:i:s', $inicio_ts),
            ':fim' => date('Y-m-d H:i:s', $fim_ts)
        ]);

        $conflito = $stmt_conflito->fetch(PDO::FETCH_ASSOC);


        if ($conflito) {

            $_SESSION['agenda_erro'] =
                "Esse período já está ocupado ou bloqueado.";

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO agenda_disponibilidade
                (
                    data_inicio,
                    data_fim,
                    status,
                    observacao
                )
                VALUES
                (
                    :inicio,
                    :fim,
                    :status,
                    :obs
                )
            ");

            $stmt->execute([
                ':inicio' =>
                    date(
                        'Y-m-d H:i:s',
                        $inicio_ts
                    ),

                ':fim' =>
                    date(
                        'Y-m-d H:i:s',
                        $fim_ts
                    ),

                ':status' => $status,

                ':obs' => $observacao
            ]);
        }
    }


    header("Location: dashboard.php");
    exit;
}

$agenda_erro = $_SESSION['agenda_erro'] ?? '';

unset($_SESSION['agenda_erro']);

$stmt_fat = $pdo->query("
    SELECT SUM(valor) AS total
    FROM solicitacoes_agendamento
    WHERE status = 'aceito'
");

$faturamento_total =
    $stmt_fat->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;


$stmt_confirmados = $pdo->query("
    SELECT COUNT(*) AS total
    FROM solicitacoes_agendamento
    WHERE status = 'aceito'
");

$total_atendimentos =
    $stmt_confirmados->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;


$pedidos_pendentes = $pdo->query("
    SELECT *
    FROM solicitacoes_agendamento
    WHERE status = 'pendente'
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stmt_dias = $pdo->query("
    SELECT COUNT(DISTINCT DATE(data_inicio)) AS total
    FROM agenda_disponibilidade
    WHERE status = 'ocupado'
");

$dias_ocupados_cnt =
    $stmt_dias->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$todos_eventos = $pdo->query("
    SELECT *
    FROM agenda_disponibilidade
    ORDER BY data_inicio DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$chart_months = [
    "Jan",
    "Fev",
    "Mar",
    "Abr",
    "Mai",
    "Jun",
    "Jul",
    "Ago",
    "Set",
    "Out",
    "Nov",
    "Dez"
];

$chart_ganhos = array_fill(0, 12, 0);


$stmt_mensal = $pdo->query("
    SELECT
        MONTH(data_solicitada) AS mes,
        SUM(valor) AS total
    FROM solicitacoes_agendamento
    WHERE status = 'aceito'
    GROUP BY MONTH(data_solicitada)
");


while ($row = $stmt_mensal->fetch(PDO::FETCH_ASSOC)) {

    $idx = intval($row['mes']) - 1;

    if ($idx >= 0 && $idx < 12) {

        $chart_ganhos[$idx] =
            (float)$row['total'];
    }
}


$chart_ocupacao = array_fill(0, 12, 0);


$stmt_ocup_mes = $pdo->query("
    SELECT
        MONTH(data_inicio) AS mes,
        COUNT(DISTINCT DATE(data_inicio)) AS total
    FROM agenda_disponibilidade
    WHERE status = 'ocupado'
    GROUP BY MONTH(data_inicio)
");


while ($row = $stmt_ocup_mes->fetch(PDO::FETCH_ASSOC)) {

    $idx = intval($row['mes']) - 1;

    if ($idx >= 0 && $idx < 12) {

        $chart_ocupacao[$idx] =
            (int)$row['total'];
    }
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Painel de Controle - Elisa Caires</title>

    <link
        rel="stylesheet"
        href="dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

<div class="dashboard-container">

    <aside class="sidebar">

        <div class="brand">

            <div class="logo-icon">
                EC
            </div>

            <span>
                OLÁ ELISA!
            </span>

        </div>


        <nav class="sidebar-nav">

            <a href="#overview" class="active">
                <i class="fa-solid fa-chart-pie"></i>
                Visão Geral
            </a>

            <a href="#solicitacoes">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Solicitações
            </a>

            <a href="#agenda">
                <i class="fa-solid fa-calendar-days"></i>
                Agenda
            </a>

        </nav>


        <div class="sidebar-footer">

            <a href="logout.php" class="btn-logout">

                <i class="fa-solid fa-right-from-bracket"></i>

                Sair

            </a>

        </div>

    </aside>


    <main class="main-content">


        <header class="topbar">

            <div class="welcome-text">

                <h1>
                    Bem-vinda de volta,
                    <?= htmlspecialchars(
                        $_SESSION['user_nome'] ?? 'Elisa'
                    ) ?>
                    !
                </h1>

                <p>
                    Acompanhe seu faturamento,
                    agenda e novos pedidos em tempo real.
                </p>

            </div>


            <div class="user-profile">

                <img
                    src="img/elisa.png"
                    alt="Elisa Caires"
                    onerror="this.src='https://ui-avatars.com/api/?name=Elisa+Caires&background=7551FF&color=fff'"
                >

            </div>

        </header>


        <?php if ($agenda_erro): ?>

            <div
                class="alerta"
                style="
                    background: #ff5c5c;
                    color: #fff;
                    padding: 1rem;
                    text-align: center;
                    font-weight: bold;
                    margin-bottom: 1rem;
                "
            >

                <?= htmlspecialchars($agenda_erro) ?>

            </div>

        <?php endif; ?>


        <!-- KPIs -->

        <section class="kpi-grid" id="overview">


            <div class="kpi-card">

                <div class="kpi-info">

                    <span>
                        FATURAMENTO TOTAL
                    </span>

                    <h2>
                        R$
                        <?= number_format(
                            $faturamento_total,
                            2,
                            ',',
                            '.'
                        ) ?>
                    </h2>

                </div>

                <div class="kpi-icon icon-purple">

                    <i class="fa-solid fa-wallet"></i>

                </div>

            </div>


            <div class="kpi-card">

                <div class="kpi-info">

                    <span>
                        ATENDIMENTOS
                    </span>

                    <h2>
                        <?= $total_atendimentos ?>
                        confirmados
                    </h2>

                </div>

                <div class="kpi-icon icon-cyan">

                    <i class="fa-solid fa-briefcase"></i>

                </div>

            </div>


            <div class="kpi-card">

                <div class="kpi-info">

                    <span>
                        NOVOS PEDIDOS
                    </span>

                    <h2>
                        <?= count($pedidos_pendentes) ?>
                        pendentes
                    </h2>

                </div>

                <div class="kpi-icon icon-orange">

                    <i class="fa-solid fa-bell"></i>

                </div>

            </div>


            <div class="kpi-card">

                <div class="kpi-info">

                    <span>
                        DIAS OCUPADOS
                    </span>

                    <h2>
                        <?= $dias_ocupados_cnt ?>
                        dias ativos
                    </h2>

                </div>

                <div class="kpi-icon icon-green">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>

            </div>


        </section>


        <!-- GRÁFICOS -->

        <section class="charts-grid">


            <div class="glass-card chart-card">

                <div class="card-header">

                    <h3>
                        <i class="fa-solid fa-chart-line"></i>
                        Evolução de Faturamento (R$)
                    </h3>

                </div>

                <div class="chart-wrapper">

                    <canvas id="faturamentoChart"></canvas>

                </div>

            </div>


            <div class="glass-card chart-card">

                <div class="card-header">

                    <h3>
                        <i class="fa-solid fa-chart-simple"></i>
                        Dias Ocupados na Agenda
                    </h3>

                </div>

                <div class="chart-wrapper">

                    <canvas id="ocupacaoChart"></canvas>

                </div>

            </div>


        </section>


        <!-- SOLICITAÇÕES -->

        <section
            class="glass-card"
            id="solicitacoes"
        >

            <div class="card-header">

                <h3>
                    <i class="fa-solid fa-inbox"></i>
                    Solicitações de Agendamento Pendentes
                </h3>

            </div>


            <?php if (count($pedidos_pendentes) > 0): ?>

                <div class="table-responsive">

                    <table class="vision-table">

                        <thead>

                            <tr>

                                <th>CLIENTE</th>
                                <th>CONTATO</th>
                                <th>SERVIÇO</th>
                                <th>DATA E HORA</th>
                                <th>DETALHES</th>
                                <th style="width: 280px;">
                                    AÇÕES & VALOR
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($pedidos_pendentes as $p): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $p['nome_cliente']
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $p['email_cliente']
                                    ) ?>

                                    <br>

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $p['telefone_cliente']
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <span class="badge badge-purple">

                                        <?= htmlspecialchars(
                                            $p['tipo_servico']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $p['data_solicitada']
                                        )
                                    ) ?>

                                    <br>

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $p['horario_inicio']
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $p['horario_fim']
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <span
                                        class="text-truncate"
                                        title="<?= htmlspecialchars(
                                            $p['detalhes']
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $p['detalhes']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <form
                                        method="POST"
                                        action="dashboard.php"
                                        class="action-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= $p['id'] ?>"
                                        >


                                        <div class="price-input-wrapper">

                                            <span>
                                                R$
                                            </span>

                                            <input
                                                type="number"
                                                step="0.01"
                                                name="valor"
                                                placeholder="0,00"
                                                required
                                                class="input-price"
                                            >

                                        </div>


                                        <button
                                            type="submit"
                                            name="acao_solicitacao"
                                            value="aceitar"
                                            class="btn-action btn-accept"
                                            title="Aceitar com Valor"
                                        >

                                            <i class="fa-solid fa-check"></i>

                                        </button>


                                        <button
                                            type="submit"
                                            name="acao_solicitacao"
                                            value="recusar"
                                            class="btn-action btn-reject"
                                            onclick="return confirm('Recusar esta solicitação?')"
                                            title="Recusar"
                                        >

                                            <i class="fa-solid fa-xmark"></i>

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-regular fa-folder-open"></i>

                    <p>
                        Nenhuma solicitação pendente no momento.
                    </p>

                </div>

            <?php endif; ?>

        </section>


        <!-- AGENDA -->

        <section
            class="grid-two-columns"
            id="agenda"
        >


            <!-- BLOQUEAR / OCUPAR -->

            <div class="glass-card">

                <div class="card-header">

                    <h3>

                        <i class="fa-solid fa-calendar-xmark"></i>

                        Bloquear Horário

                    </h3>

                </div>


                <form
                    method="POST"
                    action="dashboard.php"
                    class="vision-form"
                >

                    <div class="form-group">

                        <label>
                            Início:
                        </label>

                        <input
                            type="datetime-local"
                            name="data_inicio"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Término:
                        </label>

                        <input
                            type="datetime-local"
                            name="data_fim"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tipo:
                        </label>

                        <select name="status">

                            <option value="bloqueado">
                                🔴 Bloqueado / Indisponível
                            </option>

                            <option value="ocupado">
                                🟣 Compromisso Ocupado
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Observação:
                        </label>

                        <input
                            type="text"
                            name="observacao"
                            placeholder="Ex: Compromisso pessoal..."
                        >

                    </div>


                    <button
                        type="submit"
                        name="add_evento"
                        class="btn-submit"
                    >

                        <i class="fa-solid fa-lock"></i>

                        Cadastrar Indisponibilidade

                    </button>

                </form>

            </div>


            <!-- EVENTOS -->

            <div class="glass-card">

                <div class="card-header">

                    <h3>

                        <i class="fa-solid fa-list-check"></i>

                        Próximos Eventos Cadastrados

                    </h3>

                </div>


                <div class="table-responsive">

                    <table class="vision-table">

                        <thead>

                            <tr>

                                <th>INÍCIO</th>
                                <th>FIM</th>
                                <th>STATUS</th>
                                <th>OBSERVAÇÃO</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($todos_eventos as $e): ?>

                            <tr>

                                <td>

                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $e['data_inicio']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $e['data_fim']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?php if (
                                        $e['status'] === 'ocupado'
                                    ): ?>

                                        <span class="badge badge-purple">
                                            Ocupado
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-red">
                                            Bloqueado
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $e['observacao']
                                        ) ?>

                                    </small>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>

</div>


<script>

    const meses =
        <?= json_encode($chart_months) ?>;

    const dadosGanhos =
        <?= json_encode($chart_ganhos) ?>;

    const dadosOcupacao =
        <?= json_encode($chart_ocupacao) ?>;


    Chart.defaults.color = '#a0aec0';

    Chart.defaults.font.family =
        'Poppins, sans-serif';


    const ctxFat =
        document
        .getElementById('faturamentoChart')
        .getContext('2d');


    const gradientPurple =
        ctxFat.createLinearGradient(
            0,
            0,
            0,
            300
        );


    gradientPurple.addColorStop(
        0,
        'rgba(117, 81, 255, 0.4)'
    );

    gradientPurple.addColorStop(
        1,
        'rgba(117, 81, 255, 0.0)'
    );


    new Chart(ctxFat, {

        type: 'line',

        data: {

            labels: meses,

            datasets: [{

                label: 'Faturamento (R$)',

                data: dadosGanhos,

                borderColor: '#7551FF',

                borderWidth: 3,

                backgroundColor:
                    gradientPurple,

                fill: true,

                tension: 0.4,

                pointBackgroundColor:
                    '#00F0FF',

                pointRadius: 4

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    display: false
                }

            },

            scales: {

                x: {
                    grid: {
                        display: false
                    }
                },

                y: {

                    grid: {
                        color:
                            'rgba(255,255,255,0.05)'
                    }

                }

            }

        }

    });


    const ctxOcup =
        document
        .getElementById('ocupacaoChart')
        .getContext('2d');


    new Chart(ctxOcup, {

        type: 'bar',

        data: {

            labels: meses,

            datasets: [{

                label: 'Dias Ocupados',

                data: dadosOcupacao,

                backgroundColor:
                    '#00F0FF',

                borderRadius: 6,

                barThickness: 16

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    display: false
                }

            },

            scales: {

                x: {

                    grid: {
                        display: false
                    }

                },

                y: {

                    grid: {
                        color:
                            'rgba(255,255,255,0.05)',

                    ticks: {
                        stepSize: 1
                    }

                }

            }

        }

    });

</script>

</body>

</html>

