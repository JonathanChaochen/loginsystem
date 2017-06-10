<?php
/* Database connection settings */
include "MySQLDB.php";
include "Interfaces.php";
include "Database.php";

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'forum';
$mysqli = new mysqli($host,$user,$pass,$db) or die($mysqli->error);
