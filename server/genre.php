<?php
require_once 'AutoRestObject.php';
require_once 'Conf.php';

$pdo = new PDO('mysql:host=localhost;dbname=cinema2013', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->exec("SET CHARACTER SET utf8");

$object = new AutoRestObject($pdo);
$object->dumpXml();
