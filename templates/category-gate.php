<?php
/**
 * Template for category gate screen
 *
 * @package MultiversX_NFT_Category_Gater
 */

get_header();
?>
<div class="mvx-category-gate-container" style="max-width: 800px; margin: 0 auto; padding: 80px 20px; font-family: 'Outfit', system-ui, sans-serif;">
    <header class="archive-header" style="margin-bottom: 40px; text-align: center;">
        <h1 class="archive-title" style="font-size: 36px; font-weight: 800; color: #ffffff; margin-bottom: 16px; background: linear-gradient(135deg, #1bbab4 0%, #7b2cbf 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            <?php single_cat_title(); ?>
        </h1>
        <?php if (category_description()) : ?>
            <div class="archive-meta" style="color: rgba(255, 255, 255, 0.6); font-size: 15px; line-height: 1.6; max-width: 600px; margin: 0 auto;">
                <?php echo category_description(); ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="mvx-category-gate-content">
        <?php echo mvx_gater_get_lock_screen_html(); ?>
    </div>
</div>
<?php
get_footer();
