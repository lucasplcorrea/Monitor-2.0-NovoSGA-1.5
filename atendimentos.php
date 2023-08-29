<?php
// Função para calcular o tempo de espera
function calcularTempoEspera($chegada, $chamada = null) {
    $chegadaData = new DateTime($chegada);
    $agora = new DateTime();
    $diferenca = $agora->diff($chegadaData);

    $tempoEspera = "";
    if ($diferenca->d > 0) {
        $tempoEspera .= $diferenca->d . " dia(s), ";
    }
    $tempoEspera .= $diferenca->format("%H:%I:%S");

    return $tempoEspera;
}

// Conexão com o banco de dados (substitua pelas suas configurações)
$servername = "192.168.160.5";
$username = "novosga";
$password = "123novosga456";
$dbname = "novosga";

try {
    // Cria a conexão PDO
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8");

    // Exibe as frases antes de exibir os atendimentos
    echo "<h2>Monitor de Atendimento</h2>";

    // Exibe os atendimentos com status 1 (Senhas aguardando atendimento)
    exibirAtendimentos($conn, 1, "Senhas aguardando atendimento");

    // Exibe os atendimentos com status 2 (Senhas chamadas pela mesa)
    exibirAtendimentos($conn, 2, "Senhas chamadas pela mesa");

    // Exibe os atendimentos com status 3 (Senhas sendo atendidas)
    exibirAtendimentos($conn, 3, "Senhas sendo atendidas");

    // Fecha a conexão com o banco de dados
    $conn = null;
} catch (PDOException $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
}

// Função para exibir as senhas
function exibirSenha($row, $status)
{
    $senhaFormatada = $row['sigla_senha'] . str_pad($row['num_senha_serv'], 3, '0', STR_PAD_LEFT);
    $prioridadeClass = ($row['prioridade_id'] == 1) ? 'prioridade-1' : 'prioridade-not-1';

    $chegada = $row['dt_cheg'];
    $chamada = $row['dt_cha'];
    $responsavel = $row['nome_usuario'];

    // Calcular o tempo de espera
    $espera = calcularTempoEspera($row['dt_cheg']);

    if ($status == 2) {
        echo "<div class='senha $prioridadeClass senha-status2' data-toggle='modal' data-target='#modalSenhaStatus2' data-numero='$senhaFormatada' data-prioridade='" . $row['nome_prioridade'] . "' data-servico='" . $row['nome_servico'] . "' data-chegada='" . $row['dt_cheg'] . "' data-chamada='$chamada' data-responsavel='$responsavel' data-status='$status' data-espera='$espera'>$senhaFormatada</div>";
    } elseif ($status == 3) {
        $duracao = calcularDuracaoAtendimento($row['dt_ini']);
        echo "<div class='senha $prioridadeClass senha-status3' data-toggle='modal' data-target='#modalSenhaStatus3' data-numero='$senhaFormatada' data-prioridade='" . $row['nome_prioridade'] . "' data-servico='" . $row['nome_servico'] . "' data-chegada='$chegada' data-chamada='$chamada' data-duracao='$duracao' data-responsavel='$responsavel' data-status='$status' data-espera='$espera'>$senhaFormatada</div>";
    } else {
        echo "<div class='senha $prioridadeClass' data-toggle='modal' data-target='#modalSenha' data-numero='$senhaFormatada' data-prioridade='" . $row['nome_prioridade'] . "' data-servico='" . $row['nome_servico'] . "' data-chegada='" . $row['dt_cheg'] . "' data-status='$status' data-espera='$espera'>$senhaFormatada</div>";
    }
}

// Função para selecionar os atendimentos com o status especificado
function exibirAtendimentos($conn, $status, $titulo)
{
    $sql = "SELECT a.id, s.descricao AS nome_servico, a.sigla_senha, a.num_senha_serv, p.descricao AS nome_prioridade, a.prioridade_id, a.dt_cheg, a.dt_cha, a.dt_ini, u.nome AS nome_usuario
            FROM atendimentos a
            INNER JOIN servicos s ON a.servico_id = s.id
            INNER JOIN prioridades p ON a.prioridade_id = p.id
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.status = $status
            ORDER BY s.descricao, a.prioridade_id DESC, a.dt_cheg, a.dt_cha";

    $result = $conn->query($sql);

    if ($result->rowCount() > 0) {
        echo "<h4>$titulo:</h4>";
        echo "<div class='servicos'>";
        $servicoAnterior = null;
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            if ($servicoAnterior !== $row['nome_servico']) {
                if ($servicoAnterior !== null) {
                    echo "</div></div>"; // Fechar a div de senhas e a div de serviço anterior
                }
                echo "<div class='servico'>";
                echo "<span class='nome-servico'>" . $row['nome_servico'] . "</span>";
                echo "<div class='senhas'>";
                $servicoAnterior = $row['nome_servico'];
            }

            exibirSenha($row, $status);
        }
        echo "</div></div>"; // Fechar a última div de senhas e a div de serviço
        echo "</div>"; // Fechar a div de servicos
    }
}

// Função para calcular a duração do atendimento
function calcularDuracaoAtendimento($dataIni)
{
    $dataAtual = date("Y-m-d H:i:s");
    $diferenca = strtotime($dataAtual) - strtotime($dataIni);
    $horas = floor($diferenca / (60 * 60));
    $minutos = floor(($diferenca % (60 * 60)) / 60);
    $segundos = $diferenca % 60;

    return sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
}
?>
