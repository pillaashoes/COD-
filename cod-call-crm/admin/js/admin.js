(function($){
'use strict';

function toast(msg){window.alert(msg);} // WP native alternative

$(document).on('click', '.cod-crm-start-call', function(){
  const button = $(this);
  const orderId = button.data('order-id');
  const panelRow = $('#panel-' + orderId);
  $.post(codCRM.ajax, {action:'cod_crm_get_call_panel', nonce:codCRM.nonce, order_id:orderId}, function(res){
    if(!res.success){ toast(res.data?.message || 'Failed'); return; }
    panelRow.find('td').html(res.data.html);
    panelRow.show();
  });
});

$(document).on('change', '.cod-crm-outcome-form input[name="outcome"]', function(){
  const v = $(this).val();
  const form = $(this).closest('form');
  form.find('.cod-crm-callback-field').toggle(v === 'callback_requested');
  form.find('.cod-crm-payment-link').toggle(v === 'prepaid_converted');
});

$(document).on('submit', '.cod-crm-outcome-form', function(e){
  e.preventDefault();
  const form = $(this);
  const orderId = form.data('order-id');
  const payload = {
    action:'cod_crm_save_outcome', nonce:codCRM.nonce, order_id:orderId,
    outcome: form.find('input[name="outcome"]:checked').val(),
    reason: form.find('textarea[name="reason"]').val(),
    duration: form.find('input[name="duration"]').val(),
    next_callback_at: form.find('input[name="next_callback_at"]').val(),
    payment_link: form.find('input[name="payment_link"]').val()
  };
  $.post(codCRM.ajax, payload, function(res){
    if(!res.success){ toast(res.data?.message || 'Error'); return; }
    toast(res.data.message || 'Saved');
    const row = $('[data-order-row="'+orderId+'"]');
    row.find('.cod-crm-status-badge').text((res.data.order_status || '').replaceAll('_',' '));
    row.find('td').eq(6).text(res.data.attempts);
    $('#panel-'+orderId).hide().find('td').empty();
  });
});

$(document).on('click', '.cod-crm-add-order', function(e){
  e.preventDefault();
  const id = $(this).data('order-id');
  $.post(codCRM.ajax, {action:'cod_crm_add_wc_order_to_queue', nonce:codCRM.nonce, order_id:id}, function(res){
    toast(res.success ? 'Added to queue' : (res.data?.message || 'Failed'));
  });
});

$(document).on('click', '#cod-crm-preview-csv', function(){
  const form = $('#cod-crm-csv-form')[0];
  const data = new FormData(form);
  data.append('action', 'cod_crm_preview_csv');
  data.append('nonce', codCRM.nonce);
  $.ajax({url: codCRM.ajax, method:'POST', data, processData:false, contentType:false}).done(function(res){
    if(!res.success){ toast(res.data?.message || 'Failed'); return; }
    let html = '<table class="widefat striped"><thead><tr>';
    res.data.header.forEach(h => html += '<th>'+h+'</th>');
    html += '</tr></thead><tbody>';
    res.data.preview.forEach(r=>{ html+='<tr>'; res.data.header.forEach(h=> html += '<td>'+(r[h]||'')+'</td>'); html+='</tr>';});
    html += '</tbody></table>';
    $('#cod-crm-csv-preview').html(html);
  });
});

$(document).on('click', '#cod-crm-import-csv', function(){
  const form = $('#cod-crm-csv-form')[0];
  const data = new FormData(form);
  data.append('action', 'cod_crm_import_csv');
  data.append('nonce', codCRM.nonce);
  $.ajax({url: codCRM.ajax, method:'POST', data, processData:false, contentType:false}).done(function(res){
    if(!res.success){ toast(res.data?.message || 'Failed'); return; }
    toast('Imported: '+res.data.success+' | Skipped: '+res.data.skip+' | Errors: '+res.data.error);
  });
});

$(document).on('click', '#cod-crm-clear-sync-log', function(){
  $.post(codCRM.ajax, {action:'cod_crm_clear_sync_log', nonce:codCRM.nonce}, function(res){
    if(res.success){ location.reload(); }
  });
});

$(function(){
  if (window.codCrmOutcomeSeries && $('#codCrmStackedChart').length) {
    const rows = window.codCrmOutcomeSeries;
    const labels = [...new Set(rows.map(r=>r.d))].sort();
    const outcomes = [...new Set(rows.map(r=>r.o))];
    const colors = {confirmed:'#00a32a',cancelled:'#d63638',no_answer:'#dba617',wrong_number:'#8c1a1a',call_not_picked:'#dba617',busy:'#dba617',switched_off:'#dba617',callback_requested:'#0073aa',prepaid_converted:'#9b59b6'};
    const datasets = outcomes.map(o=>({label:o,data:labels.map(d=>{const f=rows.find(r=>r.d===d && r.o===o);return f?parseInt(f.c,10):0;}),backgroundColor:colors[o]||'#666'}));
    new Chart(document.getElementById('codCrmStackedChart'), {type:'bar',data:{labels,datasets},options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true}}}});
  }
  if (window.codCrmOutcomeDistribution && $('#codCrmPieChart').length) {
    const rows = window.codCrmOutcomeDistribution;
    new Chart(document.getElementById('codCrmPieChart'), {type:'pie',data:{labels:rows.map(r=>r.call_outcome),datasets:[{data:rows.map(r=>parseInt(r.cnt,10)),backgroundColor:['#00a32a','#d63638','#dba617','#8c1a1a','#0073aa','#9b59b6','#666']}]},options:{plugins:{legend:{position:'bottom'}}}});
  }
});

})(jQuery);
