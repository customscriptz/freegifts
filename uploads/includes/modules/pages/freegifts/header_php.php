<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

$zco_notifier->notify('NOTIFY_HEADER_START_FREEGIFTS');

require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

$breadcrumb->add(NAVBAR_TITLE);

// This should be last line of the script:
$zco_notifier->notify('NOTIFY_HEADER_END_FREEGIFTS');
