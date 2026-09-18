<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/seo.php";
require_once __DIR__ . "/product-gtin.php";

function merchantXml($value){
    return htmlspecialchars(
        (string)$value,
        ENT_XML1 | ENT_COMPAT,
        "UTF-8"
    );
}

function merchantLimitText(
    $value,
    $maximumLength
){
    $value = seoPlainText(
        $value
    );

    if($maximumLength <= 0){
        return "";
    }

    if(function_exists("mb_strlen")){
        if(
            mb_strlen(
                $value,
                "UTF-8"
            ) <=
            $maximumLength
        ){
            return $value;
        }

        return rtrim(
            mb_substr(
                $value,
                0,
                $maximumLength,
                "UTF-8"
            )
        );
    }

    if(strlen($value) <= $maximumLength){
        return $value;
    }

    return rtrim(
        substr(
            $value,
            0,
            $maximumLength
        )
    );
}

function merchantProductTitle($product){
    $artist = trim(
        (string)(
            $product["artist_name"] ??
            $product["artist"] ??
            ""
        )
    );

    $album = trim(
        (string)(
            $product["album"] ??
            ""
        )
    );

    $title = trim(
        (string)(
            $product["title"] ??
            ""
        )
    );

    if(
        $artist !== "" &&
        $album !== ""
    ){
        $title =
            $artist .
            " - " .
            $album .
            " - CD físico";
    }elseif($title !== ""){
        $title .= " - CD físico";
    }else{
        $title = "CD físico de reggaetón";
    }

    return merchantLimitText(
        $title,
        150
    );
}

function merchantProductDescription($product){
    $artist = trim(
        (string)(
            $product["artist_name"] ??
            $product["artist"] ??
            ""
        )
    );

    $album = trim(
        (string)(
            $product["album"] ??
            ""
        )
    );

    $year = trim(
        (string)(
            $product["release_year"] ??
            ""
        )
    );

    $cdCondition = trim(
        (string)(
            $product["cd_condition"] ??
            ""
        )
    );

    $caseCondition = trim(
        (string)(
            $product["case_condition"] ??
            ""
        )
    );

    $parts = [];

    if($artist !== ""){
        $parts[] =
            "Artista: " .
            $artist . ".";
    }

    if($album !== ""){
        $parts[] =
            "Álbum: " .
            $album . ".";
    }

    if($year !== ""){
        $parts[] =
            "Año: " .
            $year . ".";
    }

    if($cdCondition !== ""){
        $parts[] =
            "Estado del CD: " .
            $cdCondition . ".";
    }

    if($caseCondition !== ""){
        $parts[] =
            "Estado de la caja: " .
            $caseCondition . ".";
    }

    $content = seoPlainText(
        $product["content"] ??
        ""
    );

    if($content !== ""){
        $parts[] = $content;
    }

    if(count($parts) === 0){
        $parts[] =
            "CD físico de reggaetón.";
    }

    return merchantLimitText(
        implode(
            " ",
            $parts
        ),
        5000
    );
}

function merchantProductCondition($condition){
    return
        seoProductConditionUrl(
            $condition
        ) ===
        "https://schema.org/NewCondition"
            ? "new"
            : "used";
}

header(
    "Content-Type: application/rss+xml; charset=UTF-8"
);
header(
    "X-Robots-Tag: noindex, nofollow"
);
header(
    "Cache-Control: public, max-age=900"
);

$products = [];

$sql = "
    SELECT
        p.*,
        a.name AS artist_name
    FROM $tableposts p
    LEFT JOIN $tableartists a
        ON a.id = p.artistid
    WHERE p.active = 1
      AND p.stock = 1
      AND p.normalprice > 0
      AND NULLIF(TRIM(p.slug), '') IS NOT NULL
      AND NULLIF(TRIM(p.picture), '') IS NOT NULL
    ORDER BY p.id DESC
";

$result = mysqli_query(
    $connection,
    $sql
);

if($result){
    while(
        $row =
            mysqli_fetch_assoc(
                $result
            )
    ){
        $products[] = $row;
    }
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<rss
    xmlns:g="http://base.google.com/ns/1.0"
    version="2.0"
>
<channel>
    <title>Reggaeton El Real - CDs físicos de reggaetón</title>
    <link><?php echo merchantXml(seoUrl()); ?></link>
    <description>Catálogo de CDs físicos de reggaetón disponibles para compra en Ecuador.</description>
    <lastBuildDate><?php echo merchantXml(gmdate(DATE_RSS)); ?></lastBuildDate>

<?php foreach($products as $product){ ?>
    <?php
        $merchantId = trim(
            (string)(
                $product["postid"] ??
                ""
            )
        );

        if($merchantId === ""){
            $merchantId =
                "cd-" .
                (int)(
                    $product["id"] ??
                    0
                );
        }

        $merchantTitle =
            merchantProductTitle(
                $product
            );

        $merchantDescription =
            merchantProductDescription(
                $product
            );

        $merchantUrl =
            seoProductUrl(
                $product["slug"]
            );

        $merchantImage =
            seoAbsoluteImageUrl(
                $product["picture"]
            );

        $merchantCondition =
            merchantProductCondition(
                $product["cd_condition"] ??
                ""
            );

        $merchantPrice =
            number_format(
                (float)$product["normalprice"],
                2,
                ".",
                ""
            ) .
            " USD";

        $merchantGtinResult =
            productGtinNormalize(
                $product["gtin"] ??
                ""
            );

        $merchantGtin =
            $merchantGtinResult["ok"]
                ? $merchantGtinResult["gtin"]
                : "";
    ?>
    <item>
        <g:id><?php echo merchantXml($merchantId); ?></g:id>
        <g:title><?php echo merchantXml($merchantTitle); ?></g:title>
        <g:description><?php echo merchantXml($merchantDescription); ?></g:description>
        <g:link><?php echo merchantXml($merchantUrl); ?></g:link>
        <g:image_link><?php echo merchantXml($merchantImage); ?></g:image_link>
        <g:availability>in stock</g:availability>
        <g:price><?php echo merchantXml($merchantPrice); ?></g:price>
        <g:condition><?php echo merchantXml($merchantCondition); ?></g:condition>
        <?php if($merchantGtin !== ""){ ?>
            <g:gtin><?php echo merchantXml($merchantGtin); ?></g:gtin>
        <?php } ?>
        <g:product_type>Música &gt; CDs &gt; Reggaetón</g:product_type>
    </item>
<?php } ?>
</channel>
</rss>
