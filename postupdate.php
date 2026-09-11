<?php
require_once("config.php");
require_once("uilang.php");
require_once("productimages.php");
require_once("artistshelper.php");

if(
    isset($_POST["editposttitle"]) &&
    isset($_POST["id"])
){
    $id = (int)$_POST["id"];

    $posttitle = mysqli_real_escape_string(
        $connection,
        isset($_POST["editposttitle"]) ? $_POST["editposttitle"] : ""
    );

    $artistid = isset($_POST["artistid"])
        ? artistResolveSelectedId($_POST["artistid"])
        : 0;

    $normalprice = mysqli_real_escape_string(
        $connection,
        isset($_POST["editnormalprice"]) ? $_POST["editnormalprice"] : "0"
    );

    $discountprice = mysqli_real_escape_string(
        $connection,
        isset($_POST["editdiscountprice"]) ? $_POST["editdiscountprice"] : "0"
    );

    $content = mysqli_real_escape_string(
        $connection,
        isset($_POST["editpostcontent"]) ? $_POST["editpostcontent"] : ""
    );

    $stock = isset($_POST["editstock"]) && (int)$_POST["editstock"] === 0
        ? 0
        : 1;

    $moreoptions = mysqli_real_escape_string(
        $connection,
        isset($_POST["moreoptions"]) ? $_POST["moreoptions"] : ""
    );

    if($id <= 0){
        echo "<div class='alert'>Id de producto inválido.</div>";
        exit;
    }

    if($posttitle === "" || $content === ""){
        echo "<div class='alert'>El título y el contenido son obligatorios.</div>";
        exit;
    }

    if($artistid <= 0){
        echo "<div class='alert'>El artista es obligatorio. Selecciona un artista válido antes de actualizar el CD.</div>";
        exit;
    }

    $sql = "SELECT * FROM $tableposts WHERE id = $id LIMIT 1";
    $result = mysqli_query($connection, $sql);

    if(!$result || mysqli_num_rows($result) === 0){
        echo "<div class='alert'>Producto no encontrado.</div>";
        exit;
    }

    $row = mysqli_fetch_assoc($result);

    /*
     * Category is legacy-only in this store.
     * All products are Reggaeton CDs, so Edit CD does not expose a category.
     * We preserve the existing catid silently to avoid changing old data.
     */
    $catid = isset($row["catid"])
        ? (int)$row["catid"]
        : 0;

    $oldpicture = $row["picture"];
    $oldmoreimages = $row["moreimages"];

    $newpicture = $oldpicture;
    $moreimages = $oldmoreimages;
    $uploadedPaths = [];

    if(
        isset($_POST["product_image_manager"]) &&
        $_POST["product_image_manager"] === "1"
    ){
        $imageResult = productImageBuildSlotsFromManagerRequest();

        if(!$imageResult["ok"]){
            productImageCleanupUploadedPaths($imageResult["uploaded"]);

            foreach($imageResult["errors"] as $error){
                echo "<div class='alert'>" . htmlspecialchars($error) . "</div>";
            }

            echo "<script>$(\"#upploadprogresstitle\").hide()</script>";
            exit;
        }

        $newpicture = productImagePictureValue($imageResult["slots"]);
        $moreimages = productImageSerializeMoreImages($imageResult["slots"]);
        $uploadedPaths = $imageResult["uploaded"];
    }else{
        // Legacy fallback in case JavaScript is unavailable.
        $moreimages = isset($_POST["moreimagesinput"])
            ? $_POST["moreimagesinput"]
            : $oldmoreimages;

        if(
            isset($_FILES["newpicture"]) &&
            isset($_FILES["newpicture"]["error"]) &&
            $_FILES["newpicture"]["error"] !== UPLOAD_ERR_NO_FILE
        ){
            $savedImage = productImageSaveUploadedFile($_FILES["newpicture"]);

            if(!$savedImage["ok"]){
                echo "<div class='alert'>" . htmlspecialchars($savedImage["error"]) . "</div>";
                exit;
            }

            if($savedImage["uploaded"]){
                $newpicture = basename($savedImage["path"]);
                $uploadedPaths[] = $savedImage["path"];
            }
        }
    }

    $newpicture = mysqli_real_escape_string($connection, $newpicture);
    $moreimages = mysqli_real_escape_string($connection, $moreimages);

    $updateSql =
        "UPDATE $tableposts SET " .
        "title = '$posttitle', " .
        "catid = $catid, " .
        "artistid = $artistid, " .
        "content = '$content', " .
        "picture = '$newpicture', " .
        "normalprice = '$normalprice', " .
        "discountprice = '$discountprice', " .
        "options = '$moreoptions', " .
        "moreimages = '$moreimages', " .
        "stock = $stock, " .
        "sold_at = " .
            ($stock === 0
                ? "COALESCE(sold_at, NOW())"
                : "NULL") . " " .
        "WHERE id = $id";

    $updateResult = mysqli_query($connection, $updateSql);

    if(!$updateResult){
        productImageCleanupUploadedPaths($uploadedPaths);
        echo "<div class='alert'>No se pudo actualizar el CD.</div>";
        exit;
    }

    /*
     * Never delete the previous image automatically here.
     * Another CD or another gallery position could still reference it.
     * Physical deletion stays under the Pictures administration section.
     */

    echo "<div class='alert'>" . uilang("Post successfully updated.") . "</div>";
}
?>
