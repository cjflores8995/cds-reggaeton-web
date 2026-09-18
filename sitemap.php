<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/seo.php";

header(
    "Content-Type: application/xml; charset=UTF-8"
);

header(
    "Cache-Control: public, max-age=3600"
);

function sitemapXml($value){
    return htmlspecialchars(
        (string)$value,
        ENT_XML1 |
        ENT_COMPAT,
        "UTF-8"
    );
}

function sitemapProductImages($product){
    $paths = [];

    $picture =
        trim(
            (string)(
                $product["picture"] ??
                ""
            )
        );

    if($picture !== ""){
        $paths[] =
            seoAbsoluteImageUrl(
                $picture
            );
    }

    $moreImages =
        trim(
            (string)(
                $product["moreimages"] ??
                ""
            )
        );

    if($moreImages !== ""){
        foreach(
            explode(
                ",",
                $moreImages
            )
            as $path
        ){
            $path =
                trim(
                    (string)$path
                );

            if($path === ""){
                continue;
            }

            $paths[] =
                seoAbsoluteImageUrl(
                    $path
                );
        }
    }

    return array_values(
        array_unique(
            $paths
        )
    );
}

$products = [];

$productResult = mysqli_query(
    $connection,
    "SELECT
        slug,
        picture,
        moreimages
     FROM $tableposts
     WHERE active = 1
       AND stock = 1
     ORDER BY id DESC"
);

if($productResult){
    while(
        $product =
            mysqli_fetch_assoc(
                $productResult
            )
    ){
        $products[] =
            $product;
    }
}

$artists = [];

$artistResult = mysqli_query(
    $connection,
    "SELECT DISTINCT
        a.slug,
        a.name
     FROM $tableartists a
     INNER JOIN $tableposts p
        ON p.artistid = a.id
     WHERE p.active = 1
       AND p.stock = 1
     ORDER BY a.name ASC"
);

if($artistResult){
    while(
        $artist =
            mysqli_fetch_assoc(
                $artistResult
            )
    ){
        $artists[] =
            $artist;
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset
    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
>
    <url>
        <loc><?php echo sitemapXml(seoUrl()); ?></loc>
    </url>

    <url>
        <loc><?php echo sitemapXml(seoUrl("como-comprar")); ?></loc>
    </url>

    <url>
        <loc><?php echo sitemapXml(seoUrl("envios-y-devoluciones")); ?></loc>
    </url>

    <?php foreach($artists as $artist){ ?>
        <url>
            <loc><?php echo sitemapXml(
                seoArtistUrl(
                    (string)$artist["slug"]
                )
            ); ?></loc>
        </url>
    <?php } ?>

    <?php foreach($products as $product){ ?>
        <url>
            <loc><?php echo sitemapXml(
                seoProductUrl(
                    $product["slug"]
                )
            ); ?></loc>

            <?php foreach(sitemapProductImages($product) as $imageUrl){ ?>
                <image:image>
                    <image:loc><?php echo sitemapXml($imageUrl); ?></image:loc>
                </image:image>
            <?php } ?>
        </url>
    <?php } ?>
</urlset>
