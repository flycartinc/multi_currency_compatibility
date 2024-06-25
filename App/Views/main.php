<?php
defined('ABSPATH') || exit;
?>

<div class="wdr-compatibility-main" id="wdr-compatibility-main">
    <div>
        <h1>
            <?php _e('Multi-currency Compatibility for Discount Rules', 'wdr-multi-currency-compatibility'); ?>
        </h1>
    </div>
    <div class="wdrc-body">
        <?php if (isset($fields) && empty($fields)): ?>
            <div class="wdrc-not-available">
                <p><?php _e("Compatibility plugins not found", "wdr-multi-currency-compatibility"); ?></p>
            </div>
        <?php elseif (isset($fields) && is_array($fields)): ?>
            <div class="wdrc-fields">
                <form action="" name="wdrc-fields-form" id="wdrc-fields-form" method="post">
                    <input type="hidden" name="action" value="wdrc_save_compatibility">
                    <input type="hidden" name="option_key" value="<?php echo $option_key ?? ''; ?>">
                    <input type="hidden" name="wdrc_nonce"
                           value="<?php echo wp_create_nonce('wdrc_compatibility_ajax'); ?>">
                    <div style="display: flex;flex-direction: column;gap: 1rem;">
                        <div class="wdrc-fields-section">
                            <?php
                            foreach ($fields as $key => $field):
                                if (empty($field)) continue;
                                ?>
                                <div class="awdr-compatible-field">
                                    <label>
                                        <input type="checkbox"
                                               name="wdrc_compatibility[<?php echo esc_attr($key ?? ''); ?>]"
                                               id="<?php echo esc_attr($key ?? ''); ?>"
                                               value="1" <?php if ($field['is_enabled'] ?? 0 == 1) { ?> checked <?php } ?>>
                                        <?php echo sprintf(__('Enable %s', 'wdr-multi-currency-compatibility'), $field['name'] ?? ''); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="wdrc-fields-save-section">
                            <button type="button" onclick="wdrc.saveCompatibility();"
                                    id="wdrc-save-button"><?php _e("Save", "wdr-multi-currency-compatibility"); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

