<?php
session_start();
require_once("config.php");
require_once("artistshelper.php");

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

if(isset($_POST["create_artist"])){
    $name = trim(isset($_POST["artist_name"]) ? $_POST["artist_name"] : "");

    if($name === ""){
        $message = "El nombre del artista es obligatorio.";
    }else if(artistFindByName($name) > 0){
        $message = "Ese artista ya existe.";
    }else{
        $artistId = artistCreate($name);

        if($artistId > 0){
            $message = "Artista creado correctamente.";
            $messageType = "success";
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
                $message = "Artista actualizado correctamente.";
                $messageType = "success";
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
        }
    }
}

$artists = [];
$listResult = mysqli_query(
    $connection,
    "SELECT a.id, a.name, COUNT(p.id) AS cdcount " .
    "FROM $tableartists a " .
    "LEFT JOIN $tableposts p ON p.artistid = a.id " .
    "GROUP BY a.id, a.name " .
    "ORDER BY a.name ASC"
);

if($listResult){
    while($row = mysqli_fetch_assoc($listResult)){
        $artists[] = $row;
    }
}

$unassignedResult = mysqli_query(
    $connection,
    "SELECT COUNT(*) AS total FROM $tableposts WHERE artistid = 0"
);

$unassignedCount = 0;
if($unassignedResult){
    $unassignedRow = mysqli_fetch_assoc($unassignedResult);
    $unassignedCount = (int)$unassignedRow["total"];
}

$currentlogo = "images/logo.png";
if(isset($logo) && $logo !== ""){
    $currentlogo = "pictures/" . $logo;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artistas | <?php echo htmlspecialchars($websitetitle) ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $baseurl ?>assets/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="admin-modern.css?v=3">
</head>
<body>
<div class="admin-page-shell">
    <aside class="admin-page-sidebar">
        <div class="admin-page-logo">
            <a href="<?php echo $baseurl ?>admin.php">
                <img src="<?php echo htmlspecialchars($currentlogo) ?>" alt="Logo">
            </a>
        </div>

        <nav class="admin-page-nav">
            <a href="<?php echo $baseurl ?>admin.php"><i class="fa fa-home"></i> Home</a>
            <a href="<?php echo $baseurl ?>admin.php?newpost"><i class="fa fa-plus"></i> Agregar CD</a>
            <a href="artists.php" class="active"><i class="fa fa-microphone"></i> Artistas</a>
            <a href="<?php echo $baseurl ?>admin.php?pictures"><i class="fa fa-image"></i> Pictures</a>
            <a href="<?php echo $baseurl ?>admin.php?categories"><i class="fa fa-tag"></i> Categories</a>
            <a href="<?php echo $baseurl ?>admin.php?orders"><i class="fa fa-file-text"></i> Orders</a>
            <a href="<?php echo $baseurl ?>admin.php?settings"><i class="fa fa-cogs"></i> Settings</a>
            <a href="<?php echo $baseurl ?>admin.php?logout"><i class="fa fa-sign-out"></i> Logout</a>
        </nav>
    </aside>

    <main class="admin-page-content">
        <div class="admin-toolbar">
            <div>
                <h1>Artistas</h1>
                <div class="admin-muted">
                    Crea, edita y administra los artistas asociados a los CDs.
                </div>
            </div>
        </div>

        <?php if($message !== ""){ ?>
            <div class="alert <?php echo $messageType === "success" ? "success" : "" ?>">
                <?php echo htmlspecialchars($message) ?>
            </div>
        <?php } ?>

        <?php if($unassignedCount > 0){ ?>
            <div class="admin-form-card">
                <h2>CDs existentes sin artista</h2>
                <p class="admin-muted">
                    Hay <?php echo $unassignedCount ?> CD(s) todavía sin asociación de artista.
                    Puedes importarlos automáticamente si el título tiene el formato
                    <strong>Artista - Álbum</strong>.
                </p>
                <form method="post">
                    <button class="admin-modern-button secondary" type="submit" name="import_artists" value="1">
                        <i class="fa fa-magic"></i> Importar artistas desde títulos
                    </button>
                </form>
            </div>
        <?php } ?>

        <div class="admin-form-card">
            <?php if($editArtist !== null){ ?>
                <h2>Editar artista</h2>
                <form method="post">
                    <input type="hidden" name="artist_id" value="<?php echo (int)$editArtist["id"] ?>">
                    <label>Nombre</label>
                    <input type="text" name="artist_name" value="<?php echo htmlspecialchars($editArtist["name"]) ?>" required maxlength="150">
                    <button class="admin-modern-button" type="submit" name="update_artist" value="1">
                        Guardar cambios
                    </button>
                    <a class="admin-modern-button secondary" href="artists.php">Cancelar</a>
                </form>
            <?php }else{ ?>
                <h2>Nuevo artista</h2>
                <form method="post">
                    <label>Nombre</label>
                    <input type="text" name="artist_name" placeholder="Ej. Daddy Yankee" required maxlength="150">
                    <button class="admin-modern-button" type="submit" name="create_artist" value="1">
                        Agregar artista
                    </button>
                </form>
            <?php } ?>
        </div>

        <div class="admin-form-card">
            <h2>Listado</h2>

            <?php if(count($artists) === 0){ ?>
                <p class="admin-muted">Todavía no hay artistas registrados.</p>
            <?php }else{ ?>
                <table>
                    <thead>
                    <tr>
                        <th>Artista</th>
                        <th style="width:110px;">CDs</th>
                        <th style="width:220px;">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($artists as $artist){ ?>
                        <tr>
                            <td><?php echo htmlspecialchars($artist["name"]) ?></td>
                            <td><span class="admin-badge"><?php echo (int)$artist["cdcount"] ?></span></td>
                            <td>
                                <a class="admin-modern-button secondary" href="artists.php?edit=<?php echo (int)$artist["id"] ?>">
                                    Editar
                                </a>

                                <?php if((int)$artist["cdcount"] === 0){ ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este artista?');">
                                        <input type="hidden" name="artist_id" value="<?php echo (int)$artist["id"] ?>">
                                        <button class="admin-modern-button danger" type="submit" name="delete_artist" value="1">
                                            Eliminar
                                        </button>
                                    </form>
                                <?php }else{ ?>
                                    <span class="admin-muted">No se puede eliminar</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    </main>
</div>
</body>
</html>
