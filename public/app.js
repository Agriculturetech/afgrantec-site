const form = document.querySelector('#contact-form');
if (form) {
  const message = document.querySelector('#project-message');
  const production = document.querySelector('#production-select');
  const context = document.querySelector('#inquiry-context');
  const status = document.querySelector('#form-status');
  const fallback = document.querySelector('#email-fallback');
  const name = form.elements.namedItem('nombre');
  const params = new URLSearchParams(location.search);
  const topic = params.get('equipo')?.slice(0, 160);
  if (topic && !message.value) {
    message.value = 'Me interesa recibir información sobre ' + topic + '. ';
    context.textContent = 'Tu consulta: ' + topic;
    context.hidden = false;
  }
  const sector = params.get('produccion');
  if (sector === 'avicultura') production.value = 'Avicultura';
  if (sector === 'porcicultura') production.value = 'Porcicultura';
  if (sector === 'both') production.value = 'Avicultura y porcicultura';
  function clearPrepared() {
    fallback.hidden = true;
    fallback.removeAttribute('href');
    status.hidden = true;
    status.textContent = '';
  }
  form.addEventListener('input', clearPrepared);
  form.addEventListener('change', clearPrepared);
  [name, message].forEach(field => {
    field.addEventListener('input', () => field.setCustomValidity(''));
  });
  message.addEventListener('input', () => { context.hidden = true; });
  form.addEventListener('submit', event => {
    event.preventDefault();
    name.setCustomValidity(name.value.trim() ? '' : 'Escribe tu nombre.');
    message.setCustomValidity(message.value.trim() ? '' : 'Cuéntanos qué equipo o proyecto necesitas.');
    if (!form.reportValidity()) return;
    const data = new FormData(form);
    const subject = 'Consulta AFGRANTEC | ' + data.get('produccion');
    const body = 'Hola, equipo AFGRANTEC:\n\n' + data.get('mensaje') + '\n\nNombre: ' + data.get('nombre') + '\nEmpresa: ' + (data.get('empresa') || 'No indicada') + '\nPaís o territorio: ' + data.get('pais') + '\nProducción: ' + data.get('produccion');
    const href = 'mailto:fernando.munoz@afgrantec.com?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    fallback.href = href;
    fallback.hidden = false;
    status.hidden = false;
    status.textContent = 'Tu consulta está preparada. Revisa y envía el mensaje en tu aplicación de correo. Si no se abrió, usa el enlace de abajo.';
    location.href = href;
  });
  document.querySelector('#inquiry-fields').disabled = false;
  document.querySelector('#form-unavailable').hidden = true;
}
