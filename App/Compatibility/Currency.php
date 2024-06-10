<?php

namespace WDRCS\App\Compatibility;

defined('ABSPATH') || exit;

abstract class Currency
{
    abstract function run();
}