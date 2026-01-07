<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to insert data";
$pdo = connect_db_and_set_http_method( "GET" );
$params = get_params_from_http_body([
    "serial"
]);

$stmt = $pdo->prepare("select lastmod from jsonOlaPay  where serial = ? order by lastMod desc");
$stmt->execute([ $params["serial"] ]);
if ( $stmt->rowCount() == 0 ) {
    $lastmod = 0;
} else {
    $row = $stmt->fetch();
    $lastmod = $row["lastmod"];
}
echo '{ "timestamp": "' . $lastmod . '" }';
exit(0);
?>
