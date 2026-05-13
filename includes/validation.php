<?php
if (!defined('ABSPATH')) {
    exit;
}

function supercraft_is_validated() {
    if (defined('SUPERCRAFT_ALLOW_UNVALIDATED') && SUPERCRAFT_ALLOW_UNVALIDATED) {
        return true;
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
    if (empty($embed_code)) {
        return false;
    }

    $endpoint = defined('SUPERCRAFT_VALIDATION_ENDPOINT')
        ? SUPERCRAFT_VALIDATION_ENDPOINT
        : 'https://superapp.supercraft.my/api/public/validate-embed';
    $plugin_name = defined('SUPERCRAFT_PLUGIN_NAME') ? SUPERCRAFT_PLUGIN_NAME : 'supercraft-superanimation';

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'embed_code' => $embed_code,
            'plugin_name' => $plugin_name,
            'domain' => get_site_url(),
        ]),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code < 200 || $status_code >= 400) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($body) && !empty($body['valid']);
}

function supercraft_delete_plugin_registration($embed_code) {
    if (empty($embed_code)) {
        return false;
    }

    $endpoint = defined('SUPERCRAFT_DELETE_REGISTRATION_ENDPOINT')
        ? SUPERCRAFT_DELETE_REGISTRATION_ENDPOINT
        : 'https://superapp.supercraft.my/api/public/validate-embed/delete-registration';
    $plugin_name = defined('SUPERCRAFT_PLUGIN_NAME') ? SUPERCRAFT_PLUGIN_NAME : 'supercraft-superanimation';

    $response = wp_remote_request($endpoint, [
        'method' => 'DELETE',
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'embed_code' => $embed_code,
            'plugin_name' => $plugin_name,
        ]),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    return $status_code >= 200 && $status_code < 400;
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
    if (!empty($code)) {
        supercraft_delete_plugin_registration($code);
    }
    update_option('supercraft_embed_code', '');
    update_option('supercraft_validation_status', 'not_set');
    update_option('supercraft_last_validated', '');
    wp_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    exit;
});


