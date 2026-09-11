<?php
require_once("config.php");
require_once("uilang.php");
require_once("productimages.php");

if(
    isset($_POST["editposttitle"]) &&
    isset($_POST["id"])
){
    $id = (int)$_POST["id"];

    $posttitle = mysqli_real_escape_string(
        $connection,
        isset($_POST["editposttitle"]) ? $_POST["editposttitle"] : ""
    );

    $catid = isset($_POST["editcatid"])
        ? (int)$_POST["editcatid"]
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

    $moreoptions = mysqli_real_escape_string(
        $connection,
        isset($_POST["moreoptions"]) ? $_POST["moreoptions"] : ""
    );

    if($id <= 0){
        echo "<div class='alert'>Id de producto inválido.</div>";
        exit;
    }

    if($posttitle !== "" && $content !== ""){
        $sql = "SELECT * FROM $tableposts WHERE id = $id LIMIT 1";
        $result = mysqli_query(
            $connection,
            $sql
        );

        if(!$result || mysqli_num_rows($result) === 0){
            echo "<div class='alert'>Producto no encontrado.</div>";
            exit;
        }

        $row = mysqli_fetch_assoc($result);

        $oldpicture = $row["picture"];
        $oldmoreimages = $row["moreimages"];

        $newpicture = $oldpicture;
        $moreimages = $oldmoreimages;
        $uploadedPaths = [];

        if(
            isset($_POST["product_image_manager"]) &&
            $_POST["product_image_manager"] === "1"
        ){
            $imageResult = productImageBuildSlotsFromRequest(
                $oldpicture,
                $oldmoreimages
            );

            if(!$imageResult["ok"]){
                productImageCleanupUploadedPaths(
                    $imageResult["uploaded"]
                );

                foreach($imageResult["errors"] as $error){
                    echo "<div class='alert'>" . htmlspecialchars($error) . "</div>";
                }

                echo "<script>$(\"#upploadprogresstitle\").hide()</script>";
                exit;
            }

            $newpicture = productImagePictureValue(
                $imageResult["slots"]
            );

            $moreimages = productImageSerializeMoreImages(
                $imageResult["slots"]
            );

            $uploadedPaths = $imageResult["uploaded"];
        }else{
            $moreimages = isset($_POST["moreimagesinput"])
                ? $_POST["moreimagesinput"]
                : $oldmoreimages;

            if(
                isset($_FILES["newpicture"]) &&
                isset($_FILES["newpicture"]["error"]) &&
                $_FILES["newpicture"]["error"] !== UPLOAD_ERR_NO_FILE
            ){
                $savedImage = productImageSaveUploadedFile(
                    $_FILES["newpicture"]
                );

                if(!$savedImage["ok"]){
                    echo "<div class='alert'>" . htmlspecialchars($savedImage["error"]) . "</div>";
                    echo "<script>$(\"#upploadprogresstitle\").hide()</script>";
                    exit;
                }

                if($savedImage["uploaded"]){
                    $newpicture = basename(
                        $savedImage["path"]
                    );

                    $uploadedPaths[] = $savedImage["path"];
                }
            }
        }

        $newpicture = mysqli_real_escape_string(
            $connection,
            $newpicture
        );

        $moreimages = mysqli_real_escape_string(
            $connection,
            $moreimages
        );

        $updateSql =
            "UPDATE $tableposts SET " .
            "title = '$posttitle', " .
            "catid = $catid, " .
            "content = '$content', " .
            "picture = '$newpicture', " .
            "normalprice = '$normalprice', " .
            "discountprice = '$discountprice', " .
            "options = '$moreoptions', " .
            "moreimages = '$moreimages' " .
            "WHERE id = $id";

        $updateResult = mysqli_query(
            $connection,
            $updateSql
        );

        if(!$updateResult){
            productImageCleanupUploadedPaths(
                $uploadedPaths
            );

            echo "<div class='alert'>No se pudo actualizar el producto.</div>";
            exit;
        }

        /*
         * IMPORTANTE:
         * No eliminamos automáticamente las imágenes anteriores.
         *
         * Una imagen puede estar referenciada por otro producto o por
         * una posición distinta de la galería. El borrado físico queda
         * exclusivamente bajo el control de la sección Pictures.
         */

        echo "<div class='alert'>" .
             uilang("Post successfully updated.") .
             "</div>";
    }
}
?>