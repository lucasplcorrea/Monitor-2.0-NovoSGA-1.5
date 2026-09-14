/* Run with agent-browser eval --stdin on a LOCAL synthetic test page only.
 * Mocks notifications and queue responses; no OS notifications are sent. */
window.monitor2TestPromise = (async function () {
    'use strict';
    if (!/^(127\.0\.0\.1|localhost)$/.test(location.hostname)) { throw new Error('Local test page required'); }
    var $ = jQuery, results = [], originalNotification = window.Notification, mocking = true;
    var root = $('#monitor2'), key = 'lucas.monitor2:' + root.attr('data-usuario') + ':' + root.attr('data-unidade');
    var previous = localStorage.getItem(key), previousNotified = localStorage.getItem(key + ':notified');
    var calls = [], permissions = 0, requests = 0, responseUser = null;
    function assert(value, label) { if (!value) { throw new Error(label); } results.push(label); }
    function MockNotification(title, options) { calls.push({title: title, options: options}); this.close = function () {}; }
    MockNotification.permission = 'default';
    MockNotification.requestPermission = function (callback) { permissions++; MockNotification.permission = 'granted'; callback('granted'); return Promise.resolve('granted'); };
    var items = [
        {id: 99001, senha: 'A001', status: 1, servico_id: 1, servico: '<img src=x onerror=alert(1)>', prioridade: 'Prioridade com um nome muito longo', preferencial: true, espera: 299, chegada: 'test', responsavel: null},
        {id: 99002, senha: 'A002', status: 1, servico_id: 1, servico: '<img src=x onerror=alert(1)>', prioridade: 'Normal', preferencial: false, espera: 300, chegada: 'test', responsavel: null},
        {id: 99003, senha: 'A003', status: 2, servico_id: 1, servico: 'Serviço', prioridade: 'Normal', preferencial: false, espera: 900, chegada: 'test', responsavel: 'Gestor'}
    ];
    function refresh() { document.dispatchEvent(new Event('visibilitychange')); }
    function save(minutes) { $('#m2-minutes').val(minutes); $('#m2-settings-form').trigger('submit'); }
    $.ajaxTransport('+json', function (options) {
        if (!mocking || options.url.indexOf('/ajax_filas') < 0) { return; }
        return {send: function (_, done) {
            requests++;
            // Deliberately omit `usuario` to simulate an old controller still held
            // by PHP-FPM's opcode cache immediately after a reinstall.
            var data = {unidade: Number(root.attr('data-unidade')), atendimentos: items};
            if (responseUser !== null) { data.usuario = responseUser; }
            done(200, 'OK', {text: JSON.stringify({success: true, data: data})}, 'Content-Type: application/json');
        }, abort: function () {}};
    });
    try {
        await new Promise(function (resolve) { setTimeout(resolve, 500); });
        window.Notification = MockNotification;
        localStorage.removeItem(key + ':notified');
        localStorage.setItem(key, JSON.stringify({minutes: 5, notifications: false}));
        window.dispatchEvent(new StorageEvent('storage', {key: key}));
        assert($('#m2-status').text().indexOf('usuário') === -1, 'Legacy response without user remains compatible');
        assert(permissions === 0 && calls.length === 0, 'No automatic notification permission or delivery');
        assert($('.m2-waiting').length === 1 && $('[data-id=99002]').hasClass('m2-waiting'), 'Five-minute boundary and waiting state only');
        assert($('[data-id=99001]').width() === $('[data-id=99002]').width(), 'Long priority does not widen ticket');
        assert($('#monitor2 img').length === 0, 'Service HTML remains text');
        save(4);
        assert($('.m2-waiting').length === 2 && JSON.parse(localStorage.getItem(key)).minutes === 4, 'Threshold applies immediately and persists per user/unit');
        save(0);
        assert(JSON.parse(localStorage.getItem(key)).minutes === 4, 'Invalid threshold rejected');
        $('#m2-notifications').trigger('click');
        assert(permissions === 1 && calls.length === 1, 'Explicit enable asks once, callback plus promise does not duplicate');
        assert(calls[0].options.body.indexOf('2 senha(s)') === 0, 'Overdue tickets grouped into one notification');
        refresh(); refresh();
        assert(calls.length === 1, 'Repeated polling does not repeat notification');
        items.push($.extend({}, items[0], {id: 99004, espera: 239}));
        save(3);
        assert(calls.length === 2, 'Lower threshold immediately notifies newly overdue ticket');
        items.push($.extend({}, items[0], {id: 99005})); refresh();
        assert(calls.length === 3, 'New overdue ticket creates new notification');
        Object.defineProperty(document, 'visibilityState', {value: 'hidden', configurable: true});
        refresh();
        var before = requests;
        await new Promise(function (resolve) { setTimeout(resolve, 6000); });
        assert(requests === before, 'Hidden tab does not keep five-second polling');
        await new Promise(function (resolve) { setTimeout(resolve, 25000); });
        assert(requests === before + 1, 'Enabled notifications poll hidden tab at thirty seconds');
        $('#m2-notifications').trigger('click');
        before = requests;
        await new Promise(function (resolve) { setTimeout(resolve, 6000); });
        assert(requests === before, 'Disabling notifications pauses hidden polling');
        Object.defineProperty(document, 'visibilityState', {value: 'visible', configurable: true});
        MockNotification.permission = 'denied';
        $('#m2-notifications').trigger('click');
        assert(permissions === 1 && !JSON.parse(localStorage.getItem(key)).notifications, 'Denied permission stays disabled without prompting again');
        responseUser = 999;
        refresh();
        assert($('#m2-status').text().indexOf('O usuário mudou.') === 0, 'Explicitly different response user is still rejected');
        return results;
    } finally {
        mocking = false;
        window.Notification = originalNotification;
        delete document.visibilityState;
        if (previous === null) { localStorage.removeItem(key); } else { localStorage.setItem(key, previous); }
        if (previousNotified === null) { localStorage.removeItem(key + ':notified'); } else { localStorage.setItem(key + ':notified', previousNotified); }
        window.dispatchEvent(new StorageEvent('storage', {key: key}));
        refresh();
    }
}())
;
window.monitor2TestPromise.then(function (r) { window.monitor2TestResults = {passed: r}; }, function (e) { window.monitor2TestResults = {error: String(e)}; });
'Checks started; inspect window.monitor2TestResults after 40 seconds';
