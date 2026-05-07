<?php
// ver_encuestas.php - PANEL DE CONTROL DE OPINIONES (DISEÑO PREMIUM)
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. CONEXIÓN
$rutas_db = ['db.php', 'includes/db.php'];
foreach ($rutas_db as $ruta) { if (file_exists($ruta)) { require_once $ruta; break; } }

if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

// --- CANDADOS DE SEGURIDAD ---
$permisos = $_SESSION['permisos'] ?? [];
$es_admin = (($_SESSION['rol'] ?? 3) <= 2);

// Candado: Acceso a la página
if (!$es_admin && !in_array('ver_encuestas', $permisos)) { 
    header("Location: dashboard.php"); exit; 
}

$conf_rubro = $conexion->query("SELECT tipo_negocio FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$rubro_actual = $conf_rubro['tipo_negocio'] ?? 'kiosco';

// 2. CONSTRUIR FILTROS
$where = "WHERE (tipo_negocio = '$rubro_actual' OR tipo_negocio IS NULL)";
$params = [];

// A. Filtro por Fecha
$fecha = $_GET['fecha'] ?? '';
if (!empty($fecha)) {
    $where .= " AND DATE(fecha) = ?";
    $params[] = $fecha;
}

// B. Filtro por Estrellas
$estrellas = $_GET['estrellas'] ?? '';
if (!empty($estrellas)) {
    $where .= " AND nivel = ?";
    $params[] = $estrellas;
}

// C. Filtro por Tipo de Cliente
$tipo = $_GET['tipo'] ?? '';
if ($tipo === 'anonimo') {
    $where .= " AND cliente_nombre = 'Anónimo'";
} elseif ($tipo === 'cliente') {
    $where .= " AND cliente_nombre != 'Anónimo'";
}

// D. Buscador de Texto (Buscador Rápido)
$buscar = trim($_GET['buscar'] ?? '');
if (!empty($buscar)) {
    $where .= " AND (cliente_nombre LIKE ? OR comentario LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

// 3. CONSULTAS SQL DINÁMICAS Y WIDGETS
try {
    // 1. Lista de resultados
    $sql = "SELECT * FROM encuestas $where ORDER BY fecha DESC LIMIT 100";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Widget Promedio
    $sqlAvg = "SELECT AVG(nivel) FROM encuestas $where";
    $stmtAvg = $conexion->prepare($sqlAvg);
    $stmtAvg->execute($params);
    $promedio = $stmtAvg->fetchColumn() ?: 0;

    // 3. Widget Total
    $sqlCount = "SELECT COUNT(*) FROM encuestas $where";
    $stmtCount = $conexion->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // 4. Widget Clientes Felices (4 o 5 estrellas) - Reutilizamos params
    // Truco: Agregamos la condición de felicidad al WHERE existente para contar solo esos
    $sqlHappy = "SELECT COUNT(*) FROM encuestas $where AND nivel >= 4";
    $stmtHappy = $conexion->prepare($sqlHappy);
    $stmtHappy->execute($params);
    $felices = $stmtHappy->fetchColumn();

} catch (Exception $e) {
    $lista = []; $total = 0; $promedio = 0; $felices = 0;
}
$conf_color_sis = $conexion->query("SELECT color_barra_nav FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$color_sistema = $conf_color_sis['color_barra_nav'] ?? '#102A57';
require_once 'includes/layout_header.php';

// --- BANNER DINÁMICO ESTANDARIZADO ---
$titulo = "Gestión de Opiniones";
$subtitulo = "Lo que dicen tus clientes sobre el servicio.";
$icono_bg = "bi-chat-quote-fill";

$botones = [
    ['texto' => 'VER FORMULARIO', 'link' => 'encuesta.php', 'icono' => 'bi-eye-fill', 'class' => 'btn btn-light text-primary fw-bold rounded-pill px-4 shadow-sm', 'target' => '_blank'],
    ['texto' => 'REPORTE PDF', 'link' => 'reporte_encuestas.php?'.$_SERVER['QUERY_STRING'], 'icono' => 'bi-file-earmark-pdf-fill', 'class' => 'btn btn-danger fw-bold rounded-pill px-4 shadow-sm ms-2', 'target' => '_blank']
];

$widgets = [
    ['label' => 'Opiniones (Filtro)', 'valor' => $total, 'icono' => 'bi-chat-left-text', 'icon_bg' => 'bg-white bg-opacity-10'],
    ['label' => 'Calificación Promedio', 'valor' => number_format((float)$promedio, 1) . ' / 5', 'icono' => 'bi-star-half', 'icon_bg' => 'bg-warning bg-opacity-20'],
    ['label' => 'Clientes Felices', 'valor' => $felices, 'icono' => 'bi-emoji-smile', 'border' => 'border-info', 'icon_bg' => 'bg-success bg-opacity-20']
];

include 'includes/componente_banner.php'; 
?>

<div class="container-fluid container-md mt-n4 px-3 mb-5" style="position: relative; z-index: 20;">
    
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
        <form id="formFiltrosEncuestas" method="GET" class="d-flex w-100 align-items-center gap-2 m-0">
            <div class="search-input-group">
                <i class="bi bi-search"></i>
                <input type="text" name="buscar" placeholder="Buscar por nombre o comentario..." value="<?php echo htmlspecialchars($buscar); ?>" autocomplete="off">
            </div>

            <input type="hidden" name="fecha" id="hiddenFecha" value="<?php echo htmlspecialchars($fecha); ?>">
            <input type="hidden" name="estrellas" id="hiddenEstrellas" value="<?php echo htmlspecialchars($estrellas); ?>">
            <input type="hidden" name="tipo" id="hiddenTipo" value="<?php echo htmlspecialchars($tipo); ?>">

            <button type="button" class="btn btn-light border btn-filter-trigger shadow-sm" onclick="abrirFiltrosEncuestas()">
                <i class="bi bi-sliders2 text-primary"></i> <span>FILTROS</span>
            </button>
            <button type="submit" class="btn btn-primary btn-filter-trigger shadow-sm">
                <i class="bi bi-arrow-right-short" style="font-size: 1.3rem;"></i>
            </button>
        </form>
    </div>

    <script>
    function abrirFiltrosEncuestas() {
        let est = document.getElementById('hiddenEstrellas').value;
        let tipo = document.getElementById('hiddenTipo').value;

        Swal.fire({
            title: 'FILTRAR OPINIONES',
            html: `
                <div style="text-align: left; display: flex; flex-direction: column; gap: 15px; margin-top: 10px;">
                    <div><label class="small fw-bold text-muted text-uppercase">Fecha Exacta</label><input type="date" id="swal_fecha" class="form-control fw-bold" value="${document.getElementById('hiddenFecha').value}"></div>
                    <div>
                        <label class="small fw-bold text-muted text-uppercase">Calificación</label>
                        <select id="swal_est" class="form-select fw-bold">
                            <option value="">Todas las estrellas</option>
                            <option value="5" ${est === '5' ? 'selected' : ''}>5 - Excelente (😍)</option>
                            <option value="4" ${est === '4' ? 'selected' : ''}>4 - Buena (🙂)</option>
                            <option value="3" ${est === '3' ? 'selected' : ''}>3 - Normal (😐)</option>
                            <option value="2" ${est === '2' ? 'selected' : ''}>2 - Regular (☹️)</option>
                            <option value="1" ${est === '1' ? 'selected' : ''}>1 - Mala (😡)</option>
                        </select>
                    </div>
                    <div>
                        <label class="small fw-bold text-muted text-uppercase">Origen</label>
                        <select id="swal_tipo" class="form-select fw-bold">
                            <option value="">Todos los orígenes</option>
                            <option value="anonimo" ${tipo === 'anonimo' ? 'selected' : ''}>Clientes Anónimos</option>
                            <option value="cliente" ${tipo === 'cliente' ? 'selected' : ''}>Clientes Identificados</option>
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true, confirmButtonText: 'APLICAR', cancelButtonText: 'LIMPIAR', confirmButtonColor: '#3b82f6', cancelButtonColor: '#ef4444',
            customClass: { confirmButton: 'rounded-pill px-4 fw-bold', cancelButton: 'rounded-pill px-4 fw-bold' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('hiddenFecha').value = document.getElementById('swal_fecha').value;
                document.getElementById('hiddenEstrellas').value = document.getElementById('swal_est').value;
                document.getElementById('hiddenTipo').value = document.getElementById('swal_tipo').value;
                document.getElementById('formFiltrosEncuestas').submit();
            } else if (result.dismiss === Swal.DismissReason.cancel) { window.location.href = 'ver_encuestas.php'; }
        });
    }
    </script>

    <h5 class="fw-bold mb-3 text-secondary">Últimas Opiniones</h5>
    
    <?php if (count($lista) > 0): ?>
        <?php foreach ($lista as $row): 
            // Determinar color del borde según nota
            $bordeClass = 'review-mid';
            if($row['nivel'] >= 4) $bordeClass = 'review-good';
            if($row['nivel'] <= 2) $bordeClass = 'review-bad';
            
            // Inicial del nombre
            $inicial = substr($row['cliente_nombre'], 0, 1);
        ?>
            <div class="card review-card <?php echo $bordeClass; ?> p-3">
                <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                    
                    <div class="d-flex align-items-center gap-3" style="min-width: 250px;">
                        <div class="avatar-circle shadow-sm">
                            <?php echo strtoupper($inicial); ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <?php echo htmlspecialchars($row['cliente_nombre']); ?>
                            </h6>
                            <small class="text-muted">
                                <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y H:i', strtotime($row['fecha'])); ?>
                            </small>
                        </div>
                    </div>

                    <div class="flex-grow-1">
                        <div class="mb-1">
                            <?php 
                            for($i=0; $i<$row['nivel']; $i++) echo '<i class="bi bi-star-fill text-warning fs-5"></i> ';
                            for($i=$row['nivel']; $i<5; $i++) echo '<i class="bi bi-star-fill text-muted opacity-25 fs-5"></i> ';
                            ?>
                            <span class="fw-bold ms-2 text-muted small">
                                <?php 
                                    if($row['nivel']==5) echo "Excelente";
                                    elseif($row['nivel']==4) echo "Muy Buena";
                                    elseif($row['nivel']==3) echo "Normal";
                                    elseif($row['nivel']==2) echo "Regular";
                                    elseif($row['nivel']==1) echo "Mala";
                                ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($row['comentario'])): ?>
                            <div class="bg-light p-2 rounded border border-light text-dark fst-italic">
                                "<?php echo htmlspecialchars($row['comentario']); ?>"
                            </div>
                        <?php else: ?>
                            <small class="text-muted opacity-50">Sin comentario escrito.</small>
                        <?php endif; ?>
                    </div>

                    <div class="text-end" style="min-width: 150px;">
                        <?php if (!empty($row['contacto'])): 
                            $wa = preg_replace('/[^0-9]/', '', $row['contacto']);
                        ?>
                            <a href="https://wa.me/<?php echo $wa; ?>" target="_blank" class="btn btn-success btn-sm fw-bold rounded-pill shadow-sm w-100">
                                <i class="bi bi-whatsapp"></i> Contactar
                            </a>
                            <div class="small text-muted mt-1 text-center" style="font-size: 0.75rem;">
                                <?php echo htmlspecialchars($row['contacto']); ?>
                            </div>
                        <?php else: ?>
                            <span class="badge bg-secondary opacity-25 rounded-pill w-100">Sin contacto</span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="opacity-25 mb-3">
                <i class="bi bi-inbox-fill display-1 text-secondary"></i>
            </div>
            <h4 class="fw-bold text-muted">No se encontraron opiniones</h4>
            <p class="text-muted">Intenta cambiar los filtros de búsqueda.</p>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include 'includes/layout_footer.php'; ?>