<?php
include_once "./library/utils.php";
enable_cors();
$pdo = connect_db_and_set_http_method( "POST" );
$tablename = "accounts";
$msgforsqlerror = "Unable to login the user.";

require "vendor/autoload.php";
use \Firebase\JWT\JWT;

#check json validity and parameter existence
$params = get_params_from_http_body([
	"id",
	"email"
]);

$stmt = $pdo->prepare("select * from accounts where id = ? and email = ?");
if($stmt->execute( [ $params["id"],$params["email"] ])){
    if ( $stmt->rowCount() != 0 ) {
        $row = $stmt->fetch();
        $secret_key = "YOUR_SECRET_KEY";
        $issuer_claim = "poslite"; // this can be the servername
        $audience_claim = "THE_AUDIENCE";
        $issuedat_claim = time(); // issued at
        $notbefore_claim = $issuedat_claim + 0; //not before in seconds
        $expire_claim = $issuedat_claim + 86400*3; // expire time in seconds
        $token = array(
            "iss" => $issuer_claim,
            "aud" => $audience_claim,
            "iat" => $issuedat_claim,
            "nbf" => $notbefore_claim,
            "exp" => $expire_claim,
            "data" => array(
                "email" => $row["email"],
		"role" => $row["role"],
		"companyname" => $row["companyname"],
                "id" => $row["id"],
                "parent_id" => $row["accounts_id"]
        ));

        http_response_code(200);
        $jwt = JWT::encode($token, $secret_key);
        echo json_encode(
            array(
                "message" => "Successful login.",
                "jwt" => $jwt,
                "email" => $params["email"],
                "expireAt" => $expire_claim
            ));
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
} else{
    send_http_status_and_exit("400",$msgforsqlerror);
}
?>
