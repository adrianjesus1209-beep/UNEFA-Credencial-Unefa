<?php
// INFORMACION DEL HEROE
if (!isset($heroe) || !is_array($heroe)) {
    echo '<div class="container py-5 text-center"><h2>Heroe no encontrado</h2><a href="index.php" class="btn btn-primary mt-3">Volver al inicio</a></div>';
    return;
}
?>
<div class="container py-5" style="max-width: 1000px;">
    <div class="row g-5">
        <div class="col-md-3">
            <div class="envoltorio-avatar-heroe mb-4">
                <img src="<?php echo URLROOT; ?>/assets/img/heroes/<?php echo $heroe['imagen']; ?>" class="avatar-heroe" alt="<?php echo $heroe['nombre']; ?>">
            </div>
            
            <div class="ps-1 mt-4">
                <div class="text-muted small text-uppercase fw-bold mb-1" style="letter-spacing: 1px;">Cronologia</div>
                <div class="text-dark small fw-bold text-uppercase" style="font-size: 11px;"><?php echo $heroe['nacimiento']; ?></div>
                <div class="text-dark small fw-bold text-uppercase" style="font-size: 11px;"><?php echo $heroe['fallecimiento']; ?></div>
            </div>
        </div>

        <div class="col-md-9 border-start ps-lg-5">
            <div class="mb-5">
                <h1 class="display-3 fw-bold text-dark mb-1"><?php echo $heroe['nombre']; ?></h1>
                <div class="fs-4 text-primary fw-medium mb-5"><?php echo $heroe['titulo']; ?></div>
                
                <div class="text-muted small text-uppercase fw-bold mb-3" style="letter-spacing: 2px;">Biografia</div>
                <p class="text-dark mb-5" style="font-size: 1.05rem; line-height: 1.8; text-align: justify; opacity: 0.9;">
                    <?php echo $heroe['biografia']; ?>
                </p>

                <div class="text-muted small text-uppercase fw-bold mb-4" style="letter-spacing: 2px;">Logros y Legado</div>
                <div class="row g-3">
                    <?php foreach($heroe['logros'] as $logro): ?>
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-start gap-2 text-dark small p-2">
                                <span class="text-primary fw-bold">—</span>
                                <span><?php echo $logro; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-5 mt-4 border-top">
                <a href="index.php" class="text-decoration-none text-dark small fw-bold text-uppercase" style="letter-spacing: 2px; opacity: 0.6;">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            </div>
        </div>
    </div>
</div>
