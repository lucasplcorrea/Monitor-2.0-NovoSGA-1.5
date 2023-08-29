<!DOCTYPE html>
<html>

<head>
    <link rel="shortcut icon" href="http://192.168.160.5/sga/public/images/favicon.png" type="image/x-icon" />
    <title>Monitor de Atendimento</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <!-- NavBar para voltar ao SGA -->
    <nav class="navbar navbar-dark bg-dark">
        <a class="navbar-brand" href="http://192.168.160.5/sga/public">
            <img src="http://192.168.160.5/sga/public/images/novosga-navbar.png" width="90" height="30" class="d-inline-block align-top" alt="">
            Voltar ao Início
        </a>
    </nav>

    <div id="app">
        <?php include 'atendimentos.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script src="app.js"></script>

    <div class="text-center">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalConsultaAtendimentos">
            Consultar Atendimentos
        </button>
    </div>

    <!-- Modal para exibir as informações da senha -->
    <div class="modal fade" id="modalSenha" tabindex="-1" role="dialog" aria-labelledby="modalSenhaLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSenhaLabel">Informações da Senha</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="informacoes-senha">
                        <div class="campo">
                            <span class="campo-titulo">Número:</span>
                            <span class="campo-valor" id="modalNumero"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Prioridade:</span>
                            <span class="campo-valor" id="modalPrioridade"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Serviço:</span>
                            <span class="campo-valor" id="modalServico"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Data Chegada:</span>
                            <span class="campo-valor" id="modalDataChegada"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Tempo de Espera:</span>
                            <span class="campo-valor" id="modalTempoEspera"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para exibir as informações da senha (Status 2 - Senhas chamadas pela mesa) -->
    <div class="modal fade" id="modalSenhaStatus2" tabindex="-1" role="dialog" aria-labelledby="modalSenhaStatus2Label" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSenhaStatus2Label">Informações da Senha</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="informacoes-senha">
                        <div class="campo">
                            <span class="campo-titulo">Número:</span>
                            <span class="campo-valor" id="modalNumeroStatus2"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Prioridade:</span>
                            <span class="campo-valor" id="modalPrioridadeStatus2"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Serviço:</span>
                            <span class="campo-valor" id="modalServicoStatus2"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Data Chegada:</span>
                            <span class="campo-valor" id="modalDataChegadaStatus2"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Data de Chamada:</span>
                            <span class="campo-valor" id="modalDataChamadaStatus2"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Tempo até ser Chamado:</span>
                            <span class="campo-valor" id="modalTempoChamadaStatus2"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Usuário Responsável:</span>
                            <span class="campo-valor" id="modalUsuarioResponsavelStatus2"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para exibir as informações da senha (Status 3 - Senhas sendo atendidas) -->
    <div class="modal fade" id="modalSenhaStatus3" tabindex="-1" role="dialog" aria-labelledby="modalSenhaStatus3Label" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSenhaStatus3Label">Informações da Senha</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="informacoes-senha">
                        <div class="campo">
                            <span class="campo-titulo">Número:</span>
                            <span class="campo-valor" id="modalNumeroStatus3"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Prioridade:</span>
                            <span class="campo-valor" id="modalPrioridadeStatus3"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Serviço:</span>
                            <span class="campo-valor" id="modalServicoStatus3"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Data Chegada:</span>
                            <span class="campo-valor" id="modalDataChegadaStatus3"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Data de Chamada:</span>
                            <span class="campo-valor" id="modalDataChamadaStatus3"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Tempo até ser Chamado:</span>
                            <span class="campo-valor" id="modalTempoChamadaStatus3"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Tempo de duração do atendimento:</span>
                            <span class="campo-valor" id="modalDuracaoAtendimento"></span>
                        </div>
                        <div class="campo">
                            <span class="campo-titulo">Usuário Responsável:</span>
                            <span class="campo-valor" id="modalUsuarioResponsavelStatus3"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para consulta de atendimentos -->
    <div class="modal fade" id="modalConsultaAtendimentos" tabindex="-1" role="dialog" aria-labelledby="modalConsultaAtendimentosLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalConsultaAtendimentosLabel">Consultar Atendimentos</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formConsultaAtendimentos">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inputSenha">Senha:</label>
                                    <input type="text" class="form-control" id="inputSenha" placeholder="Digite o número da senha">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inputData">Data:</label>
                                    <input type="date" class="form-control" id="inputData">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Consultar</button>
                    </form>
                    <div id="resultadoConsulta" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        setInterval(function() {
            $('#app')
        }, 5000) /* tempo em MS para atualizar*/
    </script>

</body>

</html>