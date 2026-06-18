<?php
$conn = mysqli_connect(
"localhost",
"root",
"",
"studentdb"
);

if(!$conn){
    die("Connection Failed");
}
?>