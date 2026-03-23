<?php require_once __DIR__.'/../config/auth.php'; requireAdmin(); require_once __DIR__.'/../config/operations.php';
if($_POST){ $n=post('name'); $p=(float)post('price');
$st=$conn->prepare('INSERT INTO services(name,price) VALUES(?,?)');
$st->bind_param('sd',$n,$p); $st->execute(); header('Location: services.php'); }
?>
<form method="post"><input name="name"><input name="price"><button>Add</button></form>