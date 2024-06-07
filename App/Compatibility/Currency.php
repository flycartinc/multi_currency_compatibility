<?php

namespace WDR\Core\Modules\Addons\MultiCurrency\Compatibility;

defined('ABSPATH') || exit;

abstract class Currency
{
    abstract function run();
}