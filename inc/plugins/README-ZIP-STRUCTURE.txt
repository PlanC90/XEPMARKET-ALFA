Plugin ZIP files for TGMPA
==========================

Place plugin .zip files here. WordPress requires each zip to have A SINGLE ROOT FOLDER with the same name as the plugin slug.

CORRECT structure (when you open the zip):
  omnixep-woocommerce.zip
    -> omnixep-woocommerce/     (single folder at root)
         -> omnixep-woocommerce.php, includes/, etc.

WRONG structure (causes "not packaged in a folder" error):
  omnixep-woocommerce.zip
    -> omnixep-woocommerce.php   (files at root = ERROR)
    -> includes/
    -> ...

How to create the zip correctly:
1. Zip the FOLDER "omnixep-woocommerce" (not its contents).
2. From plugins parent: zip -r omnixep-woocommerce.zip omnixep-woocommerce/
3. Put omnixep-woocommerce.zip in this inc/plugins/ directory.

Same for xepmarket-telegram-bot.zip: root must be one folder xepmarket-telegram-bot/
