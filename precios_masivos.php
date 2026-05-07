<?php
// precios_masivos.php - VERSIÓN ESTANDARIZADA Y REPARADA
session_start();
require_once 'includes/db.php';

// SEGURIDAD
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] > 2) { 
    header("Location: dashboard.php"); exit; 
}

$conf_rubro = $conexion->query("SELECT tipo_negocio FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$rubro_actual = $conf_rubro['tipo_negocio'] ?? 'kiosco';

// CARGA DE DATOS PARA FILTROS Y WIDGETS
$categorias = $conexion->query("SELECT * FROM categorias WHERE activo=1 AND (tipo_negocio = '$rubro_actual' OR tipo_negocio IS NULL)")->fetchAll(PDO::FETCH_OBJ);
$proveedores = $conexion->query("SELECT * FROM proveedores WHERE (tipo_negocio = '$rubro_actual' OR tipo_negocio IS NULL) ORDER BY empresa ASC")->fetchAll(PDO::FETCH_OBJ);
$total_cats = count($categorias);
$total_provs = count($proveedores);

$tipo_filtro = $_GET['tipo'] ?? 'proveedor';
$id_filtro = $_GET['id'] ?? '';

// AUTO-FILTRADO PREDETERMINADO: Si no hay ID, tomamos el primer elemento disponible
if (!$id_filtro) {
    if ($tipo_filtro == 'proveedor' && !empty($proveedores)) {
        $id_filtro = $proveedores[0]->id;
    } elseif ($tipo_filtro == 'categoria' && !empty($categorias)) {
        $id_filtro = $categorias[0]->id;
    }
}

$productos_filtrados = [];

// 1. CARGAR PRODUCTOS SEGÚN FILTRO
if ($id_filtro) {
    $where = ($tipo_filtro == 'proveedor') ? "id_proveedor = ?" : "id_categoria = ?";
    $stmt = $conexion->prepare("SELECT * FROM productos WHERE $where AND activo = 1 AND (tipo_negocio = ? OR tipo_negocio IS NULL) ORDER BY descripcion ASC");
    $stmt->execute([$id_filtro, $rubro_actual]);
    $productos_filtrados = $stmt->fetchAll(PDO::FETCH_OBJ);
}

// 2. PROCESAR AUMENTO (LÓGICA ORIGINAL PRESERVADA)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_aumento'])) {
    $ids = $_POST['productos_seleccionados'] ?? [];
    $accion = $_POST['accion']; 
    $porcentaje = floatval($_POST['porcentaje']);
    $tipo_h = $_POST['tipo_hidden'];
    $id_h = $_POST['id_hidden'];

    if(count($ids) > 0 && $porcentaje > 0) {
        try {
            $conexion->beginTransaction();
            $ids_str = implode(',', array_map('intval', $ids));
            
            $nombre_grupo = "General";
            if ($tipo_h == 'proveedor') {
                $st = $conexion->prepare("SELECT empresa FROM proveedores WHERE id = ?");
            } else {
                $st = $conexion->prepare("SELECT nombre FROM categorias WHERE id = ?");
            }
            $st->execute([$id_h]);
            $nombre_grupo = $st->fetchColumn() ?: "ID #$id_h";

           $detalles = "Aumento Masivo del $porcentaje% en " . strtoupper($accion) . " aplicado a " . count($ids) . " productos del grupo " . strtoupper($tipo_h) . ": " . $nombre_grupo;
            $conexion->prepare("INSERT INTO auditoria (id_usuario, accion, detalles, fecha, tipo_negocio) VALUES (?, 'INFLACION', ?, NOW(), ?)")->execute([$_SESSION['usuario_id'], $detalles, $rubro_actual]);
            $conexion->prepare("INSERT INTO historial_inflacion (fecha, porcentaje, accion, grupo_afectado, cantidad_productos, id_usuario, tipo_negocio) VALUES (NOW(), ?, ?, ?, ?, ?, ?)")->execute([$porcentaje, strtoupper($accion), $nombre_grupo, count($ids), $_SESSION['usuario_id'], $rubro_actual]);

            $factor = 1 + ($porcentaje / 100);
            if ($accion == 'costo') {
                $sql = "UPDATE productos SET precio_costo = precio_costo * $factor, precio_venta = precio_venta * $factor WHERE id IN ($ids_str)";
            } else {
                $sql = "UPDATE productos SET precio_venta = precio_venta * $factor WHERE id IN ($ids_str)";
            }
            
            $conexion->exec($sql);
            $conexion->commit();
            header("Location: precios_masivos.php?tipo=$tipo_h&id=$id_h&msg=ok&count=" . count($ids));
            exit;
        } catch (Exception $e) { 
            if($conexion->inTransaction()) $conexion->rollBack(); 
            die("Error: " . $e->getMessage());
        }
    }
}

$conf = $conexion->query("SELECT * FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$color_sistema = $conf['color_barra_nav'] ?? '#102A57';

require_once 'includes/layout_header.php'; ?>

<?php
// --- DEFINICIÓN DEL BANNER DINÁMICO ESTANDARIZADO ---
$titulo = "Actualización de Precios";
$subtitulo = "Ajuste masivo de precios por inflación.";
$icono_bg = "bi-graph-up-arrow";

$botones = [
    ['texto' => 'Ver Historial', 'link' => "historial_inflacion.php", 'icono' => 'bi-clock-history', 'class' => 'btn btn-warning text-dark fw-bold rounded-pill px-3 px-md-4 py-2 shadow-sm']
];

$widgets = [
    ['label' => 'Categorías', 'valor' => $total_cats, 'icono' => 'bi-tags', 'icon_bg' => 'bg-white bg-opacity-10'],
    ['label' => 'Proveedores', 'valor' => $total_provs, 'icono' => 'bi-truck', 'border' => 'border-success', 'icon_bg' => 'bg-success bg-opacity-20'],
    ['label' => 'Productos', 'valor' => count($productos_filtrados), 'icono' => 'bi-box-seam', 'border' => 'border-info', 'icon_bg' => 'bg-info bg-opacity-20']
];

include 'includes/componente_banner.php'; 
?>

<div class="container pb-5 mt-n4" style="position: relative; z-index: 20;">
    
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
        <form id="formFiltrosPrecios" method="GET" class="d-flex w-100 align-items-center gap-2 m-0">
            <div class="search-input-group">
                <i class="bi bi-search"></i>
                <input type="text" id="buscadorNaranja" placeholder="Filtrar productos en la lista inferior..." onkeyup="document.getElementById('buscadorTabla').value = this.value; filtrarTabla();" autocomplete="off">
            </div>

            <input type="hidden" name="tipo" id="hiddenTipo" value="<?php echo htmlspecialchars($tipo_filtro); ?>">
            <input type="hidden" name="id" id="hiddenId" value="<?php echo htmlspecialchars($id_filtro); ?>">

            <button type="button" class="btn btn-primary border btn-filter-trigger shadow-sm" onclick="abrirFiltrosPrecios()">
                <i class="bi bi-tags-fill"></i> <span style="color:#fff;">ELEGIR CATEGORÍA / PROVEEDOR</span>
            </button>
        </form>
    </div>

    <script>
    function abrirFiltrosPrecios() {
        let cats = <?php echo json_encode($categorias); ?>;
        let provs = <?php echo json_encode($proveedores); ?>;
        let currTipo = document.getElementById('hiddenTipo').value;
        let currId = document.getElementById('hiddenId').value;
        
        let updateSelect = function(t, val) {
            let options = '<option value="">-- Seleccione para cargar --</option>';
            let list = (t === 'proveedor') ? provs : cats;
            list.forEach(i => {
                let text = (t === 'proveedor') ? i.empresa : i.nombre;
                options += `<option value="${i.id}" ${val == i.id ? 'selected' : ''}>${text.toUpperCase()}</option>`;
            });
            document.getElementById('swal_id').innerHTML = options;
        };

        Swal.fire({
            title: 'SELECCIONAR GRUPO',
            html: `
                <div style="text-align: left; display: flex; flex-direction: column; gap: 15px; margin-top: 10px;">
                    <div>
                        <label class="small fw-bold text-muted text-uppercase">1. Seleccionar Tipo</label>
                        <select id="swal_tipo" class="form-select fw-bold">
                            <option value="proveedor" ${currTipo === 'proveedor' ? 'selected' : ''}>Filtrar por Proveedor</option>
                            <option value="categoria" ${currTipo === 'categoria' ? 'selected' : ''}>Filtrar por Categoría</option>
                        </select>
                    </div>
                    <div>
                        <label class="small fw-bold text-muted text-uppercase">2. Elegir Grupo a Aumentar</label>
                        <select id="swal_id" class="form-select fw-bold"></select>
                    </div>
                </div>
            `,
            showCancelButton: true, confirmButtonText: 'CARGAR PRODUCTOS', cancelButtonText: 'CANCELAR',
            confirmButtonColor: '#3b82f6', cancelButtonColor: '#6c757d',
            customClass: { confirmButton: 'rounded-pill px-4 fw-bold', cancelButton: 'rounded-pill px-4 fw-bold' },
            didOpen: () => {
                updateSelect(currTipo, currId);
                document.getElementById('swal_tipo').addEventListener('change', (e) => updateSelect(e.target.value, ''));
            },
            preConfirm: () => {
                let selId = document.getElementById('swal_id').value;
                if(!selId) return Swal.showValidationMessage('⚠️ Debes seleccionar un grupo para cargar los productos.');
                document.getElementById('hiddenTipo').value = document.getElementById('swal_tipo').value;
                document.getElementById('hiddenId').value = selId;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formFiltrosPrecios').submit();
            }
        });
    }
    </script>

    <?php if($id_filtro): ?>
    <form method="POST" id="formInflacion">
        <input type="hidden" name="confirmar_aumento" value="1">
        <input type="hidden" name="tipo_hidden" value="<?php echo $tipo_filtro; ?>">
        <input type="hidden" name="id_hidden" value="<?php echo $id_filtro; ?>">

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                        <span class="text-dark">Productos en Lista (<span id="contadorVisible"><?php echo count($productos_filtrados); ?></span>)</span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="checkAll" checked onchange="toggleAll(this)">
                            <label class="form-check-label small text-muted" for="checkAll">Todos</label>
                        </div>
                    </div>
                    <div class="p-2 bg-light border-bottom">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="buscadorTabla" class="form-control border-0 bg-white" placeholder="Buscar en esta lista..." onkeyup="filtrarTabla()">
                        </div>
                    </div>
                    <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small text-uppercase">
                                <tr>
                                    <th width="50" class="text-center">#</th>
                                    <th>Producto</th>
                                    <th class="text-end pe-3">P. Venta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($productos_filtrados as $p): ?>
                                <tr class="fila-producto">
                                    <td class="text-center">
                                        <input type="checkbox" name="productos_seleccionados[]" value="<?php echo $p->id; ?>" class="form-check-input item-check" checked>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark nombre-prod"><?php echo htmlspecialchars($p->descripcion); ?></div>
                                        <small class="text-muted"><?php echo $p->codigo_barras; ?></small>
                                    </td>
                                    <td class="text-end pe-3">
                                        <span class="fw-bold text-primary">$<?php echo number_format($p->precio_venta, 2, ',', '.'); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-lg rounded-4" style="border-left: 5px solid #dc3545 !important; position: sticky; top: 20px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4 text-dark">Aplicar el Ajuste</h5>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Porcentaje de Aumento (%)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger text-white border-0"><i class="bi bi-percent fs-5"></i></span>
                                <input type="number" name="porcentaje" class="form-control form-control-lg fw-bold text-center fs-2 text-danger shadow-sm" placeholder="0.00" step="0.01" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="accion" id="a_costo" value="costo" checked>
                                <label class="btn btn-outline-secondary w-100 py-3 h-100 shadow-sm" for="a_costo">
                                    <i class="bi bi-shield-check fs-4 d-block mb-1"></i> Costo y Venta
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="accion" id="a_venta" value="venta">
                                <label class="btn btn-outline-primary w-100 py-3 h-100 shadow-sm" for="a_venta">
                                    <i class="bi bi-cash-stack fs-4 d-block mb-1"></i> Solo Venta
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-danger w-100 btn-lg fw-bold py-3 shadow">
                            <i class="bi bi-check-circle-fill me-2"></i> APLICAR AHORA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function actualizarVista() { 
        window.location.href = "precios_masivos.php?tipo=" + document.getElementById('select_tipo').value; 
    }
    
    function toggleAll(source) { 
        document.querySelectorAll('.item-check').forEach(cb => {
            if(cb.closest('tr').style.display !== 'none') {
                cb.checked = source.checked;
            }
        });
    }
    
    function filtrarTabla() {
        const txt = document.getElementById('buscadorTabla').value.toLowerCase();
        let count = 0;
        document.querySelectorAll('.fila-producto').forEach(row => {
            const nombre = row.querySelector('.nombre-prod').textContent.toLowerCase();
            if(nombre.includes(txt)) {
                row.style.display = '';
                count++;
            } else {
                row.style.display = 'none';
            }
        });
        document.getElementById('contadorVisible').innerText = count;
    }

    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('msg') === 'ok') {
        Swal.fire({
            icon: 'success',
            title: '¡Ajuste aplicado!',
            text: 'Se actualizaron los productos correctamente.',
            timer: 3000,
            showConfirmButton: false
        });
    }
</script>

<?php include 'includes/layout_footer.php'; ?>