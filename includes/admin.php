<?php
if (!defined('ABSPATH')) {
    exit;
}

function supercraft_admin_notice() {
    if (!defined('SUPERCRAFT_SUPABASE_URL')) {
        return;
    }

    $status = get_option('supercraft_validation_status', 'not_set');
    if ($status === 'invalid') {
        echo '<div class="notice notice-warning is-dismissible">
            <p><strong>Supercraft Animations:</strong> Embed code is invalid. Animations are disabled. <a href="' . admin_url('admin.php?page=supercraft-animations') . '">Enter a valid embed code</a>.</p>
        </div>';
    }
}
add_action('admin_notices', 'supercraft_admin_notice');

function supercraft_render_admin_page() {
    $status = get_option('supercraft_validation_status', 'not_set');
    $embed_code = get_option('supercraft_embed_code', '');
    $last_validated = get_option('supercraft_last_validated', '');
    $lenis_enabled = get_option('supercraft_lenis_enabled', '1');
    $show_all_tabs = defined('SUPERCRAFT_SUPABASE_URL');

    ?>
    <div class="wrap">
        <h1>Supercraft Animations</h1>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Settings saved.</p>
            </div>
        <?php endif; ?>

        <?php if (!$show_all_tabs): ?>
            <div class="notice notice-error">
                <p>Configuration file missing.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('supercraft_save_settings'); ?>
            <input type="hidden" name="action" value="supercraft_save_embed_code">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="supercraft_embed_code">Embed Code</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="supercraft_embed_code" 
                               name="supercraft_embed_code" 
                               class="regular-text" 
                               value="<?php echo esc_attr($embed_code); ?>"
                               placeholder="Enter your embed code"
                               <?php echo ($status === 'valid') ? 'readonly' : ''; ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <?php if ($status === 'valid'): ?>
                            <span style="color: green; font-weight: bold;">Valid</span>
                        <?php elseif ($status === 'invalid'): ?>
                            <span style="color: red; font-weight: bold;">Invalid</span>
                        <?php else: ?>
                            <span style="color: gray;">Not Set</span>
                        <?php endif; ?>
                        <?php if ($last_validated): ?>
                            <p class="description">Last validated: <?php echo esc_html($last_validated); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Smooth Scrolling</th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="supercraft_lenis_enabled" 
                                   name="supercraft_lenis_enabled" 
                                   value="1" 
                                   <?php checked($lenis_enabled, '1'); ?>>
                            Enable Lenis smooth scrolling
                        </label>
                        <p class="description">Adds smooth scrolling effect. May cause slight delay with scrub animations.</p>
                    </td>
                </tr>
            </table>

            <?php if ($status === 'valid'): ?>
                <input type="hidden" name="supercraft_embed_code" value="<?php echo esc_attr($embed_code); ?>">
            <?php endif; ?>
            <input type="submit" name="submit" class="button button-primary" value="Save Settings" onclick="this.form.action.value='supercraft_save_settings'">
            <?php if ($status === 'valid'): ?>
                <?php submit_button('Unlink Embed Code', 'delete', 'submit', false, ['onclick' => 'return confirm("Are you sure you want to unlink this embed code?");']); ?>
            <?php else: ?>
                <?php submit_button('Save & Validate'); ?>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

function supercraft_admin_menu() {
    add_menu_page(
        'Supercraft Settings',
        'Supercraft Animations',
        'manage_options',
        'supercraft-animations',
        'supercraft_render_admin_page',
        'dashicons-controls-play',
        80
    );
}
add_action('admin_menu', 'supercraft_admin_menu');