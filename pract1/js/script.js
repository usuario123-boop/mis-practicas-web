// Botón "Actualizar Precios": jala el tipo de cambio actual y recalcula
// USD y JPY al vuelo. El precio en MXN nunca cambia, es el que ya está
// fijado en el HTML (data-base-mxn), solo movemos las otras dos monedas.

const API_URL = 'https://open.er-api.com/v6/latest/MXN';

const updateBtn = document.getElementById('updateBtn');
const statusMsg = document.getElementById('statusMsg');

function formatCurrency(value, symbol, decimals) {
  const formatted = value.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });
  return symbol + formatted;
}

function markUpdated(el) {
  el.classList.add('updated');
  setTimeout(() => el.classList.remove('updated'), 1200);
}

async function actualizarPrecios() {
  updateBtn.classList.add('is-loading');
  updateBtn.disabled = true;
  statusMsg.textContent = 'Consultando tipo de cambio...';
  statusMsg.className = 'status-msg';

  try {
    const response = await fetch(API_URL);

    if (!response.ok) {
      throw new Error('Respuesta no válida del servidor');
    }

    const data = await response.json();

    if (data.result !== 'success' || !data.rates || !data.rates.USD || !data.rates.JPY) {
      throw new Error('Datos de tipo de cambio incompletos');
    }

    // la API regresa cuántos USD/JPY vale 1 MXN, por eso solo multiplicamos
    const tasaUSD = data.rates.USD;
    const tasaJPY = data.rates.JPY;

    document.querySelectorAll('.card').forEach((card) => {
      const mxnEl = card.querySelector('[data-currency="mxn"]');
      const usdEl = card.querySelector('[data-currency="usd"]');
      const jpyEl = card.querySelector('[data-currency="jpy"]');

      const baseMXN = parseFloat(mxnEl.dataset.baseMxn);

      const nuevoUSD = baseMXN * tasaUSD;
      const nuevoJPY = baseMXN * tasaJPY;

      usdEl.textContent = formatCurrency(nuevoUSD, '$', 2);
      jpyEl.textContent = formatCurrency(nuevoJPY, '¥', 0);

      markUpdated(usdEl);
      markUpdated(jpyEl);
    });

    const fecha = new Date(data.time_last_update_utc);
    statusMsg.textContent = 'Precios actualizados correctamente (' + fecha.toLocaleString('es-MX') + ')';
    statusMsg.className = 'status-msg ok';

  } catch (error) {
    statusMsg.textContent = 'No se pudo conectar con el servicio de tipo de cambio. Intenta de nuevo.';
    statusMsg.className = 'status-msg error';
    console.error(error);
  } finally {
    updateBtn.classList.remove('is-loading');
    updateBtn.disabled = false;
  }
}

updateBtn.addEventListener('click', actualizarPrecios);
