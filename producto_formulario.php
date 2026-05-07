<?php
// producto_formulario.php - VERSIÓN ESTABLE VANGUARD PRO
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

$id = $_GET['id'] ?? null;
$producto = null;

if ($id) {
    $stmt = $conexion->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
}

$categorias = $conexion->query("SELECT * FROM categorias WHERE activo=1")->fetchAll(PDO::FETCH_ASSOC);
$proveedores = $conexion->query("SELECT * FROM proveedores")->fetchAll(PDO::FETCH_ASSOC);

// Carga de taras reales de tu SQL
$taras_lista = [];
try {
    $taras_lista = $conexion->query("SELECT nombre, peso FROM taras_predefinidas ORDER BY peso ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// --- LÓGICA DE PROCESAMIENTO ORIGINAL (SIN CAMBIOS) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $codigo = trim($_POST['codigo'] ?? '');
        $codigo = ($codigo === '') ? null : $codigo; 
        $descripcion = trim($_POST['descripcion'] ?? '');
        $id_cat = !empty($_POST['id_categoria']) ? $_POST['id_categoria'] : null;
        $id_prov = !empty($_POST['id_proveedor']) ? $_POST['id_proveedor'] : null;
        $p_costo = (isset($_POST['precio_costo']) && $_POST['precio_costo'] !== '') ? floatval($_POST['precio_costo']) : 0;
        $p_venta = (isset($_POST['precio_venta']) && $_POST['precio_venta'] !== '') ? floatval($_POST['precio_venta']) : 0;
        $p_oferta = (isset($_POST['precio_oferta']) && $_POST['precio_oferta'] !== '') ? floatval($_POST['precio_oferta']) : null;
        $s_actual = (isset($_POST['stock_actual']) && $_POST['stock_actual'] !== '') ? floatval($_POST['stock_actual']) : 0;
        $s_min = (isset($_POST['stock_minimo']) && $_POST['stock_minimo'] !== '') ? floatval($_POST['stock_minimo']) : 0;
        $f_venc = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        $d_alerta = (isset($_POST['dias_alerta']) && $_POST['dias_alerta'] !== '') ? intval($_POST['dias_alerta']) : 30;
        $es_vegano = isset($_POST['es_vegano']) ? 1 : 0;
        $es_celiaco = isset($_POST['es_celiaco']) ? 1 : 0;
        $es_destacado = isset($_POST['es_destacado_web']) ? 1 : 0;
        $plu = (isset($_POST['plu']) && $_POST['plu'] !== '') ? intval($_POST['plu']) : null;
        $tara_defecto = (isset($_POST['tara_defecto']) && $_POST['tara_defecto'] !== '') ? floatval($_POST['tara_defecto']) : 0;
        $tipo = $_POST['tipo'] ?? 'unitario';

        $imagen_url = !empty($_POST['imagen_actual']) ? $_POST['imagen_actual'] : 'default.jpg';
        if (isset($_FILES['imagen_nueva']) && $_FILES['imagen_nueva']['error'] === UPLOAD_ERR_OK) {
            $dir_uploads = 'uploads/';
            if (!file_exists($dir_uploads)) mkdir($dir_uploads, 0777, true);
            $ext = strtolower(pathinfo($_FILES['imagen_nueva']['name'], PATHINFO_EXTENSION));
            $nombre_img = 'prod_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['imagen_nueva']['tmp_name'], $dir_uploads . $nombre_img)) { $imagen_url = $dir_uploads . $nombre_img; }
        }

        if ($id) {
            $sql = "UPDATE productos SET codigo_barras=?, descripcion=?, id_categoria=?, id_proveedor=?, precio_costo=?, precio_venta=?, precio_oferta=?, stock_actual=?, stock_minimo=?, fecha_vencimiento=?, dias_alerta=?, es_vegano=?, es_celiaco=?, es_destacado_web=?, plu=?, tara_defecto=?, tipo=?, imagen_url=? WHERE id=?";
            $conexion->prepare($sql)->execute([$codigo, $descripcion, $id_cat, $id_prov, $p_costo, $p_venta, $p_oferta, $s_actual, $s_min, $f_venc, $d_alerta, $es_vegano, $es_celiaco, $es_destacado, $plu, $tara_defecto, $tipo, $imagen_url, $id]);
        } else {
            $sql = "INSERT INTO productos (codigo_barras, descripcion, id_categoria, id_proveedor, precio_costo, precio_venta, precio_oferta, stock_actual, stock_minimo, fecha_vencimiento, dias_alerta, es_vegano, es_celiaco, es_destacado_web, plu, tara_defecto, tipo, imagen_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $conexion->prepare($sql)->execute([$codigo, $descripcion, $id_cat, $id_prov, $p_costo, $p_venta, $p_oferta, $s_actual, $s_min, $f_venc, $d_alerta, $es_vegano, $es_celiaco, $es_destacado, $plu, $tara_defecto, $tipo, $imagen_url]);
        }
        echo "<script>window.location.href='productos.php?msg=ok';</script>"; exit;
    } catch (Exception $e) { die("Error: " . $e->getMessage()); }
}

// --- DATOS DEL SISTEMA ---
$color_sistema = '#102A57';
try {
    $resC = $conexion->query("SELECT color_barra_nav FROM configuracion WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if($resC) $color_sistema = $resC['color_barra_nav'];
} catch(Exception $e) {}

$costo_ini = floatval($producto['precio_costo'] ?? 0);
$venta_ini = floatval($producto['precio_venta'] ?? 0);
$ganancia_ini = $venta_ini - $costo_ini;
$margen_ini = ($costo_ini > 0) ? ($ganancia_ini / $costo_ini) * 100 : 0;

// SOLUCIÓN: Definimos la ruta de la imagen real para la previsualización
$imgSrc = "https://ui-avatars.com/api/?name=Producto&background=f4f6f9&color=adb5bd&size=250&font-size=0.33"; // Placeholder elegante por defecto
if (!empty($producto['imagen_url']) && $producto['imagen_url'] !== 'default.jpg' && file_exists($producto['imagen_url'])) {
    $imgSrc = $producto['imagen_url'];
}

$titulo = $id ? "Editar Producto" : "Nuevo Producto";

include 'includes/layout_header.php'; 
?>

<style>
    .form-header-modern { position: sticky; top: 0; z-index: 1020; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; margin: -24px -20px 20px -20px; }
    .form-title-modern { font-family: 'Oswald', sans-serif; font-size: 1.5rem; color: #1e293b; margin: 0; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
    .card-modern { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin-bottom: 20px; overflow: hidden; }
    .card-header-modern { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 20px; font-weight: 700; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .card-body-modern { padding: 20px; }
    .form-label-modern { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block; }
    .form-control-modern { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 15px; font-size: 0.95rem; font-weight: 600; color: #1e293b; transition: 0.2s; background: #fff; width: 100%; }
    .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); outline: none; }
    .input-group-modern { display: flex; align-items: center; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; transition: 0.2s; }
    .input-group-modern:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .input-group-text-modern { background: #f1f5f9; color: #64748b; padding: 10px 15px; font-weight: 700; font-size: 0.9rem; border-right: 1px solid #cbd5e1; }
    .input-group-modern .form-control-modern { border: none; border-radius: 0; }
    .input-group-modern .form-control-modern:focus { box-shadow: none; }
    .tipo-selector { display: flex; gap: 10px; background: #f1f5f9; padding: 5px; border-radius: 10px; }
    .tipo-option { flex: 1; text-align: center; padding: 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: 0.2s; color: #64748b; }
    .tipo-option.active { background: #fff; color: #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .img-upload-box { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: 0.2s; background: #f8fafc; }
    .img-upload-box:hover { border-color: #3b82f6; background: #eff6ff; }
    .switch-modern { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; cursor: pointer; }
    .switch-modern .form-check-input { margin: 0; transform: scale(1.3); cursor: pointer; }
    .switch-modern .form-check-label { font-weight: 600; color: #334155; cursor: pointer; margin: 0; font-size: 0.9rem; }
    .stat-badge { background: #e2e8f0; color: #475569; padding: 5px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; }
    .stat-badge.success { background: #dcfce7; color: #16a34a; }
    .stat-badge.warning { background: #fef08a; color: #ca8a04; }
    @media (max-width: 768px) { .form-header-modern { flex-direction: column; gap: 10px; align-items: stretch; padding: 10px; margin: -24px -15px 15px -15px; } .form-header-modern .btn { justify-content: center; } }
</style>

<form method="POST" enctype="multipart/form-data" id="formProducto">
    <input type="hidden" name="imagen_actual" value="<?php echo $producto['imagen_url'] ?? 'default.jpg'; ?>">
    <input type="hidden" name="tipo" id="tipo_final" value="<?php echo $producto['tipo'] ?? 'unitario'; ?>">

    <div class="form-header-modern">
        <div class="d-flex align-items-center gap-3">
            <a href="productos.php" class="btn btn-light border text-dark fw-bold rounded-pill px-3 py-2 shadow-sm"><i class="bi bi-arrow-left"></i> <span class="d-none d-md-inline">Volver</span></a>
            <h1 class="form-title-modern"><i class="bi bi-box-seam text-primary"></i> <?php echo $titulo; ?></h1>
        </div>
        <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 py-2 shadow-sm d-flex align-items-center gap-2 justify-content-center">
            <i class="bi bi-check2-circle fs-5"></i> <span>GUARDAR PRODUCTO</span>
        </button>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            
            <div class="card-modern">
                <div class="card-body-modern p-3">
                    <div class="tipo-selector">
                        <div class="tipo-option <?php echo (!isset($producto['tipo']) || $producto['tipo'] == 'unitario') ? 'active' : ''; ?>" id="btn-tipo-unitario" onclick="cambiarTipo('unitario')">
                            <i class="bi bi-box-seam me-1"></i> VENTA POR UNIDAD
                        </div>
                        <div class="tipo-option <?php echo (isset($producto['tipo']) && $producto['tipo'] == 'pesable') ? 'active' : ''; ?>" id="btn-tipo-pesable" onclick="cambiarTipo('pesable')">
                            <i class="bi bi-speedometer2 me-1"></i> VENTA POR KILO (BALANZA)
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern">Información Básica</div>
                <div class="card-body-modern">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label-modern">Nombre del Producto</label>
                            <input type="text" name="descripcion" class="form-control-modern" value="<?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?>" required placeholder="Ej: Gaseosa Cola 2.25L">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Categoría</label>
                            <select name="id_categoria" class="form-control-modern" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo (isset($producto['id_categoria']) && $producto['id_categoria'] == $cat['id']) ? 'selected' : ''; ?>><?php echo $cat['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="box-codigo">
                            <label class="form-label-modern">Código de Barras</label>
                            <div class="input-group-modern">
                                <span class="input-group-text-modern"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" name="codigo" class="form-control-modern" value="<?php echo $producto['codigo_barras'] ?? ''; ?>" placeholder="Escanear o tipear">
                            </div>
                        </div>
                        
                        <div class="col-md-6" id="box-plu" style="display:none;">
                            <label class="form-label-modern text-primary">Código PLU (Balanza)</label>
                            <input type="number" name="plu" id="plu_val" class="form-control-modern" style="border-color: #3b82f6;" value="<?php echo $producto['plu'] ?? ''; ?>" placeholder="Ej: 105">
                        </div>
                        <div class="col-md-6" id="box-tara" style="display:none;">
                            <label class="form-label-modern text-primary">Tara / Envase x Defecto</label>
                            <div class="input-group-modern" style="border-color: #3b82f6;">
                                <select class="form-control-modern border-0" style="width:60%;" onchange="document.getElementById('tara_val').value = this.value">
                                    <option value="0.000">Sin envase</option>
                                    <?php foreach($taras_lista as $t): ?>
                                        <option value="<?php echo $t['peso']; ?>"><?php echo $t['nombre']; ?> (<?php echo $t['peso']; ?> Kg)</option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" step="0.001" name="tara_defecto" id="tara_val" class="form-control-modern border-0 border-start bg-light" style="width:40%;" value="<?php echo $producto['tara_defecto'] ?? '0.000'; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <span>Precios y Márgenes</span>
                    <div class="d-flex gap-2">
                        <span class="stat-badge" id="badge_ganancia">$0.00</span>
                        <span class="stat-badge success" id="badge_margen">0% ROI</span>
                    </div>
                </div>
                <div class="card-body-modern">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label-modern" id="lbl-costo">Costo</label>
                            <div class="input-group-modern">
                                <span class="input-group-text-modern">$</span>
                                <input type="number" step="0.01" name="precio_costo" id="precio_costo" class="form-control-modern" value="<?php echo isset($producto['precio_costo']) ? (float)$producto['precio_costo'] : ''; ?>" required oninput="calc()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-modern" id="lbl-venta">Precio de Venta</label>
                            <div class="input-group-modern">
                                <span class="input-group-text-modern text-primary bg-primary bg-opacity-10 border-primary">$</span>
                                <input type="number" step="0.01" name="precio_venta" id="precio_venta" class="form-control-modern fw-bold text-primary border-primary" value="<?php echo isset($producto['precio_venta']) ? (float)$producto['precio_venta'] : ''; ?>" required oninput="calc()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-modern text-success" id="lbl-oferta">Precio Oferta (Opcional)</label>
                            <div class="input-group-modern border-success">
                                <span class="input-group-text-modern bg-success bg-opacity-10 text-success border-success">$</span>
                                <input type="number" step="0.01" name="precio_oferta" id="precio_oferta" class="form-control-modern text-success border-0" value="<?php echo isset($producto['precio_oferta']) ? (float)$producto['precio_oferta'] : ''; ?>" oninput="calc()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-lg-4">
            
            <div class="card-modern">
                <div class="card-header-modern">Fotografía</div>
                <div class="card-body-modern">
                    <label class="img-upload-box d-block w-100 m-0">
                        <img src="<?php echo $imgSrc; ?>" id="preview" style="max-height: 160px; max-width: 100%; object-fit: contain; margin-bottom: 10px; border-radius: 8px;">
                        <span class="d-block form-label-modern text-primary m-0"><i class="bi bi-camera"></i> Subir o Cambiar Imagen</span>
                        <input type="file" name="imagen_nueva" accept="image/*" hidden onchange="updPreview(this)">
                    </label>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <span>Inventario</span>
                    <span class="stat-badge" id="badge_stock">OK</span>
                </div>
                <div class="card-body-modern">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label-modern" id="lbl-stk">Stock Actual</label>
                            <input type="number" step="0.001" name="stock_actual" id="stock_actual" class="form-control-modern" value="<?php echo isset($producto['stock_actual']) ? (float)$producto['stock_actual'] : ''; ?>" required oninput="calc()">
                        </div>
                        <div class="col-6">
                            <label class="form-label-modern" id="lbl-min">Mínimo Alerta</label>
                            <input type="number" step="0.001" name="stock_minimo" id="stock_minimo" class="form-control-modern text-danger" style="background:#fef2f2; border-color:#fca5a5;" value="<?php echo isset($producto['stock_minimo']) ? (float)$producto['stock_minimo'] : 5; ?>" required oninput="calc()">
                        </div>
                        <div class="col-12">
                            <label class="form-label-modern">Proveedor Asociado</label>
                            <select name="id_proveedor" class="form-control-modern" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach($proveedores as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo (isset($producto['id_proveedor']) && $producto['id_proveedor'] == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['empresa']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header-modern">Propiedades Web</div>
                <div class="card-body-modern p-3">
                    <label class="switch-modern" for="switchDestacado">
                        <div class="d-flex align-items-center gap-2"><i class="bi bi-star-fill text-warning fs-5"></i> <span class="pt-1">Destacar en Tienda</span></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="switchDestacado" name="es_destacado_web" <?php if(!empty($producto['es_destacado_web'])) echo 'checked'; ?>></div>
                    </label>
                    <label class="switch-modern" for="switchVegano">
                        <div class="d-flex align-items-center gap-2"><i class="bi bi-leaf-fill text-success fs-5"></i> <span class="pt-1">Apto Vegano</span></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="switchVegano" name="es_vegano" <?php if(!empty($producto['es_vegano'])) echo 'checked'; ?>></div>
                    </label>
                    <label class="switch-modern m-0" for="switchCeliaco">
                        <div class="d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill text-info fs-5"></i> <span class="pt-1">Sin TACC</span></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="switchCeliaco" name="es_celiaco" <?php if(!empty($producto['es_celiaco'])) echo 'checked'; ?>></div>
                    </label>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
    function cambiarTipo(tipo) {
        document.getElementById('tipo_final').value = tipo;
        const isPesable = (tipo === 'pesable');
        
        document.getElementById('btn-tipo-unitario').className = 'tipo-option ' + (!isPesable ? 'active' : '');
        document.getElementById('btn-tipo-pesable').className = 'tipo-option ' + (isPesable ? 'active' : '');
        
        document.getElementById('box-codigo').style.display = isPesable ? 'none' : 'block';
        document.getElementById('box-plu').style.display = isPesable ? 'block' : 'none';
        document.getElementById('box-tara').style.display = isPesable ? 'block' : 'none';
        
        document.getElementById('lbl-costo').innerText = isPesable ? "Costo por Kilo" : "Costo";
        document.getElementById('lbl-venta').innerText = isPesable ? "Precio Venta por Kilo" : "Precio de Venta";
        document.getElementById('lbl-oferta').innerText = isPesable ? "Precio Oferta por Kilo" : "Precio Oferta (Opcional)";
        document.getElementById('lbl-stk').innerText = isPesable ? "Stock Actual (KG)" : "Stock Actual";
        document.getElementById('lbl-min').innerText = isPesable ? "Mínimo Alerta (KG)" : "Mínimo Alerta";

        if(isPesable) { document.getElementById('plu_val').setAttribute('required', 'required'); } 
        else { document.getElementById('plu_val').removeAttribute('required'); }
    }

    function updPreview(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => document.getElementById('preview').src = e.target.result;
            reader.readAsDataURL(input.files[0]);
        }
    }

    function calc() {
        const cost = parseFloat(document.getElementById('precio_costo').value) || 0;
        const vent = parseFloat(document.getElementById('precio_venta').value) || 0;
        const ofer = parseFloat(document.getElementById('precio_oferta').value) || 0;
        const stock = parseFloat(document.getElementById('stock_actual').value) || 0;
        const minim = parseFloat(document.getElementById('stock_minimo').value) || 0;

        const realV = (ofer > 0) ? ofer : vent;
        const profit = realV - cost;
        const margin = (cost > 0) ? (profit / cost) * 100 : 0;

        document.getElementById('badge_ganancia').innerText = `$${profit.toLocaleString('es-AR', {minimumFractionDigits: 2})}`;
        document.getElementById('badge_margen').innerText = `${margin.toFixed(1)}% ROI`;

        const badgeStock = document.getElementById('badge_stock');
        if(stock <= minim) { badgeStock.innerText = "REPONER"; badgeStock.className = "stat-badge warning"; } 
        else { badgeStock.innerText = "OK"; badgeStock.className = "stat-badge success"; }
    }

    document.addEventListener('DOMContentLoaded', () => {
        cambiarTipo('<?php echo $producto['tipo'] ?? 'unitario'; ?>');
        calc();
    });
</script>

<?php include 'includes/layout_footer.php'; ?>