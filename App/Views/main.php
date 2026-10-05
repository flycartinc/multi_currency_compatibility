<?php
defined('ABSPATH') || exit;
?>

<div class="wdrc-notification" id="wdrc-notification"></div>
<div class="wdr-compatibility-main" id="wdr-compatibility-main">
    <div class="wdrc-main-title-container">
        <h1 class="wdrc-main-title">
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
                    <input type="hidden" name="option_key" value="<?php echo esc_attr($option_key ?? ''); ?>">
                    <div style="display: flex;flex-direction: column;gap: 1rem;">
                        <div class="wdrc-fields-section">
                            <?php
                            foreach ($fields as $key => $field):
                                if (empty($field)) continue;
                                ?>
                                <div class="wdrc-compatible-field">
                                    <div class="wdrc-compatible-field-info">
                                        <div class="wdrc-compatible-field-header">
                                            <span class="wdrc-compatible-field-name"><?php echo esc_html($field['name'] ?? ''); ?></span>
                                            <span class="wdrc-compatible-field-author"><?php echo esc_html(sprintf(__('by %s', 'wdr-multi-currency-compatibility'), $field['author'] ?? '')); ?></span>
                                        </div>
                                        <p class="wdrc-compatible-field-desc">
                                            <?php echo esc_html(sprintf(__('Adjusts Discount Rules calculations for prices converted by %s.', 'wdr-multi-currency-compatibility'), $field['name'] ?? '')); ?>
                                        </p>
                                    </div>
                                    <label class="wdrc-toggle-switch">
                                        <input type="checkbox"
                                               name="wdrc_compatibility[<?php echo esc_attr($key ?? ''); ?>]"
                                               id="<?php echo esc_attr($key ?? ''); ?>"
                                               value="1" <?php checked(!empty($field['is_enabled'])); ?>>
                                        <span class="wdrc-toggle-slider" aria-hidden="true"></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="wdrc-fields-save-section">
                            <button type="button"
                                    id="wdrc-save-button"><?php _e("Save", "wdr-multi-currency-compatibility"); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
