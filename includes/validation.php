<?php
if (!defined('ABSPATH')) {
    exit;
}

function supercraft_is_validated() {
    if (defined('SUPERCRAFT_ALLOW_UNVALIDATED') && SUPERCRAFT_ALLOW_UNVALIDATED) {
        return true;
    }

    if (!defined('SUPERCRAFT_SUPABASE_URL')) {
        return false;
    }

    $status = get_option('supercraft_validation_status', 'not_set');
    return $status === 'valid';
}

function supercraft_get_validation_status() {
    return get_option('supercraft_validation_status', 'not_set');
}

function supercraft_get_embed_code() {
    return get_option('supercraft_embed_code', '');
}

function supercraft_get_last_validated() {
    return get_option('supercraft_last_validated', '');
}

function supercraft_validate_embed_code_standalone($embed_code) {
    if (empty($embed_code) || !defined('SUPERCRAFT_SUPABASE_URL')) {
        return false;
    }

    $code_col = defined('SUPERCRAFT_CODE_COLUMN') ? SUPERCRAFT_CODE_COLUMN : 'embed_public_key';
    $plugin_name = defined('SUPERCRAFT_PLUGIN_NAME') ? SUPERCRAFT_PLUGIN_NAME : 'supercraft-superanimation';
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    $url = SUPERCRAFT_SUPABASE_URL . '/rest/v1/' . SUPERCRAFT_TABLE . '?select=id&' . $code_col . '=eq.' . urlencode($embed_code);

    $response = wp_remote_get($url, [
        'headers' => [
            'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
            'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || !is_array($data)) {
        return false;
    }

    $project_id = $data[0]['id'];

    $reg_url = SUPERCRAFT_SUPABASE_URL . '/rest/v1/project_plugin_registrations?project_id=eq.' . $project_id . '&plugin_name=eq.' . urlencode($plugin_name);

    $reg_response = wp_remote_get($reg_url, [
        'headers' => [
            'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
            'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
        ],
        'timeout' => 15,
    ]);

    $reg_body = wp_remote_retrieve_body($reg_response);
    $reg_data = json_decode($reg_body, true);

    if (!empty($reg_data) && is_array($reg_data)) {
        $existing_domain = isset($reg_data[0]['registered_domain']) ? $reg_data[0]['registered_domain'] : '';
        if (!empty($existing_domain) && $existing_domain !== $domain) {
            return false;
        }
    } else {
        $insert_response = wp_remote_post(SUPERCRAFT_SUPABASE_URL . '/rest/v1/project_plugin_registrations', [
            'headers' => [
                'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
                'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=minimal',
            ],
            'body' => json_encode([
                'project_id' => $project_id,
                'plugin_name' => $plugin_name,
                'registered_domain' => $domain,
            ]),
        ]);
    }

    return true;
}

add_action('admin_post_supercraft_save_embed_code', function() {
    check_admin_referer('supercraft_save_settings');
    $code = isset($_POST['supercraft_embed_code']) ? sanitize_text_field($_POST['supercraft_embed_code']) : '';
    update_option('supercraft_embed_code', $code);
    if (!empty($code)) {
        $valid = supercraft_validate_embed_code_standalone($code);
        update_option('supercraft_validation_status', $valid ? 'valid' : 'invalid');
    } else {
        update_option('supercraft_validation_status', 'not_set');
    }
    update_option('supercraft_last_validated', current_time('mysql'));
    $lenis_enabled = isset($_POST['supercraft_lenis_enabled']) ? '1' : '0';
    update_option('supercraft_lenis_enabled', $lenis_enabled);
    wp_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    exit;
});

add_action('admin_post_supercraft_save_settings', function() {
    check_admin_referer('supercraft_save_settings');
    $lenis_enabled = isset($_POST['supercraft_lenis_enabled']) ? '1' : '0';
    update_option('supercraft_lenis_enabled', $lenis_enabled);
    wp_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    exit;
});

add_action('admin_post_supercraft_validate_now', function() {
    check_admin_referer('supercraft_validate');
    $code = get_option('supercraft_embed_code', '');
    if (!empty($code)) {
        $valid = supercraft_validate_embed_code_standalone($code);
        update_option('supercraft_validation_status', $valid ? 'valid' : 'invalid');
        update_option('supercraft_last_validated', current_time('mysql'));
    }
    wp_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    exit;
});

add_action('admin_post_supercraft_unlink', function() {
    check_admin_referer('supercraft_unlink');
    $code = get_option('supercraft_embed_code', '');
    $project_id = '';
    if (!empty($code)) {
        $code_col = defined('SUPERCRAFT_CODE_COLUMN') ? SUPERCRAFT_CODE_COLUMN : 'embed_public_key';
        $url = SUPERCRAFT_SUPABASE_URL . '/rest/v1/' . SUPERCRAFT_TABLE . '?select=id&' . $code_col . '=eq.' . urlencode($code);
        $response = wp_remote_get($url, [
            'headers' => [
                'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
                'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
            ],
            'timeout' => 15,
        ]);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!empty($data) && isset($data[0]['id'])) {
            $project_id = $data[0]['id'];
        }
    }
    if (!empty($project_id) && defined('SUPERCRAFT_PLUGIN_NAME')) {
        $plugin_name = SUPERCRAFT_PLUGIN_NAME;
        $delete_url = SUPERCRAFT_SUPABASE_URL . '/rest/v1/project_plugin_registrations?project_id=eq.' . $project_id . '&plugin_name=eq.' . urlencode($plugin_name);
        wp_remote_request($delete_url, [
            'method' => 'DELETE',
            'headers' => [
                'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
                'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
                'Prefer' => 'return=minimal',
            ],
        ]);
    }
    update_option('supercraft_embed_code', '');
    update_option('supercraft_validation_status', 'not_set');
    update_option('supercraft_last_validated', '');
    $lenis_enabled = isset($_POST['supercraft_lenis_enabled']) ? '1' : '0';
    update_option('supercraft_lenis_enabled', $lenis_enabled);
    wp_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    exit;
});

function supercraft_schedule_validation() {
    if (!wp_next_scheduled('supercraft_daily_validation')) {
        wp_schedule_event(time(), 'daily', 'supercraft_daily_validation');
    }
}
add_action('wp', 'supercraft_schedule_validation');

function supercraft_daily_validation_event() {
    $code = get_option('supercraft_embed_code', '');
    if (!empty($code)) {
        $valid = supercraft_validate_embed_code_standalone($code);
        update_option('supercraft_validation_status', $valid ? 'valid' : 'invalid');
        update_option('supercraft_last_validated', current_time('mysql'));
    }
}
add_action('supercraft_daily_validation', 'supercraft_daily_validation_event');