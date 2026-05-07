<?php
// includes/componente_banner.php
// NUEVA PROPUESTA: Estilo Dashboard Moderno (SaaS) Limpio y Elegante
?>
<style>
    /* CABECERA MODERNA */
    .page-header-modern { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; }
    .ph-title-wrap { display: flex; flex-direction: column; gap: 4px; }
    .ph-title { font-family: 'Oswald', sans-serif; font-size: 1.8rem; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.2; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
    .ph-subtitle { font-size: 0.95rem; color: #64748b; margin: 0; font-weight: 500; }
    .ph-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
    
    /* TARJETAS DE ESTADÍSTICAS */
    .ph-stats-row { display: flex; gap: 15px; margin-bottom: 25px; overflow-x: auto; padding-bottom: 8px; -webkit-overflow-scrolling: touch; }
    .ph-stats-row::-webkit-scrollbar { height: 6px; }
    .ph-stats-row::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .ph-stats-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    
    .ph-stat-card { background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid var(--azul-fuerte, #102A57); border-radius: 10px; padding: 15px 20px; display: flex; align-items: center; gap: 15px; min-width: 220px; flex: 1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
    .ph-stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
    .ph-stat-icon { font-size: 1.6rem; color: var(--azul-fuerte, #102A57); background: #f8fafc; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
    .ph-stat-info { display: flex; flex-direction: column; }
    .ph-stat-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
    .ph-stat-value { font-family: 'Oswald', sans-serif; font-size: 1.4rem; font-weight: 700; color: #0f172a; line-height: 1.1; }
    
    @media (max-width: 768px) {
        .page-header-modern { flex-direction: column; align-items: flex-start; padding-bottom: 10px; margin-bottom: 15px; }
        .ph-title { font-size: 1.5rem; }
        .ph-actions { width: 100%; justify-content: flex-start; }
        .ph-actions .btn { flex-grow: 1; text-align: center; justify-content: center; }
        .ph-stat-card { min-width: 180px; padding: 12px 15px; }
    }
</style>

<div class="page-header-modern">
    <div class="ph-title-wrap">
        <h1 class="ph-title">
            <i class="bi <?php echo $icono_bg; ?> text-primary"></i> 
            <?php echo $titulo; ?>
        </h1>
        <p class="ph-subtitle"><?php echo $subtitulo; ?></p>
    </div>
    
    <?php if(!empty($botones)): ?>
    <div class="ph-actions">
        <?php foreach($botones as $btn): ?>
            <a href="<?php echo $btn['link']; ?>" target="<?php echo $btn['target'] ?? '_self'; ?>" class="<?php echo $btn['class']; ?> fw-bold d-flex align-items-center rounded-3 shadow-sm" style="padding: 8px 16px;">
                <?php if(!empty($btn['icono'])): ?><i class="bi <?php echo $btn['icono']; ?> me-2"></i><?php endif; ?>
                <?php echo $btn['texto']; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if(!empty($widgets)): ?>
<div class="ph-stats-row">
    <?php foreach($widgets as $w): ?>
        <div class="ph-stat-card">
            <div class="ph-stat-icon">
                <i class="bi <?php echo $w['icono']; ?>"></i>
            </div>
            <div class="ph-stat-info">
                <span class="ph-stat-label"><?php echo $w['label']; ?></span>
                <span class="ph-stat-value"><?php echo $w['valor']; ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// Mantengo intacto tu JS para el cambio entre Lista y Grilla
document.addEventListener("DOMContentLoaded", function() {
    const wrapperFiltros = document.getElementById('wrapperFiltros');
    const btnToggleFiltros = document.querySelector('.btn-toggle-filters');
    if (!wrapperFiltros || document.getElementById('btnGridDesk')) return; 
    
    const pageName = window.location.pathname.split("/").pop().split("?")[0];
    const userId = <?php echo isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : '0'; ?>;
    const storageKey = `vista_lista_${userId}_${pageName}`;
    const prefGuardada = localStorage.getItem(storageKey);
    const iniciarEnLista = (prefGuardada === 'true');

    const switchDesktop = `
        <div class="d-none d-md-flex align-items-center bg-white border rounded-pill p-1 shadow-sm ms-auto" style="min-width: max-content; height: 38px;">
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check btn-check-grid" name="btnradioVistaDesk" id="btnGridDesk" autocomplete="off" ${!iniciarEnLista ? 'checked' : ''}>
                <label class="btn btn-outline-primary border-0 rounded-pill btn-sm px-3 fw-bold m-0 d-flex align-items-center" for="btnGridDesk"><i class="bi bi-grid-fill me-1"></i> Tarjetas</label>
                <input type="radio" class="btn-check btn-check-list" name="btnradioVistaDesk" id="btnListDesk" autocomplete="off" ${iniciarEnLista ? 'checked' : ''}>
                <label class="btn btn-outline-primary border-0 rounded-pill btn-sm px-3 fw-bold m-0 d-flex align-items-center" for="btnListDesk"><i class="bi bi-list-ul me-1"></i> Lista</label>
            </div>
        </div>
    `;
    wrapperFiltros.insertAdjacentHTML('beforeend', switchDesktop);
    
    if (btnToggleFiltros && btnToggleFiltros.parentElement) {
        const switchMobile = `
            <div class="d-flex d-md-none align-items-center bg-white border rounded-pill p-1 shadow-sm ms-2" style="flex-shrink: 0; height: 38px;">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check btn-check-grid" name="btnradioVistaMob" id="btnGridMob" autocomplete="off" ${!iniciarEnLista ? 'checked' : ''}>
                    <label class="btn btn-outline-primary border-0 rounded-pill btn-sm px-3 fw-bold m-0 d-flex align-items-center" for="btnGridMob"><i class="bi bi-grid-fill"></i></label>
                    <input type="radio" class="btn-check btn-check-list" name="btnradioVistaMob" id="btnListMob" autocomplete="off" ${iniciarEnLista ? 'checked' : ''}>
                    <label class="btn btn-outline-primary border-0 rounded-pill btn-sm px-3 fw-bold m-0 d-flex align-items-center" for="btnListMob"><i class="bi bi-list-ul"></i></label>
                </div>
            </div>
        `;
        btnToggleFiltros.parentElement.insertAdjacentHTML('beforeend', switchMobile);
    }

    function actualizarVista(esLista) {
        localStorage.setItem(storageKey, esLista);
        document.querySelectorAll('.btn-check-list').forEach(b => b.checked = esLista);
        document.querySelectorAll('.btn-check-grid').forEach(b => b.checked = !esLista);
        const gridBox = document.getElementById('gridProductos') || document.querySelector('.row.g-4:not(.no-grid)');
        const listaBox = document.getElementById('listaCategorias') || document.getElementById('vistaListaGenerica');
        if (gridBox && listaBox) {
            if (esLista) { gridBox.classList.add('d-none'); listaBox.classList.remove('d-none'); }
            else { gridBox.classList.remove('d-none'); listaBox.classList.add('d-none'); }
        }
    }
    document.querySelectorAll('.btn-check-grid').forEach(btn => btn.addEventListener('change', () => actualizarVista(false)));
    document.querySelectorAll('.btn-check-list').forEach(btn => btn.addEventListener('change', () => actualizarVista(true)));
    setTimeout(() => { actualizarVista(iniciarEnLista); }, 50);
});
</script>