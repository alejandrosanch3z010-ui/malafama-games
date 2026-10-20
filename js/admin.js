// =====================================================================
//  Panel de administración: cada sección consulta y modifica datos
//  únicamente a través de la API REST (ver js/api.js).
// =====================================================================
(function () {
    const P = window.PANEL || { permisos: {} };
    const cuerpo = document.getElementById("panel-cuerpo");
    const seccion = document.getElementById("panel").dataset.seccion;
    const modal = document.getElementById("modal");
    const modalForm = document.getElementById("modal-form");
    let graficas = [];

    // ---------- Utilidades de interfaz ----------
    const cargando = (texto = "Cargando…") => `<div class="estado-carga"><div class="spinner" aria-hidden="true"></div><p>${texto}</p></div>`;
    const vacio = texto => `<p class="sin-contenido">${texto}</p>`;
    const chipEstado = e => {
        const clases = { pagado: "preventa", preparando: "pocas", enviado: "info", entregado: "disponible", cancelado: "agotado" };
        return `<span class="insignia ${clases[e] || "agotado"}">${esc(e)}</span>`;
    };
    const chipActivo = a => a ? '<span class="insignia disponible">Activo</span>' : '<span class="insignia agotado">Baja</span>';
    const hoy = () => new Date().toISOString().slice(0, 10);
    const haceDias = n => new Date(Date.now() - n * 86400000).toISOString().slice(0, 10);

    function mostrarError(err, reintentar) {
        cuerpo.innerHTML = `<div class="estado-carga"><h2>No se pudo cargar la información</h2><p>${esc(err.message)}</p>
            <button type="button" class="btn" id="reintentar">Reintentar</button></div>`;
        document.getElementById("reintentar").onclick = reintentar;
    }

    function tabla(columnas, filas, textoVacio = "No hay registros que coincidan.") {
        if (!filas.length) return vacio(textoVacio);
        return `<div class="tabla-scroll"><table class="tabla">
            <thead><tr>${columnas.map(c => `<th scope="col">${c}</th>`).join("")}</tr></thead>
            <tbody>${filas.join("")}</tbody></table></div>`;
    }

    function paginador(r, alCambiar) {
        const nav = document.createElement("nav");
        nav.className = "paginacion";
        nav.setAttribute("aria-label", "Páginas");
        if (r.totalPaginas <= 1) { nav.innerHTML = `<small>${r.total} registros</small>`; return nav; }
        const desde = Math.max(1, r.pagina - 2), hasta = Math.min(r.totalPaginas, r.pagina + 2);
        let html = `<button data-p="${r.pagina - 1}" ${r.pagina <= 1 ? "disabled" : ""} aria-label="Anterior">‹</button>`;
        for (let i = desde; i <= hasta; i++) html += `<button data-p="${i}" ${i === r.pagina ? 'class="activa" aria-current="page"' : ""}>${i}</button>`;
        html += `<button data-p="${r.pagina + 1}" ${r.pagina >= r.totalPaginas ? "disabled" : ""} aria-label="Siguiente">›</button><small>${r.total} registros · página ${r.pagina} de ${r.totalPaginas}</small>`;
        nav.innerHTML = html;
        nav.onclick = e => { const b = e.target.closest("button[data-p]"); if (b && !b.disabled) alCambiar(Number(b.dataset.p)); };
        return nav;
    }

    // Lee los filtros de un formulario como parámetros de la URL
    function parametros(form, extra = {}) {
        const p = new URLSearchParams();
        new FormData(form).forEach((v, k) => { if (v !== "") p.set(k, v); });
        Object.entries(extra).forEach(([k, v]) => p.set(k, v));
        return p.toString();
    }
    function alCambiarFiltros(form, fn) {
        let espera;
        form.addEventListener("input", e => { if (e.target.type === "search") { clearTimeout(espera); espera = setTimeout(fn, 350); } });
        form.addEventListener("change", e => { if (e.target.type !== "search") fn(); });
        form.addEventListener("submit", e => { e.preventDefault(); fn(); });
    }

    // Confirmación + petición + aviso
    async function accion(texto, ruta, metodo, datos, luego) {
        if (texto && !confirm(texto)) return;
        try {
            const r = await api(ruta, { metodo, datos });
            avisar(r.mensaje || "Listo.");
            luego && luego(r);
        } catch (err) {
            avisar(err.message, "error");
        }
    }

    // ---------- Ventana modal para formularios ----------
    function abrirModal(titulo, html, alGuardar, textoBoton = "Guardar") {
        document.getElementById("modal-titulo").textContent = titulo;
        document.getElementById("modal-cuerpo").innerHTML = html;
        document.getElementById("modal-guardar").textContent = textoBoton;
        mostrarErrores(modalForm);
        modalForm.onsubmit = async e => {
            e.preventDefault();
            const boton = document.getElementById("modal-guardar");
            boton.disabled = true;
            boton.textContent = "Guardando…";
            try {
                await alGuardar(modalForm);
                modal.close();
            } catch (err) {
                mostrarErrores(modalForm, err.campos || {});
                avisar(err.message, "error");
            } finally {
                boton.disabled = false;
                boton.textContent = textoBoton;
            }
        };
        modal.showModal();
        modalForm.querySelector("input, select, textarea")?.focus();
    }
    modal.addEventListener("click", e => { if (e.target === modal || e.target.closest("[data-cerrar-modal]")) modal.close(); });

    // ---------- Gráficas (Chart.js con los colores de la tienda) ----------
    const COLORES = ["#45f3ff", "#ff3fa4", "#b46bff", "#ffd23f", "#2bd576", "#2f7cf6", "#ff8a3d", "#a4a9c9"];
    function grafica(id, config) {
        if (!window.Chart) return;
        Chart.defaults.color = "#a4a9c9";
        Chart.defaults.borderColor = "rgba(255,255,255,.08)";
        Chart.defaults.font.family = "Inter, Arial, sans-serif";
        graficas.push(new Chart(document.getElementById(id), config));
    }
    function limpiarGraficas() { graficas.forEach(g => g.destroy()); graficas = []; }
    const opcionesPesos = { scales: { y: { beginAtZero: true, ticks: { callback: v => "$" + Number(v).toLocaleString("es-MX") } } } };

    // Descarga una tabla como CSV (se abre en Excel)
    function descargarCSV(nombre, encabezados, filas) {
        const celda = v => `"${String(v ?? "").replace(/"/g, '""')}"`;
        const csv = "\uFEFF" + [encabezados, ...filas].map(f => f.map(celda).join(",")).join("\n");
        const a = Object.assign(document.createElement("a"), { href: URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" })), download: nombre });
        a.click();
        URL.revokeObjectURL(a.href);
    }

    // =================================================================
    //  DASHBOARD
    // =================================================================
    async function dashboard() {
        cuerpo.innerHTML = cargando();
        try {
            const { datos: d } = await api("dashboard");
            const t = d.tarjetas;
            const cambio = t.ventas_mes_anterior ? Math.round((t.ventas_mes / t.ventas_mes_anterior - 1) * 100) : null;
            cuerpo.innerHTML = `
                <div class="kpis">
                    <div class="kpi"><span>Ventas del mes</span><b data-contar="${t.ventas_mes}" data-formato="pesos">${pesos(t.ventas_mes)}</b>${cambio !== null ? `<small class="${cambio >= 0 ? "sube" : "baja"}">${cambio >= 0 ? "▲" : "▼"} ${Math.abs(cambio)}% vs. mes anterior</small>` : ""}</div>
                    <div class="kpi"><span>Pedidos del mes</span><b data-contar="${t.pedidos_mes}">${t.pedidos_mes}</b><small>Ticket promedio ${pesos(t.ticket_promedio)}</small></div>
                    <div class="kpi"><span>Por enviar</span><b data-contar="${t.por_enviar}">${t.por_enviar}</b><small>Pagados o en preparación</small></div>
                    <div class="kpi"><span>Clientes activos</span><b data-contar="${t.clientes}">${t.clientes}</b><small>${t.juegos_activos} juegos en catálogo</small></div>
                    <div class="kpi ${t.stock_bajo ? "alerta-kpi" : ""}"><span>Existencias bajas</span><b data-contar="${t.stock_bajo}">${t.stock_bajo}</b><small>Físicos con ${P.stockBajo} piezas o menos</small></div>
                </div>
                <div class="graficas">
                    <div class="caja-grafica ancha"><h2>Ventas de los últimos 30 días</h2><canvas id="g-ventas" height="110"></canvas></div>
                    <div class="caja-grafica"><h2>Ventas por género</h2><canvas id="g-generos" height="220"></canvas></div>
                    <div class="caja-grafica"><h2>Métodos de pago</h2><canvas id="g-metodos" height="220"></canvas></div>
                </div>
                <div class="graficas">
                    <div class="caja-grafica"><h2>Últimos pedidos</h2>${tabla(["Folio", "Cliente", "Estado", "Total"],
                        d.ultimos_pedidos.map(p => `<tr><td><a href="pedido.php?id=${p.id}">${esc(p.folio)}</a><br><small>${fechaCorta(p.creado)}</small></td><td>${esc(p.cliente)}</td><td>${chipEstado(p.estado)}</td><td class="num">${pesos(p.total)}</td></tr>`))}</div>
                    <div class="caja-grafica"><h2>⚠ Alertas de existencias</h2>${tabla(["Juego", "Formato", "Existencia"],
                        d.stock_bajo.map(s => `<tr><td>${esc(s.titulo)}${s.version !== "Estándar" ? ` <small>${esc(s.version)}</small>` : ""}</td><td>${s.formato === "fisico" ? "Físico" : "Digital"}</td><td class="num"><span class="insignia ${s.stock == 0 ? "agotado" : "pocas"}">${s.stock == 0 ? "Agotado" : s.stock + " pzas"}</span></td></tr>`),
                        "Todo el inventario está en orden.")}
                        ${P.permisos["inventario.ajustar"] && d.stock_bajo.length ? '<a class="btn btn-secundario" href="admin.php?s=inventario">Ir a inventario</a>' : ""}</div>
                </div>`;

            window.MF_ANIM?.kpis(cuerpo);
            // Se completan los días sin ventas para que la línea sea continua
            const porDia = Object.fromEntries(d.ventas_diarias.map(v => [v.fecha, Number(v.total)]));
            const dias = [...Array(30)].map((_, i) => haceDias(29 - i));
            grafica("g-ventas", { type: "line", data: { labels: dias.map(f => f.slice(8) + "/" + f.slice(5, 7)),
                datasets: [{ label: "Ventas", data: dias.map(f => porDia[f] || 0), borderColor: "#45f3ff", backgroundColor: "rgba(69,243,255,.12)", fill: true, tension: .3 }] },
                options: { plugins: { legend: { display: false } }, ...opcionesPesos } });
            grafica("g-generos", { type: "bar", data: { labels: d.ventas_por_genero.map(g => g.genero),
                datasets: [{ label: "Ventas", data: d.ventas_por_genero.map(g => Number(g.total)), backgroundColor: COLORES }] },
                options: { plugins: { legend: { display: false } }, ...opcionesPesos } });
            const nombresMetodo = { tarjeta: "Tarjeta", paypal: "PayPal", mercadopago: "Mercado Pago", oxxo: "OXXO Pay" };
            grafica("g-metodos", { type: "doughnut", data: { labels: d.metodos_pago.map(m => nombresMetodo[m.metodo] || m.metodo),
                datasets: [{ data: d.metodos_pago.map(m => Number(m.pedidos)), backgroundColor: COLORES, borderWidth: 0 }] },
                options: { plugins: { legend: { position: "bottom" } } } });
        } catch (err) { mostrarError(err, dashboard); }
    }

    // =================================================================
    //  PEDIDOS
    // =================================================================
    function pedidos() {
        cuerpo.innerHTML = `
            <form class="barra-filtros" id="f-pedidos">
                <label>Buscar<input type="search" name="q" placeholder="Folio, cliente o correo"></label>
                <label>Estado<select name="estado"><option value="">Todos</option>${["pagado", "preparando", "enviado", "entregado", "cancelado"].map(e => `<option>${e}</option>`).join("")}</select></label>
                <label>Desde<input type="date" name="desde"></label>
                <label>Hasta<input type="date" name="hasta"></label>
                <label>Ordenar<select name="orden"><option value="recientes">Más recientes</option><option value="antiguos">Más antiguos</option><option value="total_desc">Total mayor a menor</option><option value="total_asc">Total menor a mayor</option></select></label>
            </form>
            <div id="t-pedidos"></div>`;
        const form = document.getElementById("f-pedidos");
        const caja = document.getElementById("t-pedidos");
        let pagina = 1;
        async function cargar() {
            caja.innerHTML = cargando();
            try {
                const r = await api("pedidos?" + parametros(form, { page: pagina, limit: 12 }));
                caja.innerHTML = tabla(["Folio", "Fecha", "Cliente", "Productos", "Estado", "Total", "Acciones"], r.datos.map(p => {
                    const puedeAvanzar = P.permisos["pedidos.cambiar_estado"] && Number(p.con_envio) && ["pagado", "preparando", "enviado"].includes(p.estado);
                    const puedeCancelar = P.permisos["pedidos.cancelar"] && ["pagado", "preparando"].includes(p.estado);
                    return `<tr><td><a href="pedido.php?id=${p.id}">${esc(p.folio)}</a></td><td>${fechaCorta(p.creado)}</td><td>${esc(p.cliente)}</td>
                        <td>${p.piezas} · ${Number(p.con_envio) ? "Envío" : "Digital"}</td><td>${chipEstado(p.estado)}</td><td class="num">${pesos(p.total)}</td>
                        <td class="acciones"><a class="btn-mini" href="pedido.php?id=${p.id}">Ver</a>
                        ${puedeAvanzar ? `<button class="btn-mini" data-avanzar="${p.id}">Avanzar</button>` : ""}
                        ${puedeCancelar ? `<button class="btn-mini peligro" data-cancelar="${p.id}" data-folio="${esc(p.folio)}">Cancelar</button>` : ""}</td></tr>`;
                }), "No hay pedidos con esos filtros.");
                caja.appendChild(paginador(r, p => { pagina = p; cargar(); }));
            } catch (err) { caja.innerHTML = vacio(esc(err.message)); }
        }
        caja.addEventListener("click", e => {
            const av = e.target.closest("[data-avanzar]");
            if (av) accion("¿Pasar el pedido a la siguiente etapa del envío?", `pedidos/${av.dataset.avanzar}/estado`, "PATCH", null, cargar);
            const ca = e.target.closest("[data-cancelar]");
            if (ca) abrirModal(`Cancelar pedido ${ca.dataset.folio}`,
                `<p class="ayuda-pago">Se regresará el inventario, se anularán los códigos digitales y se ajustarán los puntos del cliente. Esta acción no se puede deshacer.</p>
                 <label>Motivo de la cancelación<textarea name="motivo" rows="3" maxlength="255" required></textarea></label>`,
                async f => { const r = await api(`pedidos/${ca.dataset.cancelar}/cancelar`, { metodo: "POST", datos: { motivo: f.motivo.value } }); avisar(r.mensaje); cargar(); },
                "Cancelar pedido");
        });
        alCambiarFiltros(form, () => { pagina = 1; cargar(); });
        cargar();
    }

    // =================================================================
    //  JUEGOS (CRUD)
    // =================================================================
    let listaGeneros = null;
    async function generosActivos() {
        if (!listaGeneros) listaGeneros = (await api("generos?todos=1")).datos;
        return listaGeneros;
    }

    // Una fila por cada versión + formato (Estándar físico, Deluxe digital, etc.)
    function filaEdicion(e, i) {
        const existe = !!e.id;
        return `<div class="fila-ed" data-i="${i}" ${existe ? `data-id="${e.id}"` : ""}>
            <div class="campos-ed">
                <label>Versión<input name="ed_${i}_version" maxlength="40" value="${esc(e.version || "Estándar")}" ${existe ? "readonly" : ""} list="versiones-comunes"></label>
                <label>Formato<select name="ed_${i}_formato" ${existe ? "disabled" : ""}>
                    <option value="fisico" ${e.formato === "fisico" ? "selected" : ""}>Físico</option>
                    <option value="digital" ${e.formato === "digital" ? "selected" : ""}>Digital</option></select></label>
                <label>Precio<input type="number" name="ed_${i}_precio" min="1" step="0.01" value="${e.precio ?? ""}"></label>
                <label>Oferta<input type="number" name="ed_${i}_precio_oferta" min="1" step="0.01" value="${e.precio_oferta ?? ""}" placeholder="Opcional"></label>
                <label>${existe ? "Existencia" : "Existencia inicial"}<input type="number" name="ed_${i}_stock" min="0" step="1" value="${e.stock ?? (e.formato === "digital" ? 999 : 0)}" ${existe ? 'disabled title="Se ajusta en Inventario"' : ""}></label>
            </div>
            <label>Contenido extra de la versión<input name="ed_${i}_incluye" maxlength="255" value="${esc(e.incluye)}" placeholder="Solo para Deluxe, Ultimate, etc. (opcional)"></label>
            ${existe ? '<small class="ayuda">Esta versión ya existe: su existencia se ajusta en Inventario y no se puede quitar porque puede tener ventas.</small>' : '<button type="button" class="btn-mini peligro" data-quitar-ed>Quitar</button>'}
        </div>`;
    }

    function formularioJuego(j, generos) {
        const ediciones = j?.ediciones?.length ? j.ediciones : [{ version: "Estándar", formato: "fisico" }, { version: "Estándar", formato: "digital" }];
        return `
            <div class="campos-2">
                <label>Título<input name="titulo" maxlength="120" required value="${esc(j?.titulo)}"></label>
                <label>Género<select name="genero_id">${generos.filter(g => g.activo || g.id === j?.genero_id).map(g => `<option value="${g.id}" ${g.id === j?.genero_id ? "selected" : ""}>${esc(g.nombre)}</option>`).join("")}</select></label>
                <label>Fecha de lanzamiento<input type="date" name="fecha_lanzamiento" required value="${esc(j?.fecha_lanzamiento)}"></label>
                <label>Desarrollador<input name="desarrollador" maxlength="80" required value="${esc(j?.desarrollador)}"></label>
                <label>Clasificación<select name="clasificacion">${["E", "E10+", "T", "M", "RP"].map(c => `<option ${c === j?.clasificacion ? "selected" : ""}>${c}</option>`).join("")}</select></label>
                <label>ID del tráiler de YouTube<input name="trailer_youtube" maxlength="20" value="${esc(j?.trailer_youtube)}" placeholder="Lo que va después de v="></label>
                <label>Voces<input name="voces" maxlength="120" required value="${esc(j?.voces ?? "Español (Latinoamérica), Inglés")}"></label>
                <label>Subtítulos<input name="subtitulos" maxlength="120" required value="${esc(j?.subtitulos ?? "Español, Inglés")}"></label>
                <label>Portada (ruta de la imagen)<input name="portada" maxlength="150" value="${esc(j?.portada)}" placeholder="img/portadas/juego.webp"></label>
                <label>Nota de internet<input name="nota_internet" maxlength="120" value="${esc(j?.nota_internet)}" placeholder="Opcional"></label>
            </div>
            <label class="check"><input type="checkbox" name="requiere_internet" ${j?.requiere_internet ? "checked" : ""}> Requiere conexión a internet</label>
            <label>Sinopsis<textarea name="sinopsis" rows="4" maxlength="3000" required>${esc(j?.sinopsis)}</textarea></label>
            <fieldset class="fieldset-ed"><legend>Versiones y formatos</legend>
                <datalist id="versiones-comunes"><option value="Estándar"><option value="Digital Deluxe"><option value="Deluxe"><option value="Ultimate"><option value="Premium"><option value="Gold"><option value="Edición de colección"></datalist>
                <div id="lista-ediciones">${ediciones.map(filaEdicion).join("")}</div>
                <button type="button" class="btn-mini" id="agregar-ed">+ Agregar versión o formato</button>
            </fieldset>`;
    }

    function datosJuego(f) {
        const d = {};
        ["titulo", "genero_id", "fecha_lanzamiento", "desarrollador", "clasificacion", "trailer_youtube", "voces", "subtitulos", "portada", "nota_internet", "sinopsis"].forEach(k => { d[k] = f[k].value.trim(); });
        d.requiere_internet = f.requiere_internet.checked;
        d.ediciones = [...f.querySelectorAll(".fila-ed")].map(fila => {
            const i = fila.dataset.i, v = n => f.querySelector(`[name="ed_${i}_${n}"]`).value;
            return { version: v("version").trim() || "Estándar", formato: v("formato"), precio: v("precio"), precio_oferta: v("precio_oferta") || null, stock: v("stock") || 0, incluye: v("incluye").trim(), _i: i };
        });
        return d;
    }

    // Traduce "ediciones.0.precio" al nombre del campo del formulario ("ed_3_precio")
    function camposJuego(err, d) {
        const campos = {};
        Object.entries(err.campos || {}).forEach(([k, v]) => {
            const m = k.match(/^ediciones\.(\d+)\.(\w+)$/);
            campos[m ? `ed_${d.ediciones[m[1]]?._i}_${m[2]}` : k] = v;
        });
        err.campos = campos;
        return err;
    }

    async function editarJuego(id, recargar) {
        try {
            const generos = await generosActivos();
            const j = id ? (await api("juegos/" + id)).datos : null;
            abrirModal(j ? `Editar ${j.titulo}` : "Nuevo juego", formularioJuego(j, generos), async f => {
                const d = datosJuego(f);
                try {
                    const r = await api(j ? "juegos/" + id : "juegos", { metodo: j ? "PUT" : "POST", datos: d });
                    avisar(r.mensaje);
                    recargar();
                } catch (err) { throw camposJuego(err, d); }
            });
            // Agregar o quitar filas de versión dentro de la ventana
            let siguiente = document.querySelectorAll(".fila-ed").length;
            document.getElementById("agregar-ed").onclick = () => {
                document.getElementById("lista-ediciones").insertAdjacentHTML("beforeend", filaEdicion({ version: "Digital Deluxe", formato: "digital" }, siguiente++));
                document.querySelector(".fila-ed:last-child input")?.focus();
            };
            document.getElementById("lista-ediciones").onclick = e => { if (e.target.closest("[data-quitar-ed]")) e.target.closest(".fila-ed").remove(); };
        } catch (err) { avisar(err.message, "error"); }
    }

    async function juegos() {
        cuerpo.innerHTML = cargando();
        let generos;
        try { generos = await generosActivos(); } catch (err) { return mostrarError(err, juegos); }
        cuerpo.innerHTML = `
            <form class="barra-filtros" id="f-juegos">
                <label>Buscar<input type="search" name="q" placeholder="Título o desarrollador"></label>
                <label>Género<select name="genero"><option value="">Todos</option>${generos.map(g => `<option value="${g.id}">${esc(g.nombre)}</option>`).join("")}</select></label>
                <label>Estado<select name="activo"><option value="">Todos</option><option value="1">Activos</option><option value="0">Dados de baja</option></select></label>
                <label>Ordenar<select name="orden"><option value="titulo">Nombre A-Z</option><option value="novedades">Más recientes</option><option value="precio_asc">Precio menor a mayor</option><option value="precio_desc">Precio mayor a menor</option><option value="calificacion">Mejor calificados</option></select></label>
                <button type="button" class="btn" id="nuevo-juego">+ Nuevo juego</button>
            </form>
            <div id="t-juegos"></div>`;
        const form = document.getElementById("f-juegos");
        const caja = document.getElementById("t-juegos");
        let pagina = 1;
        async function cargar() {
            caja.innerHTML = cargando();
            try {
                const r = await api("juegos?todos=1&" + parametros(form, { page: pagina, limit: 12 }));
                caja.innerHTML = tabla(["Juego", "Género", "Precio desde", "Existencia", "Calificación", "Estado", "Acciones"], r.datos.map(j => `
                    <tr class="${j.activo ? "" : "fila-baja"}"><td><a href="juego.php?j=${encodeURIComponent(j.slug)}" target="_blank">${esc(j.titulo)}</a><br><small>${esc(j.formatos.map(f => f === "fisico" ? "Físico" : "Digital").join(" · "))}${j.preventa ? " · Preventa" : ""}</small></td>
                    <td>${esc(j.genero)}</td><td class="num">${pesos(j.precio_final)}${j.descuento ? ` <small class="texto-oferta">-${j.descuento}%</small>` : ""}</td>
                    <td class="num">${j.stock}</td><td class="num">${j.total_resenas ? "★ " + j.calificacion.toFixed(1) : "–"}</td><td>${chipActivo(j.activo)}</td>
                    <td class="acciones"><button class="btn-mini" data-editar="${j.id}">Editar</button>
                    ${j.activo ? `<button class="btn-mini peligro" data-baja="${j.id}" data-titulo="${esc(j.titulo)}">Dar de baja</button>` : `<button class="btn-mini" data-reactivar="${j.id}">Reactivar</button>`}</td></tr>`));
                caja.appendChild(paginador(r, p => { pagina = p; cargar(); }));
            } catch (err) { caja.innerHTML = vacio(esc(err.message)); }
        }
        document.getElementById("nuevo-juego").onclick = () => editarJuego(null, cargar);
        caja.addEventListener("click", e => {
            const b = e.target.closest("button");
            if (!b) return;
            if (b.dataset.editar) editarJuego(b.dataset.editar, cargar);
            if (b.dataset.baja) accion(`¿Dar de baja "${b.dataset.titulo}"? Dejará de aparecer en la tienda, pero sus ventas y reseñas se conservan.`, "juegos/" + b.dataset.baja, "DELETE", null, cargar);
            if (b.dataset.reactivar) accion("¿Reactivar este juego en la tienda?", "juegos/" + b.dataset.reactivar, "PATCH", { activo: true }, cargar);
        });
        alCambiarFiltros(form, () => { pagina = 1; cargar(); });
        cargar();
    }

    // =================================================================
    //  GÉNEROS (CRUD)
    // =================================================================
    async function generos() {
        cuerpo.innerHTML = cargando();
        try {
            listaGeneros = null;
            const lista = await generosActivos();
            cuerpo.innerHTML = `<div class="barra-filtros"><button type="button" class="btn" id="nuevo-genero">+ Nuevo género</button></div>` +
                tabla(["Género", "Juegos activos", "Estado", "Acciones"], lista.map(g => `
                    <tr class="${g.activo ? "" : "fila-baja"}"><td>${esc(g.nombre)}</td><td class="num">${g.juegos_activos}</td><td>${chipActivo(g.activo)}</td>
                    <td class="acciones"><button class="btn-mini" data-editar="${g.id}">Editar</button>
                    ${g.activo ? `<button class="btn-mini peligro" data-baja="${g.id}" data-nombre="${esc(g.nombre)}">Dar de baja</button>` : `<button class="btn-mini" data-reactivar="${g.id}" data-nombre="${esc(g.nombre)}">Reactivar</button>`}</td></tr>`));
            const formGenero = (g) => abrirModal(g ? "Editar género" : "Nuevo género", `<label>Nombre<input name="nombre" maxlength="40" required value="${esc(g?.nombre)}"></label>`, async f => {
                const r = await api(g ? "generos/" + g.id : "generos", { metodo: g ? "PUT" : "POST", datos: { nombre: f.nombre.value.trim() } });
                avisar(r.mensaje); generos();
            });
            document.getElementById("nuevo-genero").onclick = () => formGenero(null);
            cuerpo.onclick = e => {
                const b = e.target.closest("button");
                if (!b) return;
                if (b.dataset.editar) formGenero(lista.find(g => g.id == b.dataset.editar));
                if (b.dataset.baja) accion(`¿Dar de baja el género "${b.dataset.nombre}"?`, "generos/" + b.dataset.baja, "DELETE", null, generos);
                if (b.dataset.reactivar) accion(`¿Reactivar el género "${b.dataset.nombre}"?`, "generos/" + b.dataset.reactivar, "PUT", { nombre: b.dataset.nombre, activo: true }, generos);
            };
        } catch (err) { mostrarError(err, generos); }
    }

    // =================================================================
    //  INVENTARIO
    // =================================================================
    async function inventario() {
        cuerpo.innerHTML = `
            <form class="barra-filtros" id="f-inv">
                <label>Buscar<input type="search" name="buscar" placeholder="Nombre del juego"></label>
                <label class="check"><input type="checkbox" name="solo_bajo" value="1"> Solo existencias bajas (≤ ${P.stockBajo})</label>
            </form>
            <div id="t-inv"></div>
            <h2 class="subtitulo-panel">Movimientos de inventario</h2>
            <div id="t-mov"></div>`;
        const form = document.getElementById("f-inv");
        const caja = document.getElementById("t-inv");
        let filas = [];
        function dibujar() {
            const q = form.buscar.value.trim().toLowerCase();
            caja.innerHTML = tabla(["Juego", "Formato", "Existencia", "Precio", "Vendidos (30 días)", "Acción"],
                filas.filter(f => f.titulo.toLowerCase().includes(q)).map(f => `
                <tr class="${f.alerta ? "fila-alerta" : ""}"><td>${esc(f.titulo)}${f.version !== "Estándar" ? `<br><small>${esc(f.version)}</small>` : ""}</td><td>${f.formato === "fisico" ? "Físico" : "Digital"}</td>
                <td class="num">${f.alerta ? `<span class="insignia ${f.stock == 0 ? "agotado" : "pocas"}">${f.stock}</span>` : f.stock}</td>
                <td class="num">${pesos(f.precio)}</td><td class="num">${f.vendidos_30d}</td>
                <td class="acciones"><button class="btn-mini" data-ajustar="${f.edicion_id}" data-stock="${f.stock}" data-titulo="${esc(f.titulo)} · ${esc(f.version)} (${f.formato})">Ajustar</button></td></tr>`));
        }
        async function cargar() {
            caja.innerHTML = cargando();
            try { filas = (await api("reportes/inventario" + (form.solo_bajo.checked ? "?solo_bajo=1" : ""))).datos.filas; dibujar(); }
            catch (err) { caja.innerHTML = vacio(esc(err.message)); }
        }
        const cajaMov = document.getElementById("t-mov");
        let paginaMov = 1;
        async function movimientos() {
            cajaMov.innerHTML = cargando();
            try {
                const r = await api(`inventario/movimientos?page=${paginaMov}&limit=10`);
                const tipos = { venta: "Venta", cancelacion: "Cancelación", ajuste: "Ajuste" };
                cajaMov.innerHTML = tabla(["Fecha", "Producto", "Tipo", "Cantidad", "Existencia", "Usuario", "Nota"], r.datos.map(m => `
                    <tr><td>${fechaCorta(m.creado)}</td><td>${esc(m.producto)}${m.formato ? ` <small>(${m.version && m.version !== "Estándar" ? esc(m.version) + " · " : ""}${m.formato})</small>` : ""}</td><td>${tipos[m.tipo]}${m.folio ? `<br><small>${esc(m.folio)}</small>` : ""}</td>
                    <td class="num ${m.cantidad > 0 ? "mas" : "menos"}">${m.cantidad > 0 ? "+" : ""}${m.cantidad}</td><td class="num">${m.stock_resultante}</td><td>${esc(m.usuario || "Sistema")}</td><td><small>${esc(m.nota || "")}</small></td></tr>`));
                cajaMov.appendChild(paginador(r, p => { paginaMov = p; movimientos(); }));
            } catch (err) { cajaMov.innerHTML = vacio(esc(err.message)); }
        }
        caja.addEventListener("click", e => {
            const b = e.target.closest("[data-ajustar]");
            if (!b) return;
            abrirModal("Ajustar existencia", `<p><b>${b.dataset.titulo}</b> · existencia actual: ${b.dataset.stock}</p>
                <label>Nueva existencia<input type="number" name="stock" min="0" max="9999" step="1" required value="${b.dataset.stock}"></label>
                <label>Motivo del ajuste<input name="nota" maxlength="160" placeholder="Ej. llegó resurtido del proveedor"></label>`, async f => {
                const r = await api("ediciones/" + b.dataset.ajustar, { metodo: "PATCH", datos: { stock: f.stock.value, nota: f.nota.value } });
                avisar(r.mensaje); cargar(); movimientos();
            });
        });
        form.addEventListener("input", e => { if (e.target.name === "buscar") dibujar(); });
        form.addEventListener("change", e => { if (e.target.name === "solo_bajo") cargar(); });
        form.addEventListener("submit", e => e.preventDefault());
        cargar();
        movimientos();
    }

    // =================================================================
    //  USUARIOS (CRUD)
    // =================================================================
    function usuarios() {
        cuerpo.innerHTML = `
            <form class="barra-filtros" id="f-usu">
                <label>Buscar<input type="search" name="q" placeholder="Nombre o correo"></label>
                <label>Rol<select name="rol"><option value="">Todos</option><option value="admin">Administrador</option><option value="empleado">Empleado</option><option value="cliente">Cliente</option></select></label>
                <label>Estado<select name="activo"><option value="">Todos</option><option value="1">Activos</option><option value="0">Dados de baja</option></select></label>
                <label>Ordenar<select name="orden"><option value="nombre">Nombre A-Z</option><option value="nombre_desc">Nombre Z-A</option><option value="recientes">Más recientes</option><option value="gastado">Mayor gasto</option></select></label>
                <button type="button" class="btn" id="nuevo-usuario">+ Nuevo usuario</button>
            </form>
            <div id="t-usu"></div>`;
        const form = document.getElementById("f-usu");
        const caja = document.getElementById("t-usu");
        let pagina = 1, datos = [];
        const opcionesRol = actual => ["cliente", "empleado", "admin"].map(r => `<option value="${r}" ${r === actual ? "selected" : ""}>${{ cliente: "Cliente", empleado: "Empleado", admin: "Administrador" }[r]}</option>`).join("");
        async function cargar() {
            caja.innerHTML = cargando();
            try {
                const r = await api("usuarios?" + parametros(form, { page: pagina, limit: 12 }));
                datos = r.datos;
                caja.innerHTML = tabla(["Usuario", "Rol", "Pedidos", "Gastado", "Puntos", "Estado", "Acciones"], datos.map(u => `
                    <tr class="${u.activo ? "" : "fila-baja"}"><td>${esc(u.nombre)}<br><small>${esc(u.email)}</small></td><td>${esc(u.rol_nombre)}</td>
                    <td class="num">${u.pedidos}</td><td class="num">${pesos(u.gastado)}</td><td class="num">${u.puntos}</td><td>${chipActivo(u.activo)}</td>
                    <td class="acciones"><button class="btn-mini" data-editar="${u.id}">Editar</button><button class="btn-mini" data-clave="${u.id}">Contraseña</button>
                    ${u.id === P.id ? "" : u.activo ? `<button class="btn-mini peligro" data-baja="${u.id}" data-nombre="${esc(u.nombre)}">Dar de baja</button>` : `<button class="btn-mini" data-reactivar="${u.id}">Reactivar</button>`}</td></tr>`));
                caja.appendChild(paginador(r, p => { pagina = p; cargar(); }));
            } catch (err) { caja.innerHTML = vacio(esc(err.message)); }
        }
        document.getElementById("nuevo-usuario").onclick = () => abrirModal("Nuevo usuario", `
            <label>Nombre<input name="nombre" maxlength="100" required></label>
            <label>Correo<input type="email" name="email" maxlength="120" required></label>
            <label>Contraseña<input type="password" name="password" minlength="8" required autocomplete="new-password"><small class="ayuda">Mínimo 8 caracteres.</small></label>
            <label>Rol<select name="rol">${opcionesRol("empleado")}</select></label>`, async f => {
            const r = await api("usuarios", { metodo: "POST", datos: { nombre: f.nombre.value, email: f.email.value, password: f.password.value, rol: f.rol.value } });
            avisar(r.mensaje); cargar();
        });
        caja.addEventListener("click", e => {
            const b = e.target.closest("button");
            if (!b) return;
            const u = datos.find(x => x.id == (b.dataset.editar || b.dataset.clave));
            if (b.dataset.editar) abrirModal("Editar usuario", `
                <label>Nombre<input name="nombre" maxlength="100" required value="${esc(u.nombre)}"></label>
                <label>Correo<input type="email" name="email" maxlength="120" required value="${esc(u.email)}"></label>
                <label>Teléfono<input name="telefono" value="${esc(u.telefono)}" inputmode="numeric"></label>
                <label>Dirección<textarea name="direccion" rows="2" maxlength="255">${esc(u.direccion)}</textarea></label>
                <label>Rol<select name="rol" ${u.id === P.id ? "disabled" : ""}>${opcionesRol(u.rol)}</select></label>`, async f => {
                const r = await api("usuarios/" + u.id, { metodo: "PUT", datos: { nombre: f.nombre.value, email: f.email.value, telefono: f.telefono.value, direccion: f.direccion.value, rol: f.rol.value } });
                avisar(r.mensaje); cargar();
            });
            if (b.dataset.clave) abrirModal(`Restablecer contraseña de ${u.nombre}`, `
                <label>Nueva contraseña<input type="password" name="password" minlength="8" required autocomplete="new-password"><small class="ayuda">Mínimo 8 caracteres.</small></label>`, async f => {
                const r = await api("usuarios/" + u.id, { metodo: "PATCH", datos: { password: f.password.value } });
                avisar(r.mensaje);
            });
            if (b.dataset.baja) accion(`¿Dar de baja a ${b.dataset.nombre}? Ya no podrá iniciar sesión, pero sus pedidos se conservan.`, "usuarios/" + b.dataset.baja, "DELETE", null, cargar);
            if (b.dataset.reactivar) accion("¿Reactivar esta cuenta?", "usuarios/" + b.dataset.reactivar, "PATCH", { activo: true }, cargar);
        });
        alCambiarFiltros(form, () => { pagina = 1; cargar(); });
        cargar();
    }

    // =================================================================
    //  TARJETAS PSN
    // =================================================================
    async function tarjetas() {
        cuerpo.innerHTML = cargando();
        try {
            const lista = (await api("tarjetas?todos=1")).datos;
            cuerpo.innerHTML = tabla(["Tarjeta", "Precio de venta", "Existencia", "Activa", ""], lista.map(t => `
                <tr><td><b>PSN ${pesos(t.monto)}</b></td>
                <td><input type="number" class="input-tabla" data-precio="${t.id}" min="1" step="0.01" value="${t.precio}" aria-label="Precio"></td>
                <td><input type="number" class="input-tabla" data-stock="${t.id}" min="0" max="9999" step="1" value="${t.stock}" aria-label="Existencia"></td>
                <td><input type="checkbox" data-activo="${t.id}" ${t.activo ? "checked" : ""} aria-label="Activa"></td>
                <td><button class="btn-mini" data-guardar="${t.id}">Guardar</button></td></tr>`));
            cuerpo.onclick = e => {
                const b = e.target.closest("[data-guardar]");
                if (!b) return;
                const id = b.dataset.guardar;
                accion(null, "tarjetas/" + id, "PUT", {
                    precio: cuerpo.querySelector(`[data-precio="${id}"]`).value,
                    stock: cuerpo.querySelector(`[data-stock="${id}"]`).value,
                    activo: cuerpo.querySelector(`[data-activo="${id}"]`).checked,
                }, tarjetas);
            };
        } catch (err) { mostrarError(err, tarjetas); }
    }

    // =================================================================
    //  REPORTES
    // =================================================================
    function reportes() {
        const inicio = new Date(); inicio.setMonth(inicio.getMonth() - 2); inicio.setDate(1);
        cuerpo.innerHTML = `
            <form class="barra-filtros" id="f-rep">
                <label>Fecha inicial<input type="date" name="desde" required value="${inicio.toISOString().slice(0, 10)}" max="${hoy()}"></label>
                <label>Fecha final<input type="date" name="hasta" required value="${hoy()}" max="${hoy()}"></label>
                <label>Agrupar por<select name="agrupar"><option value="dia">Día</option><option value="mes">Mes</option></select></label>
                <button type="submit" class="btn">Generar reportes</button>
                <button type="button" class="btn btn-secundario" id="imprimir">Imprimir</button>
            </form>
            <section class="reporte"><div class="reporte-cabecera"><h2>Reporte de ventas por periodo</h2><button type="button" class="btn-mini" id="csv-ventas">Descargar CSV</button></div><div id="r-ventas"></div></section>
            <section class="reporte"><div class="reporte-cabecera"><h2>Juegos más vendidos</h2><button type="button" class="btn-mini" id="csv-top">Descargar CSV</button></div><div id="r-top"></div></section>`;
        const form = document.getElementById("f-rep");
        let ventas = null, top = null;
        async function generar() {
            if (!form.reportValidity()) return;
            if (form.desde.value > form.hasta.value) { mostrarErrores(form, { desde: "Debe ser menor o igual a la fecha final." }); return; }
            mostrarErrores(form);
            limpiarGraficas();
            const cv = document.getElementById("r-ventas"), ct = document.getElementById("r-top");
            cv.innerHTML = ct.innerHTML = cargando("Generando reporte…");
            const q = parametros(form);
            try {
                ventas = (await api("reportes/ventas?" + q)).datos;
                const t = ventas.totales;
                cv.innerHTML = `<div class="kpis compactos">
                        <div class="kpi"><span>Ventas</span><b>${pesos(t.total)}</b></div><div class="kpi"><span>Pedidos</span><b>${t.pedidos}</b></div>
                        <div class="kpi"><span>Piezas</span><b>${t.piezas}</b></div><div class="kpi"><span>Ticket promedio</span><b>${pesos(t.ticket_promedio)}</b></div>
                        <div class="kpi"><span>IVA</span><b>${pesos(t.iva)}</b></div><div class="kpi"><span>Descuento con puntos</span><b>${pesos(t.descuento)}</b></div>
                        <div class="kpi"><span>Cancelados</span><b>${t.cancelados}</b></div></div>
                    <div class="caja-grafica"><canvas id="g-rep-ventas" height="90"></canvas></div>` +
                    tabla(["Periodo", "Pedidos", "Piezas", "Subtotal", "Desc. puntos", "IVA", "Total"], ventas.filas.map(f => `
                        <tr><td>${esc(f.periodo)}</td><td class="num">${f.pedidos}</td><td class="num">${f.piezas}</td><td class="num">${pesos(f.subtotal)}</td>
                        <td class="num">${pesos(f.descuento)}</td><td class="num">${pesos(f.iva)}</td><td class="num"><b>${pesos(f.total)}</b></td></tr>`), "No hubo ventas en ese periodo.");
                if (ventas.filas.length) grafica("g-rep-ventas", { type: "bar", data: { labels: ventas.filas.map(f => f.periodo),
                    datasets: [{ label: "Ventas", data: ventas.filas.map(f => Number(f.total)), backgroundColor: "rgba(69,243,255,.7)" }] },
                    options: { plugins: { legend: { display: false } }, ...opcionesPesos } });
            } catch (err) { cv.innerHTML = vacio(esc(err.message)); }
            try {
                top = (await api("reportes/juegos-mas-vendidos?limite=10&" + q)).datos;
                ct.innerHTML = `<div class="caja-grafica"><canvas id="g-rep-top" height="120"></canvas></div>` +
                    tabla(["#", "Juego", "Género", "Piezas", "Físicos", "Digitales", "Ingresos"], top.filas.map((f, i) => `
                        <tr><td>${i + 1}</td><td>${esc(f.titulo)}</td><td>${esc(f.genero)}</td><td class="num"><b>${f.piezas}</b></td><td class="num">${f.fisicos}</td><td class="num">${f.digitales}</td><td class="num">${pesos(f.ingresos)}</td></tr>`), "No hubo ventas de juegos en ese periodo.");
                if (top.filas.length) grafica("g-rep-top", { type: "bar", data: { labels: top.filas.map(f => f.titulo),
                    datasets: [{ label: "Piezas vendidas", data: top.filas.map(f => Number(f.piezas)), backgroundColor: COLORES }] },
                    options: { indexAxis: "y", plugins: { legend: { display: false } } } });
            } catch (err) { ct.innerHTML = vacio(esc(err.message)); }
        }
        form.addEventListener("submit", e => { e.preventDefault(); generar(); });
        document.getElementById("imprimir").onclick = () => window.print();
        document.getElementById("csv-ventas").onclick = () => ventas && descargarCSV(`ventas_${ventas.desde}_${ventas.hasta}.csv`,
            ["Periodo", "Pedidos", "Piezas", "Subtotal", "Descuento puntos", "IVA", "Total"], ventas.filas.map(f => [f.periodo, f.pedidos, f.piezas, f.subtotal, f.descuento, f.iva, f.total]));
        document.getElementById("csv-top").onclick = () => top && descargarCSV(`juegos_mas_vendidos_${top.desde}_${top.hasta}.csv`,
            ["Juego", "Género", "Piezas", "Físicos", "Digitales", "Ingresos"], top.filas.map(f => [f.titulo, f.genero, f.piezas, f.fisicos, f.digitales, f.ingresos]));
        generar();
    }

    // =================================================================
    //  HISTORIAL (auditoría)
    // =================================================================
    function historial() {
        cuerpo.innerHTML = `
            <form class="barra-filtros" id="f-his">
                <label>Usuario<input type="search" name="q" placeholder="Nombre o correo"></label>
                <label>Entidad<select name="entidad"><option value="">Todas</option>${["pedido", "juego", "edicion", "genero", "usuario", "tarjeta"].map(x => `<option>${x}</option>`).join("")}</select></label>
                <label>Acción<select name="accion"><option value="">Todas</option>${["crear", "editar", "baja", "reactivar", "cambiar_estado", "cancelar", "ajuste_stock", "editar_precio"].map(x => `<option>${x}</option>`).join("")}</select></label>
                <label>Desde<input type="date" name="desde"></label>
                <label>Hasta<input type="date" name="hasta"></label>
            </form>
            <div id="t-his"></div>`;
        const form = document.getElementById("f-his");
        const caja = document.getElementById("t-his");
        let pagina = 1;
        const bonito = j => {
            if (!j) return "–";
            try { return Object.entries(JSON.parse(j)).map(([k, v]) => `<b>${esc(k)}:</b> ${esc(typeof v === "object" ? JSON.stringify(v) : v)}`).join("<br>"); }
            catch { return esc(j); }
        };
        async function cargar() {
            caja.innerHTML = cargando();
            try {
                const r = await api("historial?" + parametros(form, { page: pagina, limit: 15 }));
                caja.innerHTML = tabla(["Fecha", "Usuario", "Acción", "Registro", "Antes", "Después"], r.datos.map(h => `
                    <tr><td>${esc(h.creado)}</td><td>${esc(h.usuario || "Sistema")}${h.rol ? `<br><small>${esc(h.rol)}</small>` : ""}</td>
                    <td><span class="chip-accion">${esc(h.accion)}</span></td><td>${esc(h.entidad)} #${h.entidad_id ?? ""}</td>
                    <td><small>${bonito(h.antes)}</small></td><td><small>${bonito(h.despues)}</small></td></tr>`), "No hay operaciones con esos filtros.");
                caja.appendChild(paginador(r, p => { pagina = p; cargar(); }));
            } catch (err) { caja.innerHTML = vacio(esc(err.message)); }
        }
        alCambiarFiltros(form, () => { pagina = 1; cargar(); });
        cargar();
    }

    const secciones = { dashboard, pedidos, juegos, generos, inventario, usuarios, tarjetas, reportes, historial };
    (secciones[seccion] || dashboard)();
})();
