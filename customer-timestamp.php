<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();

$msgfornoterminal = "No permission";
$msgforsqlerror = "No entry found";
$pdo = connect_db_and_set_http_method( "POST" );
$params = get_params_from_http_body([
    "serial"
]);

$terminal_serial = $params["serial"];

$stmt = $pdo->prepare("select * from terminals where serial = ?");
$stmt->execute([$terminal_serial]);
if ( $stmt->rowCount() == 0 ) {
    send_http_status_and_exit("403",$msgfornoterminal);
}
$row = $stmt->fetch();
$terminal_vendors_id = $row["vendors_id"];

$stmt = $pdo->prepare("select lastmod from customer where vendors_id = ? order by lastmod desc");
$stmt->execute([ $terminal_vendors_id ]);

if ( $stmt->rowCount() == 0 ) {
    $lastmod = 0;
} else {
    $row = $stmt->fetch();
    $lastmod = $row["lastmod"];
}
echo $lastmod;
exit(0);
?>
