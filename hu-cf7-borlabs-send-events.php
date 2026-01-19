<?php
/**
 * Plugin Name: HU CF7 Borlabs Multi-Platform Event Tracker
 * Description: Hochperformantes Tracking (GA4, Ads, Meta, TikTok, Matomo) mit Borlabs 3 Integration und Transient-Caching.
 * Version:     1.2
 * Author:      HUisHU. Digitale Kreativagentur GmbH
 * Author URI:  https://www.huishu-agentur.de
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: hu-cf7-borlabs-tracker
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. Assets im Backend laden
 */
add_action('admin_enqueue_scripts', function($hook) {
    // Styles nur auf der CF7-Editor-Seite laden
    if (strpos($hook, 'toplevel_page_wpcf7') !== false) {
        wp_enqueue_style('cp-tracking-admin-css', plugins_url('assets/admin-styles.css', __FILE__));
        wp_enqueue_script('cp-tracking-admin-js', plugins_url('assets/admin-script.js', __FILE__));
    }
});

/**
 * 2. Contact Form 7 Tab hinzufügen
 */
add_filter('wpcf7_editor_panels', function($panels) {
    $panels['tracking-panel'] = [
        'title'    => __('Event Tracking', 'cf7-tracking'),
        'callback' => 'cp_tracking_settings_callback',
    ];
    return $panels;
});

/**
 * 3. HTML-Inhalt des Einstellungs-Tabs
 */
function cp_tracking_settings_callback($post) {
    $settings = get_post_meta($post->id(), '_cp_tracking_settings', true) ?: [];
    wp_nonce_field('cp_tracking_save_data', 'cp_tracking_nonce');
    ?>
    <div class="cp-tracking-wrapper">
        <div class="cp-tracking-section" style="background: #e7f3ff; border-color: #b3d7ff;">
            <label style="font-size: 1.1em; font-weight: bold;">
                <input type="checkbox" name="cp_tracking[active]" id="cp_tracking_active" value="1" <?php checked(1, $settings['active'] ?? 0); ?>>
                Tracking für dieses Formular aktivieren
            </label>
        </div>

        <div id="cp-tracking-details" class="<?php echo ($settings['active'] ?? 0) ? '' : 'hidden-tracking'; ?>">
            
            <div class="cp-tracking-section">
                <h3>Google Plattformen</h3>
                <div class="cp-tracking-field">
                    <label>
                        <input type="checkbox" name="cp_tracking[ga4_enabled]" value="1" <?php checked(1, $settings['ga4_enabled'] ?? 0); ?>> GA4 Event (generate_lead) senden
                    </label>
                </div>
                <div class="cp-tracking-field">
                    <label>Google Ads Conversion-ID</label>
                    <input type="text" name="cp_tracking[ads_id]" value="<?php echo esc_attr($settings['ads_id'] ?? ''); ?>" placeholder="AW-123456789">
                </div>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div class="cp-tracking-section" style="flex: 1; min-width: 300px;">
                    <h3>Meta Pixel</h3>
                    <div class="cp-tracking-field">
                        <label>Event-Art</label>
                        <select name="cp_tracking[meta_event]">
                            <option value="">Deaktiviert</option>
                            <?php foreach(['Contact', 'CompleteRegistration', 'Lead', 'SubmitApplication'] as $ev): ?>
                                <option value="<?php echo esc_attr($ev); ?>" <?php selected($ev, $settings['meta_event'] ?? ''); ?>><?php echo esc_html($ev); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="cp-tracking-section" style="flex: 1; min-width: 300px;">
                    <h3>TikTok Pixel</h3>
                    <div class="cp-tracking-field">
                        <label>Event-Art</label>
                        <select name="cp_tracking[tiktok_event]">
                            <option value="">Deaktiviert</option>
                            <?php foreach(['Contact', 'CompleteRegistration', 'SubmitApplication', 'SubmitForm', 'Subscribe'] as $tev): ?>
                                <option value="<?php echo esc_attr($tev); ?>" <?php selected($tev, $settings['tiktok_event'] ?? ''); ?>><?php echo esc_html($tev); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="cp-tracking-section">
                <h3>Matomo</h3>
                <div class="cp-tracking-field" style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label>Event-Kategorie</label>
                        <input type="text" name="cp_tracking[matomo_cat]" value="<?php echo esc_attr($settings['matomo_cat'] ?? ''); ?>">
                    </div>
                    <div style="flex: 1;">
                        <label>Event-Aktion</label>
                        <input type="text" name="cp_tracking[matomo_act]" value="<?php echo esc_attr($settings['matomo_act'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        
    </script>
    <?php
}

/**
 * 4. Einstellungen speichern & Cache (Transient) leeren
 */
add_action('wpcf7_save_contact_form', function($contact_form) {
    if (!isset($_POST['cp_tracking_nonce']) || !wp_verify_nonce($_POST['cp_tracking_nonce'], 'cp_tracking_save_data')) return;
    
    $raw = $_POST['cp_tracking'] ?? [];
    
    do_action('qm/debug', $raw);
    $sanitized = [
        'active'       => isset($raw['active']) ? 1 : 0,
        'ga4_enabled'  => isset($raw['ga4_enabled']) ? 1 : 0,
        'ads_id'       => sanitize_text_field($raw['ads_id'] ?? ''),
        'meta_event'   => sanitize_text_field($raw['meta_event'] ?? ''),
        'tiktok_event' => sanitize_text_field($raw['tiktok_event'] ?? ''),
        'matomo_cat'   => sanitize_text_field($raw['matomo_cat'] ?? ''),
        'matomo_act'   => sanitize_text_field($raw['matomo_act'] ?? ''),
    ];

    update_post_meta($contact_form->id(), '_cp_tracking_settings', $sanitized);
    
    // Transient löschen, damit Daten im Frontend aktuell sind
    delete_transient('cp_tracking_active_configs');
});

/**
 * 5. Frontend-Ausgabe mit Transient-Caching und Borlabs-Check
 */
add_action('wp_footer', function() {
    $form_configs = get_transient('cp_tracking_active_configs');

    if (false === $form_configs) {
        $all_forms = get_posts(['post_type' => 'wpcf7_contact_form', 'numberposts' => -1]);
        $form_configs = [];
        foreach($all_forms as $f) {
            $meta = get_post_meta($f->ID, '_cp_tracking_settings', true);
            if(!empty($meta['active'])) {
                $form_configs[$f->ID] = [
                    'title' => $f->post_title,
                    'settings' => $meta
                ];
            }
        }
        set_transient('cp_tracking_active_configs', $form_configs, DAY_IN_SECONDS);
    }

    if (empty($form_configs)) return;
    ?>
    <script>
    document.addEventListener('wpcf7mailsent', function(event) {
        const configs = <?php echo json_encode($form_configs); ?>;
        const currentForm = configs[event.detail.contactFormId];

        if (!currentForm || !currentForm.settings) return;

        const s = currentForm.settings;
        const title = currentForm.title;

        // Borlabs 3 API Consent Check
        const isAllowed = (serviceId) => {
            // 1. Prüfen, ob Borlabs Cookie 3 überhaupt geladen ist
            if (typeof window.BorlabsCookie === 'undefined' || typeof window.BorlabsCookie.Consents === 'undefined') {
                return true; // Borlabs nicht aktiv -> Tracking standardmäßig erlauben
            }
            
            // 2. Falls Borlabs aktiv ist, den tatsächlichen Consent prüfen
            return window.BorlabsCookie.Consents.hasConsent(serviceId);
        };

        // --- TRACKING EXECUTION ---

        // GA4
        if (s.ga4_enabled && isAllowed('google-analytics')) {
            if (typeof gtag === 'function') gtag('event', 'generate_lead', { 'lead_source': title });
        }

        // Google Ads
        if (s.ads_id && isAllowed('google-ads')) {
            if (typeof gtag === 'function') gtag('event', 'conversion', { 'send_to': s.ads_id });
        }

        // Meta (Facebook)
        if (s.meta_event && isAllowed('meta-pixel')) {
            if (typeof fbq === 'function') fbq('track', s.meta_event, { content_name: title });
        }

        // TikTok
        if (s.tiktok_event && isAllowed('tiktok-pixel')) {
            if (typeof ttq === 'object') ttq.track(s.tiktok_event, { description: title });
        }

        // Matomo
        if (s.matomo_cat && s.matomo_act && isAllowed('matomo')) {
            if (typeof _paq !== 'undefined') _paq.push(['trackEvent', s.matomo_cat, s.matomo_act, title]);
        }

    }, false);
    </script>
    <?php
});

/**
 * 6. Status-Spalte in der CF7 Formular-Übersicht
 */
add_filter('manage_wpcf7_contact_forms_columns', function($cols) {
    $cols['tracking_status'] = 'Tracking';
    return $cols;
});

add_action('manage_wpcf7_contact_forms_custom_column', function($column, $post_id) {
    if ($column === 'tracking_status') {
        $settings = get_post_meta($post_id, '_cp_tracking_settings', true);
        if (!empty($settings['active'])) {
            echo '<span style="color: #46b450; font-weight: bold;">✓ Aktiv</span>';
        } else {
            echo '<span style="color: #ccc;">-</span>';
        }
    }
}, 10, 2);