function toggleDetails(id) {
    const row = document.getElementById(id);
    let toDisplay = row.style.display === 'table-row' ? 'none' : 'table-row';

    document.querySelectorAll('.log-details').forEach(row => {
        row.style.display = 'none';
    });

    row.style.display = toDisplay;
}

document.addEventListener('DOMContentLoaded', function() {
  const cfg = window.pxpCronConfig;
  if (!cfg) {
    console.warn('pxpCronConfig not defined');
    return;
  }

  const btn = document.getElementById('pxp-run-installments-sync');
  if (!btn) return;

  // Initialise les textes
  btn.textContent      = cfg.defaultText;
  btn.setAttribute('data-default-text', cfg.defaultText);
  btn.setAttribute('data-running-text', cfg.runningText);

  btn.addEventListener('click', function() {
    btn.disabled = true;
    btn.textContent = cfg.runningText;

    fetch(cfg.ajaxUrl, { credentials: 'same-origin' })
      .then(resp => resp.json())
      .then(data => {
        if (data.success) {
          const refreshBtn = document.getElementById('payxpert-refresh-dashboard');
          if (refreshBtn) {
            refreshBtn.click();
          }
        } else {
          alert('❌ ' + (data.message || 'An error occured'));
        }
      })
      .catch(err => {
        console.error(err);
        alert('An error occured during CRON call.');
      })
      .finally(() => {
        btn.disabled = false;
        btn.textContent = cfg.defaultText;
      });
  });
});
