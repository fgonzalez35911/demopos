<?php
// includes/componente_filtros.php
// PROPUESTA: Toolbar Minimalista con Filtros en Modal (SweetAlert2)
?>
<style>
    .minimal-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        padding: 8px 15px;
        border-radius: 50px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        margin-bottom: 20px;
        width: 100%;
    }
    .search-input-group {
        display: flex;
        align-items: center;
        flex-grow: 1;
        background: #f8fafc;
        border-radius: 50px;
        padding: 2px 15px;
        border: 1px solid transparent;
        transition: 0.2s;
    }
    .search-input-group:focus-within {
        background: #fff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .search-input-group i { color: #94a3b8; font-size: 1.1rem; }
    .search-input-group input {
        border: none;
        background: transparent;
        padding: 8px 10px;
        width: 100%;
        outline: none;
        font-weight: 500;
        color: #1e293b;
        font-size: 0.95rem;
    }
    .btn-filter-trigger {
        white-space: nowrap;
        border-radius: 50px;
        padding: 8px 18px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        transition: 0.2s;
    }
    
    /* Estilos para el interior del SweetAlert */
    .swal-filter-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        text-align: left;
        padding: 10px;
    }
    .swal-filter-item { display: flex; flex-direction: column; gap: 5px; }
    .swal-filter-item label { font-weight: 800; font-size: 0.75rem; text-transform: uppercase; color: #64748b; }
    .swal-filter-item input, .swal-filter-item select {
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-weight: 600;
        color: #1e293b;
    }

    @media (max-width: 768px) {
        .minimal-toolbar { padding: 5px 10px; gap: 5px; }
        .btn-filter-trigger span { display: none; } /* En móvil solo mostramos el ícono */
        .btn-filter-trigger { padding: 10px; }
        .swal-filter-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="minimal-toolbar">
    <form id="formFiltrosPrincipal" method="GET" class="d-flex w-100 align-items-center gap-2 m-0">
        <div class="search-input-group">
            <i class="bi bi-search"></i>
            <input type="text" name="buscar" id="inputBuscar" placeholder="¿Qué estás buscando?..." value="<?php echo htmlspecialchars($_GET['buscar'] ?? ''); ?>" autocomplete="off">
        </div>

        <input type="hidden" name="desde" id="hiddenDesde" value="<?php echo $desde; ?>">
        <input type="hidden" name="hasta" id="hiddenHasta" value="<?php echo $hasta; ?>">
        <?php if(!empty($filtros_extra)): foreach($filtros_extra as $f): ?>
            <input type="hidden" name="<?php echo $f['name']; ?>" id="hidden_<?php echo $f['name']; ?>" value="<?php echo $_GET[$f['name']] ?? ''; ?>">
        <?php endforeach; endif; ?>

        <button type="button" class="btn btn-light border btn-filter-trigger shadow-sm" onclick="abrirModalFiltros()">
            <i class="bi bi-sliders2 text-primary"></i> <span>FILTROS</span>
            <?php 
                $activos = 0;
                if(!empty($_GET['desde']) || !empty($_GET['id_categoria'])) $activos = "•"; 
                if($activos) echo "<span class='text-primary' style='margin-left:-5px'>$activos</span>";
            ?>
        </button>

        <button type="submit" class="btn btn-primary btn-filter-trigger shadow-sm">
            <i class="bi bi-arrow-right-short" style="font-size: 1.4rem;"></i>
        </button>
    </form>
</div>

<script>
function abrirModalFiltros() {
    const filtrosExtra = <?php echo json_encode($filtros_extra ?? []); ?>;
    
    // Construimos el HTML del modal dinámicamente
    let htmlContent = `
        <div class="swal-filter-grid">
            <div class="swal-filter-item">
                <label>Desde</label>
                <input type="date" id="swal_desde" value="${document.getElementById('hiddenDesde').value}">
            </div>
            <div class="swal-filter-item">
                <label>Hasta</label>
                <input type="date" id="swal_hasta" value="${document.getElementById('hiddenHasta').value}">
            </div>
    `;

    filtrosExtra.forEach(f => {
        let optionsHtml = `<option value="">Todos</option>`;
        const currentVal = document.getElementById('hidden_' + f.name).value;
        
        // f.options puede ser objeto o array
        for (const [val, text] of Object.entries(f.options)) {
            const selected = (String(val) === String(currentVal)) ? 'selected' : '';
            optionsHtml += `<option value="${val}" ${selected}>${text}</option>`;
        }

        htmlContent += `
            <div class="swal-filter-item" style="grid-column: span 2">
                <label>${f.label}</label>
                <select id="swal_${f.name}">${optionsHtml}</select>
            </div>
        `;
    });

    htmlContent += `</div>`;

    Swal.fire({
        title: 'AJUSTAR FILTROS',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: 'APLICAR FILTROS',
        cancelButtonText: 'CANCELAR',
        confirmButtonColor: '#3b82f6',
        background: '#ffffff',
        customClass: {
            title: 'font-cancha',
            confirmButton: 'rounded-pill px-4 fw-bold',
            cancelButton: 'rounded-pill px-4'
        },
        preConfirm: () => {
            // Pasamos los valores del modal a los inputs ocultos del form
            document.getElementById('hiddenDesde').value = document.getElementById('swal_desde').value;
            document.getElementById('hiddenHasta').value = document.getElementById('swal_hasta').value;
            
            filtrosExtra.forEach(f => {
                document.getElementById('hidden_' + f.name).value = document.getElementById('swal_' + f.name).value;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formFiltrosPrincipal').submit();
        }
    });
}

// Para que al apretar "Enter" en el buscador también envíe
document.getElementById('inputBuscar').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('formFiltrosPrincipal').submit();
    }
});
</script>