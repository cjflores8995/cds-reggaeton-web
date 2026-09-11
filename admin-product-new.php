<?php

session_start();

require_once __DIR__ . "/config.php";

if (
    !isset($_SESSION["adminusername"]) ||
    !isset($_SESSION["adminpassword"]) ||
    $_SESSION["adminusername"] !== $username ||
    $_SESSION["adminpassword"] !== $password
) {
    header("Location: admin.php");
    exit;
}

$tableArtists = $tableprefix . "artists";
$tableProductImages = $tableprefix . "product_images";

$errors = [];

$success =
    isset($_GET["success"]) &&
    $_GET["success"] === "1";

$createdProductId =
    isset($_GET["id"])
        ? (int)$_GET["id"]
        : 0;

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function htmlValue(string $fieldName): string
{
    return htmlspecialchars(
        $_POST[$fieldName] ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}

function isSelected(
    string $fieldName,
    string $value,
    string $default = ""
): string {
    $currentValue =
        $_POST[$fieldName] ??
        $default;

    return $currentValue === $value
        ? "selected"
        : "";
}

/*
|--------------------------------------------------------------------------
| Load artists
|--------------------------------------------------------------------------
*/

$artists = [];

$artistResult = mysqli_query(
    $connection,
    "SELECT id, name
     FROM $tableArtists
     WHERE active = 1
     ORDER BY name ASC"
);

if (!$artistResult) {
    $errors[] =
        "No se pudo cargar el listado de artistas.";
} else {
    while (
        $artist =
            mysqli_fetch_assoc($artistResult)
    ) {
        $artists[] = $artist;
    }
}

/*
|--------------------------------------------------------------------------
| Save product
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $artistId =
        (int)($_POST["artist_id"] ?? 0);

    $album =
        trim($_POST["album"] ?? "");

    $releaseYearRaw =
        trim($_POST["release_year"] ?? "");

    $releaseYear =
        $releaseYearRaw === ""
            ? null
            : (int)$releaseYearRaw;

    $price =
        (float)($_POST["price"] ?? 0);

    $stock = 1;

    $cdCondition =
        trim(
            $_POST["cd_condition"] ??
            "Buen estado"
        );

    $caseCondition =
        trim(
            $_POST["case_condition"] ??
            "Buen estado"
        );

    $description =
        trim(
            $_POST["description"] ?? ""
        );

    $active =
        isset($_POST["active"])
            ? 1
            : 0;

    /*
    |--------------------------------------------------------------------------
    | Validate artist
    |--------------------------------------------------------------------------
    */

    $artistName = "";

    if ($artistId <= 0) {

        $errors[] =
            "Debes seleccionar un artista.";

    } else {

        $artistStatement =
            mysqli_prepare(
                $connection,
                "SELECT name
                 FROM $tableArtists
                 WHERE id = ?
                   AND active = 1
                 LIMIT 1"
            );

        if (!$artistStatement) {

            $errors[] =
                "No se pudo validar el artista.";

        } else {

            mysqli_stmt_bind_param(
                $artistStatement,
                "i",
                $artistId
            );

            mysqli_stmt_execute(
                $artistStatement
            );

            $artistResult =
                mysqli_stmt_get_result(
                    $artistStatement
                );

            $artistRow =
                mysqli_fetch_assoc(
                    $artistResult
                );

            mysqli_stmt_close(
                $artistStatement
            );

            if (!$artistRow) {

                $errors[] =
                    "El artista seleccionado no existe.";

            } else {

                $artistName =
                    $artistRow["name"];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate product fields
    |--------------------------------------------------------------------------
    */

    if ($album === "") {
        $errors[] =
            "El álbum es obligatorio.";
    }

    if ($price <= 0) {
        $errors[] =
            "El precio debe ser mayor a 0.";
    }

    if (
        $releaseYear !== null &&
        (
            $releaseYear < 1980 ||
            $releaseYear > 2100
        )
    ) {
        $errors[] =
            "El año no es válido.";
    }

    /*
    |--------------------------------------------------------------------------
    | Process optional images
    |--------------------------------------------------------------------------
    |
    | 1 = Portada tomada de Internet
    | 2 = Foto real de la portada
    | 3 = Foto real de la contraportada
    | 4 = Foto del CD
    | 5 = Interior / detalle adicional
    |
    */

    $uploadedImages = [];

    for (
        $position = 1;
        $position <= 5;
        $position++
    ) {

        $fieldName =
            "image_" . $position;

        if (
            !isset(
                $_FILES[$fieldName]
            )
        ) {
            continue;
        }

        $file =
            $_FILES[$fieldName];

        if (
            $file["error"] ===
            UPLOAD_ERR_NO_FILE
        ) {
            continue;
        }

        if (
            $file["error"] !==
            UPLOAD_ERR_OK
        ) {

            $errors[] =
                "Error al cargar la imagen "
                . $position
                . ".";

            continue;
        }

        if (
            $file["size"] >
            8 * 1024 * 1024
        ) {

            $errors[] =
                "La imagen "
                . $position
                . " supera el límite de 8 MB.";

            continue;
        }

        $uploadedImages[$position] =
            $file;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate MIME types
    |--------------------------------------------------------------------------
    */

    $imageInformation = [];

    if (count($errors) === 0) {

        $allowedMimeTypes = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );

        foreach (
            $uploadedImages
            as $position => $file
        ) {

            $mimeType =
                $finfo->file(
                    $file["tmp_name"]
                );

            if (
                !isset(
                    $allowedMimeTypes[
                        $mimeType
                    ]
                )
            ) {

                $errors[] =
                    "La imagen "
                    . $position
                    . " debe ser JPG, PNG o WEBP.";

                continue;
            }

            $imageInformation[
                $position
            ] = [
                "tmp_name" =>
                    $file["tmp_name"],

                "extension" =>
                    $allowedMimeTypes[
                        $mimeType
                    ]
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert product
    |--------------------------------------------------------------------------
    */

    if (count($errors) === 0) {

        $savedFiles = [];

        $productDirectory = null;

        mysqli_begin_transaction(
            $connection
        );

        try {

            /*
            |--------------------------------------------------------------------------
            | Legacy fields required by original template
            |--------------------------------------------------------------------------
            */

            $postId =
                bin2hex(
                    random_bytes(5)
                );

            $categoryId = 0;

            $title =
                $artistName
                . " - "
                . $album;

            $currentTime = date("Y-m-d H:i:s");

            /*
            |--------------------------------------------------------------------------
            | Insert CD
            |--------------------------------------------------------------------------
            */

            $insertSql =
                "INSERT INTO $tableposts (
                    postid,
                    catid,
                    normalprice,
                    discountprice,
                    title,
                    artist_id,
                    artist,
                    album,
                    release_year,
                    stock,
                    cd_condition,
                    case_condition,
                    active,
                    time,
                    options,
                    picture,
                    moreimages,
                    content
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    0,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    '',
                    '',
                    '',
                    ?
                )";

            $insertStatement =
                mysqli_prepare(
                    $connection,
                    $insertSql
                );

            if (
                !$insertStatement
            ) {
                throw new Exception(
                    "No se pudo preparar el registro del CD: "
                    . mysqli_error(
                        $connection
                    )
                );
            }

            mysqli_stmt_bind_param(
                $insertStatement,
                "sidsissiississ",
                $postId,
                $categoryId,
                $price,
                $title,
                $artistId,
                $artistName,
                $album,
                $releaseYear,
                $stock,
                $cdCondition,
                $caseCondition,
                $active,
                $currentTime,
                $description
            );

            if (
                !mysqli_stmt_execute(
                    $insertStatement
                )
            ) {

                throw new Exception(
                    "No se pudo guardar el CD: "
                    . mysqli_stmt_error(
                        $insertStatement
                    )
                );
            }

            $productId =
                mysqli_insert_id(
                    $connection
                );

            mysqli_stmt_close(
                $insertStatement
            );

            /*
            |--------------------------------------------------------------------------
            | Create product image directory
            |--------------------------------------------------------------------------
            */

            $legacyImages = [];

            if (
                count(
                    $imageInformation
                ) > 0
            ) {

                $productDirectory =
                    __DIR__
                    . "/pictures/products/"
                    . $productId;

                if (
                    !is_dir(
                        $productDirectory
                    )
                ) {

                    if (
                        !mkdir(
                            $productDirectory,
                            0755,
                            true
                        )
                    ) {

                        throw new Exception(
                            "No se pudo crear la carpeta de imágenes."
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Save images
                |--------------------------------------------------------------------------
                */

                foreach (
                    $imageInformation
                    as $position => $image
                ) {

                    $fileName =
                        str_pad(
                            (string)$position,
                            2,
                            "0",
                            STR_PAD_LEFT
                        )
                        . "."
                        . $image[
                            "extension"
                        ];

                    $absolutePath =
                        $productDirectory
                        . "/"
                        . $fileName;

                    if (
                        !move_uploaded_file(
                            $image[
                                "tmp_name"
                            ],
                            $absolutePath
                        )
                    ) {

                        throw new Exception(
                            "No se pudo guardar la imagen "
                            . $position
                            . "."
                        );
                    }

                    $savedFiles[] =
                        $absolutePath;

                    /*
                    |--------------------------------------------------------------------------
                    | Path for original template
                    |--------------------------------------------------------------------------
                    */

                    $legacyPath =
                        "products/"
                        . $productId
                        . "/"
                        . $fileName;

                    /*
                    |--------------------------------------------------------------------------
                    | Path stored in new image table
                    |--------------------------------------------------------------------------
                    */

                    $databasePath =
                        "pictures/"
                        . $legacyPath;

                    $legacyImages[
                        $position
                    ] =
                        $legacyPath;

                    /*
                    |--------------------------------------------------------------------------
                    | Insert image
                    |--------------------------------------------------------------------------
                    */

                    $imageInsert =
                        mysqli_prepare(
                            $connection,
                            "INSERT INTO $tableProductImages (
                                product_id,
                                image_path,
                                sort_order
                            )
                            VALUES (?, ?, ?)"
                        );

                    if (
                        !$imageInsert
                    ) {

                        throw new Exception(
                            "No se pudo preparar el registro de la imagen."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $imageInsert,
                        "isi",
                        $productId,
                        $databasePath,
                        $position
                    );

                    if (
                        !mysqli_stmt_execute(
                            $imageInsert
                        )
                    ) {

                        throw new Exception(
                            "No se pudo registrar la imagen "
                            . $position
                            . "."
                        );
                    }

                    mysqli_stmt_close(
                        $imageInsert
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Determine catalog main image
            |--------------------------------------------------------------------------
            |
            | If image 1 exists, it will naturally be first.
            |
            | If image 1 does not exist, the lowest available position
            | becomes the temporary main image.
            |
            */

            $mainPicture = "";

            $additionalImages = [];

            if (
                count(
                    $legacyImages
                ) > 0
            ) {

                ksort(
                    $legacyImages
                );

                $mainImagePosition =
                    array_key_first(
                        $legacyImages
                    );

                $mainPicture =
                    $legacyImages[
                        $mainImagePosition
                    ];

                foreach (
                    $legacyImages
                    as
                    $position =>
                    $legacyPath
                ) {

                    if (
                        $position !==
                        $mainImagePosition
                    ) {

                        $additionalImages[] =
                            $legacyPath;
                    }
                }
            }

            $moreImages =
                implode(
                    ",",
                    $additionalImages
                );

            /*
            |--------------------------------------------------------------------------
            | Update legacy picture fields
            |--------------------------------------------------------------------------
            */

            $updateStatement =
                mysqli_prepare(
                    $connection,
                    "UPDATE $tableposts
                     SET picture = ?,
                         moreimages = ?
                     WHERE id = ?"
                );

            if (
                !$updateStatement
            ) {

                throw new Exception(
                    "No se pudo preparar la actualización de imágenes."
                );
            }

            mysqli_stmt_bind_param(
                $updateStatement,
                "ssi",
                $mainPicture,
                $moreImages,
                $productId
            );

            if (
                !mysqli_stmt_execute(
                    $updateStatement
                )
            ) {

                throw new Exception(
                    "No se pudo actualizar la información de imágenes."
                );
            }

            mysqli_stmt_close(
                $updateStatement
            );

            /*
            |--------------------------------------------------------------------------
            | Commit transaction
            |--------------------------------------------------------------------------
            */

            mysqli_commit(
                $connection
            );

            header(
                "Location: admin-product-new.php"
                . "?success=1&id="
                . $productId
            );

            exit;

        } catch (Throwable $exception) {

            mysqli_rollback(
                $connection
            );

            /*
            |--------------------------------------------------------------------------
            | Delete files if DB transaction fails
            |--------------------------------------------------------------------------
            */

            foreach (
                $savedFiles
                as $savedFile
            ) {

                if (
                    file_exists(
                        $savedFile
                    )
                ) {
                    unlink(
                        $savedFile
                    );
                }
            }

            if (
                $productDirectory !== null &&
                is_dir(
                    $productDirectory
                )
            ) {

                @rmdir(
                    $productDirectory
                );
            }

            $errors[] =
                $exception->getMessage();
        }
    }
}

$formActive =
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    isset($_POST["active"]);

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Agregar CD | Tienda CDs Reggaeton
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f4f4;
            font-family: Arial, sans-serif;
            color: #222;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow:
                0 2px 12px
                rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 25px;
        }

        h2 {
            margin-top: 35px;
        }

        .top-links {
            margin-bottom: 25px;
        }

        .top-links a {
            margin-right: 20px;
            color: #222;
        }

        .field {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        select,
        textarea {

            width: 100%;

            padding: 11px;

            border:
                1px solid
                #cccccc;

            border-radius:
                5px;

            font-size:
                15px;
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        .row {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                18px;
        }

        .image-box {

            border:
                1px solid
                #dddddd;

            padding:
                15px;

            margin-bottom:
                12px;

            border-radius:
                6px;

            background:
                #fafafa;
        }

        .image-box strong {
            display: block;
            margin-bottom: 7px;
        }

        .image-description {

            font-size:
                13px;

            color:
                #666666;

            margin-bottom:
                10px;
        }

        .required {
            color: #c00000;
        }

        .optional {

            color:
                #777777;

            font-size:
                12px;

            font-weight:
                normal;
        }

        .alert-error {

            background:
                #ffe5e5;

            border:
                1px solid
                #d88;

            padding:
                15px;

            margin-bottom:
                20px;

            border-radius:
                6px;
        }

        .alert-success {

            background:
                #e6ffe9;

            border:
                1px solid
                #7abf82;

            padding:
                15px;

            margin-bottom:
                20px;

            border-radius:
                6px;
        }

        .button {

            width:
                100%;

            border:
                0;

            padding:
                14px;

            background:
                #222222;

            color:
                #ffffff;

            border-radius:
                6px;

            font-size:
                16px;

            cursor:
                pointer;
        }

        .button:hover {
            background: #000000;
        }

        .checkbox {

            width:
                auto;

            margin-right:
                8px;
        }

        .images-help {

            background:
                #f1f1f1;

            padding:
                15px;

            margin-bottom:
                20px;

            border-radius:
                6px;

            line-height:
                1.6;
        }

        @media (
            max-width: 650px
        ) {

            .container {

                margin:
                    0;

                border-radius:
                    0;
            }

            .row {

                grid-template-columns:
                    1fr;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="top-links">

        <a href="admin.php">
            ← Panel administrativo
        </a>

        <a
            href="index.php"
            target="_blank"
        >
            Ver tienda
        </a>

    </div>

    <h1>
        Agregar CD
    </h1>

    <?php if ($success): ?>

        <div class="alert-success">

            CD guardado correctamente.

            <?php
            if (
                $createdProductId > 0
            ):
            ?>

                ID del producto:

                <strong>
                    <?php
                    echo
                        $createdProductId;
                    ?>
                </strong>

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <?php
    if (
        count($errors) > 0
    ):
    ?>

        <div class="alert-error">

            <strong>
                Corrige lo siguiente:
            </strong>

            <ul>

                <?php
                foreach (
                    $errors
                    as $error
                ):
                ?>

                    <li>

                        <?php

                        echo
                            htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                "UTF-8"
                            );

                        ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <form
        method="post"
        enctype="multipart/form-data"
    >

        <div class="field">

            <label>

                Artista

                <span class="required">
                    *
                </span>

            </label>

            <select
                name="artist_id"
                required
            >

                <option value="">
                    Selecciona un artista
                </option>

                <?php
                foreach (
                    $artists
                    as $artist
                ):
                ?>

                    <option
                        value="<?php
                            echo
                                (int)$artist[
                                    "id"
                                ];
                        ?>"
                        <?php

                        echo
                            (
                                (int)(
                                    $_POST[
                                        "artist_id"
                                    ] ??
                                    0
                                ) ===
                                (int)$artist[
                                    "id"
                                ]
                            )
                                ? "selected"
                                : "";

                        ?>
                    >

                        <?php

                        echo
                            htmlspecialchars(
                                $artist[
                                    "name"
                                ],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                        ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="row">

            <div class="field">

                <label>

                    Álbum

                    <span class="required">
                        *
                    </span>

                </label>

                <input
                    type="text"
                    name="album"
                    maxlength="200"
                    value="<?php
                        echo
                            htmlValue(
                                "album"
                            );
                    ?>"
                    required
                >

            </div>

            <div class="field">

                <label>
                    Año
                </label>

                <input
                    type="number"
                    name="release_year"
                    min="1980"
                    max="2100"
                    value="<?php
                        echo
                            htmlValue(
                                "release_year"
                            );
                    ?>"
                >

            </div>

        </div>

        <div class="row">

            <div class="field">

                <label>

                    Precio USD

                    <span class="required">
                        *
                    </span>

                </label>

                <input
                    type="number"
                    name="price"
                    min="0.01"
                    step="0.01"
                    value="<?php
                        echo
                            htmlValue(
                                "price"
                            );
                    ?>"
                    required
                >

            </div>

        </div>

        <div class="row">

            <div class="field">

                <label>
                    Estado del CD
                </label>

                <select
                    name="cd_condition"
                >

                    <option
                        value="Nuevo / Sellado"
                        <?php
                        echo
                            isSelected(
                                "cd_condition",
                                "Nuevo / Sellado"
                            );
                        ?>
                    >
                        Nuevo / Sellado
                    </option>

                    <option
                        value="Como nuevo"
                        <?php
                        echo
                            isSelected(
                                "cd_condition",
                                "Como nuevo"
                            );
                        ?>
                    >
                        Como nuevo
                    </option>

                    <option
                        value="Muy buen estado"
                        <?php
                        echo
                            isSelected(
                                "cd_condition",
                                "Muy buen estado"
                            );
                        ?>
                    >
                        Muy buen estado
                    </option>

                    <option
                        value="Buen estado"
                        <?php
                        echo
                            isSelected(
                                "cd_condition",
                                "Buen estado",
                                "Buen estado"
                            );
                        ?>
                    >
                        Buen estado
                    </option>

                    <option
                        value="Estado aceptable"
                        <?php
                        echo
                            isSelected(
                                "cd_condition",
                                "Estado aceptable"
                            );
                        ?>
                    >
                        Estado aceptable
                    </option>

                </select>

            </div>

            <div class="field">

                <label>
                    Estado de la caja
                </label>

                <select
                    name="case_condition"
                >

                    <option
                        value="Nueva"
                        <?php
                        echo
                            isSelected(
                                "case_condition",
                                "Nueva"
                            );
                        ?>
                    >
                        Nueva
                    </option>

                    <option
                        value="Como nueva"
                        <?php
                        echo
                            isSelected(
                                "case_condition",
                                "Como nueva"
                            );
                        ?>
                    >
                        Como nueva
                    </option>

                    <option
                        value="Muy buen estado"
                        <?php
                        echo
                            isSelected(
                                "case_condition",
                                "Muy buen estado"
                            );
                        ?>
                    >
                        Muy buen estado
                    </option>

                    <option
                        value="Buen estado"
                        <?php
                        echo
                            isSelected(
                                "case_condition",
                                "Buen estado",
                                "Buen estado"
                            );
                        ?>
                    >
                        Buen estado
                    </option>

                    <option
                        value="Estado aceptable"
                        <?php
                        echo
                            isSelected(
                                "case_condition",
                                "Estado aceptable"
                            );
                        ?>
                    >
                        Estado aceptable
                    </option>

                </select>

            </div>

        </div>

        <div class="field">

            <label>
                Descripción
            </label>

            <textarea
                name="description"
                placeholder="Edición, observaciones, detalles del CD, contenido, etc."
            ><?php
                echo
                    htmlValue(
                        "description"
                    );
            ?></textarea>

        </div>

        <h2>
            Imágenes
        </h2>

        <div class="images-help">

            Todas las imágenes son opcionales.

            <br>

            Puedes crear primero el CD
            y agregar las fotografías después.

        </div>

        <div class="image-box">

            <strong>

                Imagen 1 —
                Portada de Internet

                <span class="optional">
                    (opcional)
                </span>

            </strong>

            <div class="image-description">

                Imagen limpia de la portada
                obtenida de Internet.

                Esta será la imagen principal
                del catálogo cuando exista.

            </div>

            <input
                type="file"
                name="image_1"
                accept="image/jpeg,image/png,image/webp"
            >

        </div>

        <div class="image-box">

            <strong>

                Imagen 2 —
                Portada real

                <span class="optional">
                    (opcional)
                </span>

            </strong>

            <div class="image-description">

                Fotografía real tomada
                al CD que estás vendiendo.

            </div>

            <input
                type="file"
                name="image_2"
                accept="image/jpeg,image/png,image/webp"
            >

        </div>

        <div class="image-box">

            <strong>

                Imagen 3 —
                Contraportada real

                <span class="optional">
                    (opcional)
                </span>

            </strong>

            <div class="image-description">

                Fotografía real de la
                parte posterior de la caja.

            </div>

            <input
                type="file"
                name="image_3"
                accept="image/jpeg,image/png,image/webp"
            >

        </div>

        <div class="image-box">

            <strong>

                Imagen 4 —
                CD

                <span class="optional">
                    (opcional)
                </span>

            </strong>

            <div class="image-description">

                Fotografía real del disco.

            </div>

            <input
                type="file"
                name="image_4"
                accept="image/jpeg,image/png,image/webp"
            >

        </div>

        <div class="image-box">

            <strong>

                Imagen 5 —
                Interior / detalle adicional

                <span class="optional">
                    (opcional)
                </span>

            </strong>

            <div class="image-description">

                Booklet, interior,
                insertos o cualquier
                detalle adicional.

            </div>

            <input
                type="file"
                name="image_5"
                accept="image/jpeg,image/png,image/webp"
            >

        </div>

        <div class="field">

            <label>

                <input
                    class="checkbox"
                    type="checkbox"
                    name="active"
                    <?php
                    echo
                        $formActive
                            ? "checked"
                            : "";
                    ?>
                >

                Producto activo y visible

            </label>

        </div>

        <button
            type="submit"
            class="button"
        >
            Guardar CD
        </button>

    </form>

</div>

</body>

</html>