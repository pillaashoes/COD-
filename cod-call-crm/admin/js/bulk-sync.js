(function($){
'use strict';
let running = false;
function runBatch(offset, imported, skipped){
  $.post(codCRM.ajax,{action:'cod_crm_bulk_sync',nonce:codCRM.nonce,offset:offset},function(res){
    if(!res.success){ $('#cod-crm-progress-text').text(res.data?.message || 'Sync failed'); running=false; return; }
    const d = res.data;
    imported += d.imported;
    skipped += d.skipped;
    const pct = d.total > 0 ? Math.round((d.processed/d.total)*100) : 100;
    $('#cod-crm-progress-bar').css('width', pct + '%');
    $('#cod-crm-progress-text').text('Syncing... ' + d.processed + '/' + d.total + ' orders');
    if (d.done) {
      $('#cod-crm-progress-text').text(imported + ' imported, ' + skipped + ' skipped (duplicates)');
      running = false;
      return;
    }
    runBatch(d.next_offset, imported, skipped);
  });
}
$(document).on('click','#cod-crm-start-bulk-sync',function(){
  if(running){return;}
  running = true;
  $('#cod-crm-progress-bar').css('width','0%');
  $('#cod-crm-progress-text').text('Starting sync...');
  runBatch(0,0,0);
});
})(jQuery);
