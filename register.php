<?php 
include_once "./library/utils.php";
$pdo = connect_db_and_set_http_method( "POST" );
$tablename = "accounts";
$msgforsqlerror = "Unable to register the user.";

#check json validity and parameter existence
$params = get_params_from_http_body([
    "firstname",
    "lastname",
    "email",
    "password"
]);

#check if email exists; if so blocks this
$stmt = $pdo->prepare("select * from $tablename where email = ?");
if ($stmt->execute([ $params["email"] ])) {
    if ($stmt->rowCount()!=0) {
      send_http_status_and_exit("401","E-mail already exists.");
    }
} else {
    send_http_status_and_exit("400",$msgforsqlerror);
}

#actual insert
$stmt = $pdo->prepare("insert into $tablename set firstname = ?, lastname = ?, email = ?, password = password(?), enterdate = now()");
$res = $stmt->execute([
    $params["firstname"],
    $params["lastname"],
    $params["email"],
    $params["password"]
]);
if($res){
    send_http_status_and_exit("200","User was successfully registered.");
} else {
    send_http_status_and_exit("400",$msgforsqlerror);
}
?>
