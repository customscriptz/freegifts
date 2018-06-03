<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[0][] = array('autoType' => 'class',
    'loadFile'                              => 'observers/class.observer.freegifts.php');

$autoLoadConfig[100][] = array('autoType' => 'classInstantiate',
    'className'                               => 'freegifts_Observer',
    'objectName'                              => 'freegifts_Observer');
