<?php
require(DIR_WS_MODULES . zen_get_module_directory('freegifts.php'));
if ($freegifts_query->RecordCount()) {
    ?>
    <div class="freegifts">
        <fieldset>
            <legend><?php echo TEXT_FREE_GIFTS; ?></legend>
            <?php echo TEXT_FREE_GIFTS_DESCRIPTION; ?>

            <br/>
            <br/>
            <table border="0" width="100%" cellspacing="0" cellpadding="0">
                <thead>
                    <tr class="tableHeading">
                        <th colspan="2"><?php echo TABLE_HEADING_GIFT; ?></th>
                        <th><?php echo TABLE_HEADING_PRICE; ?></th>
                        <th><?php echo TABLE_HEADING_ACTION; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $c = 0;
                    foreach ($freegifts as $freegift) {
                        $rowClass = $c % 2 == 0 ? 'rowEven' : 'rowOdd';
                        $link = zen_href_link(zen_get_info_page($freegift['products_id']), 'products_id=' . $freegift['products_id']);
                        $products_image = zen_image(DIR_WS_IMAGES . $freegift['products_image'], $freegift['products_name'], IMAGE_SHOPPING_CART_WIDTH, IMAGE_SHOPPING_CART_HEIGHT);
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td valign="top" style="text-align: center">
                                <span id="cartImage" class="back"><a href="<?php echo $link; ?>" target="_blank"><?php echo $products_image; ?></a></span>
                            </td>
                            <td valign="top">
                            <span class="back">
                            <a href="<?php echo $link; ?>" target="_blank"><?php echo $freegift['products_name']; ?></a>
                            <br/>
                                <div class="conditions">
                                    <?php echo TEXT_CONDITION; ?>:
                                    <ul>
                                        <?php

                                        $c = 0;
                                        foreach ($freegift['conditions'] as $condition) {
                                            if (isset($condition['categories']) && count($condition['categories'])) {
                                                echo "<li>" . $condition['text'] . " ";
                                                $i = 0;
                                                foreach ($condition['categories'] as $category) {
                                                    $link = zen_href_link(FILENAME_DEFAULT, zen_get_path($category['categories_id']), 'NONSSL');
                                                    echo '<a href="' . $link . '" target="_blank">' . $category['categories_name'] . '</a>';
                                                    if ($i + 1 < count($condition['categories'])) {
                                                        echo ', ';
                                                    }

                                                    $i++;
                                                }
                                                echo "</li>";
                                            }

                                            if (isset($condition['products']) && count($condition['products'])) {
                                                echo "<li>" . $condition['text'] . " ";
                                                $i = 0;
                                                foreach ($condition['products'] as $product) {
                                                    $link = zen_href_link(zen_get_info_page($product['products_id']), 'products_id=' . $product['products_id']);
                                                    echo '<a href="' . $link . '" target="_blank">' . $product['products_name'] . '</a>';
                                                    if ($i + 1 < count($condition['products'])) {
                                                        echo ', ';
                                                    }

                                                    $i++;
                                                }
                                                echo "</li>";
                                            }

                                            if (!isset($condition['categories']) && !isset($condition['products'])) {
                                                echo "<li>" . $condition['text'] . "</li>";
                                            }

                                            $c++;

                                            if ($c < count($freegift['conditions'])) {
                                                echo "<li class='and'>" . TEXT_AND . "</li>";
                                            }
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </span>
                            </td>
                            <td valign="top" style="text-align: right">
                                <?php echo zen_get_products_display_price($freegift['products_id']); ?>
                            </td>
                            <td valign="top" style="text-align: center">
                                <a href="<?php echo zen_href_link(FILENAME_SHOPPING_CART, 'act=add_free_gift&product_id=' . $freegift['products_id']) . '">' . zen_image($template->get_template_dir('add_freegifts_to_cart.png', DIR_WS_TEMPLATE, $current_page_base, 'images/icons') . '/add_freegifts_to_cart.png', ICON_ADD_FREEGIFTS_TO_CART_ALT); ?></a>
                        </td>
                    </tr>
                    <tr class="<?php echo $rowClass; ?>">
                            <td colspan="4">&nbsp;</td>
                        </tr>
                        <?php
                        $c++;
                    } ?>
                </tbody>
            </table>
        </fieldset>
    </div><br/>
<?php } ?>