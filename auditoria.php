<?php
// auditoria.php - VERSIÓN VANGUARD PRO
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'includes/db.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

$permisos = $_SESSION['permisos'] ?? [];
$es_admin = (($_SESSION['rol'] ?? 3) <= 2);
if (!$es_admin && !in_array('ver_auditoria', $permisos)) { header("Location: dashboard.php"); exit; }

$conf = $conexion->query("SELECT * FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$color_sistema = $conf['color_barra_nav'] ?? '#102A57';

$conf_rubro = $conexion->query("SELECT tipo_negocio FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$rubro_actual = $conf_rubro['tipo_negocio'] ?? 'kiosco';

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 week'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$f_user   = $_GET['f_user'] ?? '';
$f_accion = $_GET['f_accion'] ?? '';
$buscar   = trim($_GET['buscar'] ?? '');

$sql_filtros = " WHERE DATE(a.fecha) >= ? AND DATE(a.fecha) <= ? AND (a.tipo_negocio = '$rubro_actual' OR a.tipo_negocio IS NULL)";
$params = [$desde, $hasta];

if(!empty($f_user)) { $sql_filtros .= " AND a.id_usuario = ?"; $params[] = $f_user; }
if(!empty($f_accion)) { $sql_filtros .= " AND a.accion LIKE ?"; $params[] = "%$f_accion%"; }
if(!empty($buscar)) { $sql_filtros .= " AND (a.detalles LIKE ? OR a.id = ?)"; array_push($params, "%$buscar%", intval($buscar)); }

$st_count = $conexion->prepare("SELECT COUNT(*) FROM auditoria a JOIN usuarios u ON a.id_usuario = u.id $sql_filtros");
$st_count->execute($params);
$total_regs = $st_count->fetchColumn();

$pag = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
$reg_x_pag = 10;
$total_paginas = ceil($total_regs / $reg_x_pag);
$inicio = ($pag - 1) * $reg_x_pag;

$sql_aud = "SELECT a.*, u.usuario, u.nombre_completo FROM auditoria a JOIN usuarios u ON a.id_usuario = u.id $sql_filtros ORDER BY a.fecha DESC LIMIT $inicio, $reg_x_pag";
$st_aud = $conexion->prepare($sql_aud);
$st_aud->execute($params);
$logs = $st_aud->fetchAll(PDO::FETCH_ASSOC);

// Lógica de Data Enriquecida para Tickets
foreach ($logs as &$l) {
    $l['rich_data'] = null;
    if ((strpos(strtoupper($l['accion']), 'VENTA') !== false) && preg_match('/Venta #(\d+)/', $l['detalles'], $m)) {
        $idV = $m[1];
        $stV = $conexion->prepare("SELECT v.*, c.nombre as nombre_cliente FROM ventas v LEFT JOIN clientes c ON v.id_cliente = c.id WHERE v.id = ?");
        $stV->execute([$idV]);
        if ($vI = $stV->fetch(PDO::FETCH_ASSOC)) {
            $stD = $conexion->prepare("SELECT d.*, p.descripcion FROM detalle_ventas d LEFT JOIN productos p ON d.id_producto = p.id WHERE d.id_venta = ?");
            $stD->execute([$idV]);
            $l['rich_data'] = ['tipo' => 'venta', 'cabecera' => $vI, 'items' => $stD->fetchAll(PDO::FETCH_ASSOC), 'id_real' => $idV];
        }
    }
}
unset($l);

function getIconoReal($accion) {
    $a = strtoupper($accion);
    if(strpos($a, 'VENTA') !== false) return '<i class="bi bi-cart-check-fill text-success"></i>';
    if(strpos($a, 'GASTO') !== false) return '<i class="bi bi-cash-stack text-danger"></i>';
    if(strpos($a, 'ELIMIN') !== false) return '<i class="bi bi-trash3-fill text-danger"></i>';
    if(strpos($a, 'LOGIN') !== false) return '<i class="bi bi-shield-check text-primary"></i>';
    return '<i class="bi bi-info-circle text-muted"></i>';
}

include 'includes/layout_header.php';
$query_filtros = !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : "desde=$desde&hasta=$hasta";

$titulo = "Auditoría de Sistema";
$subtitulo = "Registro de trazabilidad y movimientos de usuarios.";
$icono_bg = "bi-shield-lock";
$botones = [['texto' => 'Reporte PDF', 'link' => "reporte_auditoria.php?$query_filtros", 'icono' => 'bi-file-earmark-pdf-fill', 'class' => 'btn btn-danger fw-bold rounded-pill px-4 shadow-sm', 'target' => '_blank']];
$widgets = [
    ['label' => 'Movimientos Hoy', 'valor' => $conexion->query("SELECT COUNT(*) FROM auditoria WHERE DATE(fecha)=CURDATE() AND (tipo_negocio = '$rubro_actual' OR tipo_negocio IS NULL)")->fetchColumn(), 'icono' => 'bi-activity', 'icon_bg' => 'bg-white bg-opacity-10'],
    ['label' => 'Críticos Hoy', 'valor' => $conexion->query("SELECT COUNT(*) FROM auditoria WHERE DATE(fecha)=CURDATE() AND (accion LIKE '%ELIMIN%' OR accion LIKE '%BAJA%') AND (tipo_negocio = '$rubro_actual' OR tipo_negocio IS NULL)")->fetchColumn(), 'icono' => 'bi-exclamation-triangle', 'border' => 'border-danger', 'icon_bg' => 'bg-danger bg-opacity-20'],
    ['label' => 'Filtrados', 'valor' => $total_regs, 'icono' => 'bi-funnel', 'border' => 'border-info', 'icon_bg' => 'bg-info bg-opacity-20']
];
include 'includes/componente_banner.php';
?>

<div class="container mt-n4 pb-5" style="position: relative; z-index: 20;">
    <style>
        .minimal-toolbar { display: flex; align-items: center; gap: 10px; background: #fff; padding: 8px 15px; border-radius: 50px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px; width: 100%; position: sticky; top: 0; z-index: 1000; }
        .search-input-group { display: flex; align-items: center; flex-grow: 1; background: #f8fafc; border-radius: 50px; padding: 2px 15px; border: 1px solid transparent; transition: 0.2s; }
        .search-input-group:focus-within { background: #fff; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .search-input-group i { color: #94a3b8; font-size: 1.1rem; }
        .search-input-group input { border: none; background: transparent; padding: 8px 10px; width: 100%; outline: none; font-weight: 500; color: #1e293b; font-size: 0.95rem; }
        .btn-filter-trigger { white-space: nowrap; border-radius: 50px; padding: 8px 18px; font-weight: 700; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; transition: 0.2s; cursor: pointer; }
        @media (max-width: 768px) { .minimal-toolbar { padding: 5px 10px; gap: 5px; } .btn-filter-trigger span { display: none; } .btn-filter-trigger { padding: 10px; } }
    </style>

    <div class="minimal-toolbar">
        <form id="formAudit" method="GET" class="d-flex w-100 align-items-center gap-2 m-0">
            <div class="search-input-group">
                <i class="bi bi-search"></i>
                <input type="text" name="buscar" placeholder="Buscar en los logs..." value="<?php echo htmlspecialchars($buscar); ?>" autocomplete="off">
            </div>

            <input type="hidden" name="desde" id="hiddenDesde" value="<?php echo htmlspecialchars($desde); ?>">
            <input type="hidden" name="hasta" id="hiddenHasta" value="<?php echo htmlspecialchars($hasta); ?>">
            <input type="hidden" name="f_user" id="hiddenUsuario" value="<?php echo htmlspecialchars($f_user); ?>">
            <input type="hidden" name="f_accion" id="inputAccion" value="<?php echo htmlspecialchars($f_accion); ?>">

            <button type="button" class="btn btn-light border btn-filter-trigger shadow-sm" onclick="abrirFiltrosAudit()">
                <i class="bi bi-sliders2 text-primary"></i> <span>FILTROS</span>
            </button>
            <button type="button" class="btn btn-dark border btn-filter-trigger shadow-sm" data-bs-toggle="modal" data-bs-target="#modalFiltroRapido" title="Filtros Rápidos">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
            </button>
            <button type="submit" class="btn btn-primary btn-filter-trigger shadow-sm" style="padding: 8px 15px;">
                <i class="bi bi-arrow-right-short" style="font-size: 1.3rem;"></i>
            </button>
        </form>
    </div>

    <script>
    function abrirFiltrosAudit() {
        let usOptions = `<option value="">Todos los usuarios</option>`;
        <?php 
        $usulist = $conexion->query("SELECT id, usuario FROM usuarios ORDER BY usuario ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach($usulist as $u): ?> 
            usOptions += `<option value="<?php echo $u['id']; ?>" ${document.getElementById('hiddenUsuario').value == '<?php echo $u['id']; ?>' ? 'selected' : ''}><?php echo addslashes(strtoupper($u['usuario'])); ?></option>`; 
        <?php endforeach; ?>

        Swal.fire({
            title: 'FILTRAR AUDITORÍA',
            html: `
                <div style="text-align: left; display: flex; flex-direction: column; gap: 15px; margin-top: 10px;">
                    <div><label class="small fw-bold text-muted text-uppercase">Desde</label><input type="date" id="swal_desde" class="form-control fw-bold" value="${document.getElementById('hiddenDesde').value}"></div>
                    <div><label class="small fw-bold text-muted text-uppercase">Hasta</label><input type="date" id="swal_hasta" class="form-control fw-bold" value="${document.getElementById('hiddenHasta').value}"></div>
                    <div><label class="small fw-bold text-muted text-uppercase">Operador</label><select id="swal_us" class="form-select fw-bold">${usOptions}</select></div>
                </div>
            `,
            showCancelButton: true, confirmButtonText: 'APLICAR', cancelButtonText: 'LIMPIAR', confirmButtonColor: '#3b82f6', cancelButtonColor: '#ef4444',
            customClass: { confirmButton: 'rounded-pill px-4 fw-bold', cancelButton: 'rounded-pill px-4 fw-bold' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('hiddenDesde').value = document.getElementById('swal_desde').value;
                document.getElementById('hiddenHasta').value = document.getElementById('swal_hasta').value;
                document.getElementById('hiddenUsuario').value = document.getElementById('swal_us').value;
                document.getElementById('formAudit').submit();
            } else if (result.dismiss === Swal.DismissReason.cancel) { window.location.href = 'auditoria.php'; }
        });
    }
    </script>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 0.85rem;">
            <thead class="bg-light text-muted small text-uppercase"><tr><th class="ps-4">Fecha/Hora</th><th>Usuario</th><th>Acción</th><th class="text-start">Resumen</th><th class="pe-4 text-end">Ficha</th></tr></thead>
            <tbody>
                <?php foreach($logs as $log): ?>
                <tr style="cursor:pointer" onclick="verTicketAuditoria(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8'); ?>)">
                    <td class="ps-4 fw-bold"><?php echo date('d/m H:i', strtotime($log['fecha'])); ?> hs</td>
                    <td><span class="badge bg-light text-dark border">@<?php echo $log['usuario']; ?></span></td>
                    <td class="fw-bold"><?php echo getIconoReal($log['accion']); ?> <?php echo strtoupper($log['accion']); ?></td>
                    <td class="text-muted small text-start"><?php echo htmlspecialchars(substr($log['detalles'], 0, 85)); ?>...</td>
                    <td class="pe-4 text-end"><button type="button" class="btn btn-sm btn-outline-dark border-0 rounded-pill"><i class="bi bi-receipt fs-5"></i></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_paginas > 1): ?>
    <div class="card-footer bg-white border-top py-3"><nav><ul class="pagination justify-content-center mb-0 pagination-sm">
        <?php $q_str = "&desde=$desde&hasta=$hasta&f_user=$f_user&f_accion=$f_accion&buscar=$buscar";
        if ($pag > 1) echo '<li class="page-item"><a class="page-link" href="?pag='.($pag-1).$q_str.'">&laquo;</a></li>';
        for ($i = max(1, $pag-2); $i <= min($total_paginas, $pag+2); $i++) { echo '<li class="page-item '.($i==$pag?'active':'').'"><a class="page-link" href="?pag='.$i.$q_str.'">'.$i.'</a></li>'; }
        if ($pag < $total_paginas) echo '<li class="page-item"><a class="page-link" href="?pag='.($pag+1).$q_str.'">&raquo;</a></li>'; ?>
    </ul></nav></div>
    <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalFiltroRapido" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow rounded-4">
    <div class="modal-header bg-dark text-white"><h6 class="modal-title fw-bold">Filtros Rápidos</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-4 row g-2">
        <button class="btn btn-outline-success btn-sm fw-bold col-5 m-1" onclick="pegarYBuscar('VENTA')">VENTAS</button>
        <button class="btn btn-outline-danger btn-sm fw-bold col-5 m-1" onclick="pegarYBuscar('ELIMIN')">BAJAS</button>
        <button class="btn btn-outline-warning btn-sm fw-bold col-5 m-1" onclick="pegarYBuscar('GASTO')">GASTOS</button>
        <button class="btn btn-outline-primary btn-sm fw-bold col-5 m-1" onclick="pegarYBuscar('LOGIN')">INGRESOS</button>
    </div>
</div></div></div>

<script>
const miLocal = <?php echo json_encode($conf); ?>;
function pegarYBuscar(val) { document.getElementById('inputAccion').value = val; document.getElementById('formAudit').submit(); }

function verTicketAuditoria(log) {
    let ts = Date.now();
    let logoH = miLocal.logo_url ? `<img src="${miLocal.logo_url}?v=${ts}" style="max-height:50px; mb-2">` : '';
    let linkPdf = window.location.origin + window.location.pathname.replace('auditoria.php','') + "ticket_auditoria_pdf.php?id=" + log.id;
    let qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=` + encodeURIComponent(linkPdf);

    let html = `
        <div style="font-family:'Inter',sans-serif; text-align:left; color:#000; padding:10px;">
            <div style="text-align:center; border-bottom:2px dashed #ccc; pb-3 mb-3">${logoH}<h4 style="font-weight:900; m-0;">${miLocal.nombre_negocio}</h4></div>
            <div style="background:#f8f9fa; border:1px solid #eee; p-3 rounded-3 mb-3; font-size:12px;">
                <div><strong>FECHA:</strong> ${new Date(log.fecha).toLocaleString()}</div>
                <div><strong>ACCIÓN:</strong> ${log.accion.toUpperCase()}</div>
                <div><strong>OPERADOR:</strong> ${log.usuario.toUpperCase()}</div>
            </div>
            <div style="font-size:12px; mb-3"><strong>DETALLE:</strong><br>${log.detalles}</div>
            <div style="display:flex; justify-content:space-between; align-items:flex-end; mt-3 pt-3 border-top:2px dashed #eee;">
                <div style="width:45%; text-align:center;"><img src="img/firmas/usuario_${log.id_usuario}.png?v=${ts}" onerror="this.src='img/firmas/firma_admin.png?v=${ts}'" style="max-height:50px;"><br><small>Firma</small></div>
                <div style="width:45%; text-align:center;"><img src="${qrUrl}" style="width:70px;"><br><small style="font-size:8px;">VALIDAR</small></div>
            </div>
        </div>
        <div class="row g-2 mt-4 pt-3 border-top no-print">
            <div class="col-4"><a href="${linkPdf}" target="_blank" class="btn btn-light border text-primary fw-bold w-100 rounded-pill small">PDF</a></div>
            <div class="col-4"><button class="btn btn-primary fw-bold w-100 rounded-pill small" onclick="mandarMail(${log.id})">MAIL</button></div>
            <div class="col-4"><button class="btn btn-success fw-bold w-100 rounded-pill small" onclick="window.open('https://wa.me/?text=Audit ${log.id}: ${linkPdf}')">WA</button></div>
        </div>`;
    Swal.fire({ html: html, width: 400, showConfirmButton: false, showCloseButton: true });
}

function mandarMail(id) {
    Swal.fire({ title: 'Enviar Ticket', input: 'email', showCancelButton: true }).then(r => {
        if(r.isConfirmed && r.value) {
            let f = new FormData(); f.append('id', id); f.append('email', r.value);
            fetch('acciones/enviar_email_auditoria.php', { method: 'POST', body: f }).then(res => res.json()).then(d => {
                Swal.fire(d.status === 'success' ? 'Enviado' : 'Error', d.msg, d.status);
            });
        }
    });
}
</script>
<?php include 'includes/layout_footer.php'; ?>