<?php

use Blocs\Common;

function original($str)
{
    return 'OK:'.$str;
}

function raw_original($str)
{
    return 'OK:'.$str;
}

function raw_original2($str)
{
    return Common::convertDefault('OK:'.$str);
}
