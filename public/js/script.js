(function ($) {
    'use strict';
    function initializeMonitor2() {
        var root = $('#monitor2');
        if (!root.length || root.data('initialized')) { return; }
        root.data('initialized', true);
        var timer = null, polling = false, stopped = false, detailRequest = null;
        var unit = String(root.attr('data-unidade')), user = String(root.attr('data-usuario'));
        var storageKey = 'lucas.monitor2:' + user + ':' + unit;
        var settings = {minutes: 5, notifications: false}, lastItems = [], notification = null, notified = {};
        var notificationButton = $('#m2-notifications');
        function readStored(key) {
            try { return JSON.parse(window.localStorage.getItem(key)); } catch (e) { return null; }
        }
        function saveStored(key, value) {
            try { window.localStorage.setItem(key, JSON.stringify(value)); return true; } catch (e) { return false; }
        }
        function loadSettings() {
            var saved = readStored(storageKey);
            settings.minutes = saved && validMinutes(saved.minutes) ? Number(saved.minutes) : 5;
            settings.notifications = !!(saved && saved.notifications === true);
        }
        function validMinutes(value) { return /^[0-9]+$/.test(String(value)) && Number(value) >= 1 && Number(value) <= 1440; }
        function canNotify() { return typeof window.Notification === 'function' && window.isSecureContext !== false; }
        function notificationsActive() { return settings.notifications && canNotify() && window.Notification.permission === 'granted'; }
        function settingsUI() {
            $('#m2-minutes').val(settings.minutes);
            $('#m2-threshold').text(settings.minutes);
            notificationButton.text(notificationsActive() ? 'Desativar notificações' : 'Ativar notificações');
            notificationButton.prop('disabled', !canNotify() || stopped);
            if (!canNotify()) { message($('#m2-settings-status'), 'Notificações indisponíveis neste navegador ou conexão. Os alertas visuais continuam ativos.', false); }
        }
        function closeNotification() { if (notification) { notification.close(); notification = null; } }
        function notifyOverdue(items) {
            if (!notificationsActive() || stopped) { return; }
            var stored = readStored(storageKey + ':notified') || {}, now = Date.now(), pending = [], active = {};
            $.each(items, function (_, item) {
                if (item.status !== 1) { return; }
                var key = item.id + '|' + item.chegada;
                if (notified[key] || stored[key]) { active[key] = notified[key] || stored[key]; }
                if (item.espera >= settings.minutes * 60 && !active[key]) { pending.push(key); }
            });
            notified = active;
            if (!pending.length) { return; }
            try {
                closeNotification();
                notification = new window.Notification('Gestão de Filas — espera prolongada', {
                    body: pending.length + ' senha(s) com espera de pelo menos ' + settings.minutes + ' min. Unidade: ' + root.attr('data-unidade-nome') + '.',
                    tag: storageKey + ':waiting'
                });
                notification.onclick = function () { if (!stopped) { window.focus(); } closeNotification(); };
                $.each(pending, function (_, key) { notified[key] = now; });
                saveStored(storageKey + ':notified', notified);
            } catch (e) {
                settings.notifications = false; saveStored(storageKey, settings); settingsUI();
                message($('#m2-settings-status'), 'O navegador não conseguiu exibir a notificação. Os alertas visuais continuam ativos.', true);
            }
        }
        loadSettings();
        var states = {}, tickets = {}, services = {};
        var detail = $('#m2-detail'), historyRows = $('#m2-history-rows');
        root.find('.m2-state').each(function () { states[$(this).attr('data-estado')] = $(this); });

        function duration(seconds) {
            if (seconds === null || typeof seconds === 'undefined') { return '—'; }
            function pad(n) { return n < 10 ? '0' + n : String(n); }
            return pad(Math.floor(seconds / 3600)) + ':' + pad(Math.floor(seconds / 60) % 60) + ':' + pad(seconds % 60);
        }
        function text(node, value) {
            var valueText = value === null || typeof value === 'undefined' ? '—' : String(value);
            if (node.text() !== valueText) { node.text(valueText); }
        }
        function message(node, value, error) { node.text(value).toggleClass('m2-error', !!error); }
        function clearData() {
            root.find('.m2-services').empty();
            root.find('.m2-count').text('0');
            root.find('.m2-empty').show();
            historyRows.empty();
            detail.find('dl').empty();
            tickets = {}; services = {}; lastItems = [];
            closeNotification();
        }
        function stop(value) {
            stopped = true;
            clearTimeout(timer);
            clearData();
            root.find('form :input').prop('disabled', true);
            message($('#m2-status'), value, true);
            message($('#m2-history-status'), value, true);
            message(detail.find('.m2-message'), value, true);
        }
        function request(action, data, success, failure) {
            return $.ajax({ url: SGA.url(action), type: 'GET', data: data, dataType: 'json', cache: false, timeout: 15000 })
                .done(function (response) {
                    if (stopped) { return; }
                    if (response && (response.invalid || response.inactive)) {
                        stop('Sessão encerrada ou inválida. Entre novamente no Novo SGA.'); return;
                    }
                    if (!response || response.success !== true) {
                        failure(response && response.message ? response.message : 'Acesso indisponível. Verifique sua sessão no SGA.'); return;
                    }
                    if (!response.data || String(response.data.unidade) !== unit) {
                        stop('A unidade selecionada mudou. Recarregue a página.'); return;
                    }
                    // Accept responses from 0.1.x controllers that can remain in PHP-FPM's
                    // opcode cache briefly after a module reinstall. New responses still
                    // protect against a user switch while this page remains open.
                    if (response.data.usuario !== null && typeof response.data.usuario !== 'undefined'
                        && String(response.data.usuario) !== user) {
                        stop('O usuário mudou. Recarregue a página.'); return;
                    }
                    success(response.data);
                }).fail(function (xhr, status) {
                    if (stopped || status === 'abort') { return; }
                    if (xhr.status === 401 || xhr.status === 403 || status === 'parsererror') {
                        stop('Acesso indisponível. Entre novamente no Novo SGA.'); return;
                    }
                    failure('Não foi possível atualizar os dados. Verifique a conexão e tente novamente.');
                });
        }
        function render(items) {
            lastItems = items;
            var seen = {}, groups = {}, counts = {1: 0, 2: 0, 3: 0};
            $.each(items, function (_, item) {
                if (!states[item.status]) { return; }
                var key = String(item.id), groupKey = item.status + ':' + item.servico_id;
                seen[key] = true; counts[item.status] += 1;
                if (!services[groupKey]) {
                    services[groupKey] = $('<div>').addClass('m2-service').append($('<h4>'), $('<div>').addClass('m2-tickets'));
                    states[item.status].find('.m2-services').append(services[groupKey]);
                }
                var group = services[groupKey];
                text(group.find('h4'), item.servico);
                if (!groups[groupKey]) { groups[groupKey] = []; }
                if (!tickets[key]) {
                    tickets[key] = $('<button type="button">').addClass('m2-ticket').attr('data-id', item.id)
                        .append($('<strong>'), $('<small>').addClass('m2-wait'), $('<small>').addClass('m2-owner'));
                }
                var button = tickets[key];
                button.toggleClass('m2-priority', !!item.preferencial).toggleClass('m2-waiting', item.status === 1 && item.espera >= settings.minutes * 60);
                text(button.find('strong'), item.senha);
                var label = item.senha + ' — ' + item.prioridade + ' — Espera: ' + duration(item.espera) + (item.responsavel ? ' — ' + item.responsavel : '');
                button.attr('title', label).attr('aria-label', label);
                text(button.find('.m2-wait'), 'Espera: ' + duration(item.espera));
                text(button.find('.m2-owner'), item.responsavel);
                button.find('.m2-owner').toggle(!!item.responsavel);
                groups[groupKey].push(button[0]);
            });
            $.each(tickets, function (key, button) { if (!seen[key]) { button.remove(); delete tickets[key]; } });
            $.each(services, function (key, group) {
                if (!groups[key]) { group.remove(); delete services[key]; return; }
                var container = group.find('.m2-tickets')[0];
                $.each(groups[key], function (position, button) {
                    if (container.children[position] !== button) { container.insertBefore(button, container.children[position] || null); }
                });
            });
            $.each(states, function (status, section) {
                text(section.find('.m2-count'), counts[status]);
                section.find('.m2-empty').toggle(counts[status] === 0);
            });
        }
        function schedule() {
            clearTimeout(timer);
            if (!stopped && (document.visibilityState !== 'hidden' || notificationsActive())) {
                timer = setTimeout(poll, document.visibilityState === 'hidden' ? 30000 : 5000);
            }
        }
        function poll() {
            clearTimeout(timer);
            if (polling || stopped || (document.visibilityState === 'hidden' && !notificationsActive())) { return; }
            polling = true;
            request('ajax_filas', {}, function (data) {
                render(data.atendimentos);
                notifyOverdue(data.atendimentos);
                message($('#m2-status'), 'Atualizado às ' + new Date().toLocaleTimeString() + '. Atualização automática a cada 5 segundos.', false);
            }, function (error) {
                clearData();
                message($('#m2-status'), error + ' Nova tentativa em 5 segundos.', true);
            }).always(function () { polling = false; schedule(); });
        }
        $(document).on('visibilitychange.monitor2', function () {
            settingsUI();
            if (document.visibilityState === 'hidden') { schedule(); } else { poll(); }
        });
        $(window).on('pagehide.monitor2', function () { clearTimeout(timer); closeNotification(); });
        $(window).on('pageshow.monitor2', function (event) { if (event.originalEvent.persisted) { poll(); } });

        $('#m2-settings-form').on('submit.monitor2', function (event) {
            event.preventDefault();
            if (stopped) { return; }
            var value = $('#m2-minutes').val();
            if (!validMinutes(value)) { message($('#m2-settings-status'), 'Informe um número inteiro de 1 a 1440 minutos.', true); return; }
            settings.minutes = Number(value);
            var saved = saveStored(storageKey, settings);
            settingsUI(); render(lastItems);
            message($('#m2-settings-status'), saved ? 'Preferências salvas neste navegador.' : 'Preferências aplicadas nesta aba. O navegador bloqueou o armazenamento.', !saved);
            notifyOverdue(lastItems);
            poll();
        });
        notificationButton.on('click.monitor2', function () {
            if (stopped || !canNotify()) { return; }
            if (notificationsActive()) {
                settings.notifications = false; saveStored(storageKey, settings); closeNotification(); settingsUI(); schedule();
                message($('#m2-settings-status'), 'Notificações desativadas para seu usuário nesta unidade.', false); return;
            }
            var completed = false;
            function permissionResult(permission) {
                if (completed || stopped) { return; }
                completed = true;
                settings.notifications = permission === 'granted';
                var saved = saveStored(storageKey, settings);
                settingsUI();
                message($('#m2-settings-status'), settings.notifications ?
                    (saved ? 'Notificações ativadas. Mantenha o módulo aberto.' : 'Notificações ativadas somente nesta aba; armazenamento indisponível.') :
                    'Permissão não concedida. Para habilitar, permita notificações nas configurações deste site no navegador.', !settings.notifications || !saved);
                if (settings.notifications) { notifyOverdue(lastItems); }
                poll();
            }
            if (window.Notification.permission !== 'default') { permissionResult(window.Notification.permission); return; }
            notificationButton.prop('disabled', true);
            try {
                var result = window.Notification.requestPermission(permissionResult);
                if (result && result.then) { result.then(permissionResult, function () { permissionResult('denied'); }); }
            } catch (e) { permissionResult('denied'); }
        });
        $(window).on('storage.monitor2', function (event) {
            if (event.originalEvent.key === storageKey && !stopped) {
                loadSettings(); settingsUI(); render(lastItems); closeNotification(); poll(); schedule();
            }
        });
        settingsUI();

        root.on('click.monitor2', '.m2-ticket', function () {
            if (stopped) { return; }
            if (detailRequest) { detailRequest.abort(); }
            detail.find('dl').empty();
            message(detail.find('.m2-message'), 'Carregando detalhes…', false);
            detail.modal('show');
            detailRequest = request('info_senha', {id: $(this).attr('data-id')}, function (data) {
                var t = data.atendimento;
                var fields = [['Senha', t.senha], ['Serviço', t.servico], ['Situação', t.situacao], ['Prioridade', t.prioridade],
                    ['Chegada', t.chegada], ['Chamada', t.chamada], ['Início', t.inicio], ['Fim', t.fim],
                    ['Espera até a chamada', duration(t.espera)], ['Duração', duration(t.duracao)], ['Responsável', t.responsavel]];
                message(detail.find('.m2-message'), '', false);
                $.each(fields, function (_, field) { detail.find('dl').append($('<dt>').text(field[0]), $('<dd>').text(field[1] || '—')); });
            }, function (error) { message(detail.find('.m2-message'), error, true); });
        });
        detail.on('hidden.bs.modal', function () { if (detailRequest) { detailRequest.abort(); } });
        $('#m2-history-form').on('submit.monitor2', function (event) {
            event.preventDefault();
            if (stopped || $(this).data('busy')) { return; }
            var form = $(this), button = form.find('button');
            form.data('busy', true); button.prop('disabled', true); historyRows.empty();
            message($('#m2-history-status'), 'Consultando…', false);
            request('historico', {senha: $('#m2-number').val(), data: $('#m2-date').val()}, function (data) {
                $.each(data.atendimentos, function (_, t) {
                    var row = $('<tr>');
                    $.each([t.senha, t.servico, t.situacao, t.prioridade, t.chegada, t.chamada, t.inicio, t.fim,
                        duration(t.espera), duration(t.duracao), duration(t.permanencia), t.responsavel], function (_, value) {
                        row.append($('<td>').text(value === null ? '—' : value));
                    });
                    historyRows.append(row);
                });
                message($('#m2-history-status'), data.limitado ? 'Exibindo os 500 registros mais recentes. Refine a busca.' : data.atendimentos.length + ' registro(s) encontrado(s).', false);
            }, function (error) { message($('#m2-history-status'), error, true); })
                .always(function () { form.data('busy', false); if (!stopped) { button.prop('disabled', false); } });
        });
        poll();
    }

    // The versioned script is also loaded at the end of the module markup.
    // Initialize it immediately there, before a stale head script can run its
    // DOM-ready callback. This works around Novo SGA 1.5's fixed asset version.
    if ($('#monitor2').length) {
        initializeMonitor2();
    } else {
        $(initializeMonitor2);
    }
}(jQuery));
