<?php
// Conexão com o banco de dados (substitua pelas suas configurações)
$host = "192.168.160.93";
$user = "visualizadorsga";
$password = "@Cartorio!531";
$dbname = "SGA";

// Cria a conexão com o banco de dados
$conn = new mysqli($host, $user, $password, $dbname);

// Verifica a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Definir o conjunto de caracteres para UTF-8
$conn->set_charset("utf8");

// Recebe os parâmetros do formulário
$senha = $_POST["senha"];
$data = $_POST["data"];

// Log dos parâmetros recebidos
error_log("Parâmetros recebidos - Senha: $senha, Data: $data");

// Inicializa a consulta
$query = "SELECT * FROM view_historico_atendimentos_completo WHERE 1 = 1";

// Verifica se a senha foi preenchida
if (!empty($senha)) {
    $query .= " AND numero = '$senha'";
}

// Verifica se a data foi preenchida e não é 'undefined/undefined/'
if (!empty($data) && $data != "undefined/undefined/") {
    $query .= " AND data_de_chegada = '$data'";
}

$query .= " LIMIT 500"; // Coloca o LIMIT no lugar correto

// Log da consulta que será executada
error_log("Consulta: $query");

// Executar a consulta
$result = $conn->query($query);

// Monta a resposta
if ($result->num_rows > 0) {
    echo "<table class='table-bordered table-striped'>
            <tr>
                <th>Senha</th>
                <th>Serviço</th>
                <th>Data de Chegada</th>
                <th>Hora de Chegada</th>
                <th>Data de Chamada</th>
                <th>Hora de Chamada</th>
                <th>Data de Atendimento</th>
                <th>Hora de Atendimento</th>
                <th>Data de Conclusão</th>
                <th>Hora de Conclusão</th>
                <th>Tempo de Duração do Atendimento</th>
                <th>Tempo de Permanência</th>
                <th>Atendente</th>
            </tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr class='table-row-highlight'>";
        echo "<td>" . $row["numero"] . "</td>";
        echo "<td>" . $row["servico"] . "</td>";
        echo "<td>" . $row["data_de_chegada"] . "</td>";
        echo "<td>" . $row["hora_de_chegada"] . "</td>";
        echo "<td>" . $row["data_de_chamada"] . "</td>";
        echo "<td>" . $row["hora_de_chamada"] . "</td>";
        echo "<td>" . $row["data_de_atendimento"] . "</td>";
        echo "<td>" . $row["hora_de_atendimento"] . "</td>";
        echo "<td>" . $row["data_de_conclusao"] . "</td>";
        echo "<td>" . $row["hora_de_conclusao"] . "</td>";
        echo "<td>" . $row["tempo_de_duracao_do_atendimento"] . "</td>";
        echo "<td>" . $row["tempo_de_permanencia"] . "</td>";
        echo "<td>" . $row["atendente"] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Nenhum resultado encontrado.";
}

// Fecha a conexão
$conn->close();
?>
