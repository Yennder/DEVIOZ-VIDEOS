<?php
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$url = 'index.php';
if($id){
    $url .= '?categoria=' . urlencode((string)$id);
}
header('Location: ' . $url);
exit;
