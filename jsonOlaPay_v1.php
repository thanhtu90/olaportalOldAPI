<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();
$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to insert data";
$pdo = connect_db_and_set_http_method( "POST" );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
$params = get_params_from_http_body([
    "serial",
    "json"
]);

####log raw data
$params["json"] = str_replace('&quot;','"',$params["json"]);
$stmt = $pdo->prepare("insert into jsonOlaPay set serial = ?, content = ?, lastmod = UNIX_TIMESTAMP(now())");
$res = $stmt->execute([ $params["serial"], $params["json"] ]);
echo '{ "status": "inserted" }';
?>
