<?php
require_once __DIR__ . "/config.php";

header("X-Robots-Tag: noindex, nofollow, noarchive", true);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

adminAuthStartSession();

if(!adminAuthSessionIsValid()){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

function adminSalesStudioEsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Studio | <?php echo adminSalesStudioEsc($websitetitle); ?></title>
    <link rel="shortcut icon" href="<?php echo adminSalesStudioEsc($baseurl); ?>favicon.ico">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>admin-modern.css?v=16">
    <link rel="stylesheet" type="text/css" href="<?php echo adminSalesStudioEsc($baseurl); ?>admin-sales-studio.css?v=1">
</head>
<body>
<div class="admin-page-shell">
    <?php
    $adminActiveSection = "sales-studio";
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content sales-studio-page">
        <div class="admin-toolbar sales-studio-toolbar">
            <div>
                <div class="sales-studio-eyebrow">VENTAS · FASE 1/11</div>
                <h1>Sales Studio</h1>
                <div class="admin-muted">
                    Espacio privado para preparar publicaciones de venta a partir del catálogo de Reggaeton El Real.
                </div>
            </div>

            <a
                class="admin-modern-button secondary"
                href="<?php echo adminSalesStudioEsc($baseurl . "admin.php"); ?>"
            >
                Volver al inicio
            </a>
        </div>

        <section class="sales-studio-hero" aria-labelledby="sales-studio-marketplace-title">
            <div class="sales-studio-hero__content">
                <span class="sales-studio-badge">CANAL PRINCIPAL</span>
                <h2 id="sales-studio-marketplace-title">Facebook Marketplace</h2>
                <p>
                    Sales Studio se construirá primero para crear publicaciones optimizadas para Marketplace. En las siguientes fases incorporaremos selección de CDs, validaciones, plantillas y generación de imágenes.
                </p>

                <div class="sales-studio-template-preview" aria-label="Plantillas previstas">
                    <span>Clásico</span>
                    <span>Coleccionista</span>
                    <span>Lote</span>
                </div>
            </div>

            <div class="sales-studio-hero__status" aria-label="Estado de la fase 1">
                <div class="sales-studio-status-row">
                    <i class="fa fa-lock" aria-hidden="true"></i>
                    <div>
                        <strong>Solo administración</strong>
                        <span>Protegido por la sesión administrativa existente.</span>
                    </div>
                </div>

                <div class="sales-studio-status-row">
                    <i class="fa fa-mobile" aria-hidden="true"></i>
                    <div>
                        <strong>Responsive</strong>
                        <span>Base preparada para escritorio, tablet y móvil.</span>
                    </div>
                </div>

                <div class="sales-studio-status-row">
                    <i class="fa fa-cubes" aria-hidden="true"></i>
                    <div>
                        <strong>Módulo independiente</strong>
                        <span>No modifica catálogo, inventario, ventas ni analítica.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="sales-studio-grid" aria-label="Canales de Sales Studio">
            <article class="sales-studio-card sales-studio-card--primary">
                <div class="sales-studio-card__icon">
                    <i class="fa fa-facebook" aria-hidden="true"></i>
                </div>
                <div>
                    <span class="sales-studio-card__label">MARKETPLACE</span>
                    <h2>Crear publicación</h2>
                    <p>
                        El flujo de creación se habilitará por fases. La infraestructura visual y de acceso ya queda separada del resto del admin.
                    </p>
                </div>
                <button class="admin-modern-button" type="button" disabled>
                    Base preparada
                </button>
            </article>

            <article class="sales-studio-card sales-studio-card--future">
                <div class="sales-studio-card__icon">
                    <i class="fa fa-share-alt" aria-hidden="true"></i>
                </div>
                <div>
                    <span class="sales-studio-card__label">FASE FUTURA</span>
                    <h2>Otros canales</h2>
                    <p>
                        La arquitectura se mantendrá preparada para incorporar nuevos destinos sin mezclar su lógica con Marketplace.
                    </p>
                </div>
                <span class="sales-studio-coming-soon">No disponible todavía</span>
            </article>
        </section>

        <section class="sales-studio-foundation" aria-labelledby="sales-studio-foundation-title">
            <div>
                <span>01</span>
                <div>
                    <h2 id="sales-studio-foundation-title">Fundación del módulo</h2>
                    <p>
                        Esta fase establece únicamente el acceso, la navegación y la interfaz base. No crea publicaciones ni modifica información de los CDs.
                    </p>
                </div>
            </div>

            <strong>LISTA PARA VALIDACIÓN</strong>
        </section>
    </main>
</div>
</body>
</html>
