<?php
session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/artistshelper.php";
require_once __DIR__ . "/artist-display-settings.php";

if(
    !isset($_SESSION["adminusername"]) ||
    !isset($_SESSION["adminpassword"]) ||
    $_SESSION["adminusername"] !== $username ||
    $_SESSION["adminpassword"] !== $password
){
    header("Location: " . $baseurl . "admin.php");
    exit;
}

$message = "";
$messageType = "info";
$collectionMinCds = 3;

if(isset($_POST["create_artist"])){
    $name = trim(isset($_POST["artist_name"]) ? $_POST["artist_name"] : "");
    $nickname = trim(isset($_POST["artist_nickname"]) ? $_POST["artist_nickname"] : "");
    $collectionExcluded = isset($_POST["artist_collection_excluded"]);

    if($name === ""){
        $message = "El nombre del artista es obligatorio.";
    }else if(artistFindByName($name) > 0){
        $message = "Ese artista ya existe.";
    }else{
        $artistId = artistCreate($name);

        if($artistId > 0){
            if(
                artistDisplaySaveSettings(
                    $artistId,
                    $nickname,
                    $collectionExcluded,
                    $cfg
                )
            ){
                $message = "Artista creado correctamente.";
                $messageType = "success";
            }else{
                $message = "El artista fue creado, pero no se pudo guardar su configuración de visualización.";
            }
        }else{
            $message = "No se pudo crear el artista.";
        }
    }
}

if(isset($_POST["update_artist"])){
    $artistId = isset($_POST["artist_id"])
        ? (int)$_POST["artist_id"]
        : 0;

    $name = trim(isset($_POST["artist_name"]) ? $_POST["artist_name"] : "");
    $nickname = trim(isset($_POST["artist_nickname"]) ? $_POST["artist_nickname"] : "");
    $collectionExcluded = isset($_POST["artist_collection_excluded"]);

    if($artistId <= 0 || !artistExists($artistId)){
        $message = "Artista no válido.";
    }else if($name === ""){
        $message = "El nombre del artista es obligatorio.";
    }else{
        $existingArtistId = artistFindByName($name);

        if($existingArtistId > 0 && $existingArtistId !== $artistId){
            $message = "Ya existe otro artista con ese nombre.";
        }else{
            $escapedName = mysqli_real_escape_string($connection, $name);

            $updated = mysqli_query(
                $connection,
                "UPDATE $tableartists SET name = '$escapedName' WHERE id = $artistId"
            );

            if($updated){
                /*
                 * Mantiene sincronizado el texto denormalizado "artist"
                 * si esa columna existe en instalaciones que ya usan el
                 * frontend nuevo.
                 */
                $artistColumn = mysqli_query(
                    $connection,
                    "SHOW COLUMNS FROM $tableposts LIKE 'artist'"
                );

                if($artistColumn && mysqli_num_rows($artistColumn) > 0){
                    mysqli_query(
                        $connection,
                        "UPDATE $tableposts SET artist = '$escapedName' WHERE artistid = $artistId"
                    );
                }

                if(
                    artistDisplaySaveSettings(
                        $artistId,
                        $nickname,
                        $collectionExcluded,
                        $cfg
                    )
                ){
                    $message = "Artista actualizado correctamente.";
                    $messageType = "success";
                }else{
                    $message = "El nombre fue actualizado, pero no se pudo guardar la configuración de visualización.";
                }
            }else{
                $message = "No se pudo actualizar el artista.";
            }
        }
    }
}

if(isset($_POST["delete_artist"])){
    $artistId = isset($_POST["artist_id"])
        ? (int)$_POST["artist_id"]
        : 0;

    if($artistId <= 0 || !artistExists($artistId)){
        $message = "Artista no válido.";
    }else{
        $cdCount = artistCdCount($artistId);

        if($cdCount > 0){
            $message = "No se puede eliminar este artista porque tiene " . $cdCount . " CD(s) asociado(s).";
        }else{
            $deleted = mysqli_query(
                $connection,
                "DELETE FROM $tableartists WHERE id = $artistId"
            );

            if($deleted){
                artistDisplayRemoveSettings($artistId, $cfg);
                $message = "Artista eliminado correctamente.";
                $messageType = "success";
            }else{
                $message = "No se pudo eliminar el artista.";
            }
        }
    }
}

if(isset($_POST["import_artists"])){
    $summary = artistImportFromExistingTitles();

    $message =
        "Importación terminada: " .
        $summary["importedArtists"] . " artista(s) creado(s), " .
        $summary["assignedCds"] . " CD(s) asociado(s) y " .
        $summary["skippedCds"] . " CD(s) omitido(s).";

    $messageType = "success";
}

$editArtist = null;

if(isset($_GET["edit"])){
    $editId = (int)$_GET["edit"];

    if($editId > 0){
        $editResult = mysqli_query(
            $connection,
            "SELECT id, name FROM $tableartists WHERE id = $editId LIMIT 1"
        );

        if($editResult && mysqli_num_rows($editResult) > 0){
            $editArtist = mysqli_fetch_assoc($editResult);
            $editArtist["nickname"] = artistDisplayNickname(
                $editId,
                $cfg
            );
            $editArtist["collection_excluded"] = artistDisplayCollectionExcluded(
                $editId,
                $cfg
            );
        }
    }
}

$artists = [];

$listResult = mysqli_query(
    $connection,
    "SELECT " .
    "a.id, a.name, a.slug, COUNT(p.id) AS cdcount, " .
    "SUM(CASE WHEN p.id IS NOT NULL AND p.active = 1 AND p.stock = 1 THEN 1 ELSE 0 END) AS available_count " .
    "FROM $tableartists a " .
    "LEFT JOIN $tableposts p ON p.artistid = a.id " .
    "GROUP BY a.id, a.name, a.slug " .
    "ORDER BY a.name ASC"
);

if($listResult){
    while($row = mysqli_fetch_assoc($listResult)){
        $row["nickname"] = artistDisplayNickname(
            (int)$row["id"],
            $cfg
        );
        $row["collection_excluded"] = artistDisplayCollectionExcluded(
            (int)$row["id"],
            $cfg
        );
        $row["available_count"] = (int)($row["available_count"] ?? 0);
        $artists[] = $row;
    }
}

$unassignedCount = 0;

$unassignedResult = mysqli_query(
    $connection,
    "SELECT COUNT(*) AS total FROM $tableposts WHERE artistid = 0"
);

if($unassignedResult){
    $unassignedRow = mysqli_fetch_assoc($unassignedResult);
    $unassignedCount = (int)$unassignedRow["total"];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Artistas | <?php echo htmlspecialchars($websitetitle, ENT_QUOTES, "UTF-8"); ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $baseurl; ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseurl; ?>admin-modern.css?v=10">
</head>
<body>
<div class="admin-page-shell">
    <?php
    $adminActiveSection = "artists";
    require __DIR__ . "/admin-menu.php";
    ?>

    <main class="admin-page-content">
        <div class="admin-toolbar">
            <div>
                <h1>Artistas</h1>
                <div class="admin-muted">
                    Crea, edita y administra los artistas asociados a los CDs. Las colecciones se activan automáticamente desde <?php echo $collectionMinCds; ?> CDs disponibles.
                </div>
            </div>
        </div>

        <?php if($message !== ""){ ?>
            <div class="admin-alert <?php echo $messageType === "success" ? "success" : "error"; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php } ?>

        <?php if($unassignedCount > 0){ ?>
            <section class="admin-form-card">
                <h2>CDs existentes sin artista</h2>
                <p class="admin-muted">
                    Hay <?php echo $unassignedCount; ?> CD(s) todavía sin asociación de artista.
                    La importación utiliza títulos con formato <strong>Artista - Álbum</strong>.
                </p>
                <form method="post">
                    <button
                        class="admin-modern-button secondary"
                        type="submit"
                        name="import_artists"
                        value="1"
                    >
                        <i class="fa fa-magic"></i>
                        Importar artistas desde títulos
                    </button>
                </form>
            </section>
        <?php } ?>

        <section class="admin-form-card">
            <?php if($editArtist !== null){ ?>
                <h2>Editar artista</h2>

                <form method="post">
                    <input
                        type="hidden"
                        name="artist_id"
                        value="<?php echo (int)$editArtist["id"]; ?>"
                    >

                    <label>Nombre</label>
                    <input
                        type="text"
                        name="artist_name"
                        value="<?php echo htmlspecialchars($editArtist["name"], ENT_QUOTES, "UTF-8"); ?>"
                        required
                        maxlength="150"
                    >

                    <label>Apodo / subtítulo de la colección</label>
                    <input
                        type="text"
                        name="artist_nickname"
                        value="<?php echo htmlspecialchars($editArtist["nickname"], ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="Ej. The Big Boss"
                        maxlength="120"
                    >
                    <div class="admin-muted">
                        Opcional. Se mostrará en la colección y en las secciones destacadas del artista.
                    </div>

                    <label style="display:flex;align-items:center;gap:10px;margin-top:18px;">
                        <input
                            type="checkbox"
                            name="artist_collection_excluded"
                            value="1"
                            <?php echo !empty($editArtist["collection_excluded"]) ? "checked" : ""; ?>
                            style="width:auto;"
                        >
                        Excluir de colecciones automáticas
                    </label>
                    <div class="admin-muted">
                        Aunque tenga <?php echo $collectionMinCds; ?> o más CDs disponibles, este artista no se destacará como colección automática.
                    </div>

                    <button
                        class="admin-modern-button"
                        type="submit"
                        name="update_artist"
                        value="1"
                    >
                        Guardar cambios
                    </button>

                    <a class="admin-modern-button secondary" href="artists.php">
                        Cancelar
                    </a>
                </form>
            <?php }else{ ?>
                <h2>Nuevo artista</h2>

                <form method="post">
                    <label>Nombre</label>
                    <input
                        type="text"
                        name="artist_name"
                        placeholder="Ej. Daddy Yankee"
                        required
                        maxlength="150"
                    >

                    <label>Apodo / subtítulo de la colección</label>
                    <input
                        type="text"
                        name="artist_nickname"
                        placeholder="Ej. The Big Boss"
                        maxlength="120"
                    >
                    <div class="admin-muted">
                        Opcional. Puedes dejarlo vacío y configurarlo después.
                    </div>

                    <label style="display:flex;align-items:center;gap:10px;margin-top:18px;">
                        <input
                            type="checkbox"
                            name="artist_collection_excluded"
                            value="1"
                            style="width:auto;"
                        >
                        Excluir de colecciones automáticas
                    </label>

                    <button
                        class="admin-modern-button"
                        type="submit"
                        name="create_artist"
                        value="1"
                    >
                        Agregar artista
                    </button>
                </form>
            <?php } ?>
        </section>

        <section class="admin-form-card">
            <h2>Listado</h2>

            <?php if(count($artists) === 0){ ?>
                <div class="admin-empty">
                    Todavía no hay artistas registrados.
                </div>
            <?php }else{ ?>
                <div class="admin-table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Artista</th>
                            <th>Apodo</th>
                            <th>URL</th>
                            <th style="width:90px;">CDs</th>
                            <th style="width:150px;">Colección</th>
                            <th style="width:260px;">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($artists as $artist){ ?>
                            <?php
                            $availableCount = (int)$artist["available_count"];
                            $collectionExcluded = !empty($artist["collection_excluded"]);
                            $collectionActive =
                                !$collectionExcluded &&
                                $availableCount >= $collectionMinCds;
                            ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($artist["name"], ENT_QUOTES, "UTF-8"); ?>
                                </td>
                                <td>
                                    <?php if($artist["nickname"] !== ""){ ?>
                                        <?php echo htmlspecialchars($artist["nickname"], ENT_QUOTES, "UTF-8"); ?>
                                    <?php }else{ ?>
                                        <span class="admin-muted">Sin apodo</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="admin-muted">
                                        /artista/<?php echo htmlspecialchars($artist["slug"], ENT_QUOTES, "UTF-8"); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-badge">
                                        <?php echo $availableCount; ?> disponibles
                                    </span>
                                </td>
                                <td>
                                    <?php if($collectionActive){ ?>
                                        <span class="admin-badge">ACTIVA</span>
                                    <?php }else if($collectionExcluded){ ?>
                                        <span class="admin-muted">Excluida</span>
                                    <?php }else{ ?>
                                        <span class="admin-muted">
                                            Faltan <?php echo max(0, $collectionMinCds - $availableCount); ?>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <a
                                        class="admin-modern-button secondary"
                                        href="artists.php?edit=<?php echo (int)$artist["id"]; ?>"
                                    >
                                        Editar
                                    </a>

                                    <?php if((int)$artist["cdcount"] === 0){ ?>
                                        <form
                                            method="post"
                                            style="display:inline;"
                                            onsubmit="return confirm('¿Eliminar este artista?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="artist_id"
                                                value="<?php echo (int)$artist["id"]; ?>"
                                            >
                                            <button
                                                class="admin-modern-button danger"
                                                type="submit"
                                                name="delete_artist"
                                                value="1"
                                            >
                                                Eliminar
                                            </button>
                                        </form>
                                    <?php }else{ ?>
                                        <span class="admin-muted">
                                            No se puede eliminar
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>
    </main>
</div>
</body>
</html>
