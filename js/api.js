// =====================================================================
//  Cliente de la API REST: el frontend nunca toca la base de datos,
//  todo pasa por peticiones HTTP a /api con JSON.
// =====================================================================
const API_BASE = document.querySelector('meta[name="api-base"]')?.content || "api/";
const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content || "";

// Petición a la API. Devuelve el JSON o lanza un error con el mensaje del servidor.
async function api(ruta, { metodo = "GET", datos = null, flash = false } = {}) {
    const opciones = { method: metodo, headers: { "Accept": "application/json" }, credentials: "same-origin" };
    if (metodo !== "GET") {
        opciones.headers["Content-Type"] = "application/json";
        opciones.headers["X-CSRF-Token"] = CSRF();
        if (flash) opciones.headers["X-Flash"] = "1";
        if (datos) opciones.body = JSON.stringify(datos);
    }
    let respuesta;
    try {
        respuesta = await fetch(API_BASE + ruta, opciones);
    } catch {
        throw Object.assign(new Error("No hay conexión con el servidor. Revisa tu internet e intenta de nuevo."), { status: 0 });
    }
    let json = {};
    try { json = await respuesta.json(); } catch { /* respuesta vacía o no JSON */ }
    if (!respuesta.ok || json.ok === false) {
        throw Object.assign(new Error(json.error || "No fue posible completar la operación."), { status: respuesta.status, campos: json.campos || {} });
    }
    return json;
}

// Aviso flotante (éxito o error)
function avisar(texto, tipo = "ok") {
    const aviso = document.getElementById("aviso");
    if (!aviso) return;
    aviso.textContent = texto;
    aviso.dataset.tipo = tipo;
    aviso.hidden = false;
    clearTimeout(avisar.t);
    avisar.t = setTimeout(() => { aviso.hidden = true; }, 3200);
}

// Muestra los errores de validación debajo de cada campo
function mostrarErrores(form, campos = {}) {
    form.querySelectorAll(".error-api").forEach(e => e.remove());
    form.querySelectorAll("[aria-invalid]").forEach(e => e.removeAttribute("aria-invalid"));
    let primero = null;
    for (const [campo, mensaje] of Object.entries(campos)) {
        const input = form.querySelector(`[name="${campo}"]`);
        const msg = Object.assign(document.createElement("small"), { className: "error-campo error-api", textContent: mensaje });
        if (input) {
            input.setAttribute("aria-invalid", "true");
            (input.closest("label") || input.parentElement).appendChild(msg);
            primero = primero || input;
        } else {
            form.prepend(Object.assign(msg, { className: "alerta error-api" }));
        }
    }
    primero?.focus();
}

// Convierte un formulario en objeto (las casillas sin marcar no se envían)
function datosDe(form, boton) {
    const datos = {};
    new FormData(form).forEach((v, k) => { if (k !== "csrf") datos[k] = v; });
    if (boton?.name) datos[boton.name] = boton.value;
    return datos;
}

// Formularios con data-api: se envían a la API sin recargar.
//   data-api="carrito"  data-metodo="POST"  data-exito="aviso|recargar"  data-confirmar="¿Seguro...?"
document.addEventListener("submit", async e => {
    const form = e.target.closest("form[data-api]");
    if (!form) return;
    e.preventDefault();
    const boton = e.submitter;
    if (form.dataset.confirmar && !confirm(form.dataset.confirmar)) return;
    if (form.dataset.validar === "html" && !form.reportValidity()) return;

    const exito = form.dataset.exito || "recargar";
    const botones = form.querySelectorAll("button[type=submit], button:not([type])");
    const textoOriginal = boton?.innerHTML;
    botones.forEach(b => { b.disabled = true; });
    if (boton) boton.innerHTML = "Procesando…";
    form.setAttribute("aria-busy", "true");

    try {
        const r = await api(form.dataset.api, { metodo: form.dataset.metodo || "POST", datos: datosDe(form, boton), flash: exito !== "aviso" });
        mostrarErrores(form);
        if (r.redirigir) { location.href = r.redirigir; return; }
        if (exito === "recargar") { location.reload(); return; }
        avisar(r.mensaje || "Listo.");
        if (r.datos?.piezas !== undefined) actualizarBurbuja("carrito", r.datos.piezas);
        form.dispatchEvent(new CustomEvent("api:exito", { detail: r }));
    } catch (err) {
        if (err.status === 401) { location.href = "login.php?volver=" + encodeURIComponent(location.pathname.split("/").pop() + location.search); return; }
        mostrarErrores(form, err.campos);
        avisar(err.message, "error");
    } finally {
        botones.forEach(b => { b.disabled = false; });
        if (boton) boton.innerHTML = textoOriginal;
        form.removeAttribute("aria-busy");
    }
});

function actualizarBurbuja(cual, n) {
    const icono = document.querySelector(`.icono[href="${cual}.php"]`);
    if (!icono) return;
    let b = icono.querySelector(".burbuja");
    if (!n) { b?.remove(); return; }
    if (!b) { b = Object.assign(document.createElement("span"), { className: "burbuja" + (cual === "carrito" ? " cian" : "") }); icono.appendChild(b); }
    const antes = b.textContent;
    b.textContent = n;
    if (antes !== String(n)) window.MF_ANIM?.rebote(icono);
}

// Utilidades de formato
const pesos = n => "$" + Number(n || 0).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const esc = t => String(t ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const fechaCorta = f => new Date(String(f).replace(" ", "T")).toLocaleDateString("es-MX", { day: "numeric", month: "short", year: "numeric" });
