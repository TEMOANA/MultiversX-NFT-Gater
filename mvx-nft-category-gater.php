<?php
/*
Plugin Name: MultiversX NFT Gater
Plugin URI: https://github.com/TEMOANA/MultiversX-NFT-Gater
Description: Plugin WordPress permettant de restreindre l'accès à des articles ou des pages selon la possession d'un NFT ou d'un Token ID sur la blockchain MultiversX. Développé à l'aide d'Antigravity et fourni tel quel, sans garantie.
Version: 1.1.0
Author: TEMOANA
Author URI: https://temoana.net
Text Domain: mvx-nft-gater
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include helpers
require_once plugin_dir_path(__FILE__) . 'includes/class-keccak.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-bech32.php';

use MvxNftGater\Utils\Keccak;
use MvxNftGater\Utils\Bech32;

// Initialize Session
add_action('init', 'mvx_gater_start_session', 1);
function mvx_gater_start_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}

// Handle Logout via Query Arg
add_action('init', 'mvx_gater_handle_logout');
function mvx_gater_handle_logout() {
    if (isset($_GET['mvx-logout']) && $_GET['mvx-logout'] === '1') {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION['mvx_has_nft']);
        unset($_SESSION['mvx_verified_address']);
        unset($_SESSION['mvx_last_checked']);
        unset($_SESSION['mvx_unlocked_posts']);
        
        $redirect_url = remove_query_arg('mvx-logout');
        wp_safe_redirect($redirect_url);
        exit;
    }
}

// Register Settings
add_action('admin_init', 'mvx_gater_register_settings');
function mvx_gater_register_settings() {
    register_setting('mvx_gater_settings_group', 'mvx_gater_network');
    register_setting('mvx_gater_settings_group', 'mvx_gater_nft_collection');
    register_setting('mvx_gater_settings_group', 'mvx_gater_categories');
    register_setting('mvx_gater_settings_group', 'mvx_gater_wc_project_id');
}

// Admin Menu
add_action('admin_menu', 'mvx_gater_add_admin_menu');
function mvx_gater_add_admin_menu() {
    add_options_page(
        'MultiversX NFT Gater',
        'MultiversX NFT Gater',
        'manage_options',
        'mvx-nft-gater',
        'mvx_gater_settings_page'
    );
}

// Settings Page HTML
function mvx_gater_settings_page() {
    $categories = get_categories(array('hide_empty' => 0));
    $selected_cats = get_option('mvx_gater_categories', array());
    if (!is_array($selected_cats)) {
        $selected_cats = array();
    }
    
    $network = get_option('mvx_gater_network', 'mainnet');
    $collection = get_option('mvx_gater_nft_collection', '');
    $wc_project_id = get_option('mvx_gater_wc_project_id', '');
    ?>
    <div class="wrap" style="max-width: 800px; margin: 30px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">
        <div style="background: #0d111c; border-radius: 16px; padding: 30px; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 10px 30px rgba(0,0,0,0.15); color: #ffffff;">
            
            <div style="display: flex; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px;">
                <div style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #1bbab4, #7b2cbf); display: flex; justify-content: center; align-items: center; margin-right: 15px; font-weight: bold; font-size: 20px; color: white;">⚡</div>
                <div>
                    <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; line-height: 1.2;">Réglages MultiversX NFT Category & Page Gater</h1>
                    <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.5); font-size: 13px;">Configurez la restriction de vos pages et catégories WordPress en fonction de l'ownership de NFTs.</p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('mvx_gater_settings_group'); ?>
                <?php do_settings_sections('mvx_gater_settings_group'); ?>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #ffffff;">Blockchain Network</label>
                    <select name="mvx_gater_network" style="width: 100%; max-width: 400px; background: #151a2b; border: 1px solid rgba(255,255,255,0.1); color: #ffffff; border-radius: 8px; padding: 10px; font-size: 14px; outline: none;">
                        <option value="mainnet" <?php selected($network, 'mainnet'); ?>>Mainnet (Production)</option>
                        <option value="devnet" <?php selected($network, 'devnet'); ?>>Devnet (Développement)</option>
                        <option value="testnet" <?php selected($network, 'testnet'); ?>>Testnet (Test)</option>
                    </select>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #ffffff;">Required NFT Collection ID (Par défaut)</label>
                    <input type="text" name="mvx_gater_nft_collection" value="<?php echo esc_attr($collection); ?>" placeholder="ex: HERO-231a4b" style="width: 100%; max-width: 400px; background: #151a2b; border: 1px solid rgba(255,255,255,0.1); color: #ffffff; border-radius: 8px; padding: 10px; font-size: 14px; outline: none;" />
                    <p style="color: rgba(255,255,255,0.4); font-size: 12px; margin: 6px 0 0 0;">L'identifiant de la collection NFT globale par défaut requise. Vous pouvez aussi définir une collection et un Token ID spécifiques page par page dans l'éditeur de page.</p>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #ffffff;">WalletConnect Project ID</label>
                    <input type="text" name="mvx_gater_wc_project_id" value="<?php echo esc_attr($wc_project_id); ?>" placeholder="ex: 13aef85..." style="width: 100%; max-width: 400px; background: #151a2b; border: 1px solid rgba(255,255,255,0.1); color: #ffffff; border-radius: 8px; padding: 10px; font-size: 14px; outline: none;" />
                    <p style="color: rgba(255,255,255,0.4); font-size: 12px; margin: 6px 0 0 0;">Requis pour permettre la connexion avec l'application mobile xPortal. Obtenez-en un gratuitement sur <a href="https://cloud.walletconnect.com/" target="_blank" style="color: #1bbab4; text-decoration: none;">WalletConnect Cloud</a>.</p>
                </div>

                <div style="margin-bottom: 30px;">
                    <label style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 12px; color: #ffffff;">Restricted Categories</label>
                    <div style="background: #151a2b; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; max-height: 200px; overflow-y: auto; max-width: 400px;">
                        <?php if (!empty($categories)) : ?>
                            <?php foreach ($categories as $cat) : ?>
                                <label style="display: flex; align-items: center; margin-bottom: 8px; color: #ffffff; cursor: pointer; font-size: 13px;">
                                    <input type="checkbox" name="mvx_gater_categories[]" value="<?php echo esc_attr($cat->term_id); ?>" <?php checked(in_array($cat->term_id, $selected_cats)); ?> style="margin-right: 10px; accent-color: #1bbab4;" />
                                    <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p style="color: rgba(255,255,255,0.4); font-size: 12px; margin: 0;">Aucune catégorie disponible.</p>
                        <?php endif; ?>
                    </div>
                    <p style="color: rgba(255,255,255,0.4); font-size: 12px; margin: 6px 0 0 0;">Tous les articles appartenant à une catégorie cochée seront verrouillés (sauf surchage spécifique dans la page).</p>
                </div>

                <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; display: flex; justify-content: flex-end;">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Enregistrer les réglages" style="background: linear-gradient(135deg, #1bbab4 0%, #7b2cbf 100%) !important; border: none !important; color: #ffffff !important; border-radius: 8px !important; padding: 10px 24px !important; font-size: 14px !important; height: auto !important; font-weight: 600 !important; cursor: pointer !important; box-shadow: 0 4px 10px rgba(27, 186, 180, 0.2) !important;" />
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Meta Box for Pages & Posts
add_action('add_meta_boxes', 'mvx_gater_add_meta_box');
function mvx_gater_add_meta_box() {
    $post_types = array('page', 'post');
    foreach ($post_types as $pt) {
        add_meta_box(
            'mvx_gater_page_settings',
            '⚡ Restriction NFT MultiversX',
            'mvx_gater_render_meta_box',
            $pt,
            'side',
            'high'
        );
    }
}

// Render Meta Box
function mvx_gater_render_meta_box($post) {
    wp_nonce_field('mvx_gater_save_meta_box', 'mvx_gater_meta_nonce');

    $enabled = get_post_meta($post->ID, '_mvx_gater_enabled', true);
    if (empty($enabled)) {
        $enabled = 'default';
    }
    $collection_override = get_post_meta($post->ID, '_mvx_gater_collection_override', true);
    $token_id = get_post_meta($post->ID, '_mvx_gater_token_id', true);
    $global_collection = get_option('mvx_gater_nft_collection', '');
    ?>
    <div style="padding: 5px 0;">
        <p style="margin-top: 0;">
            <label for="mvx_gater_enabled" style="font-weight: 600; display: block; margin-bottom: 5px;">Mode de verrouillage :</label>
            <select name="mvx_gater_enabled" id="mvx_gater_enabled" style="width: 100%;">
                <option value="default" <?php selected($enabled, 'default'); ?>>Par défaut (selon catégories)</option>
                <option value="yes" <?php selected($enabled, 'yes'); ?>>🔒 Restreindre par NFT</option>
                <option value="no" <?php selected($enabled, 'no'); ?>>🔓 Libérer l'accès (aucun NFT)</option>
            </select>
        </p>

        <div id="mvx-gater-meta-fields" style="<?php echo ($enabled === 'no') ? 'display:none;' : ''; ?>">
            <p>
                <label for="mvx_gater_collection_override" style="font-weight: 600; display: block; margin-bottom: 5px;">Collection NFT :</label>
                <input type="text" name="mvx_gater_collection_override" id="mvx_gater_collection_override" value="<?php echo esc_attr($collection_override); ?>" placeholder="<?php echo esc_attr($global_collection ? $global_collection : 'ex: HERO-231a4b'); ?>" style="width: 100%;" />
                <span class="description" style="font-size: 11px; color: #666; display: block; margin-top: 3px;">
                    Optionnel. Par défaut : <code><?php echo esc_html($global_collection ? $global_collection : 'Non définie'); ?></code>
                </span>
            </p>

            <p>
                <label for="mvx_gater_token_id" style="font-weight: 600; display: block; margin-bottom: 5px;">Token ID / Nonce spécifique :</label>
                <input type="text" name="mvx_gater_token_id" id="mvx_gater_token_id" value="<?php echo esc_attr($token_id); ?>" placeholder="ex: HERO-231a4b-01 ou 01" style="width: 100%;" />
                <span class="description" style="font-size: 11px; color: #666; display: block; margin-top: 3px;">
                    Optionnel. Saisissez l'identifiant exact (ex: <code>HERO-231a4b-01</code>) ou le nonce (ex: <code>01</code>). Laissez vide pour autoriser tout NFT de la collection.
                </span>
            </p>
        </div>
    </div>
    <script>
    (function() {
        var select = document.getElementById('mvx_gater_enabled');
        var fields = document.getElementById('mvx-gater-meta-fields');
        if (select && fields) {
            select.addEventListener('change', function() {
                fields.style.display = (this.value === 'no') ? 'none' : 'block';
            });
        }
    })();
    </script>
    <?php
}

// Save Meta Box
add_action('save_post', 'mvx_gater_save_meta_box');
function mvx_gater_save_meta_box($post_id) {
    if (!isset($_POST['mvx_gater_meta_nonce']) || !wp_verify_nonce($_POST['mvx_gater_meta_nonce'], 'mvx_gater_save_meta_box')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['mvx_gater_enabled'])) {
        update_post_meta($post_id, '_mvx_gater_enabled', sanitize_text_field($_POST['mvx_gater_enabled']));
    }
    if (isset($_POST['mvx_gater_collection_override'])) {
        update_post_meta($post_id, '_mvx_gater_collection_override', sanitize_text_field($_POST['mvx_gater_collection_override']));
    }
    if (isset($_POST['mvx_gater_token_id'])) {
        update_post_meta($post_id, '_mvx_gater_token_id', sanitize_text_field($_POST['mvx_gater_token_id']));
    }
}

// Add Admin Column in Page / Post List Table (edit.php?post_type=page)
add_filter('manage_pages_columns', 'mvx_gater_add_admin_columns');
add_filter('manage_posts_columns', 'mvx_gater_add_admin_columns');
function mvx_gater_add_admin_columns($columns) {
    $columns['mvx_nft_gate'] = '⚡ NFT Gate';
    return $columns;
}

add_action('manage_pages_custom_column', 'mvx_gater_render_admin_columns', 10, 2);
add_action('manage_posts_custom_column', 'mvx_gater_render_admin_columns', 10, 2);
function mvx_gater_render_admin_columns($column_name, $post_id) {
    if ($column_name === 'mvx_nft_gate') {
        $req = mvx_gater_get_post_requirements($post_id);
        $enabled_meta = get_post_meta($post_id, '_mvx_gater_enabled', true);
        
        if ($enabled_meta === 'no') {
            echo '<span style="color:#999;">🔓 Libre</span>';
        } elseif ($req['is_gated']) {
            $badge = '<span style="color:#1bbab4; font-weight:600;">🔒 Verrouillé</span>';
            $details = array();
            if (!empty($req['collection'])) {
                $details[] = esc_html($req['collection']);
            }
            if (!empty($req['token_id'])) {
                $details[] = 'Token: <strong>' . esc_html($req['token_id']) . '</strong>';
            }
            if (!empty($details)) {
                $badge .= '<br><small style="color:#777;">' . implode(' | ', $details) . '</small>';
            }
            echo $badge;
        } else {
            echo '<span style="color:#bbb;">—</span>';
        }
    }
}

// Helper: Get Post NFT Requirements
function mvx_gater_get_post_requirements($post_id) {
    $enabled_meta = get_post_meta($post_id, '_mvx_gater_enabled', true);
    if (empty($enabled_meta)) {
        $enabled_meta = 'default';
    }

    $is_gated = false;

    if ($enabled_meta === 'yes') {
        $is_gated = true;
    } elseif ($enabled_meta === 'no') {
        $is_gated = false;
    } else {
        // default: check categories
        $restricted_cats = get_option('mvx_gater_categories', array());
        if (!empty($restricted_cats) && is_array($restricted_cats) && in_category($restricted_cats, $post_id)) {
            $is_gated = true;
        }
    }

    if (!$is_gated) {
        return array(
            'is_gated' => false,
            'collection' => '',
            'token_id' => ''
        );
    }

    $collection_override = get_post_meta($post_id, '_mvx_gater_collection_override', true);
    $collection = !empty($collection_override) ? $collection_override : get_option('mvx_gater_nft_collection', '');
    $token_id = get_post_meta($post_id, '_mvx_gater_token_id', true);

    return array(
        'is_gated' => true,
        'collection' => trim($collection),
        'token_id' => trim($token_id)
    );
}

// Register WP REST API Endpoints
add_action('rest_api_init', 'mvx_gater_register_rest_routes');
function mvx_gater_register_rest_routes() {
    register_rest_route('mvx-nft-gater/v1', '/challenge', array(
        'methods' => 'GET',
        'callback' => 'mvx_gater_get_challenge_callback',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('mvx-nft-gater/v1', '/verify', array(
        'methods' => 'POST',
        'callback' => 'mvx_gater_verify_callback',
        'permission_callback' => '__return_true'
    ));
}

// GET Challenge Callback
function mvx_gater_get_challenge_callback($request) {
    $nonce = wp_generate_password(12, false);
    $timestamp = time();
    $challenge = "Auth Challenge: " . $nonce . " | Time: " . $timestamp;
    
    $challenge_id = wp_generate_password(24, false);
    set_transient('mvx_chall_' . $challenge_id, $challenge, 300);
    
    return new WP_REST_Response(array(
        'challenge' => $challenge,
        'challenge_id' => $challenge_id
    ), 200);
}

// POST Verify Callback
function mvx_gater_verify_callback($request) {
    if (!session_id()) {
        session_start();
    }

    $params = $request->get_json_params();
    $address = isset($params['address']) ? sanitize_text_field($params['address']) : '';
    $signature = isset($params['signature']) ? sanitize_text_field($params['signature']) : '';
    $challenge_id = isset($params['challenge_id']) ? sanitize_text_field($params['challenge_id']) : '';
    $post_id = isset($params['post_id']) ? intval($params['post_id']) : 0;

    if (empty($address) || empty($signature) || empty($challenge_id)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Paramètres d\'authentification manquants.'
        ), 400);
    }

    // Retrieve stored challenge from transient
    $challenge = get_transient('mvx_chall_' . $challenge_id);

    if (empty($challenge)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Défi d\'authentification expiré ou inexistant. Veuillez réessayer.'
        ), 400);
    }

    try {
        // 1. Decode Bech32 address
        $publicKeyHex = Bech32::decodeToHex($address);
        
        // 2. Format payload
        $payload = "\x17Elrond Signed Message:\n" . strlen($challenge) . $challenge;
        
        // 3. Compute Keccak-256
        $messageHash = Keccak::hash($payload, 256, true);
        
        // 4. Verify signature using Ed25519
        $sigBin = hex2bin($signature);
        $pubKeyBin = hex2bin($publicKeyHex);

        if (!$sigBin || strlen($sigBin) !== 64) {
            delete_transient('mvx_chall_' . $challenge_id);
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Format de signature invalide.'
            ), 400);
        }

        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            delete_transient('mvx_chall_' . $challenge_id);
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'L\'extension PHP Libsodium (sodium) est requise sur le serveur.'
            ), 500);
        }

        $is_valid = sodium_crypto_sign_verify_detached($sigBin, $messageHash, $pubKeyBin);

        if ($is_valid) {
            delete_transient('mvx_chall_' . $challenge_id);

            // 5. Query MultiversX API for NFT ownership
            $network = get_option('mvx_gater_network', 'mainnet');

            if ($post_id > 0) {
                $req = mvx_gater_get_post_requirements($post_id);
                $collection = $req['collection'];
                $token_id = $req['token_id'];
            } else {
                $collection = get_option('mvx_gater_nft_collection', '');
                $token_id = '';
            }

            if (empty($collection)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Le plugin n\'est pas configuré (ID de collection manquant).'
                ), 500);
            }

            $owns_nft = mvx_gater_check_nft_ownership($address, $collection, $network, $token_id);

            if ($owns_nft) {
                // Save access state to session
                $_SESSION['mvx_verified_address'] = $address;
                $_SESSION['mvx_has_nft'] = true;
                $_SESSION['mvx_last_checked'] = time();

                if (!isset($_SESSION['mvx_unlocked_posts']) || !is_array($_SESSION['mvx_unlocked_posts'])) {
                    $_SESSION['mvx_unlocked_posts'] = array();
                }
                if ($post_id > 0) {
                    $_SESSION['mvx_unlocked_posts'][$post_id] = time();
                }

                return new WP_REST_Response(array('success' => true), 200);
            } else {
                $msg = 'Aucun NFT de la collection requise trouvé sur votre portefeuille.';
                if (!empty($token_id)) {
                    $msg = sprintf('Le NFT requis (%s) n\'a pas été trouvé sur votre portefeuille.', $token_id);
                }
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $msg
                ), 403);
            }

        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'La signature cryptographique est invalide.'
            ), 401);
        }

    } catch (Exception $e) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Erreur de décodage cryptographique: ' . $e->getMessage()
        ), 400);
    }
}

// Query MultiversX API to check NFT / Token ID ownership
function mvx_gater_check_nft_ownership($address, $collection, $network, $token_id = '') {
    if (empty($address)) {
        return false;
    }

    $api_url = 'https://api.multiversx.com';
    if ($network === 'devnet') {
        $api_url = 'https://devnet-api.multiversx.com';
    } elseif ($network === 'testnet') {
        $api_url = 'https://testnet-api.multiversx.com';
    }

    // Check specific token_id if provided
    if (!empty($token_id)) {
        $tokens = array_map('trim', explode(',', $token_id));

        if (!empty($collection)) {
            $url = sprintf('%s/accounts/%s/nfts?collection=%s&size=100', $api_url, esc_attr($address), esc_attr($collection));
            $response = wp_remote_get($url, array('timeout' => 15, 'sslverify' => true));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return false;
            }

            $user_nfts = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($user_nfts) || empty($user_nfts)) {
                return false;
            }

            foreach ($user_nfts as $nft) {
                $nft_identifier = strtolower(isset($nft['identifier']) ? $nft['identifier'] : '');
                $nft_nonce = isset($nft['nonce']) ? $nft['nonce'] : null;

                foreach ($tokens as $target_token) {
                    $target_lower = strtolower($target_token);

                    // 1. Exact match with identifier (e.g. "HERO-231a4b-01")
                    if ($nft_identifier === $target_lower) {
                        return true;
                    }

                    // 2. Nonce match (hex or decimal e.g. "01" or "1")
                    if ($nft_nonce !== null && is_numeric($nft_nonce)) {
                        $hex_nonce = sprintf('%02x', $nft_nonce);
                        if (strtolower(ltrim($target_lower, '0')) === strtolower(ltrim(dechex($nft_nonce), '0')) ||
                            $target_lower === (string)$nft_nonce ||
                            $target_lower === $hex_nonce) {
                            return true;
                        }
                    }

                    // 3. Match identifier ending with "-target" (e.g. "-01")
                    if (substr($nft_identifier, -strlen('-' . $target_lower)) === '-' . $target_lower) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            // Direct identifier query when collection is empty
            foreach ($tokens as $target_token) {
                $url = sprintf('%s/accounts/%s/nfts?identifiers=%s&size=1', $api_url, esc_attr($address), esc_attr($target_token));
                $response = wp_remote_get($url, array('timeout' => 15, 'sslverify' => true));
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (is_array($data) && count($data) > 0) {
                        return true;
                    }
                }
            }
            return false;
        }
    }

    // Default collection check
    if (empty($collection)) {
        return false;
    }

    $url = sprintf('%s/accounts/%s/nfts?collection=%s&size=1', $api_url, esc_attr($address), esc_attr($collection));
    $response = wp_remote_get($url, array('timeout' => 15, 'sslverify' => true));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return is_array($data) && count($data) > 0;
}

// Enqueue CSS and JS scripts
add_action('wp_enqueue_scripts', 'mvx_gater_enqueue_assets');
function mvx_gater_enqueue_assets() {
    $should_enqueue = false;
    $post_id = 0;

    if (is_single() || is_page()) {
        $post_id = get_the_ID();
        $req = mvx_gater_get_post_requirements($post_id);
        if ($req['is_gated']) {
            $should_enqueue = true;
        }
    } elseif (is_category()) {
        $restricted_cats = get_option('mvx_gater_categories', array());
        $current_cat = get_queried_object();
        if ($current_cat && !empty($restricted_cats) && is_array($restricted_cats) && in_array($current_cat->term_id, $restricted_cats)) {
            $should_enqueue = true;
        }
    }

    if (!$should_enqueue) {
        return;
    }

    // Always enqueue CSS on restricted pages (locked or unlocked)
    wp_enqueue_style(
        'mvx-gater-css',
        plugins_url('assets/css/frontend.css', __FILE__),
        array(),
        '1.2.0'
    );

    // Check if post is already unlocked in current session
    $address = isset($_SESSION['mvx_verified_address']) ? $_SESSION['mvx_verified_address'] : '';
    $unlocked_posts = isset($_SESSION['mvx_unlocked_posts']) ? $_SESSION['mvx_unlocked_posts'] : array();

    $is_already_unlocked = false;
    if ($post_id > 0 && !empty($address) && isset($unlocked_posts[$post_id])) {
        if (time() - $unlocked_posts[$post_id] < 900) {
            $is_already_unlocked = true;
        }
    }

    // Enqueue JS if not already unlocked
    if (!$is_already_unlocked) {
        wp_register_script(
            'mvx-gater-js',
            plugins_url('assets/js/frontend.js', __FILE__),
            array(),
            '1.2.0',
            true
        );

        $network = get_option('mvx_gater_network', 'mainnet');
        $wc_project_id = get_option('mvx_gater_wc_project_id', '');
        
        $api_url = 'https://api.multiversx.com';
        if ($network === 'devnet') {
            $api_url = 'https://devnet-api.multiversx.com';
        } elseif ($network === 'testnet') {
            $api_url = 'https://testnet-api.multiversx.com';
        }

        wp_localize_script('mvx-gater-js', 'mvxGaterSettings', array(
            'restUrl' => esc_url_raw(rest_url('mvx-nft-gater/v1/')),
            'wpNonce' => wp_create_nonce('wp_rest'),
            'apiUrl' => esc_url_raw($api_url),
            'chainType' => esc_attr($network),
            'wcProjectId' => esc_attr($wc_project_id)
        ));

        wp_enqueue_script('mvx-gater-js');
    }
}

// Render Gated Overlay HTML
function mvx_gater_get_lock_screen_html($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $req = mvx_gater_get_post_requirements($post_id);
    $collection = !empty($req['collection']) ? $req['collection'] : get_option('mvx_gater_nft_collection', 'HERO-123456');
    $token_id = $req['token_id'];

    ob_start();
    ?>
    <div class="mvx-gater-overlay" data-post-id="<?php echo esc_attr($post_id); ?>">
        <div class="mvx-gater-card">
            <div class="mvx-gater-icon-wrap">
                <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            </div>
            <h3 class="mvx-gater-title">Contenu Privé</h3>
            <p class="mvx-gater-description">
                Ce contenu est exclusif. Vous devez posséder le NFT requis pour le déverrouiller :
                <br>
                <span class="mvx-gater-collection-tag"><?php echo esc_html($collection); ?></span>
                <?php if (!empty($token_id)) : ?>
                    <br><span class="mvx-gater-collection-tag" style="background: rgba(243, 77, 76, 0.2); border-color: rgba(243, 77, 76, 0.5); color: #f34d4c; margin-top: 6px; display: inline-block;">Token ID / Nonce : <?php echo esc_html($token_id); ?></span>
                <?php endif; ?>
            </p>
            <button class="mvx-gater-btn" id="mvx-connect-trigger">Connecter mon Wallet</button>
        </div>
    </div>

    <!-- Modal Dialog -->
    <div class="mvx-modal-backdrop" id="mvx-wallet-modal-backdrop">
        <div class="mvx-wallet-modal">
            <div class="mvx-modal-close" id="mvx-modal-close-btn">&times;</div>
            <h3 class="mvx-modal-title">Choisir un Wallet</h3>
            <p class="mvx-modal-subtitle">Connectez votre adresse MultiversX afin de valider la possession du NFT requis.</p>
            
            <div class="mvx-wallet-options" id="mvx-wallet-options-list">
                <button class="mvx-wallet-opt" id="mvx-btn-extension">
                    <div class="mvx-wallet-opt-icon icon-extension">De</div>
                    <div>
                        <div class="mvx-wallet-opt-name">DeFi Wallet</div>
                        <div class="mvx-wallet-opt-desc">Extension de navigateur (Chrome/Brave)</div>
                    </div>
                </button>
                
                <button class="mvx-wallet-opt" id="mvx-btn-xportal">
                    <div class="mvx-wallet-opt-icon icon-xportal">xP</div>
                    <div>
                        <div class="mvx-wallet-opt-name">xPortal App</div>
                        <div class="mvx-wallet-opt-desc">Application Mobile (WalletConnect)</div>
                    </div>
                </button>
                
                <button class="mvx-wallet-opt" id="mvx-btn-web">
                    <div class="mvx-wallet-opt-icon icon-web">We</div>
                    <div>
                        <div class="mvx-wallet-opt-name">Web Wallet</div>
                        <div class="mvx-wallet-opt-desc">Portefeuille MultiversX officiel en ligne</div>
                    </div>
                </button>
                
                <button class="mvx-wallet-opt" id="mvx-btn-xalias">
                    <div class="mvx-wallet-opt-icon icon-xalias">xA</div>
                    <div>
                        <div class="mvx-wallet-opt-name">xAlias</div>
                        <div class="mvx-wallet-opt-desc">Connexion rapide par Google ou Mail</div>
                    </div>
                </button>
            </div>

            <div class="mvx-qr-container" id="mvx-qr-code-holder">
                <p>Scannez ce code QR depuis votre application xPortal mobile :</p>
                <div class="mvx-qr-wrapper" id="mvx-qr-canvas-container"></div>
            </div>

            <div class="mvx-status-container" id="mvx-status-holder">
                <div class="mvx-spinner"></div>
                <div class="mvx-status-msg" id="mvx-status-message">Préparation en cours...</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Filter Post Content to Apply Gate
add_filter('the_content', 'mvx_gater_filter_content');
function mvx_gater_filter_content($content) {
    if (!is_single() && !is_page()) {
        return $content;
    }

    $post_id = get_the_ID();
    $req = mvx_gater_get_post_requirements($post_id);

    if (!$req['is_gated']) {
        return $content;
    }

    // Check session state
    $unlocked_posts = isset($_SESSION['mvx_unlocked_posts']) ? $_SESSION['mvx_unlocked_posts'] : array();
    $address = isset($_SESSION['mvx_verified_address']) ? $_SESSION['mvx_verified_address'] : '';

    $is_unlocked = false;

    if (!empty($address)) {
        if (isset($unlocked_posts[$post_id]) && (time() - $unlocked_posts[$post_id] < 900)) {
            $is_unlocked = true;
        } else {
            $network = get_option('mvx_gater_network', 'mainnet');
            if (mvx_gater_check_nft_ownership($address, $req['collection'], $network, $req['token_id'])) {
                if (!isset($_SESSION['mvx_unlocked_posts']) || !is_array($_SESSION['mvx_unlocked_posts'])) {
                    $_SESSION['mvx_unlocked_posts'] = array();
                }
                $_SESSION['mvx_unlocked_posts'][$post_id] = time();
                $is_unlocked = true;
            } else {
                unset($_SESSION['mvx_unlocked_posts'][$post_id]);
            }
        }
    }

    if ($is_unlocked) {
        $abridged_addr = substr($address, 0, 8) . '...' . substr($address, -6);
        $logout_url = add_query_arg('mvx-logout', '1');
        $token_tag = !empty($req['token_id']) 
            ? sprintf('<span class="mvx-gater-badge-token">Token #%s</span>', esc_html($req['token_id'])) 
            : '';

        $badge = sprintf(
            '<div class="mvx-gater-badge">
                <div class="mvx-gater-badge-left">
                    <div class="mvx-gater-badge-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </div>
                    <div class="mvx-gater-badge-info">
                        <div class="mvx-gater-badge-title">
                            Accès déverrouillé %s
                        </div>
                        <div class="mvx-gater-badge-wallet">
                            Wallet connecté : <code>%s</code>
                        </div>
                    </div>
                </div>
                <a href="%s" class="mvx-gater-logout-btn">Déconnecter</a>
            </div>',
            $token_tag,
            esc_html($abridged_addr),
            esc_url($logout_url)
        );

        return $badge . $content;
    } else {
        return mvx_gater_get_lock_screen_html($post_id);
    }
}

// Category Archive Page Gate: Intercept template include
add_filter('template_include', 'mvx_gater_category_archive_gate');
function mvx_gater_category_archive_gate($template) {
    if (is_category()) {
        $restricted_cats = get_option('mvx_gater_categories', array());
        if (empty($restricted_cats) || !is_array($restricted_cats)) {
            return $template;
        }

        $current_cat = get_queried_object();
        if ($current_cat && in_array($current_cat->term_id, $restricted_cats)) {
            if (!isset($_SESSION['mvx_has_nft']) || $_SESSION['mvx_has_nft'] !== true) {
                return plugin_dir_path(__FILE__) . 'templates/category-gate.php';
            } else {
                if (time() - $_SESSION['mvx_last_checked'] > 900) {
                    $address = $_SESSION['mvx_verified_address'];
                    $collection = get_option('mvx_gater_nft_collection', '');
                    $network = get_option('mvx_gater_network', 'mainnet');

                    if (!mvx_gater_check_nft_ownership($address, $collection, $network)) {
                        unset($_SESSION['mvx_has_nft']);
                        unset($_SESSION['mvx_verified_address']);
                        unset($_SESSION['mvx_last_checked']);
                        return plugin_dir_path(__FILE__) . 'templates/category-gate.php';
                    } else {
                        $_SESSION['mvx_last_checked'] = time();
                    }
                }
            }
        }
    }
    return $template;
}

// Inject un-lock badge at the top of category archive pages when unlocked
add_action('loop_start', 'mvx_gater_category_archive_badge');
function mvx_gater_category_archive_badge($query) {
    if (is_category() && $query->is_main_query()) {
        $restricted_cats = get_option('mvx_gater_categories', array());
        if (empty($restricted_cats) || !is_array($restricted_cats)) {
            return;
        }

        $current_cat = get_queried_object();
        if ($current_cat && in_array($current_cat->term_id, $restricted_cats)) {
            if (isset($_SESSION['mvx_has_nft']) && $_SESSION['mvx_has_nft'] === true) {
                $address = $_SESSION['mvx_verified_address'];
                $abridged_addr = substr($address, 0, 8) . '...' . substr($address, -6);
                $logout_url = add_query_arg('mvx-logout', '1');

                printf(
                    '<div class="mvx-gater-badge">
                        <div class="mvx-gater-badge-left">
                            <div class="mvx-gater-badge-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                            </div>
                            <div class="mvx-gater-badge-info">
                                <div class="mvx-gater-badge-title">Accès déverrouillé</div>
                                <div class="mvx-gater-badge-wallet">Wallet connecté : <code>%s</code></div>
                            </div>
                        </div>
                        <a href="%s" class="mvx-gater-logout-btn">Déconnecter</a>
                     </div>',
                    esc_html($abridged_addr),
                    esc_url($logout_url)
                );
            }
        }
    }
}

