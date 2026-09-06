<!DOCTYPE html>

<html>

<body>

<?php

$url = $_SERVER['REQUEST_URI'];

if ($url == "/") {
    echo "you are in the root";
} else {
    echo "you are on $url route";
}

?>

</body>


</html>
