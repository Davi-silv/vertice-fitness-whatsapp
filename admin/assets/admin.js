document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      const msg = el.getAttribute('data-confirm') || 'Confirmar?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  });

  const chartNodes = document.querySelectorAll('[data-chart]');
  chartNodes.forEach((canvas) => {
    if (typeof Chart === 'undefined') return;
    try {
      const raw = canvas.getAttribute('data-chart');
      const cfg = JSON.parse(raw);
      const ctx = canvas.getContext('2d');
      new Chart(ctx, {
        type: cfg.type || 'line',
        data: cfg.data,
        options: Object.assign({
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { labels: { color: '#9d917c' } } },
          scales: cfg.type === 'doughnut' ? {} : {
            x: { ticks: { color: '#9d917c' }, grid: { color: 'rgba(201,162,39,0.08)' } },
            y: { ticks: { color: '#9d917c' }, grid: { color: 'rgba(201,162,39,0.08)' } },
          },
        }, cfg.options || {}),
      });
    } catch (err) {
      console.warn('Chart error', err);
    }
  });
});
