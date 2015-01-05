<?php
require_once 'ga-include/ga-db.php';

$d = new Database();
var_dump($d->AddWebHookKey(1, 'abc'));
var_dump($d->VerifyWebHookKey(1, 'abcd'));
var_dump($d->VerifyWebHookKey(1, 'abc'));
var_dump($d->DeleteWebHookKeys(3));
//var_dump($d->DeleteWebHookKeys(1));
