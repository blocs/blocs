<?php

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
    return Blocs\Common::convertDefault('OK:'.$str);
}
