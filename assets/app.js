(() => {
  const net = document.getElementById('net');
  function setNet() {
    if (!net) return;
    if (navigator.onLine) { net.textContent = '🟢 ONLINE'; net.className = 'pill online'; }
    else { net.textContent = '🔴 OFFLINE'; net.className = 'pill offline'; }
  }
  window.addEventListener('online', () => { setNet(); window.dispatchEvent(new Event('sicobc-online')); });
  window.addEventListener('offline', setNet);
  setNet();
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(console.error);


  function normalizeTextField(el) {
    if (!el || el.disabled || el.readOnly) return;
    const type = String(el.type || '').toLowerCase();
    if (type === 'email' || el.dataset.lowercase === 'email') {
      const start = el.selectionStart, end = el.selectionEnd;
      el.value = String(el.value || '').toLocaleLowerCase('pt-BR');
      try { if (start !== null) el.setSelectionRange(start,end); } catch(e){}
      return;
    }
    if (['password','file','hidden','number','date','time','datetime-local','tel','url','checkbox','radio','range','color'].includes(type)) return;
    if (el.dataset.preserveCase === '1') return;
    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
      const start = el.selectionStart, end = el.selectionEnd;
      el.value = String(el.value || '').toLocaleUpperCase('pt-BR');
      try { if (start !== null) el.setSelectionRange(start,end); } catch(e){}
    }
  }
  document.addEventListener('input', e => normalizeTextField(e.target));
  document.addEventListener('change', e => normalizeTextField(e.target));
  document.querySelectorAll('input,textarea').forEach(normalizeTextField);

  window.apiFetch = async (url, options={}) => {
    options.headers = Object.assign({'Accept':'application/json'}, options.headers || {});
    if (window.SICOBC?.csrf) options.headers['X-CSRF-Token'] = window.SICOBC.csrf;
    const r = await fetch(url, options);
    if (!r.ok) {
      let msg = 'Erro HTTP ' + r.status;
      try { const j = await r.json(); msg = j.error || msg; } catch(e){}
      throw new Error(msg);
    }
    return r.json();
  };
  window.escapeHtml = (s='') => String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
})();
