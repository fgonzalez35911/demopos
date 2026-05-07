<?php
// combos.php - ARCHIVO COMPLETO FINAL (SWEETALERT2 PURO + RESPONSIVE MOBILE FIX)
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

// --- CANDADOS DE SEGURIDAD ---
$permisos = $_SESSION['permisos'] ?? [];
$es_admin = (($_SESSION['rol'] ?? 3) <= 2);

// Candado de Página
if (!$es_admin && !in_array('stock_gestionar_combos', $permisos)) { header("Location: dashboard.php"); exit; }

// --- 1. PROCESAR IMAGEN (CROPPER) ---
function procesarImagenBase64($base64, $url_texto, $actual) {
    if (!empty($base64)) {
        $data = explode(',', $base64);
        $decoded = base64_decode($data[1]);
        $nombre = 'combo_' . time() . '_' . rand(100,999) . '.png';
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        file_put_contents('uploads/' . $nombre, $decoded);
        return 'uploads/' . $nombre;
    }
    return (!empty($url_texto)) ? $url_texto : $actual;
}

// --- 2. LÓGICA DE GUARDADO (CREAR/EDITAR/BORRAR) ---
$conf_rubro = $conexion->query("SELECT tipo_negocio FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$rubro_actual = $conf_rubro['tipo_negocio'] ?? 'kiosco';

if (isset($_POST['crear_combo'])) {
    if (!$es_admin && !in_array('crear_combo', $permisos)) die("Sin permiso para crear.");
    try {
        $conexion->beginTransaction();
        $img = procesarImagenBase64($_POST['imagen_base64'], '', 'default.jpg');
        
        $stmt = $conexion->prepare("INSERT INTO combos (nombre, precio, codigo_barras, activo, fecha_inicio, fecha_fin, es_ilimitado, tipo_negocio) VALUES (?, ?, ?, 1, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nombre'], $_POST['precio'], !empty($_POST['codigo']) ? $_POST['codigo'] : 'COMBO-'.time(), 
            !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d'), 
            !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : date('Y-m-d'), isset($_POST['es_ilimitado']) ? 1 : 0, $rubro_actual
        ]);
        $id_nuevo = $conexion->lastInsertId();

        $sqlP = "INSERT INTO productos (descripcion, precio_venta, precio_oferta, codigo_barras, tipo, id_categoria, stock_actual, activo, es_destacado_web, imagen_url, tipo_negocio) VALUES (?, ?, ?, ?, 'combo', ?, 1, 1, ?, ?, ?)";
        $conexion->prepare($sqlP)->execute([
            $_POST['nombre'], $_POST['precio'], !empty($_POST['precio_oferta']) ? $_POST['precio_oferta'] : NULL,
            !empty($_POST['codigo']) ? $_POST['codigo'] : 'COMBO-'.time(), $_POST['id_categoria'],
            isset($_POST['es_destacado']) ? 1 : 0, $img, $rubro_actual
        ]);
        
        if (isset($_POST['prod_ids'])) {
            $stmtAdd = $conexion->prepare("INSERT INTO combo_items (id_combo, id_producto, cantidad) VALUES (?, ?, ?)");
            foreach ($_POST['prod_ids'] as $idx => $p_id) {
                if(!empty($p_id)) $stmtAdd->execute([$id_nuevo, $p_id, $_POST['prod_cants'][$idx]]);
            }
        }
        $conexion->commit();
        $detalles_audit = "Combo Creado: " . $_POST['nombre'] . " | Precio: $" . $_POST['precio'];
        $conexion->prepare("INSERT INTO auditoria (id_usuario, accion, detalles, fecha) VALUES (?, 'COMBO_NUEVO', ?, NOW())")->execute([$_SESSION['usuario_id'], $detalles_audit]);
        header("Location: combos.php?msg=creado"); exit;
    } catch (Exception $e) { $conexion->rollBack(); die($e->getMessage()); }
}

if (isset($_POST['editar_combo'])) {
    if (!$es_admin && !in_array('editar_combo', $permisos)) die("Sin permiso para editar.");
    try {
        $conexion->beginTransaction();
        $id = $_POST['id_combo'];
        $img = procesarImagenBase64($_POST['imagen_base64'], '', $_POST['imagen_actual']);
        
        $stmtOld = $conexion->prepare("SELECT c.*, p.precio_oferta, p.id_categoria, p.es_destacado_web FROM combos c LEFT JOIN productos p ON c.codigo_barras = p.codigo_barras WHERE c.id = ?");
        $stmtOld->execute([$id]);
        $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
        $cod_viejo = $old['codigo_barras'];

        $stmtOldItems = $conexion->prepare("SELECT ci.id_producto, ci.cantidad, p.descripcion FROM combo_items ci JOIN productos p ON ci.id_producto = p.id WHERE ci.id_combo = ?");
        $stmtOldItems->execute([$id]);
        $oldItemsArr = $stmtOldItems->fetchAll(PDO::FETCH_ASSOC);
        $oldItemsStr = implode(", ", array_map(function($i) { return $i['cantidad']."x ".$i['descripcion']; }, $oldItemsArr));
        if(!$oldItemsStr) $oldItemsStr = "Vacío";

        $n_nombre = $_POST['nombre']; $n_precio = $_POST['precio']; $n_oferta = !empty($_POST['precio_oferta']) ? $_POST['precio_oferta'] : 0;
        $n_cat = $_POST['id_categoria']; $n_cod = !empty($_POST['codigo']) ? $_POST['codigo'] : $cod_viejo;
        $n_ilim = isset($_POST['es_ilimitado']) ? 1 : 0; $n_fini = $_POST['fecha_inicio']; $n_ffin = $_POST['fecha_fin'];
        $n_dest = isset($_POST['es_destacado']) ? 1 : 0;

        $cambios = [];
        if($old['nombre'] != $n_nombre) $cambios[] = "Nombre: " . $old['nombre'] . " -> " . $n_nombre;
        if(floatval($old['precio']) != floatval($n_precio)) $cambios[] = "Precio: $" . floatval($old['precio']) . " -> $" . floatval($n_precio);
        if(floatval($old['precio_oferta']) != floatval($n_oferta)) $cambios[] = "Oferta: $" . floatval($old['precio_oferta']) . " -> $" . floatval($n_oferta);
        if($old['codigo_barras'] != $n_cod) $cambios[] = "Código: " . $old['codigo_barras'] . " -> " . $n_cod;
        if($old['es_ilimitado'] != $n_ilim) $cambios[] = "Ilimitado: " . ($old['es_ilimitado']?"Sí":"No") . " -> " . ($n_ilim?"Sí":"No");

        $conexion->prepare("UPDATE combos SET nombre=?, precio=?, codigo_barras=?, fecha_inicio=?, fecha_fin=?, es_ilimitado=? WHERE id=?")
            ->execute([$n_nombre, $n_precio, $n_cod, $n_fini, $n_ffin, $n_ilim, $id]);

        $conexion->prepare("UPDATE productos SET descripcion=?, precio_venta=?, precio_oferta=?, id_categoria=?, es_destacado_web=?, imagen_url=?, codigo_barras=? WHERE codigo_barras=?")
            ->execute([$n_nombre, $n_precio, $n_oferta>0?$n_oferta:NULL, $n_cat, $n_dest, $img, $n_cod, $cod_viejo]);

        $conexion->prepare("DELETE FROM combo_items WHERE id_combo = ?")->execute([$id]);
        $newItemsArr = [];
        if (isset($_POST['prod_ids'])) {
            $stmtAdd = $conexion->prepare("INSERT INTO combo_items (id_combo, id_producto, cantidad) VALUES (?, ?, ?)");
            foreach ($_POST['prod_ids'] as $idx => $p_id) {
                if(!empty($p_id)) {
                    $stmtAdd->execute([$id, $p_id, $_POST['prod_cants'][$idx]]);
                    $n_desc = $conexion->query("SELECT descripcion FROM productos WHERE id=".intval($p_id))->fetchColumn();
                    $newItemsArr[] = $_POST['prod_cants'][$idx] . "x " . $n_desc;
                }
            }
        }
        $newItemsStr = implode(", ", $newItemsArr);
        if(!$newItemsStr) $newItemsStr = "Vacío";
        if($oldItemsStr != $newItemsStr) $cambios[] = "Contenido: [" . $oldItemsStr . "] -> [" . $newItemsStr . "]";

        $conexion->commit();
        if(!empty($cambios)) {
            $detalles_audit = "Combo Editado: " . $old['nombre'] . " | " . implode(" | ", $cambios);
            $conexion->prepare("INSERT INTO auditoria (id_usuario, accion, detalles, fecha) VALUES (?, 'COMBO_EDITADO', ?, NOW())")->execute([$_SESSION['usuario_id'], $detalles_audit]);
        }
        if (isset($_POST['origen']) && $_POST['origen'] === 'productos') {
            header("Location: productos.php?msg=editado"); exit;
        }
        header("Location: combos.php?msg=editado"); exit;
    } catch (Exception $e) { $conexion->rollBack(); die($e->getMessage()); }
}

// --- BORRADO MASIVO AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitud_borrar_masivo'])) {
    if (!$es_admin && !in_array('eliminar_combo', $permisos)) { echo "Sin permiso"; exit; }
    $ids = json_decode($_POST['ids_a_borrar'], true);
    if (!empty($ids) && is_array($ids)) {
        try {
            $conexion->beginTransaction();
            foreach($ids as $id) {
                $id = intval($id);
                $stmtC = $conexion->prepare("SELECT codigo_barras FROM combos WHERE id = ?");
                $stmtC->execute([$id]);
                $cod = $stmtC->fetchColumn();

                $conexion->prepare("DELETE FROM combo_items WHERE id_combo = ?")->execute([$id]);
                $conexion->prepare("DELETE FROM combos WHERE id = ?")->execute([$id]);
                if($cod) $conexion->prepare("DELETE FROM productos WHERE codigo_barras = ?")->execute([$cod]);
            }
            $conexion->commit();
            echo "EXITO";
        } catch(Exception $e) { $conexion->rollBack(); echo "ERROR: ".$e->getMessage(); }
    }
    exit;
}

if (isset($_GET['eliminar_id'])) {
    if (!$es_admin && !in_array('eliminar_combo', $permisos)) die("Sin permiso para eliminar.");
    try {
        $conexion->beginTransaction();
        $id = $_GET['eliminar_id'];
        $stmtC = $conexion->prepare("SELECT nombre, precio, codigo_barras FROM combos WHERE id = ?");
        $stmtC->execute([$id]);
        $old = $stmtC->fetch(PDO::FETCH_ASSOC);
        $cod = $old ? $old['codigo_barras'] : null;

        $conexion->prepare("DELETE FROM combo_items WHERE id_combo = ?")->execute([$id]);
        $conexion->prepare("DELETE FROM combos WHERE id = ?")->execute([$id]);
        if($cod) $conexion->prepare("DELETE FROM productos WHERE codigo_barras = ?")->execute([$cod]);
        
        $conexion->commit();
        if($old) {
            $detalles_audit = "Combo Eliminado: " . $old['nombre'] . " | Precio: $" . floatval($old['precio']) . " | Código: " . $old['codigo_barras'];
            $conexion->prepare("INSERT INTO auditoria (id_usuario, accion, detalles, fecha) VALUES (?, 'COMBO_ELIMINADO', ?, NOW())")->execute([$_SESSION['usuario_id'], $detalles_audit]);
        }
        header("Location: combos.php?msg=eliminado"); exit;
    } catch (Exception $e) { $conexion->rollBack(); die($e->getMessage()); }
}

// --- 3. DATOS ---
$combos = $conexion->query("SELECT c.*, p.precio_oferta, p.imagen_url, p.id_categoria, p.es_destacado_web FROM combos c LEFT JOIN productos p ON c.codigo_barras = p.codigo_barras WHERE c.activo=1 AND (c.tipo_negocio = '$rubro_actual' OR c.tipo_negocio IS NULL) ORDER BY c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $conexion->query("SELECT * FROM categorias WHERE activo=1 AND (tipo_negocio = '$rubro_actual' OR tipo_negocio IS NULL)")->fetchAll(PDO::FETCH_ASSOC);
$productos_lista = $conexion->query("SELECT id, descripcion, stock_actual, precio_venta, precio_costo, id_categoria, codigo_barras FROM productos WHERE activo=1 AND tipo != 'combo' AND (tipo_negocio = '$rubro_actual' OR tipo_negocio IS NULL) ORDER BY descripcion ASC")->fetchAll(PDO::FETCH_ASSOC);

$recetas_data = [];
foreach($combos as $c) {
    $stmtItems = $conexion->prepare("SELECT ci.id, p.id as id_producto, ci.cantidad, p.descripcion, p.precio_venta FROM combo_items ci JOIN productos p ON ci.id_producto = p.id WHERE ci.id_combo = ?");
    $stmtItems->execute([$c['id']]);
    $recetas_data[$c['id']] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
}

$recetas_json = json_encode($recetas_data);
$productos_json = json_encode($productos_lista);
$combos_json = json_encode($combos);
$categorias_json = json_encode($categorias);

// WIDGETS
$total = count($combos);
$ofertas = 0; $destacados = 0;
foreach($combos as $c) {
    if($c['precio_oferta'] > 0) $ofertas++;
    if($c['es_destacado_web']) $destacados++;
}

$color_sistema = '#102A57';
try {
    $resColor = $conexion->query("SELECT color_barra_nav FROM configuracion WHERE id=1");
    if ($resColor && $dataC = $resColor->fetch(PDO::FETCH_ASSOC)) {
        if (isset($dataC['color_barra_nav'])) $color_sistema = $dataC['color_barra_nav'];
    }
} catch (Exception $e) { }

include 'includes/layout_header.php'; 

// --- DEFINICIÓN DEL BANNER ESTANDARIZADO ---
$titulo = "Mis Packs y Combos";
$subtitulo = "Gestión de ofertas y promociones";
$icono_bg = "bi-basket2-fill";

$botones = [];

if($es_admin || in_array('crear_combo', $permisos)) {
    $botones[] = ['texto' => 'NUEVO COMBO', 'icono' => 'bi-plus-circle-fill', 'class' => 'btn btn-light text-primary fw-bold rounded-pill px-4 shadow-sm ms-2', 'link' => 'javascript:void(0)" onclick="abrirComboSwal(\'crear\')"'];
}
$botones[] = ['texto' => 'REPORTE PDF', 'link' => 'reporte_combos.php', 'icono' => 'bi-file-earmark-pdf-fill', 'class' => 'btn btn-danger fw-bold rounded-pill px-4 shadow-sm', 'target' => '_blank'];
$widgets = [
    ['label' => 'Total Combos', 'valor' => $total, 'icono' => 'bi-basket', 'icon_bg' => 'bg-white bg-opacity-10'],
    ['label' => 'En Oferta', 'valor' => $ofertas, 'icono' => 'bi-percent', 'border' => 'border-warning', 'icon_bg' => 'bg-warning bg-opacity-20'],
    ['label' => 'Destacados Web', 'valor' => $destacados, 'icono' => 'bi-star-fill', 'border' => 'border-success', 'icon_bg' => 'bg-success bg-opacity-20']
];

include 'includes/componente_banner.php'; 
?>

<style>
    /* TOOLBAR MINIMALISTA */
    .minimal-toolbar { display: flex; align-items: center; gap: 10px; background: #fff; padding: 8px 15px; border-radius: 50px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px; width: 100%; position: sticky; top: 0; z-index: 1000; }
    .search-input-group { display: flex; align-items: center; flex-grow: 1; background: #f8fafc; border-radius: 50px; padding: 2px 15px; border: 1px solid transparent; transition: 0.2s; }
    .search-input-group:focus-within { background: #fff; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .search-input-group i { color: #94a3b8; font-size: 1.1rem; }
    .search-input-group input { border: none; background: transparent; padding: 8px 10px; width: 100%; outline: none; font-weight: 500; color: #1e293b; font-size: 0.95rem; }
    .btn-filter-trigger { white-space: nowrap; border-radius: 50px; padding: 8px 18px; font-weight: 700; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; transition: 0.2s; cursor: pointer; }
    
    /* TARJETAS DE COMBOS MODERNAS */
    .card-combo-modern { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; height: 100%; display: flex; flex-direction: column; position: relative; }
    .card-combo-modern:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -8px rgba(0,0,0,0.1); }
    .img-box-modern { height: 180px; position: relative; background: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid #e2e8f0; }
    .img-box-modern img { width: 100%; height: 100%; object-fit: cover; }
    .badge-destacado { position: absolute; top: 12px; right: 12px; background: rgba(255, 255, 255, 0.95); color: #ca8a04; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; box-shadow: 0 2px 4px rgba(0,0,0,0.1); backdrop-filter: blur(4px); }
    .check-flotante { position: absolute; top: 12px; left: 12px; transform: scale(1.4); cursor: pointer; z-index: 10; margin: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 2px solid #fff; }
    .info-box-modern { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
    .combo-title { font-family: 'Oswald', sans-serif; font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 2px; line-height: 1.2; }
    .combo-code { font-family: 'monospace'; font-size: 0.75rem; color: #94a3b8; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 15px; }
    .price-box { display: flex; align-items: flex-end; gap: 8px; margin-bottom: 15px; }
    .price-current { font-size: 1.5rem; font-weight: 900; color: #0f172a; line-height: 1; }
    .price-old { font-size: 0.9rem; font-weight: 600; color: #94a3b8; text-decoration: line-through; line-height: 1.2; }
    .price-offer { color: #ef4444; }
    .items-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 0.85rem; color: #475569; flex-grow: 1; }
    .items-box b { color: #1e293b; }
    .action-box-modern { padding: 15px 20px; background: #fff; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
    
    @media (max-width: 768px) { .minimal-toolbar { padding: 5px 10px; gap: 5px; } .btn-filter-trigger span { display: none; } .btn-filter-trigger { padding: 10px; } }
</style>

<div class="minimal-toolbar">
    <div class="search-input-group">
        <i class="bi bi-search"></i>
        <input type="text" id="buscador" placeholder="Buscar nombre o código de combo..." onkeyup="aplicarFiltros()" autocomplete="off">
    </div>

    <?php if($es_admin || in_array('eliminar_combo', $permisos)): ?>
    <button type="button" id="btnBorrarMasivo" class="btn btn-danger btn-filter-trigger shadow-sm d-none" onclick="borrarSeleccionados()">
        <i class="bi bi-trash3-fill"></i> <span id="cuentaSeleccionados" class="ms-1">0</span>
    </button>
    <?php endif; ?>
</div>

<div class="row g-4" id="gridProductos">
    <?php foreach($combos as $c): $items = $recetas_data[$c['id']]; ?>
    <div class="col-12 col-md-6 col-xl-4 item-grid" data-nombre="<?php echo strtolower($c['nombre'] . ' ' . $c['codigo_barras']); ?>">
        <div class="card-combo-modern">
            <div class="img-box-modern">
                <?php if($es_admin || in_array('eliminar_combo', $permisos)): ?>
                <input type="checkbox" class="form-check-input check-flotante checkCombo" value="<?php echo $c['id']; ?>" onclick="event.stopPropagation(); revisarChecks()">
                <?php endif; ?>
                <img src="<?php echo $c['imagen_url'] ?: 'img/no-image.png'; ?>" loading="lazy">
                <?php if($c['es_destacado_web']): ?><div class="badge-destacado"><i class="bi bi-star-fill"></i> Destacado</div><?php endif; ?>
            </div>
            
            <div class="info-box-modern">
                <h5 class="combo-title"><?php echo htmlspecialchars($c['nombre']); ?></h5>
                <span class="combo-code"><i class="bi bi-upc-scan"></i> <?php echo $c['codigo_barras']; ?></span>
                
                <div class="price-box">
                    <?php if($c['precio_oferta']): ?>
                        <div class="price-current price-offer">$<?php echo number_format($c['precio_oferta'], 0); ?></div>
                        <div class="price-old">$<?php echo number_format($c['precio'], 0); ?></div>
                    <?php else: ?>
                        <div class="price-current text-primary">$<?php echo number_format($c['precio'], 0); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <?php if($c['es_ilimitado']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2"><i class="bi bi-infinity"></i> PROMOCIÓN ILIMITADA</span>
                    <?php else: ?>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-3 py-2"><i class="bi bi-calendar-event"></i> <?php echo date('d/m', strtotime($c['fecha_inicio'])); ?> al <?php echo date('d/m', strtotime($c['fecha_fin'])); ?></span>
                    <?php endif; ?>
                </div>

                <div class="items-box custom-scrollbar" style="max-height: 120px; overflow-y: auto;">
                    <div class="saas-label mb-2 border-bottom pb-1" style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Contenido del Pack</div>
                    <?php if(empty($items)): ?><div class="text-muted fst-italic">Vacío.</div><?php else: foreach($items as $i): ?>
                        <div class="mb-1"><b class="text-primary"><?php echo $i['cantidad']; ?>x</b> <?php echo htmlspecialchars($i['descripcion']); ?></div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="action-box-modern">
                <?php if($es_admin || in_array('editar_combo', $permisos)): ?>
                <button class="btn btn-sm btn-light border text-primary fw-bold px-3 rounded-pill shadow-sm" onclick="abrirEditar(<?php echo $c['id']; ?>)"><i class="bi bi-pencil-fill"></i> CONFIGURAR</button>
                <?php endif; ?>
                <?php if($es_admin || in_array('eliminar_combo', $permisos)): ?>
                <button class="btn btn-sm btn-light border text-danger fw-bold px-3 rounded-pill shadow-sm" onclick="borrarPack(<?php echo $c['id']; ?>)"><i class="bi bi-trash3-fill"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="vistaListaGenerica" class="d-none">
    <div class="card shadow-sm mb-4 border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 14px;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 5%;">
                            <?php if($es_admin || in_array('eliminar_combo', $permisos)): ?>
                            <input type="checkbox" class="form-check-input" onclick="alternarTodos(this)" style="transform: scale(1.2); cursor:pointer;">
                            <?php endif; ?>
                        </th>
                        <th style="width: 15%;">CÓDIGO</th>
                        <th style="width: 30%;">COMBO / PACK</th>
                        <th class="text-center" style="width: 15%;">ESTADO</th>
                        <th class="text-end" style="width: 15%;">PRECIO</th>
                        <th class="text-center" style="width: 20%;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($combos as $c): ?>
                    <tr class="item-lista" data-nombre="<?php echo strtolower($c['nombre'] . ' ' . $c['codigo_barras']); ?>">
                        <td class="ps-3">
                            <?php if($es_admin || in_array('eliminar_combo', $permisos)): ?>
                            <input type="checkbox" class="form-check-input checkCombo" value="<?php echo $c['id']; ?>" onclick="event.stopPropagation(); revisarChecks()" style="transform: scale(1.2); cursor:pointer;">
                            <?php endif; ?>
                        </td>
                        <td class="text-muted fw-bold"><?php echo $c['codigo_barras']; ?></td>
                        <td>
                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($c['nombre']); ?></div>
                            <div class="small text-muted">Contiene <?php echo count($recetas_data[$c['id']]); ?> productos</div>
                        </td>
                        <td class="text-center">
                            <?php if($c['es_ilimitado']): ?><span class="badge bg-success">Ilimitado</span><?php else: ?><span class="badge bg-info">Temporal</span><?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if($c['precio_oferta']): ?>
                                <div class="text-danger fw-bold fs-6">$<?php echo number_format($c['precio_oferta'], 0); ?></div>
                                <div class="text-muted small text-decoration-line-through">$<?php echo number_format($c['precio'], 0); ?></div>
                            <?php else: ?>
                                <div class="text-primary fw-bold fs-6">$<?php echo number_format($c['precio'], 0); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <?php if($es_admin || in_array('editar_combo', $permisos)): ?>
                                <button class="btn btn-sm btn-light border text-primary py-1 px-2 rounded-3 shadow-sm" onclick="event.stopPropagation(); abrirEditar(<?php echo $c['id']; ?>)"><i class="bi bi-pencil-fill"></i></button>
                                <?php endif; ?>
                                <?php if($es_admin || in_array('eliminar_combo', $permisos)): ?>
                                <button type="button" class="btn btn-sm btn-light border text-danger py-1 px-2 rounded-3 shadow-sm" onclick="event.stopPropagation(); borrarPack(<?php echo $c['id']; ?>)"><i class="bi bi-trash3-fill"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // --- VARIABLES GLOBALES ---
    const prodsDB = <?php echo $productos_json; ?>;
    const recetasOriginales = <?php echo $recetas_json; ?>;
    const combosDB = <?php echo $combos_json; ?>;
    const categoriasDB = <?php echo $categorias_json; ?>;
    
    let itemsActuales = []; 
    let cropper = null;

    // --- FILTROS DE LA PÁGINA PRINCIPAL ---
    function aplicarFiltros() {
        let txt = document.getElementById('buscador').value.toLowerCase();
        document.querySelectorAll('.item-grid, .item-lista').forEach(item => {
            item.classList.toggle('d-none', !item.dataset.nombre.includes(txt));
        });
    }

    // --- MOTOR SWEETALERT2 PARA COMBOS ---
    function abrirComboSwal(modo, idCombo = null) {
        let obj = null;
        itemsActuales = [];

        if (modo === 'editar') {
            obj = combosDB.find(c => c.id == idCombo);
            if(!obj) return;
            let itemsBD = recetasOriginales[obj.id] || [];
            itemsBD.forEach(i => {
                let p = prodsDB.find(x => x.id == i.id_producto);
                if(p) itemsActuales.push({ id: p.id, nombre: p.descripcion, costo: p.precio_costo, venta: p.precio_venta, cant: i.cantidad });
            });
        }

        let catOptions = `<option value="">-- Elegir Categoría --</option>` + categoriasDB.map(c => `<option value="${c.id}" ${obj && obj.id_categoria==c.id?'selected':''}>${c.nombre}</option>`).join('');
        let catSearchOptions = `<option value="">Todas las Categorías</option>` + categoriasDB.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');

        let title = modo === 'crear' ? '<i class="bi bi-basket2-fill text-primary"></i> Crear Nuevo Combo' : '<i class="bi bi-pencil-square text-warning"></i> Editar Combo';
        let btnText = modo === 'crear' ? 'GUARDAR COMBO' : 'GUARDAR CAMBIOS';
        let btnColor = modo === 'crear' ? '#3b82f6' : '#ca8a04';

        Swal.fire({
            title: title,
            width: 900,
            showCancelButton: true,
            showConfirmButton: false, 
            customClass: { popup: 'rounded-4 p-0', title: 'fs-4 fw-bold p-3 border-bottom m-0 bg-light' },
            html: `
                <div id="swal_view_main" class="text-start" style="display: flex; flex-direction: column; height: 100%;">
                    <div class="row g-3 p-3 m-0" style="max-height: 70vh; overflow-y: auto; overflow-x: hidden;">
                        <div class="col-md-4">
                            <div class="border rounded-4 p-3 mb-3 text-center bg-white shadow-sm">
                                <label class="small fw-bold text-muted mb-2 d-block text-uppercase">Fotografía</label>
                                <img src="${obj && obj.imagen_url ? obj.imagen_url : 'img/no-image.png'}" id="swal_img_preview" class="border mb-2" style="width: 120px; height: 120px; object-fit: cover; border-radius: 16px;">
                                <label class="btn btn-sm btn-outline-primary w-100 rounded-pill fw-bold mt-2">
                                    <i class="bi bi-camera"></i> CAMBIAR FOTO
                                    <input type="file" class="d-none" accept="image/*" onchange="abrirCropperSwal(this)">
                                </label>
                                <input type="hidden" id="swal_base64">
                            </div>
                            
                            <div class="border rounded-4 p-3 mb-3 bg-white shadow-sm">
                                <label class="small fw-bold text-muted mb-2 d-block text-uppercase">Vigencia</label>
                                <div class="form-check form-switch mb-2 d-flex justify-content-between align-items-center p-0">
                                    <label class="form-check-label small fw-bold text-dark m-0">Promo Ilimitada</label>
                                    <input class="form-check-input m-0" type="checkbox" id="swal_ilim" ${!obj || obj.es_ilimitado==1 ? 'checked':''} onchange="document.getElementById('swal_fechas_box').style.display = this.checked ? 'none' : 'block'">
                                </div>
                                <div id="swal_fechas_box" style="display: ${!obj || obj.es_ilimitado==1 ? 'none':'block'};">
                                    <label class="small text-muted">Desde</label><input type="date" id="swal_fini" class="form-control form-control-sm mb-2" value="${obj?obj.fecha_inicio:''}">
                                    <label class="small text-muted">Hasta</label><input type="date" id="swal_ffin" class="form-control form-control-sm" value="${obj?obj.fecha_fin:''}">
                                </div>
                            </div>

                            <div class="form-check form-switch border rounded-4 p-3 bg-white shadow-sm d-flex align-items-center justify-content-between m-0">
                                <label class="form-check-label small fw-bold text-warning m-0"><i class="bi bi-star-fill"></i> Destacar Web</label>
                                <input class="form-check-input m-0" type="checkbox" id="swal_dest" ${obj && obj.es_destacado_web==1 ? 'checked':''}>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="border rounded-4 p-3 mb-3 bg-white shadow-sm">
                                <label class="small fw-bold text-muted mb-2 d-block text-uppercase">Datos Principales</label>
                                <div class="row g-2">
                                    <div class="col-12"><input type="text" id="swal_nombre" class="form-control form-control-sm" placeholder="Nombre del Combo..." value="${obj?obj.nombre:''}"></div>
                                    <div class="col-6"><input type="number" id="swal_precio" class="form-control form-control-sm text-primary fw-bold" placeholder="Precio ($)" value="${obj?obj.precio:''}"></div>
                                    <div class="col-6"><input type="number" id="swal_oferta" class="form-control form-control-sm text-danger fw-bold" placeholder="Oferta ($)" value="${obj?obj.precio_oferta:''}"></div>
                                    <div class="col-6"><select id="swal_cat" class="form-select form-select-sm">${catOptions}</select></div>
                                    <div class="col-6"><input type="text" id="swal_cod" class="form-control form-control-sm" placeholder="Cód. Barras" value="${obj?obj.codigo_barras:''}"></div>
                                </div>
                            </div>

                            <div class="border rounded-4 p-3 bg-white shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="small fw-bold text-success m-0 text-uppercase"><i class="bi bi-box-seam"></i> Contenido del Pack</label>
                                </div>
                                
                                <div id="swal_added_items" class="custom-scrollbar mb-3" style="max-height: 160px; overflow-y: auto; overflow-x: hidden;"></div>
                                
                                <button type="button" class="btn btn-outline-primary w-100 fw-bold rounded-pill shadow-sm py-2" onclick="toggleViews('search')">
                                    <i class="bi bi-search"></i> BUSCAR Y AGREGAR PRODUCTOS
                                </button>

                                <div id="swal_totales" class="mt-3 text-end small fw-bold text-muted p-2 bg-light border rounded-3"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-3 border-top bg-light text-end">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold me-2" onclick="Swal.close()">CANCELAR</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" style="background-color:${btnColor}; border-color:${btnColor};" onclick="validarYEnviar('${modo}', ${idCombo})">${btnText}</button>
                    </div>
                </div>

                <div id="swal_view_search" class="text-start" style="display:none; flex-direction: column; height: 100%;">
                    <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fw-bold text-primary"><i class="bi bi-search"></i> Catálogo de Productos</h5>
                        <button type="button" class="btn-close" onclick="toggleViews('main')"></button>
                    </div>
                    <div class="p-3" style="background:#f8fafc;">
                        <div class="d-flex flex-column flex-md-row gap-2 mb-3">
                            <input type="text" id="swal_search_input" class="form-control fw-bold flex-grow-1 shadow-sm" placeholder="Escribir nombre o código..." onkeyup="filtrarBuscadorSwal()" autocomplete="off">
                            <select id="swal_search_cat" class="form-select fw-bold shadow-sm" style="max-width: 250px;" onchange="filtrarBuscadorSwal()">${catSearchOptions}</select>
                        </div>
                        <div id="swal_search_results" class="custom-scrollbar bg-white border rounded-3 shadow-sm" style="flex-grow:1; max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                            </div>
                    </div>
                    <div class="p-3 border-top bg-white text-center">
                        <button type="button" class="btn btn-primary rounded-pill px-5 py-2 fw-bold w-100" onclick="toggleViews('main')"><i class="bi bi-check-lg"></i> TERMINAR Y VOLVER AL COMBO</button>
                    </div>
                </div>

                <div id="swal_view_crop" class="text-start" style="display:none; flex-direction: column; height: 100%;">
                    <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fw-bold"><i class="bi bi-crop"></i> Recortar Imagen</h5>
                        <button type="button" class="btn-close" onclick="cerrarCropper(false)"></button>
                    </div>
                    <div style="flex-grow:1; background:#000; min-height: 50vh; position:relative; overflow:hidden;">
                        <img id="cropper_img" src="" style="max-width:100%; max-height:100%;">
                    </div>
                    <div class="p-3 border-top bg-light text-end">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold me-2" onclick="cerrarCropper(false)">CANCELAR</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="cerrarCropper(true)">APLICAR RECORTE</button>
                    </div>
                </div>

                <style>
                    .swal2-html-container { padding: 0 !important; margin: 0 !important; overflow: hidden !important; }
                    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
                    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
                </style>
            `
        });
        
        renderizarItemsSwal();
    }

    // --- MANEJO DE VISTAS (MAIN / SEARCH / CROP) ---
    function toggleViews(view) {
        document.getElementById('swal_view_main').style.display = view === 'main' ? 'flex' : 'none';
        document.getElementById('swal_view_search').style.display = view === 'search' ? 'flex' : 'none';
        document.getElementById('swal_view_crop').style.display = view === 'crop' ? 'flex' : 'none';
        
        if(view === 'search') {
            let input = document.getElementById('swal_search_input');
            input.value = '';
            filtrarBuscadorSwal();
            setTimeout(() => input.focus(), 100);
        }
    }

    // --- LÓGICA DEL BUSCADOR ---
    function filtrarBuscadorSwal() {
        let txt = document.getElementById('swal_search_input').value.toLowerCase();
        let cat = document.getElementById('swal_search_cat').value;
        let box = document.getElementById('swal_search_results');
        
        let count = 0;
        let html = '';
        prodsDB.forEach(p => {
            let matchesTxt = p.descripcion.toLowerCase().includes(txt) || (p.codigo_barras || '').toLowerCase().includes(txt);
            let matchesCat = cat === '' || p.id_categoria == cat;
            
            if (matchesTxt && matchesCat && count < 60) {
                let catName = categoriasDB.find(c => c.id == p.id_categoria)?.nombre || 'General';
                // El botón AGREGAR ahora usa text-nowrap y flex-shrink-0 para evitar que se apile en vertical en celulares.
                // El contenedor del nombre usa text-truncate para cortar el texto si es muy largo y no aplastar al botón.
                html += `
                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom bg-white gap-2" style="cursor:pointer; transition:0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                        <div onclick="agregarItemSwal(${p.id}, this.nextElementSibling)" style="flex-grow:1; min-width:0;">
                            <div class="fw-bold text-dark text-truncate" style="font-size:0.9rem;">${p.descripcion}</div>
                            <div class="text-muted small text-truncate"><i class="bi bi-tag"></i> ${catName} | $${p.precio_venta}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-sm px-3 fw-bold text-nowrap d-flex align-items-center gap-1 flex-shrink-0" onclick="agregarItemSwal(${p.id}, this)"><i class="bi bi-plus-lg"></i> AGREGAR</button>
                    </div>`;
                count++;
            }
        });
        
        box.innerHTML = count > 0 ? html : '<div class="p-4 text-center text-muted fs-6 fw-bold"><i class="bi bi-emoji-frown display-6 d-block mb-2"></i>No se encontraron productos</div>';
    }

    function agregarItemSwal(idProd, btnElement) {
        let p = prodsDB.find(x => x.id == idProd);
        if(!p) return;
        
        let existe = itemsActuales.find(i => i.id == idProd);
        if(existe) { existe.cant++; } 
        else { itemsActuales.push({ id: p.id, nombre: p.descripcion, costo: p.precio_costo, venta: p.precio_venta, cant: 1 }); }
        
        renderizarItemsSwal();
        
        // Feedback visual en el botón sin lanzar otra alerta
        let txtOriginal = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="bi bi-check-lg"></i> OK';
        btnElement.classList.replace('btn-primary', 'btn-success');
        setTimeout(() => {
            btnElement.innerHTML = txtOriginal;
            btnElement.classList.replace('btn-success', 'btn-primary');
        }, 1000);
    }

    function borrarItemSwal(index) {
        itemsActuales.splice(index, 1);
        renderizarItemsSwal();
    }

    function cambiarCantSwal(index, val) {
        let cant = parseFloat(val) || 1;
        if(cant < 1) cant = 1;
        itemsActuales[index].cant = cant;
        renderizarItemsSwal();
    }

    function renderizarItemsSwal() {
        let html = '';
        let costoTotal = 0;
        let ventaTotal = 0;

        itemsActuales.forEach((i, idx) => {
            costoTotal += parseFloat(i.costo || 0) * i.cant;
            ventaTotal += parseFloat(i.venta || 0) * i.cant;
            
            // Reemplazo del "Cant" apilable por un Input Group compacto y protegido contra line-breaks
            html += `
                <div class="d-flex align-items-center justify-content-between p-2 border rounded-3 bg-white mb-2 shadow-sm gap-2">
                    <div class="fw-bold text-dark" style="font-size:0.85rem; flex-grow:1; line-height: 1.2;">${i.nombre}</div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <div class="input-group input-group-sm flex-nowrap" style="width: 85px;">
                            <span class="input-group-text bg-light text-muted fw-bold px-2">x</span>
                            <input type="number" class="form-control text-center fw-bold text-primary px-1" value="${i.cant}" min="1" onchange="cambiarCantSwal(${idx}, this.value)">
                        </div>
                        <button type="button" class="btn btn-sm btn-light text-danger border ms-1" onclick="borrarItemSwal(${idx})"><i class="bi bi-trash3-fill"></i></button>
                    </div>
                </div>`;
        });

        document.getElementById('swal_added_items').innerHTML = html || '<div class="text-muted text-center small fst-italic p-3 border rounded-3 bg-white">No hay productos. Toca el botón de abajo para buscar.</div>';
        document.getElementById('swal_totales').innerHTML = `Costo Base: $${costoTotal.toLocaleString()} <span class="mx-2">|</span> Sugerido: <span class="text-success">$${ventaTotal.toLocaleString()}</span>`;
        
        let inputPrecio = document.getElementById('swal_precio');
        if(inputPrecio && (inputPrecio.value == '' || inputPrecio.value == 0) && itemsActuales.length > 0) {
            inputPrecio.value = (ventaTotal * 0.85).toFixed(0); 
        }
    }

    // --- VALIDACIÓN Y ENVÍO ---
    function validarYEnviar(modo, idCombo) {
        let nombre = document.getElementById('swal_nombre').value;
        let precio = document.getElementById('swal_precio').value;
        let cat = document.getElementById('swal_cat').value;
        
        if(!nombre || !precio || !cat) { 
            alert('Error: Faltan datos obligatorios (Nombre, Precio o Categoría).');
            return; 
        }
        if(itemsActuales.length === 0) { 
            alert('Error: Debes agregar al menos 1 producto al combo.');
            return; 
        }

        let obj = null;
        if(modo === 'editar') obj = combosDB.find(c => c.id == idCombo);

        let data = {
            id_combo: idCombo,
            nombre: nombre,
            precio: precio,
            precio_oferta: document.getElementById('swal_oferta').value,
            id_categoria: cat,
            codigo: document.getElementById('swal_cod').value,
            es_ilimitado: document.getElementById('swal_ilim').checked ? 1 : 0,
            fecha_inicio: document.getElementById('swal_fini').value,
            fecha_fin: document.getElementById('swal_ffin').value,
            es_destacado: document.getElementById('swal_dest').checked ? 1 : 0,
            imagen_base64: document.getElementById('swal_base64').value,
            imagen_actual: obj ? obj.imagen_url : 'default.jpg',
            items: itemsActuales
        };

        enviarFormularioSwaL(modo, data);
    }

    function enviarFormularioSwaL(modo, data) {
        let form = document.createElement('form');
        form.method = 'POST';

        const addInput = (name, val) => { let i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = val; form.appendChild(i); };

        if(modo === 'crear') addInput('crear_combo', '1');
        if(modo === 'editar') { addInput('editar_combo', '1'); addInput('id_combo', data.id_combo); addInput('imagen_actual', data.imagen_actual); }

        addInput('nombre', data.nombre);
        addInput('precio', data.precio);
        addInput('precio_oferta', data.precio_oferta);
        addInput('id_categoria', data.id_categoria);
        addInput('codigo', data.codigo);
        if(data.es_ilimitado) addInput('es_ilimitado', '1');
        addInput('fecha_inicio', data.fecha_inicio);
        addInput('fecha_fin', data.fecha_fin);
        if(data.es_destacado) addInput('es_destacado', '1');
        addInput('imagen_base64', data.imagen_base64);

        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('origen') === 'productos') addInput('origen', 'productos');

        data.items.forEach(item => {
            addInput('prod_ids[]', item.id);
            addInput('prod_cants[]', item.cant);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // --- CROPPER ---
    function abrirCropperSwal(input) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('cropper_img').src = e.target.result;
                toggleViews('crop');
                
                if(cropper) cropper.destroy();
                setTimeout(() => {
                    cropper = new Cropper(document.getElementById('cropper_img'), { aspectRatio: 1, viewMode: 1 });
                }, 100);
            }
            reader.readAsDataURL(input.files[0]);
            input.value = ''; 
        }
    }

    function cerrarCropper(aplicar) {
        if(aplicar && cropper) {
            let b64 = cropper.getCroppedCanvas({width:600, height:600}).toDataURL('image/png');
            document.getElementById('swal_img_preview').src = b64;
            document.getElementById('swal_base64').value = b64;
        }
        if(cropper) cropper.destroy();
        toggleViews('main');
    }

    window.abrirEditar = function(id) { abrirComboSwal('editar', id); }
    window.borrarPack = function(id) { procesarBorrado([id]); }

    window.revisarChecks = function() {
        let marcados = document.querySelectorAll('.checkCombo:checked').length;
        let btn = document.getElementById('btnBorrarMasivo');
        let span = document.getElementById('cuentaSeleccionados');
        if(span) span.innerText = marcados;
        if(btn) {
            if(marcados > 0) btn.classList.remove('d-none');
            else btn.classList.add('d-none');
        }
    }

    window.borrarSeleccionados = function() {
        let seleccionados = [];
        document.querySelectorAll('.checkCombo:checked').forEach(c => seleccionados.push(c.value));
        if(seleccionados.length > 0) procesarBorrado(seleccionados);
    }

    window.procesarBorrado = function(listaIds) {
        Swal.fire({
            title: '¿Eliminar Combos?',
            text: "Vas a eliminar " + listaIds.length + " combo(s). Esto no afectará el stock interno de tus productos sueltos.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, Eliminar',
            cancelButtonText: 'Cancelar',
            customClass: { confirmButton: 'rounded-pill px-4 fw-bold', cancelButton: 'rounded-pill px-4 fw-bold' }
        }).then((result) => {
            if (result.isConfirmed) {
                let formData = new FormData();
                formData.append('solicitud_borrar_masivo', '1');
                formData.append('ids_a_borrar', JSON.stringify(listaIds));
                fetch(window.location.pathname, { method: 'POST', body: formData })
                .then(res => res.text())
                .then(texto => {
                    if(texto.includes("EXITO")) { Swal.fire({title: 'Eliminado', icon: 'success', customClass:{confirmButton:'rounded-pill fw-bold'}}).then(()=>location.reload()); }
                    else { Swal.fire('Error', texto, 'error'); }
                });
            }
        });
    }

    // --- AUTO-ABRIR SWEETALERT SI VIENE REDIRECCIONADO ---
    document.addEventListener("DOMContentLoaded", function() {
        const params = new URLSearchParams(window.location.search);
        
        if (params.get('crear') === 'combo' || params.get('nuevo') === '1') {
            setTimeout(() => { abrirComboSwal('crear'); }, 300);
        }

        const editarCodigo = params.get('editar_codigo');
        if (editarCodigo) {
            let cod = String(editarCodigo).trim().toLowerCase();
            let comboEncontrado = combosDB.find(c => String(c.codigo_barras || '').trim().toLowerCase() === cod);
            if (comboEncontrado) {
                setTimeout(() => { abrirComboSwal('editar', comboEncontrado.id); }, 400);
            }
        }
        
        let btnNuevoCombo = document.querySelector('a[data-bs-target="#modalCrear"]');
        if(btnNuevoCombo) {
            btnNuevoCombo.removeAttribute('data-bs-toggle');
            btnNuevoCombo.removeAttribute('data-bs-target');
            btnNuevoCombo.setAttribute('onclick', "abrirComboSwal('crear')");
            btnNuevoCombo.href = "javascript:void(0)";
        }
    });
</script>
<?php include 'includes/layout_footer.php'; ?>