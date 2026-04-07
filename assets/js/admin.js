(function($){
    'use strict';

    const state = {
        page: 1,
        per_page: 50
    };

    function esc(str) {
        return $('<div>').text(str || '').html();
    }

    function getFilters() {
        return {
            search: $('#crm-search').val(),
            call_status: $('#crm-filter-call-status').val(),
            confirmation_status: $('#crm-filter-confirmation-status').val(),
            city: $('#crm-filter-city').val(),
            date_from: $('#crm-date-from').val(),
            date_to: $('#crm-date-to').val(),
            high_value: $('#crm-filter-high-value').is(':checked') ? 1 : 0,
            due_followup: $('#crm-filter-due-followup').is(':checked') ? 1 : 0,
            page: state.page,
            per_page: state.per_page
        };
    }

    function renderSelect(field, selected) {
        const opts = codOrderCrm.fields[field].options;
        let html = '<select class="crm-inline-field" data-field="'+ field +'">';
        Object.keys(opts).forEach(function(value){
            html += '<option value="'+ esc(value) +'" '+ (value === selected ? 'selected' : '') +'>'+ esc(opts[value]) +'</option>';
        });
        html += '</select>';
        return html;
    }

    function statusClass(status){
        if (status === 'cod_confirmed') return 'status-green';
        if (status === 'cancelled' || status === 'fake_order') return 'status-red';
        return 'status-yellow';
    }

    function renderRows(rows) {
        const today = codOrderCrm.dueToday;
        if (!rows.length) {
            $('#cod-order-crm-body').html('<tr><td colspan="15">No orders found.</td></tr>');
            return;
        }

        const html = rows.map(function(row){
            const due = row.follow_up_date && row.follow_up_date.slice(0,10) === today;
            return '<tr data-order-id="'+ row.order_id +'" class="'+ (due ? 'crm-due-followup' : '') +'">' +
                '<td><input type="checkbox" class="crm-row-select" value="'+ row.order_id +'" /></td>' +
                '<td>#'+ row.order_id +'</td>' +
                '<td>'+ esc(row.customer_name) +'</td>' +
                '<td><a href="tel:'+ esc(row.phone) +'" class="button button-small crm-call-now">Call Now</a> '+ esc(row.phone) + (row.duplicate_phone_flag ? ' <span class="crm-risk" title="Duplicate phone">⚠ ' + row.duplicate_phone_count + '</span>' : '') +'</td>' +
                '<td>'+ esc(row.city) +'</td>' +
                '<td>'+ esc(row.product_name) +'</td>' +
                '<td>₹'+ esc(row.order_value) +'</td>' +
                '<td>'+ renderSelect('call_status', row.call_status) +'</td>' +
                '<td class="'+ statusClass(row.confirmation_status) +'">'+ renderSelect('confirmation_status', row.confirmation_status) +'</td>' +
                '<td>'+ renderSelect('cancellation_reason', row.cancellation_reason) +'</td>' +
                '<td class="crm-call-attempts">'+ row.call_attempts +'</td>' +
                '<td><input type="datetime-local" class="crm-inline-field" data-field="follow_up_date" value="'+ esc((row.follow_up_date || '').replace(' ', 'T')) +'" /></td>' +
                '<td>'+ renderSelect('order_priority', row.order_priority) +'</td>' +
                '<td><textarea class="crm-inline-notes" data-field="call_notes">'+ esc(row.call_notes) +'</textarea></td>' +
                '<td>'+ esc(row.order_date) +'</td>' +
                '</tr>';
        }).join('');

        $('#cod-order-crm-body').html(html);
    }

    function renderPagination(payload) {
        const total = payload.totalPage || 1;
        $('#crm-pagination').html('Page ' + payload.page + ' of ' + total + ' (' + payload.total + ' orders)');
    }

    function loadOrders() {
        const data = $.extend(getFilters(), {
            action: 'cod_order_crm_fetch_orders',
            nonce: codOrderCrm.nonce
        });

        $.post(codOrderCrm.ajaxUrl, data, function(response){
            if (!response.success) return;
            renderRows(response.data.rows);
            renderPagination(response.data);
        });
    }

    function updateField($el, valueOverride) {
        const $row = $el.closest('tr');
        const orderId = $row.data('order-id');
        const field = $el.data('field');
        const value = typeof valueOverride !== 'undefined' ? valueOverride : $el.val();

        $.post(codOrderCrm.ajaxUrl, {
            action: 'cod_order_crm_update_field',
            nonce: codOrderCrm.nonce,
            order_id: orderId,
            field: field,
            value: value
        });
    }

    function callNow($button){
        const $row = $button.closest('tr');
        const orderId = $row.data('order-id');
        $.post(codOrderCrm.ajaxUrl, {
            action: 'cod_order_crm_call_now',
            nonce: codOrderCrm.nonce,
            order_id: orderId
        }, function(response){
            if (response.success) {
                $row.find('.crm-call-attempts').text(response.data.call_attempts);
            }
        });
    }

    $(document).on('change', '.crm-inline-field', function(){
        updateField($(this));
    });

    $(document).on('blur', '.crm-inline-notes', function(){
        updateField($(this));
    });

    $(document).on('click', '.crm-call-now', function(){
        callNow($(this));
    });

    $('#crm-apply-filters').on('click', function(){
        state.page = 1;
        loadOrders();
    });

    $('#crm-reset-filters').on('click', function(){
        $('input[type="search"], input[type="date"]').val('');
        $('select').val('');
        $('#crm-filter-high-value, #crm-filter-due-followup').prop('checked', false);
        state.page = 1;
        loadOrders();
    });

    $('#crm-select-all').on('change', function(){
        $('.crm-row-select').prop('checked', $(this).is(':checked'));
    });

    $('#crm-apply-bulk').on('click', function(){
        const action = $('#crm-bulk-action').val();
        if (!action) return;
        const parts = action.split(':');
        const ids = $('.crm-row-select:checked').map(function(){ return $(this).val(); }).get();
        if (!ids.length) return;

        $.post(codOrderCrm.ajaxUrl, {
            action: 'cod_order_crm_bulk_update',
            nonce: codOrderCrm.nonce,
            order_ids: ids,
            field: parts[0],
            value: parts[1]
        }, function(response){
            if (response.success) {
                loadOrders();
            }
        });
    });

    $(document).ready(loadOrders);
})(jQuery);
