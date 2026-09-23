jQuery(function($){
    'use strict';

    const app = $('#imp-app');
    if (!app.length || typeof IMP === 'undefined') return;

    let page = 1;
    let modal;

    function esc(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function money(value) {
        return IMP.currency + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    function init() {
        modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('imp-modal'));
        $('#imp-branch').html((IMP.branches || []).map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join(''));
        loadInvoices();

        $('#imp-search').on('input', debounce(function(){ page=1; loadInvoices(); }, 300));
        $('#imp-per-page').on('change', function(){ page=1; loadInvoices(); });
        $('#imp-new-invoice').on('click', newInvoice);
        $('#imp-add-item').on('click', addItem);
        $('#imp-items-table').on('input', '.imp-calc', recalc);
        $('#imp-items-table').on('click', '.imp-remove-item', function(){ $(this).closest('tr').remove(); recalc(); });
        $('#imp-form').on('submit', saveInvoice);

        $('#imp-table').on('click', '.imp-view', function(){ viewInvoice($(this).data('id')); });
        $('#imp-table').on('click', '.imp-edit', function(){ getInvoice($(this).data('id')); });
        $('#imp-table').on('click', '.imp-delete', function(){ deleteInvoice($(this).data('id')); });
        $('#imp-table').on('click', '.imp-print', function(){
            window.open(IMP.ajax_url + '?action=imp_print_invoice&id=' + encodeURIComponent($(this).data('id')) + '&nonce=' + encodeURIComponent(IMP.nonce), '_blank');
        });
        $('#imp-pagination').on('click', 'a[data-page]', function(e){ e.preventDefault(); page = Number($(this).data('page')); loadInvoices(); });
    }

    function debounce(fn, wait) {
        let t; return function(){ clearTimeout(t); const args=arguments; t=setTimeout(()=>fn.apply(this,args),wait); };
    }

    function loadInvoices() {
        const tbody = $('#imp-table tbody');
        tbody.html('<tr><td colspan="6" class="text-center py-4">Loading...</td></tr>');

        $.post(IMP.ajax_url, {
            action: 'imp_list_invoices',
            nonce: IMP.nonce,
            search: $('#imp-search').val(),
            page: page,
            per_page: $('#imp-per-page').val()
        }).done(function(r){
            if (!r.success) return showTableError(r.data && r.data.message);
            renderRows(r.data);
        }).fail(function(){ showTableError('Unable to connect to the server.'); });
    }

    function showTableError(message) {
        $('#imp-table tbody').html(`<tr><td colspan="6" class="text-center text-danger py-4">${esc(message || IMP.i18n.error)}</td></tr>`);
    }

    function renderRows(data) {
        const tbody = $('#imp-table tbody');
        if (!data.rows.length) {
            tbody.html('<tr><td colspan="6" class="text-center py-4">No invoices found.</td></tr>');
        } else {
            tbody.html(data.rows.map(row => `
                <tr>
                    <td><strong>${esc(row.invoice_number)}</strong></td>
                    <td>${esc(row.invoice_date)}</td>
                    <td>${esc(row.invoice_time)}</td>
                    <td>${esc(row.branch)}</td>
                    <td class="text-end">${money(row.total_amount)}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-info imp-view" data-id="${row.id}">View</button> <button class="btn btn-sm btn-outline-primary imp-edit" data-id="${row.id}">Edit</button>
                        <button class="btn btn-sm btn-outline-secondary imp-print" data-id="${row.id}">PDF</button>
                        <button class="btn btn-sm btn-outline-danger imp-delete" data-id="${row.id}">Delete</button>
                    </td>
                </tr>
            `).join(''));
        }

        const start = data.total ? ((data.page-1)*data.per_page)+1 : 0;
        const end = Math.min(data.page*data.per_page, data.total);
        $('#imp-summary').text(data.total ? `Showing ${start}-${end} of ${data.total}` : '0 invoices');

        let html = '';
        for (let i=1; i<=data.pages; i++) {
            html += `<li class="page-item ${i===Number(data.page)?'active':''}"><a href="#" data-page="${i}" class="page-link">${i}</a></li>`;
        }
        $('#imp-pagination').html(html);
    }

    function newInvoice() {
        $('#imp-form input, #imp-form select').prop('disabled', false);
        $('#imp-form .imp-remove-item, #imp-add-item').show();
        $('#imp-save').show();
        $('#imp-form .btn-secondary').text('Close');
        $('#imp-form')[0].reset();
        $('#imp-id').val('');
        $('#imp-modal-title').text('New Invoice');
        $('#imp-form-message').empty();
        $('#imp-items-table tbody').empty();
        addItem();
        const now = new Date();
        $('#imp-invoice-date').val(now.toISOString().slice(0,10));
        $('#imp-invoice-time').val(now.toTimeString().slice(0,5));
        modal.show();
    }

    function addItem(item = {}) {
        const units = (IMP.units || []).map(v => `<option value="${esc(v)}" ${item.unit===v?'selected':''}>${esc(v)}</option>`).join('');
        const row = $(`
            <tr>
                <td><input type="text" class="form-control item-name" required value="${esc(item.item_name || '')}"></td>
                <td><input type="number" class="form-control imp-calc amount" min="0" step="0.01" required value="${item.amount ?? 0}"></td>
                <td><input type="number" class="form-control imp-calc total-quantity" min="0" step="0.001" required value="${item.total_quantity ?? 0}"></td>
                <td><input type="number" class="form-control imp-calc consumed-quantity" min="0" step="0.001" value="${item.consumed_quantity ?? 0}"></td>
                <td><input type="number" class="form-control remaining-quantity" readonly value="${item.remaining_quantity ?? 0}"></td>
                <td><select class="form-select unit">${units}</select></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger imp-remove-item">&times;</button></td>
            </tr>
        `);
        $('#imp-items-table tbody').append(row);
        recalc();
    }

    function recalc() {
        let total = 0;
        $('#imp-items-table tbody tr').each(function(){
            const row = $(this);
            const amount = Number(row.find('.amount').val()) || 0;
            const qty = Number(row.find('.total-quantity').val()) || 0;
            const consumed = Number(row.find('.consumed-quantity').val()) || 0;
            row.find('.remaining-quantity').val((qty-consumed).toFixed(3).replace(/\.?0+$/,''));
            total += amount * qty;
        });
        $('#imp-grand-total').text(money(total));
    }

    function viewInvoice(id) {
        $.post(IMP.ajax_url, {action:'imp_get_invoice', nonce:IMP.nonce, id:id})
        .done(function(r){
            if (!r.success) return alert(r.data && r.data.message ? r.data.message : IMP.i18n.error);
            const x = r.data;

            $('#imp-id').val(x.id);
            $('#imp-invoice-number').val(x.invoice_number);
            $('#imp-invoice-date').val(x.invoice_date);
            $('#imp-invoice-time').val(x.invoice_time);
            $('#imp-branch').val(x.branch);

            $('#imp-modal-title').text('View Invoice');
            $('#imp-form-message').empty();
            $('#imp-items-table tbody').empty();

            (x.items || []).forEach(addItem);
            if (!(x.items || []).length) addItem();
            recalc();

            // Make the form read-only while viewing.
            $('#imp-form input, #imp-form select').prop('disabled', true);
            $('#imp-form .imp-remove-item, #imp-add-item').hide();
            $('#imp-save').hide();
            $('#imp-form .btn-secondary').text('Close');

            modal.show();
        });
    }

    function getInvoice(id) {
        $('#imp-form input, #imp-form select').prop('disabled', false);
        $('#imp-form .imp-remove-item, #imp-add-item').show();
        $('#imp-save').show();
        $('#imp-form .btn-secondary').text('Close');
        $.post(IMP.ajax_url, {action:'imp_get_invoice', nonce:IMP.nonce, id:id})
        .done(function(r){
            if (!r.success) return alert(r.data && r.data.message ? r.data.message : IMP.i18n.error);
            const x = r.data;
            $('#imp-id').val(x.id);
            $('#imp-invoice-number').val(x.invoice_number);
            $('#imp-invoice-date').val(x.invoice_date);
            $('#imp-invoice-time').val(x.invoice_time);
            $('#imp-branch').val(x.branch);
            $('#imp-modal-title').text('Edit Invoice');
            $('#imp-form-message').empty();
            $('#imp-items-table tbody').empty();
            (x.items || []).forEach(addItem);
            if (!(x.items || []).length) addItem();
            recalc();
            modal.show();
        });
    }

    function collectItems() {
        const items = [];
        $('#imp-items-table tbody tr').each(function(){
            const row = $(this);
            items.push({
                item_name: row.find('.item-name').val(),
                amount: row.find('.amount').val(),
                total_quantity: row.find('.total-quantity').val(),
                consumed_quantity: row.find('.consumed-quantity').val(),
                unit: row.find('.unit').val()
            });
        });
        return items;
    }

    function saveInvoice(e) {
        e.preventDefault();
        const btn = $('#imp-save');
        const original = btn.text();
        btn.prop('disabled', true).text('Saving...');
        $('#imp-form-message').empty();

        $.post(IMP.ajax_url, {
            action:'imp_save_invoice',
            nonce:IMP.nonce,
            id:$('#imp-id').val(),
            invoice_number:$('#imp-invoice-number').val(),
            invoice_date:$('#imp-invoice-date').val(),
            invoice_time:$('#imp-invoice-time').val(),
            branch:$('#imp-branch').val(),
            items:collectItems()
        }).done(function(r){
            if (!r.success) {
                $('#imp-form-message').html(`<div class="alert alert-danger">${esc(r.data && r.data.message ? r.data.message : IMP.i18n.error)}</div>`);
                return;
            }
            $('#imp-form-message').html(`<div class="alert alert-success">${esc(r.data.message)}</div>`);
            setTimeout(function(){ modal.hide(); loadInvoices(); }, 500);
        }).fail(function(xhr){
            const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : IMP.i18n.error;
            $('#imp-form-message').html(`<div class="alert alert-danger">${esc(msg)}</div>`);
        }).always(function(){ btn.prop('disabled', false).text(original); });
    }

    function deleteInvoice(id) {
        if (!window.confirm(IMP.i18n.confirmDelete)) return;
        $.post(IMP.ajax_url, {action:'imp_delete_invoice', nonce:IMP.nonce, id:id})
        .done(function(r){
            if (!r.success) return alert(r.data && r.data.message ? r.data.message : IMP.i18n.error);
            loadInvoices();
        });
    }

    init();
});
