<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

$content = "";
$content .= '<div class="sideBoxContent centeredContent">';
while (!$freegifts_sidebox_product->EOF) {
    $content .= '<a href="' . zen_href_link(zen_get_info_page($freegifts_sidebox_product->fields["products_id"]), 'cPath=' . zen_get_generated_category_path_rev($freegifts_sidebox_product->fields["master_categories_id"]) . '&products_id=' . $freegifts_sidebox_product->fields["products_id"]) . '">' . zen_image(DIR_WS_IMAGES . $freegifts_sidebox_product->fields['products_image'], $freegifts_sidebox_product->fields['products_name'], GIFTS_IMAGE_WIDTH, GIFTS_IMAGE_HEIGHT);
    $content .= '<br />' . $freegifts_sidebox_product->fields['products_name'] . '</a><br /><br />';
    $freegifts_sidebox_product->MoveNext();
}

$content .= '<br />';

if ($freegifts_count > 1) {
    $content .= sprintf(TEXT_FREE_GIFTS_AVAILABLE, $freegifts_count);
} else {
    $content .= TEXT_FREE_GIFTS_AVAILABLE_ONE;
}
$content .= '<br /><a href="' . zen_href_link('freegifts') . '">' . TEXT_FREE_GIFTS_AVAILABLE_LINK . '</a>';
$content .= '</div>';
