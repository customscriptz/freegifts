Free Gifts is a module for Zen Cart.

*Author* Diego Vieira - https://customscriptz.com

# Features
- Get a product when you buy more than $x.xx.
- Get a product when you buy more than $x.xx from selected categories.
- Get a product when you buy another product.
- Get a product from one or more categories and get another product.
   - Example: If the customer spend $10.00, he can get product X for Free. If the customer spend $10.00 in category A, he can get product Y for Free.

# Screenshots
![01](https://raw.githubusercontent.com/customscriptz/freegifts/master/screenshots/01.jpg)

![02](https://raw.githubusercontent.com/customscriptz/freegifts/master/screenshots/02.jpg)

![03](https://raw.githubusercontent.com/customscriptz/freegifts/master/screenshots/02.jpg)


# Before Installing / Upgrading
- Thought our modules are exhausted tested, we do not guarantee that everything goes smoothly, so please, BACKUP YOUR DATABASE AND FILES before proceeding.

# Installation/Upgrade

- Open the folder `uploads`
- Rename the folder `admin` to match yours
- Rename the folder `uploads/includes/templates/YOUR-TEMPLATE` to match your template
- Upload all the contents of the folder `uploads` to the root of your store. Don't upload the `uploads` folder, but what's inside of it. There are no overwrites.
- Wait for all files to be uploaded
- Using your FTP program, go to `/includes/classes` and rename the file `shopping_cart.php` to `shopping_cart-backup.php`
- Go to `/includes/classes` and rename the file `shopping_cart_freegifts_xxx.php` to `shopping_cart.php` (where xxx is the version of your Zen Cart).
- If you changed the original file `shopping_cart.php`, you will need a comparison tool like WinMerge or Beyond Compare to merge both files.
- Go to `/includes/modules/order_total` and copy the file `ot_total.php` to your computer, then delete it.
- Go to `/includes/modules/order_total` and rename the file `ot_total_freegifts.php.freegifts` to `ot_total.php`

- Continue with the [Tutorial](Tutorial).

# Tutorial
- Go to Admin -> Tools -> Layout Boxes Controller. You will notice that a new box has been found `freegifts`. Go ahead and activate it. I recommend you to place on the top, so your customers will notice the free gifts. The box is not displayed if there are no free gifts available.
- If your Zen Cart version is 1.3.x: Go to Admin -> Tools -> Free Gifts
- If your Zen Cart version is 1.5.x: Go to Admin -> Custom Scriptz -> Free Gifts
- Click the button `New Product`.
- The fields should be self explanatory.
- Fill in all the required fields and click `Insert`.
- The Free Gift will be inserted, but the Status will be Inactive. To activate it, click the Red flag.

- Note: If you want to make the Free Gift available on 01/01/2020 only. Do as follow:
   - Start Date -> 01/01/2020
   - End Date -> 02/01/2020
   - Then the Free Gift will be available from 01/01/2010 00:00:00 to 01/01/2010 23:59:59.

# FAQ
- What happens if the customer remove products from the cart so the threshold is less then the cart total?
  - The free gift will act like a normal product.

- Can I set more than one free gift at the same time for the same timeframe?
  - Yes. The customer will be able to choose which one to pick.

# License

[GNU GPL license v2](LICENSE)


# Disclaimer
Please note: all tools/scripts in this repo are released for use "AS IS" without any warranties of any kind, including, but not limited to their installation, use, or performance. We disclaim any and all warranties, either express or implied, including but not limited to any warranty of noninfringement, merchantability, and/or fitness for a particular purpose. We do not warrant that the technology will meet your requirements, that the operation thereof will be uninterrupted or error-free, or that any errors will be corrected.

Any use of these scripts and tools is at your own risk. There is no guarantee that they have been through thorough testing in a comparable environment and we are not responsible for any damage or data loss incurred with their use.

You are responsible for reviewing and testing any scripts you run thoroughly before use in any non-testing environment.