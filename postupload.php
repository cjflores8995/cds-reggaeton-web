<?php
require_once("config.php");
require_once("uilang.php");
require_once("productimages.php");

if(isset($_POST["newposttitle"])){
    $newposttitle = mysqli_real_escape_string(
        $connection,
        isset($_POST["newposttitle"]) ? $_POST["newposttitle"] : ""
    );

    $newpostcontent = mysqli_real_escape_string(
        $connection,
        isset($_POST["newpostcontent"]) ? $_POST["newpostcontent"] : ""
    );

    $catid = isset($_POST["catid"])
        ? (int)$_POST["catid"]
        : 0;

    $normalprice = isset($_POST["newpostnormalprice"])
        ? trim((string)$_POST["newpostnormalprice"])
        : "";

    if($normalprice === ""){
        $normalprice = 0;
    }

    $discountprice = isset($_POST["newpostdiscountprice"])
        ? trim((string)$_POST["newpostdiscountprice"])
        : "";

    if($discountprice === ""){
        $discountprice = 0;
    }

    $normalprice = mysqli_real_escape_string(
        $connection,
        $normalprice
    );

    $discountprice = mysqli_real_escape_string(
        $connection,
        $discountprice
    );

    $moreoptions = mysqli_real_escape_string(
        $connection,
        isset($_POST["moreoptions"]) ? $_POST["moreoptions"] : ""
    );

    $currenttime = round(microtime(true) * 1000);

    if($newposttitle !== "" && $newpostcontent !== ""){
        $postid = substr(
            str_shuffle(str_repeat("abcdefghijklmnopqrstuvwxyz", 5)),
            0,
            10
        );

        $newpicture = "";
        $moreimages = "";
        $uploadedPaths = [];

        if(
            isset($_POST["product_image_manager"]) &&
            $_POST["product_image_manager"] === "1"
        ){
            $imageResult = productImageBuildSlotsFromRequest();

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
                : "";

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

        $sql = "INSERT INTO $tableposts " .
               "(postid, catid, title, content, picture, time, normalprice, discountprice, options, moreimages) " .
               "VALUES " .
               "('$postid', $catid, '$newposttitle', '$newpostcontent', '$newpicture', '$currenttime', '$normalprice', '$discountprice', '$moreoptions', '$moreimages')";

        $insertResult = mysqli_query(
            $connection,
            $sql
        );

        if(!$insertResult){
            productImageCleanupUploadedPaths(
                $uploadedPaths
            );

            echo "<div class='alert'>No se pudo guardar el producto.</div>";
            echo "<script>$(\"#upploadprogresstitle\").hide()</script>";
            exit;
        }

        ?>
        <h3><?php echo uilang("Congratulation!") ?></h3>
        <p>
            <?php echo uilang("New post has been published. Click") ?>
            <a
                class="textlink"
                href="<?php echo $baseurl ?>"
                target="_blank"
            >
                <?php echo uilang("here") ?>
            </a>
            <?php echo uilang("to view it") ?>.
        </p>
        <?php
    }else{
        ?>
        <h3><?php echo uilang("Oh no...") ?></h3>
        <p>
            <?php echo uilang("You did not submit your post correctly. Click") ?>
            <a
                class="textlink"
                href="<?php echo $baseurl ?>admin.php?newpost"
            >
                <?php echo uilang("here") ?>
            </a>
            <?php echo uilang("to try again") ?>.
        </p>
        <script>$("#upploadprogresstitle").hide()</script>
        <?php
    }
}
?>