<?php
require_once 'AutoRestObject.php';
$pdo = new PDO('mysql:host=localhost;dbname=cinema2013', 'root', 'root');

$test = new AutoRestObject($pdo);
$test->getName();
echo "lol";