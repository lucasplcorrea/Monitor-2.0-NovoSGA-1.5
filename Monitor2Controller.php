<?php

namespace modules\lucas\monitor2;

use Novosga\Context;
use Novosga\Controller\ModuleController;
use Novosga\Http\JsonResponse;
use Novosga\Service\AtendimentoService;
use Novosga\Service\FilaService;

/** Read-only module. Authentication and module permissions belong to Novo SGA. */
class Monitor2Controller extends ModuleController
{
    public function index(Context $context)
    {
        $this->app()->response()->headers->set('Cache-Control', 'private, no-store');
        if ($this->access($context) !== true) {
            $this->app()->halt(403, 'Acesso negado. Entre novamente no Novo SGA.');
        }
        $this->app()->view()->set('unidade', $context->getUser()->getUnidade());
    }

    public function ajax_filas(Context $context)
    {
        return $this->respond($context, function ($unidade) {
            $service = new FilaService($this->em());
            $builder = $service->builder()
                ->addSelect('p, su, s, u')
                ->where('su.unidade = :unidade AND e.status IN (:estados)')
                ->setParameter('unidade', $unidade)
                ->setParameter('estados', array(
                    AtendimentoService::SENHA_EMITIDA,
                    AtendimentoService::CHAMADO_PELA_MESA,
                    AtendimentoService::ATENDIMENTO_INICIADO
                ));
            $service->applyOrders($builder, $unidade);
            $items = array();
            foreach ($builder->getQuery()->getResult() as $atendimento) {
                $items[] = $this->serializeTicket($atendimento);
            }
            return array('unidade' => (int) $unidade->getId(), 'atendimentos' => $items);
        });
    }

    public function info_senha(Context $context)
    {
        return $this->respond($context, function ($unidade) use ($context) {
            $id = $this->input($context, 'id', 10);
            if (!preg_match('/^[1-9][0-9]{0,9}$/D', $id) || (float) $id > 2147483647) {
                throw new Monitor2InputException('Atendimento inválido.');
            }
            $service = new AtendimentoService($this->em());
            $atendimento = $service->buscaAtendimento($unidade, (int) $id);
            if (!$atendimento) {
                throw new Monitor2InputException('Atendimento não encontrado nesta unidade.');
            }
            return array('unidade' => (int) $unidade->getId(), 'atendimento' => $this->serializeTicket($atendimento));
        });
    }

    public function historico(Context $context)
    {
        return $this->respond($context, function ($unidade) use ($context) {
            $senha = strtoupper($this->input($context, 'senha', 11));
            $data = $this->input($context, 'data', 10);
            if ($senha === '' && $data === '') {
                throw new Monitor2InputException('Informe a senha e/ou a data de chegada.');
            }
            // Direct entity joins preserve archived records even if a service is disabled.
            $builder = $this->em()->createQueryBuilder()
                ->select('e, s, p, u')
                ->from('Novosga\Model\AtendimentoHistorico', 'e')
                ->join('e.servico', 's')
                ->join('e.prioridade', 'p')
                ->leftJoin('e.usuario', 'u')
                ->where('e.unidade = :unidade')
                ->setParameter('unidade', $unidade)
                ->orderBy('e.dataChegada', 'DESC')
                ->addOrderBy('e.id', 'DESC')
                ->setMaxResults(501);
            if ($senha !== '') {
                if (!preg_match('/^([A-Z]?)([0-9]{1,10})$/D', $senha, $parts)
                    || (float) $parts[2] < 1 || (float) $parts[2] > 2147483647) {
                    throw new Monitor2InputException('Senha inválida. Use o número, com sigla opcional (ex.: A001).');
                }
                // Field chosen exclusively from the host configuration, never from input.
                $field = AtendimentoService::isNumeracaoServico() ? 'e.numeroSenhaServico' : 'e.numeroSenha';
                $builder->andWhere($field.' = :numero')->setParameter('numero', (int) $parts[2]);
                if ($parts[1] !== '') {
                    $builder->andWhere('e.siglaSenha = :sigla')->setParameter('sigla', $parts[1]);
                }
            }
            if ($data !== '') {
                $date = \DateTime::createFromFormat('!Y-m-d', $data);
                if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $data)
                    || !$date || $date->format('Y-m-d') !== $data || substr($data, 0, 4) < 1000) {
                    throw new Monitor2InputException('Data inválida. Use AAAA-MM-DD.');
                }
                $end = clone $date;
                $end->modify('+1 day');
                $builder->andWhere('e.dataChegada >= :inicio AND e.dataChegada < :fim')
                    ->setParameter('inicio', $date)->setParameter('fim', $end);
            }
            $rows = $builder->getQuery()->getResult();
            $limited = count($rows) > 500;
            $items = array();
            foreach (array_slice($rows, 0, 500) as $atendimento) {
                $items[] = $this->serializeTicket($atendimento);
            }
            return array('unidade' => (int) $unidade->getId(), 'atendimentos' => $items, 'limitado' => $limited);
        });
    }

    private function respond(Context $context, $callback)
    {
        $this->app()->response()->headers->set('Cache-Control', 'private, no-store');
        $response = new JsonResponse();
        try {
            // 1.5.2 can return a truthy JsonResponse for invalid sessions. Require true.
            $access = $this->access($context);
            if ($access !== true) {
                return $access instanceof JsonResponse ? $access : new JsonResponse(false, 'Acesso negado.');
            }
            $user = $context->getUser();
            $unidade = $user ? $user->getUnidade() : null;
            if (!$unidade) {
                throw new Monitor2InputException('Selecione uma unidade no Novo SGA.');
            }
            $response->data = call_user_func($callback, $unidade);
            $response->data['usuario'] = (int) $user->getId();
            $response->success = true;
        } catch (Monitor2InputException $e) {
            $response->message = $e->getMessage();
        } catch (\Exception $e) {
            $response->message = 'Não foi possível consultar os atendimentos. Tente novamente.';
        }
        return $response;
    }

    private function access(Context $context)
    {
        try {
            $module = $context->getModulo();
            if (!$module || !$module->getStatus() || $module->getChave() !== 'lucas.monitor2') {
                return false;
            }
            return $this->app()->getAcessoService()->checkAccess($context, 'lucas.monitor2', 'consulta');
        } catch (\Exception $e) {
            return false;
        }
    }

    private function input(Context $context, $name, $length)
    {
        $value = $context->request()->get($name);
        if ($value === null) {
            return '';
        }
        if (!is_string($value) || strlen($value) > $length) {
            throw new Monitor2InputException('Parâmetro inválido.');
        }
        return trim($value);
    }

    private function serializeTicket($ticket)
    {
        $chegada = $ticket->getDataChegada();
        $chamada = $ticket->getDataChamada();
        $inicio = $ticket->getDataInicio();
        $fim = $ticket->getDataFim();
        $now = new \DateTime();
        $user = $ticket->getUsuario();
        $service = $ticket->getServico();
        $priority = $ticket->getPrioridade();
        // Explicit allowlist: no customer documents, session data or full entity serialization.
        return array(
            'id' => (int) $ticket->getId(),
            'senha' => $ticket->getSenha()->toString(),
            'status' => (int) $ticket->getStatus(),
            'situacao' => $ticket->getNomeStatus(),
            'servico_id' => (int) $service->getId(),
            'servico' => $service->getNome(),
            'prioridade' => $priority->getNome(),
            'preferencial' => $priority->getPeso() > 0,
            'responsavel' => $user ? $user->getNome() : null,
            'chegada' => $this->dateText($chegada),
            'chamada' => $this->dateText($chamada),
            'inicio' => $this->dateText($inicio),
            'fim' => $this->dateText($fim),
            'espera' => $this->seconds($chegada, $chamada ? $chamada : ($fim ? $fim : $now)),
            'duracao' => $this->seconds($inicio, $fim ? $fim : $now),
            'permanencia' => $this->seconds($chegada, $fim ? $fim : $now)
        );
    }

    private function dateText($date)
    {
        return $date ? $date->format('d/m/Y H:i:s') : null;
    }

    private function seconds($start, $end)
    {
        return $start && $end ? max(0, $end->getTimestamp() - $start->getTimestamp()) : null;
    }
}

/** Only these explicitly validated messages may be returned to the browser. */
class Monitor2InputException extends \InvalidArgumentException
{
}
