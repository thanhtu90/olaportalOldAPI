<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to insert data";
$pdo = connect_db_and_set_http_method( "POST" );
$params = get_params_from_http_body([
    "serial",
    "json"
]);

$stmt = $pdo->prepare("select * from terminals where serial = ?");
$stmt->execute([ $params["serial"] ]);
if ( $stmt->rowCount() == 0 ) {
    send_http_status_and_exit("403",$msgfornoterminal);
}
$row = $stmt->fetch();
$terminals_id = $row["id"];
$stmt = $pdo->prepare("select lastmod from orders where terminals_id = ? order by lastMod desc");
$stmt->execute([ $terminals_id ]);
if ( $stmt->rowCount() == 0 ) {
    $lastmod = 0;
} else {
    $row = $stmt->fetch();
    $lastmod = $row["lastmod"];
}
echo $lastmod;
exit(0);
?>
