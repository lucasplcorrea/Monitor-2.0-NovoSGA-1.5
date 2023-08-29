$(document).ready(function () {
  // Função para carregar o conteúdo das senhas na div "app"
  function carregarConteudoSenhas() {
    $("#app").load("atendimentos.php", function () {
      // Esta função será chamada após o conteúdo ser carregado
      inicializarFuncionalidades(); // Recarrega as funcionalidades do JS
      setTimeout(carregarConteudoSenhas, 5000); // Recarrega a cada 5 segundos
    });
  }

  // Função para inicializar as funcionalidades do JS
  function inicializarFuncionalidades() {
    // Ao passar o mouse sobre a senha, exibir prioridade e tempo de espera
    $(".senha").hover(function () {
      var prioridade = $(this).data("prioridade");
      var espera = $(this).data("espera");

      console.log("Hover - Prioridade: " + prioridade + ", Tempo de Espera: " + espera);

      $(this).attr(
        "title",
        "Prioridade: " + prioridade + ", Tempo de Espera: " + espera
      );
    });

    // Manipular a submissão do formulário de consulta
    $("#formConsultaAtendimentos").submit(function (event) {
      event.preventDefault();

      var senha = $("#inputSenha").val();
      var dataInput = $("#inputData").val();

      // Converte a data para o formato DD/MM/AAAA
      var dataParts = dataInput.split('-');
      var dataFormatada = dataParts[2] + '/' + dataParts[1] + '/' + dataParts[0];

      // Log dos parâmetros recebidos
      console.log("Parâmetros recebidos - Senha: " + senha + ", Data: " + dataFormatada);

      // Lógica para realizar a consulta
      $.ajax({
        type: "POST",
        url: "consulta_atendimentos.php",
        data: { senha: senha, data: dataFormatada },
        success: function (response) {
          $("#resultadoConsulta").html(response);
        }
      });
    });

    // Verificar o status e aplicar a cor de fundo correspondente
    $(".senha").each(function () {
      var status = $(this).data("status");
      var espera = $(this).data("espera");
      var prioridade = $(this).data("prioridade");

      console.log("Status:", status);
      console.log("Tempo de Espera:", espera);

      if (status === 1) {
        if (espera > "00:10:00") {
          if (prioridade === "prioridade-1") {
            console.log("Adicionando classe piscar-prioridade-1");
            $(this).addClass("piscar-prioridade-1");
            $(this).removeClass("piscar-prioridade-not-1");
          } else {
            console.log("Adicionando classe piscar-prioridade-not-1");
            $(this).addClass("piscar-prioridade-not-1");
            $(this).removeClass("piscar-prioridade-1");
          }
        } else {
          console.log("Removendo classes piscar-prioridade-1 e piscar-prioridade-not-1");
          $(this).removeClass("piscar-prioridade-1 piscar-prioridade-not-1");
        }
      } else {
        console.log("Removendo classes piscar-prioridade-1 e piscar-prioridade-not-1");
        $(this).removeClass("piscar-prioridade-1 piscar-prioridade-not-1");
      }
    });

    // Ao clicar na senha, exibir modal com as informações
    $(".senha").click(function () {
      var status = $(this).data("status");
      var numero = $(this).data("numero");
      var prioridade = $(this).data("prioridade");
      var servico = $(this).data("servico");
      var chegada = $(this).data("chegada");
      var espera = $(this).data("espera");

      console.log("Clique - Status:", status);
      console.log("Número:", numero);
      console.log("Prioridade:", prioridade);
      console.log("Serviço:", servico);
      console.log("Data Chegada:", chegada);
      console.log("Tempo de Espera:", espera);

      if (status === 1) {
        $("#modalNumero").text(numero);
        $("#modalPrioridade").text(prioridade);
        $("#modalServico").text(servico);
        $("#modalDataChegada").text(formatarDataHora(chegada));
        $("#modalTempoEspera").text(espera);
        $("#modalSenha").modal("show");
      } else if (status === 2) {
        var chamada = $(this).data("chamada");
        var responsavel = $(this).data("responsavel");
        var esperaChamada = calcularTempoEspera(chegada, chamada);

        console.log("Data Chamada:", chamada);
        console.log("Usuário Responsável:", responsavel);
        console.log("Tempo até ser Chamado:", esperaChamada);

        $("#modalNumeroStatus2").text(numero);
        $("#modalPrioridadeStatus2").text(prioridade);
        $("#modalServicoStatus2").text(servico);
        $("#modalDataChegadaStatus2").text(formatarDataHora(chegada));
        $("#modalDataChamadaStatus2").text(formatarDataHora(chamada));
        $("#modalTempoChamadaStatus2").text(esperaChamada);
        $("#modalUsuarioResponsavelStatus2").text(responsavel);
        $("#modalSenhaStatus2").modal("show");
      } else if (status === 3) {
        var chamada = $(this).data("chamada");
        var duracao = $(this).data("duracao");
        var responsavel = $(this).data("responsavel");
        var esperaChamada = calcularTempoEspera(chegada, chamada);

        console.log("Data Chamada:", chamada);
        console.log("Usuário Responsável:", responsavel);
        console.log("Tempo até ser Chamado:", esperaChamada);
        console.log("Tempo de duração do atendimento:", duracao);

        $("#modalNumeroStatus3").text(numero);
        $("#modalPrioridadeStatus3").text(prioridade);
        $("#modalServicoStatus3").text(servico);
        $("#modalDataChegadaStatus3").text(formatarDataHora(chegada));
        $("#modalDataChamadaStatus3").text(formatarDataHora(chamada));
        $("#modalTempoChamadaStatus3").text(esperaChamada);
        $("#modalDuracaoAtendimento").text(duracao);
        $("#modalUsuarioResponsavelStatus3").text(responsavel);
        $("#modalSenhaStatus3").modal("show");
      }
    });
  }

  // Função para calcular o tempo de espera
  function calcularTempoEspera(chegada, chamada = null) {
    var chegadaData = new Date(chegada);
    var agora = new Date();
    var diferenca = agora - chegadaData;
    if (chamada) {
      var chamadaData = new Date(chamada);
      diferenca = chamadaData - chegadaData;
    }
    var segundos = Math.floor(diferenca / 1000);
    var minutos = Math.floor(segundos / 60);
    segundos = segundos % 60;
    var horas = Math.floor(minutos / 60);
    minutos = minutos % 60;
    var dias = Math.floor(horas / 24);
    horas = horas % 24;
    var tempoEspera = "";
    if (dias > 0) {
      tempoEspera += dias + " dia(s), ";
    }
    tempoEspera +=
      horas.toString().padStart(2, "0") +
      ":" +
      minutos.toString().padStart(2, "0") +
      ":" +
      segundos.toString().padStart(2, "0");
    return tempoEspera;
  }

  // Função para formatar a data e hora
  function formatarDataHora(dataHora) {
    var data = new Date(dataHora);
    var dia = data.getDate();
    var mes = data.getMonth() + 1;
    var ano = data.getFullYear();
    var hora = data.getHours();
    var minutos = data.getMinutes();
    var segundos = data.getSeconds();
    return (
      dia.toString().padStart(2, "0") +
      "/" +
      mes.toString().padStart(2, "0") +
      "/" +
      ano +
      " " +
      hora.toString().padStart(2, "0") +
      ":" +
      minutos.toString().padStart(2, "0") +
      ":" +
      segundos.toString().padStart(2, "0")
    );
  }

  // Inicializa a primeira chamada para carregar o conteúdo das senhas
  carregarConteudoSenhas();
});
