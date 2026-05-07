<?php
// includes/layout_header.php - LOGO DINÁMICO + DISEÑO CAMISETA SUPLENTE
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

// 1. CONEXIÓN Y DATOS DE USUARIO
if(!isset($nombre_mostrar) || !isset($rol_usuario) || !isset($logo_url)) {
    $ruta_db = file_exists('includes/db.php') ? 'includes/db.php' : (file_exists('../includes/db.php') ? '../includes/db.php' : 'db.php');
    require_once $ruta_db;
    
    $id_user = $_SESSION['usuario_id'];
    
    // Datos Usuario
    $stmtUser = $conexion->prepare("SELECT nombre_completo, usuario, id_rol FROM usuarios WHERE id = ?");
    $stmtUser->execute([$id_user]);
    $datosUsuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $nombre_mostrar = !empty($datosUsuario['nombre_completo']) ? $datosUsuario['nombre_completo'] : $datosUsuario['usuario'];
    $rol_usuario = $datosUsuario['id_rol'] ?? 3;

    // 2. DATOS DE CONFIGURACIÓN (LOGO Y COLOR GLOBAL)
    $stmtConfig = $conexion->query("SELECT logo_url, nombre_negocio, color_barra_nav FROM configuracion WHERE id=1");
    $configData = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    $logo_url = $configData['logo_url'] ?? '';
    $nombre_negocio = $configData['nombre_negocio'] ?? 'EL 10 POS';
    $color_sistema = $configData['color_barra_nav'] ?? '#102A57'; // Color global
} else {
    // Fallback por si la variable no viene de un archivo anterior
    if (!isset($color_sistema)) {
        $stmtColor = $conexion->query("SELECT color_barra_nav FROM configuracion WHERE id=1");
        $colorData = $stmtColor->fetch(PDO::FETCH_ASSOC);
        $color_sistema = $colorData['color_barra_nav'] ?? '#102A57';
    }
}
$current_page = basename($_SERVER['PHP_SELF']);

// --- CARGA DE CANDADOS (PERMISOS) ---
$permisos = $_SESSION['permisos'] ?? [];
$es_admin = ($rol_usuario <= 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo htmlspecialchars($nombre_negocio); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/estilo_premium.css?v=<?php echo time(); ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --color-sistema: <?php echo $color_sistema; ?>;
            --azul-fuerte: var(--color-sistema);    /* Dinámico desde la BD */
            --celeste-claro: #e3f2fd;               /* Mantenemos el fondo de hover suave */
            --celeste-afa: var(--color-sistema);    /* Dinámico desde la BD */
            --blanco: #ffffff;
            --negro: #212529;
            --gris-fondo: #f8f9fa;
        }
        body { background-color: var(--gris-fondo); font-family: 'Roboto', sans-serif; padding-top: 0; padding-bottom: 0; }
        .font-cancha, h1, h2, h3, h4, .navbar-brand { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 0.5px; }

        /* NAVBAR */
        .navbar-10 { background-color: var(--blanco); border-bottom: 1px solid var(--celeste-afa); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        /* LOGO EN NAVBAR */
        .navbar-brand { font-size: 1.5rem; color: var(--azul-fuerte) !important; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .logo-navbar { height: 45px; width: auto; max-width: 150px; object-fit: contain; }

        /* LINKS MENÚ */
        .nav-link { font-family: 'Oswald', sans-serif; font-size: 1.05rem; color: #555 !important; padding: 8px 15px !important; transition: 0.2s; border-radius: 5px; }
        .nav-link:hover, .nav-link.active { color: var(--azul-fuerte) !important; background: var(--celeste-claro); }
        
        /* USUARIO */
        .user-badge { background: #333; color: white; padding: 6px 15px; border-radius: 50px; font-weight: 500; font-size: 0.9rem; cursor: pointer; transition: 0.2s; }
        .user-badge:hover { background: var(--celeste-afa); }

        /* WIDGETS ESTADÍSTICAS */
        .widget-stat {
            background: white; border: 1px solid #e1e4e8; border-left: 5px solid var(--celeste-afa);
            border-radius: 10px; padding: 15px; height: 100%; position: relative; overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03); transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none; display: block;
        }
        .widget-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(117, 170, 219, 0.2); border-color: var(--celeste-afa); }
        .stat-label { font-size: 0.75rem; text-transform: uppercase; color: #6c757d; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
        .stat-value { font-family: 'Oswald', sans-serif; font-size: 2rem; color: var(--azul-fuerte); font-weight: 500; line-height: 1; }
        .stat-icon { position: absolute; right: 10px; bottom: 5px; font-size: 2.5rem; color: var(--celeste-afa); opacity: 0.15; }

        /* ALERTA COLORES */
        .border-rojo { border-left-color: #dc3545 !important; } .text-rojo { color: #dc3545 !important; }
        .border-verde { border-left-color: #198754 !important; } .text-verde { color: #198754 !important; }
        .border-amarillo { border-left-color: #ffc107 !important; } .text-amarillo { color: #ffc107 !important; }

        /* ACCESOS DIRECTOS */
        .card-menu {
            background: white; border: 1px solid #eee; border-radius: 12px; padding: 20px 15px;
            height: 100%; display: flex; flex-direction: column; align-items: center; text-align: center;
            text-decoration: none; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: 0.2s;
        }
        .card-menu:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: var(--celeste-afa); }
        .icon-box-lg { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 12px; transition: 0.2s; }
        .menu-title { font-family: 'Oswald', sans-serif; font-weight: 600; font-size: 1.1rem; color: var(--azul-fuerte); text-transform: uppercase; }
        .menu-sub { font-size: 0.85rem; color: #777; font-weight: 500; }

        /* Colores Iconos */
        .icon-azul { background: #e3f2fd; color: #0d47a1; } .icon-verde { background: #e8f5e9; color: #1b5e20; }
        .icon-rojo { background: #ffebee; color: #b71c1c; } .icon-celeste { background: #e1f5fe; color: #0288d1; }
        .icon-amarillo { background: #fff8e1; color: #f57f17; } .icon-violeta { background: #f3e5f5; color: #7b1fa2; }
        /* Nuevo Sidebar Responsive */
        .topbar-movil { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background: var(--blanco); border-bottom: 1px solid var(--celeste-afa); position: sticky; top: 0; z-index: 1030; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-menu { color: var(--azul-fuerte); padding: 0; border: none; background: transparent; font-size: 2.2rem; }
        .logo-navbar-movil { height: 40px; width: auto; object-fit: contain; }
        .sidebar-kiosco { width: 280px !important; background: var(--blanco); border-right: 1px solid var(--celeste-afa); box-shadow: 2px 0 15px rgba(0,0,0,0.03); z-index: 1045 !important; }
        .sidebar-kiosco .offcanvas-header { padding: 20px; background: var(--gris-fondo); }
        .sidebar-kiosco .nav-link { font-family: 'Oswald', sans-serif; font-size: 1.05rem; color: #555 !important; padding: 12px 20px; border-radius: 8px; margin-bottom: 5px; transition: 0.2s; display: flex; align-items: center; }
        .sidebar-kiosco .nav-link i { font-size: 1.3rem; margin-right: 15px; width: 25px; text-align: center; }
        .sidebar-kiosco .nav-link:hover, .sidebar-kiosco .nav-link[aria-expanded="true"] { color: var(--azul-fuerte) !important; background: var(--celeste-claro); transform: translateX(5px); }
        .sidebar-kiosco .submenu-link { font-size: 0.95rem !important; padding: 8px 15px !important; color: #6c757d !important; font-family: 'Roboto', sans-serif !important; border-radius: 6px; }
        .sidebar-kiosco .submenu-link:hover { background: var(--blanco); color: var(--azul-fuerte) !important; transform: translateX(3px) !important; }
        .sidebar-kiosco .submenu-link i { font-size: 1.1rem !important; margin-right: 10px !important; }
        .sidebar-user { padding: 20px; border-top: 1px solid #eee; background: var(--gris-fondo); margin-top: auto; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; min-height: 100vh; transition: 0.3s; width: 100%; }
        @media (min-width: 1200px) {
            body { display: flex; }
            .sidebar-kiosco { position: fixed; top: 0; left: 0; height: 100vh; transform: none; visibility: visible; }
            .topbar-movil { display: none !important; }
            .main-wrapper { margin-left: 280px; width: calc(100% - 280px); }
        }
    </style>
</head>
<body>

<div class="d-xl-none topbar-movil">
    <a class="navbar-brand text-truncate m-0 p-0" href="dashboard.php" style="display:flex; align-items:center;">
        <?php if(!empty($logo_url)): ?>
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo" class="logo-navbar-movil">
        <?php endif; ?>
        <span class="font-cancha text-dark ms-2"><?php echo htmlspecialchars($nombre_negocio); ?></span>
    </a>
    <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu">
        <i class="bi bi-list"></i>
    </button>
</div>

<div class="offcanvas-xl offcanvas-start sidebar-kiosco" tabindex="-1" id="sidebarMenu">
    <div class="offcanvas-header border-bottom">
        <a class="navbar-brand text-truncate w-100 font-cancha m-0" href="dashboard.php" style="display:flex; align-items:center;">
            <?php if(!empty($logo_url)): ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo" class="logo-navbar" style="max-height:40px;">
            <?php endif; ?>
            <span class="ms-2"><?php echo htmlspecialchars($nombre_negocio); ?></span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0 custom-scrollbar">
        <ul class="nav flex-column w-100 p-3 gap-1 mb-auto">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page=='dashboard.php'?'active':''; ?>" href="dashboard.php">
                    <i class="bi bi-house-door"></i> INICIO
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#menuCaja" aria-expanded="false">
                    <div style="display:flex; align-items:center; width:100%;">
                        <i class="bi bi-cash-coin text-success"></i> <span class="ms-1">CAJA</span>
                        <i class="bi bi-chevron-down ms-auto" style="font-size:0.9rem; width:auto; margin:0;"></i>
                    </div>
                </a>
                <div class="collapse" id="menuCaja">
                    <ul class="nav flex-column ms-3 mb-2 mt-1 ps-2 border-start border-2 border-light">
                        <li><a class="nav-link submenu-link py-1" href="ventas.php"><i class="bi bi-cart4 text-success"></i> Nueva Venta</a></li>
                        <li><a class="nav-link submenu-link py-1" href="admin_pedidos_whatsapp.php"><i class="bi bi-whatsapp text-success"></i> Pedidos WhatsApp</a></li>
                        <li><a class="nav-link submenu-link py-1" href="cierre_caja.php"><i class="bi bi-calculator"></i> Cerrar Caja</a></li>
                        
                        <?php if($es_admin || in_array('ver_historial_cajas', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="historial_cajas.php"><i class="bi bi-clock-history text-primary"></i> Historial de Cajas</a></li>
                        <?php endif; ?>
                        
                        <?php if($es_admin || in_array('ver_historial_ventas', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="historial_ventas.php"><i class="bi bi-receipt text-info"></i> Historial Ventas</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_transferencias', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="ver_transferencias_ia.php"><i class="bi bi-bank text-warning"></i> Transferencias (IA)</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_devoluciones', $permisos)): ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="nav-link submenu-link py-1" href="devoluciones.php"><i class="bi bi-arrow-counterclockwise text-danger"></i> Devoluciones</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            
            <?php if($es_admin || in_array('ver_productos', $permisos) || in_array('ver_combos', $permisos) || in_array('ver_proveedores', $permisos) || in_array('ver_activos', $permisos)): ?>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#menuInve" aria-expanded="false">
                    <div style="display:flex; align-items:center; width:100%;">
                        <i class="bi bi-box-seam text-primary"></i> <span class="ms-1">INVENTARIO</span>
                        <i class="bi bi-chevron-down ms-auto" style="font-size:0.9rem; width:auto; margin:0;"></i>
                    </div>
                </a>
                <div class="collapse" id="menuInve">
                    <ul class="nav flex-column ms-3 mb-2 mt-1 ps-2 border-start border-2 border-light">
                        <?php if($es_admin || in_array('ver_productos', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="productos.php"><i class="bi bi-box-seam text-secondary"></i> Productos</a></li>
                        <li><a class="nav-link submenu-link py-1" href="gestionar_taras.php"><i class="bi bi-box-seam-fill text-secondary"></i> Administrar Taras</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_combos', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="combos.php"><i class="bi bi-stars text-warning"></i> Pack de Oferta</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('imprimir_etiquetas', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="carteleria.php" target="_blank"><i class="bi bi-upc-scan text-dark"></i> Etiquetas</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_proveedores', $permisos)): ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="nav-link submenu-link py-1" href="proveedores.php"><i class="bi bi-truck text-info"></i> Proveedores</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_activos', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="bienes_uso.php"><i class="bi bi-hdd-network text-success"></i> Activos / Bienes</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <?php if(($es_admin || in_array('ver_clientes', $permisos)) && ($configData['modulo_clientes'] ?? 1) == 1): ?>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#menuClub" aria-expanded="false">
                    <div style="display:flex; align-items:center; width:100%;">
                        <i class="bi bi-people-fill text-info"></i> <span class="ms-1">EL CLUB</span>
                        <i class="bi bi-chevron-down ms-auto" style="font-size:0.9rem; width:auto; margin:0;"></i>
                    </div>
                </a>
                <div class="collapse" id="menuClub">
                    <ul class="nav flex-column ms-3 mb-2 mt-1 ps-2 border-start border-2 border-light">
                        <?php if($es_admin || in_array('ver_clientes', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="clientes.php"><i class="bi bi-person-lines-fill text-primary"></i> Clientes</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_canje_puntos', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="canje_puntos.php"><i class="bi bi-gift-fill text-danger"></i> Canje de Puntos</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_encuestas', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="ver_encuestas.php"><i class="bi bi-chat-quote text-secondary"></i> Encuestas</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_premios', $permisos)): ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="nav-link submenu-link py-1" href="gestionar_premios.php"><i class="bi bi-trophy text-warning"></i> Configurar Premios</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('cartel_qr', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="cartel_qr.php" target="_blank"><i class="bi bi-qr-code text-dark"></i> Autoregistro (QR)</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <?php if($es_admin || in_array('ver_gastos', $permisos) || in_array('ver_mermas', $permisos) || in_array('ver_inflacion', $permisos)): ?>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#menuFinanzas" aria-expanded="false">
                    <div style="display:flex; align-items:center; width:100%;">
                        <i class="bi bi-cash-stack text-danger"></i> <span class="ms-1">FINANZAS</span>
                        <i class="bi bi-chevron-down ms-auto" style="font-size:0.9rem; width:auto; margin:0;"></i>
                    </div>
                </a>
                <div class="collapse" id="menuFinanzas">
                    <ul class="nav flex-column ms-3 mb-2 mt-1 ps-2 border-start border-2 border-light">
                        <?php if($es_admin || in_array('ver_gastos', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="gastos.php"><i class="bi bi-graph-down-arrow text-danger"></i> Gastos</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_mermas', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="mermas.php"><i class="bi bi-trash3 text-secondary"></i> Mermas</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_inflacion', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="precios_masivos.php"><i class="bi bi-graph-up-arrow text-primary"></i> Inflación (Precios)</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <?php if($es_admin || in_array('ver_cupones', $permisos) || in_array('ver_sorteos', $permisos) || in_array('revista_builder', $permisos)): ?>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#menuMkt" aria-expanded="false">
                    <div style="display:flex; align-items:center; width:100%;">
                        <i class="bi bi-megaphone-fill text-warning"></i> <span class="ms-1">MARKETING</span>
                        <i class="bi bi-chevron-down ms-auto" style="font-size:0.9rem; width:auto; margin:0;"></i>
                    </div>
                </a>
                <div class="collapse" id="menuMkt">
                    <ul class="nav flex-column ms-3 mb-2 mt-1 ps-2 border-start border-2 border-light">
                        <?php if($es_admin || in_array('ver_cupones', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="gestionar_cupones.php"><i class="bi bi-ticket-perforated text-success"></i> Cupones</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_sorteos', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="sorteos.php"><i class="bi bi-ticket-detailed-fill text-danger"></i> Sorteos y Rifas</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('revista_builder', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="admin_revista.php"><i class="bi bi-newspaper text-primary"></i> Gestor Revistas</a></li>
                        <li><a class="nav-link submenu-link py-1" href="revista_builder.php"><i class="bi bi-magic text-info"></i> Constructor Builder</a></li>
                        <?php endif; ?>

                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="nav-link submenu-link py-1 text-primary" href="tienda.php" target="_blank"><i class="bi bi-shop text-primary"></i> Tienda Online</a></li>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <?php if($es_admin || in_array('ver_reportes', $permisos) || in_array('ver_configuracion', $permisos) || in_array('ver_usuarios', $permisos) || in_array('ver_auditoria', $permisos)): ?>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#menuAdmin" aria-expanded="false">
                    <div style="display:flex; align-items:center; width:100%;">
                        <i class="bi bi-gear-fill text-secondary"></i> <span class="ms-1">ADMIN</span>
                        <i class="bi bi-chevron-down ms-auto" style="font-size:0.9rem; width:auto; margin:0;"></i>
                    </div>
                </a>
                <div class="collapse" id="menuAdmin">
                    <ul class="nav flex-column ms-3 mb-2 mt-1 ps-2 border-start border-2 border-light">
                        <?php if(($es_admin || in_array('ver_reportes', $permisos)) && ($configData['modulo_reportes'] ?? 1) == 1): ?>
                        <li><a class="nav-link submenu-link py-1" href="reportes.php"><i class="bi bi-bar-chart-fill text-primary"></i> Reportes</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_configuracion', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="configuracion.php"><i class="bi bi-sliders text-dark"></i> Configuración</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_usuarios', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="usuarios.php"><i class="bi bi-shield-lock text-info"></i> Usuarios y Roles</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('ver_auditoria', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="auditoria.php"><i class="bi bi-eye text-danger"></i> Auditoría</a></li>
                        <?php endif; ?>

                        <?php if($es_admin || in_array('restaurar_sistema', $permisos)): ?>
                        <li><a class="nav-link submenu-link py-1" href="restaurar_sistema.php"><i class="bi bi-clock-history text-secondary"></i> Restaurar Backups</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-user mt-auto">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-primary text-white me-3 d-flex justify-content-center align-items-center rounded-circle" style="width:40px;height:40px;font-size:1.2rem;">
                    <i class="bi bi-person"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark" style="line-height:1.2;font-size:0.95rem;"><?php echo htmlspecialchars(explode(' ', $nombre_mostrar)[0]); ?></div>
                    <small class="text-muted" style="font-size:0.8rem;">En línea</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="perfil.php" class="btn btn-sm btn-outline-secondary w-50" style="font-size:0.85rem;"><i class="bi bi-gear"></i> Perfil</a>
                <a href="logout.php" class="btn btn-sm btn-outline-danger w-50" style="font-size:0.85rem;"><i class="bi bi-power"></i> Salir</a>
            </div>
        </div>
    </div>
</div>

<div class="main-wrapper">
    <div class="container fade-in mt-4 mb-4">