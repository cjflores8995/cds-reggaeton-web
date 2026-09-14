<?php

if(!function_exists("adminSalesQuoteIdentifier")){
    function adminSalesQuoteIdentifier($value){
        return "`" . str_replace("`", "", (string)$value) . "`";
    }
}

if(!function_exists("adminSalesTable")){
    function adminSalesTable(){
        global $tableprefix;

        return adminSalesQuoteIdentifier(
            (string)($tableprefix ?? "") . "inventory_sales"
        );
    }
}

if(!function_exists("adminSalesEscapedText")){
    function adminSalesEscapedText($connection, $value){
        return mysqli_real_escape_string(
            $connection,
            trim((string)$value)
        );
    }
}

if(!function_exists("adminSalesAlbumName")){
    function adminSalesAlbumName($row){
        $album = trim((string)($row["album"] ?? ""));

        if($album !== ""){
            return $album;
        }

        $title = trim((string)($row["title"] ?? ""));
        $artist = trim((string)($row["artist_name"] ?? $row["artist"] ?? ""));

        if($artist !== ""){
            $prefix = $artist . " - ";

            if(stripos($title, $prefix) === 0){
                return trim(substr($title, strlen($prefix)));
            }
        }

        return $title;
    }
}

if(!function_exists("adminSalesEnsureSchema")){
    function adminSalesEnsureSchema($connection){
        $table = adminSalesTable();

        $sql =
            "CREATE TABLE IF NOT EXISTS $table (" .
            "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," .
            "product_id INT UNSIGNED NOT NULL," .
            "product_postid VARCHAR(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''," .
            "artist_name VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''," .
            "album_name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''," .
            "listed_price DECIMAL(10,2) NOT NULL DEFAULT 0.00," .
            "sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00," .
            "cost_basis DECIMAL(10,2) NOT NULL DEFAULT 0.00," .
            "sold_at DATETIME NOT NULL," .
            "status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'completed'," .
            "source VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'admin'," .
            "reverted_at DATETIME NULL," .
            "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP," .
            "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP," .
            "PRIMARY KEY (id)," .
            "KEY idx_inventory_sales_product_status (product_id, status)," .
            "KEY idx_inventory_sales_status_sold (status, sold_at)," .
            "KEY idx_inventory_sales_sold (sold_at)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool)mysqli_query($connection, $sql);
    }
}

if(!function_exists("adminSalesSyncLegacyInventory")){
    function adminSalesSyncLegacyInventory($connection){
        global $tableposts, $tableartists;

        $salesTable = adminSalesTable();

        $restoreSyncSql =
            "UPDATE $salesTable s " .
            "INNER JOIN $tableposts p ON p.id = s.product_id " .
            "SET s.status = 'reverted', " .
            "s.reverted_at = COALESCE(s.reverted_at, NOW()) " .
            "WHERE s.status = 'completed' " .
            "AND COALESCE(p.stock, 1) = 1";

        if(!mysqli_query($connection, $restoreSyncSql)){
            return false;
        }

        $soldResult = mysqli_query(
            $connection,
            "SELECT p.id, p.postid, p.artist, p.album, p.title, " .
            "p.normalprice, p.sold_at, a.name AS artist_name " .
            "FROM $tableposts p " .
            "LEFT JOIN $tableartists a ON a.id = p.artistid " .
            "WHERE COALESCE(p.stock, 1) = 0 " .
            "AND NOT EXISTS (" .
                "SELECT 1 FROM $salesTable s " .
                "WHERE s.product_id = p.id " .
                "AND s.status = 'completed'" .
            ")"
        );

        if(!$soldResult){
            return false;
        }

        while($row = mysqli_fetch_assoc($soldResult)){
            $productId = (int)$row["id"];
            $postId = adminSalesEscapedText(
                $connection,
                $row["postid"] ?? ""
            );
            $artist = trim((string)($row["artist_name"] ?? ""));

            if($artist === ""){
                $artist = trim((string)($row["artist"] ?? ""));
            }

            $album = adminSalesAlbumName([
                "album" => $row["album"] ?? "",
                "title" => $row["title"] ?? "",
                "artist_name" => $artist
            ]);

            $artistEscaped = adminSalesEscapedText($connection, $artist);
            $albumEscaped = adminSalesEscapedText($connection, $album);
            $listedPrice = max(0, (float)($row["normalprice"] ?? 0));
            $salePrice = $listedPrice;

            $soldAt = trim((string)($row["sold_at"] ?? ""));
            $soldAtSql = $soldAt === ""
                ? "NOW()"
                : "'" . adminSalesEscapedText($connection, $soldAt) . "'";

            $insertSql =
                "INSERT INTO $salesTable (" .
                    "product_id, product_postid, artist_name, album_name, " .
                    "listed_price, sale_price, cost_basis, sold_at, status, source" .
                ") VALUES (" .
                    $productId . ", " .
                    "'$postId', " .
                    "'$artistEscaped', " .
                    "'$albumEscaped', " .
                    number_format($listedPrice, 2, ".", "") . ", " .
                    number_format($salePrice, 2, ".", "") . ", " .
                    "0.00, " .
                    $soldAtSql . ", " .
                    "'completed', 'legacy_backfill'" .
                ")";

            if(!mysqli_query($connection, $insertSql)){
                mysqli_free_result($soldResult);
                return false;
            }
        }

        mysqli_free_result($soldResult);
        return true;
    }
}

if(!function_exists("adminSalesEnsureReady")){
    function adminSalesEnsureReady($connection){
        return
            adminSalesEnsureSchema($connection) &&
            adminSalesSyncLegacyInventory($connection);
    }
}

if(!function_exists("adminSalesPeriod")){
    function adminSalesPeriod($value){
        $value = strtolower(trim((string)$value));

        return in_array(
            $value,
            ["today", "7d", "30d", "year", "all"],
            true
        )
            ? $value
            : "all";
    }
}

if(!function_exists("adminSalesPeriodCondition")){
    function adminSalesPeriodCondition($period, $column = "sold_at"){
        $period = adminSalesPeriod($period);
        $column = preg_match('/^[A-Za-z0-9_.]+$/', (string)$column) === 1
            ? (string)$column
            : "sold_at";

        if($period === "today"){
            return "DATE($column) = CURDATE()";
        }

        if($period === "7d"){
            return "$column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }

        if($period === "30d"){
            return "$column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        if($period === "year"){
            return "YEAR($column) = YEAR(CURDATE())";
        }

        return "1 = 1";
    }
}

if(!function_exists("adminSalesSummary")){
    function adminSalesSummary($connection, $period = "all"){
        global $tableposts;

        $period = adminSalesPeriod($period);
        $salesTable = adminSalesTable();
        $condition = adminSalesPeriodCondition($period, "sold_at");

        $inventory = [
            "available_count" => 0,
            "inventory_value" => 0.0
        ];

        $inventoryResult = mysqli_query(
            $connection,
            "SELECT COUNT(*) AS available_count, " .
            "COALESCE(SUM(normalprice), 0) AS inventory_value " .
            "FROM $tableposts WHERE COALESCE(stock, 1) = 1"
        );

        if($inventoryResult){
            $row = mysqli_fetch_assoc($inventoryResult);
            $inventory["available_count"] = (int)($row["available_count"] ?? 0);
            $inventory["inventory_value"] = (float)($row["inventory_value"] ?? 0);
            mysqli_free_result($inventoryResult);
        }

        $sales = [
            "sold_count" => 0,
            "revenue" => 0.0,
            "profit" => 0.0,
            "average_sale" => 0.0
        ];

        $salesResult = mysqli_query(
            $connection,
            "SELECT COUNT(*) AS sold_count, " .
            "COALESCE(SUM(sale_price), 0) AS revenue, " .
            "COALESCE(SUM(sale_price - cost_basis), 0) AS profit, " .
            "COALESCE(AVG(sale_price), 0) AS average_sale " .
            "FROM $salesTable " .
            "WHERE status = 'completed' AND $condition"
        );

        if($salesResult){
            $row = mysqli_fetch_assoc($salesResult);
            $sales["sold_count"] = (int)($row["sold_count"] ?? 0);
            $sales["revenue"] = (float)($row["revenue"] ?? 0);
            $sales["profit"] = (float)($row["profit"] ?? 0);
            $sales["average_sale"] = (float)($row["average_sale"] ?? 0);
            mysqli_free_result($salesResult);
        }

        return array_merge($inventory, $sales, ["period" => $period]);
    }
}

if(!function_exists("adminSalesAvailableProducts")){
    function adminSalesAvailableProducts($connection){
        global $tableposts, $tableartists;

        $rows = [];
        $result = mysqli_query(
            $connection,
            "SELECT p.id, p.postid, p.title, p.album, p.artist, " .
            "p.normalprice, p.time, a.name AS artist_name " .
            "FROM $tableposts p " .
            "LEFT JOIN $tableartists a ON a.id = p.artistid " .
            "WHERE COALESCE(p.stock, 1) = 1 " .
            "ORDER BY p.id DESC"
        );

        if(!$result){
            return $rows;
        }

        while($row = mysqli_fetch_assoc($result)){
            if(trim((string)($row["artist_name"] ?? "")) === ""){
                $row["artist_name"] = trim((string)($row["artist"] ?? ""));
            }

            $row["album_name"] = adminSalesAlbumName($row);
            $rows[] = $row;
        }

        mysqli_free_result($result);
        return $rows;
    }
}

if(!function_exists("adminSalesHistory")){
    function adminSalesHistory($connection, $period = "all", $limit = 200){
        $salesTable = adminSalesTable();
        $period = adminSalesPeriod($period);
        $condition = adminSalesPeriodCondition($period, "sold_at");
        $limit = max(1, min(500, (int)$limit));
        $rows = [];

        $result = mysqli_query(
            $connection,
            "SELECT id, product_id, product_postid, artist_name, album_name, " .
            "listed_price, sale_price, cost_basis, sold_at, status, source, reverted_at " .
            "FROM $salesTable " .
            "WHERE $condition " .
            "ORDER BY sold_at DESC, id DESC " .
            "LIMIT $limit"
        );

        if(!$result){
            return $rows;
        }

        while($row = mysqli_fetch_assoc($result)){
            $rows[] = $row;
        }

        mysqli_free_result($result);
        return $rows;
    }
}

if(!function_exists("adminSalesFindProduct")){
    function adminSalesFindProduct($connection, $productId){
        global $tableposts, $tableartists;

        $productId = (int)$productId;

        if($productId <= 0){
            return null;
        }

        $result = mysqli_query(
            $connection,
            "SELECT p.id, p.postid, p.title, p.album, p.artist, p.normalprice, " .
            "p.stock, p.sold_at, a.name AS artist_name " .
            "FROM $tableposts p " .
            "LEFT JOIN $tableartists a ON a.id = p.artistid " .
            "WHERE p.id = $productId LIMIT 1"
        );

        if(!$result || mysqli_num_rows($result) === 0){
            if($result){
                mysqli_free_result($result);
            }
            return null;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        if(trim((string)($row["artist_name"] ?? "")) === ""){
            $row["artist_name"] = trim((string)($row["artist"] ?? ""));
        }

        $row["album_name"] = adminSalesAlbumName($row);
        return $row;
    }
}

if(!function_exists("adminSalesActiveSaleForProduct")){
    function adminSalesActiveSaleForProduct($connection, $productId){
        $salesTable = adminSalesTable();
        $productId = (int)$productId;

        if($productId <= 0){
            return null;
        }

        $result = mysqli_query(
            $connection,
            "SELECT * FROM $salesTable " .
            "WHERE product_id = $productId AND status = 'completed' " .
            "ORDER BY id DESC LIMIT 1"
        );

        if(!$result || mysqli_num_rows($result) === 0){
            if($result){
                mysqli_free_result($result);
            }
            return null;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $row;
    }
}

if(!function_exists("adminSalesRegister")){
    function adminSalesRegister($connection, $productId, $salePrice){
        global $tableposts, $tableartists;

        $productId = (int)$productId;
        $salePrice = (float)$salePrice;
        $salesTable = adminSalesTable();

        if($productId <= 0 || $salePrice <= 0 || $salePrice > 100000){
            return [
                "ok" => false,
                "message" => "Producto o precio de venta no válido."
            ];
        }

        if(!mysqli_begin_transaction($connection)){
            return [
                "ok" => false,
                "message" => "No se pudo iniciar el registro de la venta."
            ];
        }

        try{
            $productResult = mysqli_query(
                $connection,
                "SELECT p.id, p.postid, p.title, p.album, p.artist, p.normalprice, " .
                "p.stock, a.name AS artist_name " .
                "FROM $tableposts p " .
                "LEFT JOIN $tableartists a ON a.id = p.artistid " .
                "WHERE p.id = $productId LIMIT 1 FOR UPDATE"
            );

            if(!$productResult || mysqli_num_rows($productResult) === 0){
                throw new RuntimeException("El CD no existe.");
            }

            $product = mysqli_fetch_assoc($productResult);
            mysqli_free_result($productResult);

            if((int)($product["stock"] ?? 1) !== 1){
                throw new RuntimeException("El CD ya está marcado como vendido.");
            }

            $activeResult = mysqli_query(
                $connection,
                "SELECT id FROM $salesTable " .
                "WHERE product_id = $productId AND status = 'completed' " .
                "LIMIT 1 FOR UPDATE"
            );

            if($activeResult && mysqli_num_rows($activeResult) > 0){
                mysqli_free_result($activeResult);
                throw new RuntimeException("Este CD ya tiene una venta activa registrada.");
            }

            if($activeResult){
                mysqli_free_result($activeResult);
            }

            $artist = trim((string)($product["artist_name"] ?? ""));

            if($artist === ""){
                $artist = trim((string)($product["artist"] ?? ""));
            }

            $album = adminSalesAlbumName([
                "album" => $product["album"] ?? "",
                "title" => $product["title"] ?? "",
                "artist_name" => $artist
            ]);

            $postId = adminSalesEscapedText($connection, $product["postid"] ?? "");
            $artistEscaped = adminSalesEscapedText($connection, $artist);
            $albumEscaped = adminSalesEscapedText($connection, $album);
            $listedPrice = max(0, (float)($product["normalprice"] ?? 0));

            $insertSql =
                "INSERT INTO $salesTable (" .
                    "product_id, product_postid, artist_name, album_name, " .
                    "listed_price, sale_price, cost_basis, sold_at, status, source" .
                ") VALUES (" .
                    $productId . ", " .
                    "'$postId', " .
                    "'$artistEscaped', " .
                    "'$albumEscaped', " .
                    number_format($listedPrice, 2, ".", "") . ", " .
                    number_format($salePrice, 2, ".", "") . ", " .
                    "0.00, NOW(), 'completed', 'admin'" .
                ")";

            if(!mysqli_query($connection, $insertSql)){
                throw new RuntimeException("No se pudo guardar el registro financiero de la venta.");
            }

            if(!mysqli_query(
                $connection,
                "UPDATE $tableposts SET stock = 0, sold_at = NOW() WHERE id = $productId"
            )){
                throw new RuntimeException("No se pudo actualizar el inventario del CD.");
            }

            if(!mysqli_commit($connection)){
                throw new RuntimeException("No se pudo confirmar la venta.");
            }

            return [
                "ok" => true,
                "message" => "Venta registrada correctamente.",
                "product_id" => $productId,
                "sale_price" => $salePrice
            ];
        }catch(Throwable $exception){
            @mysqli_rollback($connection);

            return [
                "ok" => false,
                "message" => $exception->getMessage()
            ];
        }
    }
}

if(!function_exists("adminSalesRevert")){
    function adminSalesRevert($connection, $productId){
        global $tableposts;

        $productId = (int)$productId;
        $salesTable = adminSalesTable();

        if($productId <= 0){
            return [
                "ok" => false,
                "message" => "CD no válido."
            ];
        }

        if(!mysqli_begin_transaction($connection)){
            return [
                "ok" => false,
                "message" => "No se pudo iniciar la reversión de la venta."
            ];
        }

        try{
            $productResult = mysqli_query(
                $connection,
                "SELECT id, stock FROM $tableposts " .
                "WHERE id = $productId LIMIT 1 FOR UPDATE"
            );

            if(!$productResult || mysqli_num_rows($productResult) === 0){
                throw new RuntimeException("El CD ya no existe en el inventario.");
            }

            mysqli_free_result($productResult);

            $saleResult = mysqli_query(
                $connection,
                "SELECT id FROM $salesTable " .
                "WHERE product_id = $productId AND status = 'completed' " .
                "ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );

            if(!$saleResult || mysqli_num_rows($saleResult) === 0){
                throw new RuntimeException("No existe una venta activa para este CD.");
            }

            $sale = mysqli_fetch_assoc($saleResult);
            mysqli_free_result($saleResult);
            $saleId = (int)$sale["id"];

            if(!mysqli_query(
                $connection,
                "UPDATE $salesTable SET status = 'reverted', reverted_at = NOW() " .
                "WHERE id = $saleId AND status = 'completed'"
            )){
                throw new RuntimeException("No se pudo revertir el registro financiero.");
            }

            if(!mysqli_query(
                $connection,
                "UPDATE $tableposts SET stock = 1, sold_at = NULL WHERE id = $productId"
            )){
                throw new RuntimeException("No se pudo restaurar el CD al inventario.");
            }

            if(!mysqli_commit($connection)){
                throw new RuntimeException("No se pudo confirmar la reversión.");
            }

            return [
                "ok" => true,
                "message" => "Venta revertida. El CD volvió a estar disponible.",
                "product_id" => $productId
            ];
        }catch(Throwable $exception){
            @mysqli_rollback($connection);

            return [
                "ok" => false,
                "message" => $exception->getMessage()
            ];
        }
    }
}
?>