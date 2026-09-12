<?php
/*
 * Official Reggaeton El Real image watermark.
 *
 * The public product images are processed by GD before they are saved as WebP.
 * This helper overlays the official transparent PNG while preserving its aspect
 * ratio and alpha channel. Existing configuration fields remain compatible:
 * - imagewatermarkfontsize (1-5) controls logo scale.
 * - imagewatermarktextopacity (0-100) controls logo opacity.
 * - imagewatermarkmargin controls the outer margin.
 * - imagewatermarkposition controls placement.
 */

if(!function_exists("productImageOfficialWatermarkAssetPath")){
    function productImageOfficialWatermarkAssetPath(){
        return
            __DIR__ .
            DIRECTORY_SEPARATOR .
            "images" .
            DIRECTORY_SEPARATOR .
            "branding" .
            DIRECTORY_SEPARATOR .
            "originals" .
            DIRECTORY_SEPARATOR .
            "reggaeton-el-real-watermark.png";
    }
}

if(!function_exists("productImageOfficialWatermarkScalePercent")){
    function productImageOfficialWatermarkScalePercent($sizeLevel){
        $sizeLevel = min(
            5,
            max(
                1,
                (int)$sizeLevel
            )
        );

        $scaleByLevel = [
            1 => 16,
            2 => 22,
            3 => 28,
            4 => 34,
            5 => 40
        ];

        return $scaleByLevel[$sizeLevel];
    }
}

if(!function_exists("productImageOfficialWatermarkApplyOpacity")){
    function productImageOfficialWatermarkApplyOpacity(
        $watermark,
        $opacityPercent
    ){
        $opacityPercent = min(
            100,
            max(
                0,
                (int)$opacityPercent
            )
        );

        if($opacityPercent >= 100){
            return;
        }

        $factor = $opacityPercent / 100;
        $width = imagesx($watermark);
        $height = imagesy($watermark);
        $colors = [];

        imagealphablending(
            $watermark,
            false
        );
        imagesavealpha(
            $watermark,
            true
        );

        for($y = 0; $y < $height; $y++){
            for($x = 0; $x < $width; $x++){
                $rgba = imagecolorat(
                    $watermark,
                    $x,
                    $y
                );

                $alpha =
                    ($rgba >> 24) & 0x7F;

                if($alpha >= 127){
                    continue;
                }

                $red =
                    ($rgba >> 16) & 0xFF;
                $green =
                    ($rgba >> 8) & 0xFF;
                $blue =
                    $rgba & 0xFF;

                $sourceOpacity =
                    (127 - $alpha) / 127;

                $finalOpacity =
                    $sourceOpacity *
                    $factor;

                $finalAlpha =
                    127 -
                    (int)round(
                        127 *
                        $finalOpacity
                    );

                $finalAlpha = min(
                    127,
                    max(
                        0,
                        $finalAlpha
                    )
                );

                $colorKey =
                    $red .
                    ":" .
                    $green .
                    ":" .
                    $blue .
                    ":" .
                    $finalAlpha;

                if(!isset($colors[$colorKey])){
                    $colors[$colorKey] =
                        imagecolorallocatealpha(
                            $watermark,
                            $red,
                            $green,
                            $blue,
                            $finalAlpha
                        );
                }

                imagesetpixel(
                    $watermark,
                    $x,
                    $y,
                    $colors[$colorKey]
                );
            }
        }

        imagealphablending(
            $watermark,
            true
        );
    }
}

if(!function_exists("productImageApplyOfficialLogoWatermark")){
    function productImageApplyOfficialLogoWatermark($image){
        $assetPath =
            productImageOfficialWatermarkAssetPath();

        if(
            !is_file($assetPath) ||
            !function_exists("imagecreatefrompng")
        ){
            return;
        }

        $watermark =
            @imagecreatefrompng(
                $assetPath
            );

        if($watermark === false){
            return;
        }

        /*
         * Generated branding assets include transparent canvas space.
         * Crop it when GD supports automatic transparent trimming so the
         * configured size refers to the visible brand, not the empty canvas.
         */
        if(
            function_exists("imagecropauto") &&
            defined("IMG_CROP_TRANSPARENT")
        ){
            $cropped =
                @imagecropauto(
                    $watermark,
                    IMG_CROP_TRANSPARENT
                );

            if($cropped !== false){
                imagedestroy($watermark);
                $watermark = $cropped;
            }
        }

        $assetWidth = imagesx($watermark);
        $assetHeight = imagesy($watermark);
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        if(
            $assetWidth <= 0 ||
            $assetHeight <= 0 ||
            $imageWidth < 80 ||
            $imageHeight < 80
        ){
            imagedestroy($watermark);
            return;
        }

        $margin =
            productImageWatermarkMargin();

        $scalePercent =
            productImageOfficialWatermarkScalePercent(
                productImageWatermarkFontSize()
            );

        $availableWidth = max(
            1,
            $imageWidth -
            ($margin * 2)
        );

        $availableHeight = max(
            1,
            $imageHeight -
            ($margin * 2)
        );

        $targetWidth = max(
            1,
            (int)round(
                $imageWidth *
                ($scalePercent / 100)
            )
        );

        $targetWidth = min(
            $targetWidth,
            $availableWidth
        );

        $targetHeight = max(
            1,
            (int)round(
                $assetHeight *
                ($targetWidth / $assetWidth)
            )
        );

        if($targetHeight > $availableHeight){
            $heightScale =
                $availableHeight /
                $targetHeight;

            $targetWidth = max(
                1,
                (int)round(
                    $targetWidth *
                    $heightScale
                )
            );

            $targetHeight =
                $availableHeight;
        }

        $resized =
            imagecreatetruecolor(
                $targetWidth,
                $targetHeight
            );

        if($resized === false){
            imagedestroy($watermark);
            return;
        }

        imagealphablending(
            $resized,
            false
        );
        imagesavealpha(
            $resized,
            true
        );

        $transparent =
            imagecolorallocatealpha(
                $resized,
                0,
                0,
                0,
                127
            );

        imagefill(
            $resized,
            0,
            0,
            $transparent
        );

        $resampled =
            imagecopyresampled(
                $resized,
                $watermark,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $assetWidth,
                $assetHeight
            );

        imagedestroy($watermark);

        if(!$resampled){
            imagedestroy($resized);
            return;
        }

        productImageOfficialWatermarkApplyOpacity(
            $resized,
            productImageWatermarkTextOpacity()
        );

        [$x, $y] =
            productImageWatermarkCoordinates(
                $imageWidth,
                $imageHeight,
                $targetWidth,
                $targetHeight,
                $margin
            );

        imagealphablending(
            $image,
            true
        );

        imagecopy(
            $image,
            $resized,
            $x,
            $y,
            0,
            0,
            $targetWidth,
            $targetHeight
        );

        imagedestroy($resized);
    }
}
?>