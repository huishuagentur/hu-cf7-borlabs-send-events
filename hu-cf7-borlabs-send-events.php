<?php
/**
 * Plugin Name: HU CF7 Borlabs Multi-Platform Event Tracker
 * Description: Hochperformantes Tracking (GA4, Ads, Meta, TikTok, Matomo) mit Borlabs 3 Integration und Transient-Caching.
 * Version: 1.2.0
 * Author: HUisHU. Digitale Kreativagentur GmbH
 * Author URI:  https://www.huishu-agentur.de
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: huishu-cf7-borlabs
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Lädt Admin-Assets auf der Contact Form 7 Einstellungsseite.
 */
function hu_cf7b_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'toplevel_page_wpcf7' ) !== false ) {
        wp_enqueue_style( 'hu-cf7b-admin-css', plugins_url( 'assets/admin-styles.css', __FILE__ ) );
        wp_enqueue_script( 'hu-cf7b-admin-js', plugins_url( 'assets/admin-script.js', __FILE__ ) );
    }
}
add_action( 'admin_enqueue_scripts', 'hu_cf7b_enqueue_admin_assets' );

/**
 * 2. Fügt den Tracking-Tab im Contact Form 7 Editor hinzu.
 */
function hu_cf7b_add_editor_panel( $panels ) {
    $panels['tracking-panel'] = [
        'title'    => esc_html__( 'Event Tracking', 'huishu-cf7-borlabs' ),
        'callback' => 'hu_cf7b_settings_callback',
    ];
    return $panels;
}
add_filter( 'wpcf7_editor_panels', 'hu_cf7b_add_editor_panel' );

/**
 * 3. Rendert das HTML für den Einstellungs-Tab.
 */
function hu_cf7b_settings_callback( $post ) {
    $settings = get_post_meta( $post->id(), '_hu_cf7b_tracking_settings', true ) ?: [];
    wp_nonce_field( 'hu_cf7b_save_data', 'hu_cf7b_nonce' );
    ?>
    <div class="cp-tracking-wrapper">
        <div class="cp-tracking-section" style="background: #e7f3ff; border-color: #b3d7ff;">
            <label style="font-size: 1.1em; font-weight: bold;">
                <input type="checkbox" name="hu_cf7b_tracking[active]" id="cp_tracking_active" value="1" <?php checked( 1, $settings['active'] ?? 0 ); ?>>
                <?php esc_html_e( 'Tracking für dieses Formular aktivieren', 'huishu-cf7-borlabs' ); ?>
            </label>
        </div>

        <div id="cp-tracking-details" class="<?php echo ( $settings['active'] ?? 0 ) ? '' : 'hidden-tracking'; ?>">
            <div class="cp-tracking-section">
                <h3><?php esc_html_e( 'Google Plattformen', 'huishu-cf7-borlabs' ); ?></h3>
                <div class="cp-tracking-field">
                    <label>
                        <input type="checkbox" name="hu_cf7b_tracking[ga4_enabled]" value="1" <?php checked( 1, $settings['ga4_enabled'] ?? 0 ); ?>> 
                        <?php esc_html_e( 'GA4 Event (generate_lead) senden', 'huishu-cf7-borlabs' ); ?>
                    </label>
                </div>
                <div class="cp-tracking-field">
                    <label><?php esc_html_e( 'Google Ads Conversion-ID', 'huishu-cf7-borlabs' ); ?></label>
                    <input type="text" name="hu_cf7b_tracking[ads_id]" value="<?php echo esc_attr( $settings['ads_id'] ?? '' ); ?>" placeholder="AW-123456789">
                </div>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div class="cp-tracking-section" style="flex: 1; min-width: 300px;">
                    <h3><?php esc_html_e( 'Meta Pixel', 'huishu-cf7-borlabs' ); ?></h3>
                    <div class="cp-tracking-field">
                        <label><?php esc_html_e( 'Event-Art', 'huishu-cf7-borlabs' ); ?></label>
                        <select name="hu_cf7b_tracking[meta_event]">
                            <option value=""><?php esc_html_e( 'Deaktiviert', 'huishu-cf7-borlabs' ); ?></option>
                            <?php foreach( [ 'Contact', 'CompleteRegistration', 'Lead', 'SubmitApplication' ] as $ev ) : ?>
                                <option value="<?php echo esc_attr( $ev ); ?>" <?php selected( $ev, $settings['meta_event'] ?? '' ); ?>><?php echo esc_html( $ev ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="cp-tracking-section" style="flex: 1; min-width: 300px;">
                    <h3><?php esc_html_e( 'TikTok Pixel', 'huishu-cf7-borlabs' ); ?></h3>
                    <div class="cp-tracking-field">
                        <label><?php esc_html_e( 'Event-Art', 'huishu-cf7-borlabs' ); ?></label>
                        <select name="hu_cf7b_tracking[tiktok_event]">
                            <option value=""><?php esc_html_e( 'Deaktiviert', 'huishu-cf7-borlabs' ); ?></option>
                            <?php foreach( [ 'Contact', 'CompleteRegistration', 'SubmitApplication', 'SubmitForm', 'Subscribe' ] as $tev ) : ?>
                                <option value="<?php echo esc_attr( $tev ); ?>" <?php selected( $tev, $settings['tiktok_event'] ?? '' ); ?>><?php echo esc_html( $tev ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="cp-tracking-section">
                <h3><?php esc_html_e( 'Matomo', 'huishu-cf7-borlabs' ); ?></h3>
                <div class="cp-tracking-field" style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label><?php esc_html_e( 'Event-Kategorie', 'huishu-cf7-borlabs' ); ?></label>
                        <input type="text" name="hu_cf7b_tracking[matomo_cat]" value="<?php echo esc_attr( $settings['matomo_cat'] ?? '' ); ?>">
                    </div>
                    <div style="flex: 1;">
                        <label><?php esc_html_e( 'Event-Aktion', 'huishu-cf7-borlabs' ); ?></label>
                        <input type="text" name="hu_cf7b_tracking[matomo_act]" value="<?php echo esc_attr( $settings['matomo_act'] ?? '' ); ?>">
                    </div>
                </div>
            </div>

            <div class="cp-tracking-section">
                <h3><?php esc_html_e( 'Pinterest Pixel', 'huishu-cf7-borlabs' ); ?></h3>
                <div class="cp-tracking-field">
                    <label><?php esc_html_e( 'Event-Art', 'huishu-cf7-borlabs' ); ?></label>
                    <select name="hu_cf7b_tracking[pinterest_event]">
                        <option value=""><?php esc_html_e( 'Deaktiviert', 'huishu-cf7-borlabs' ); ?></option>
                        <?php foreach( [ 'lead', 'signup', 'contact', 'custom' ] as $pev ) : ?>
                            <option value="<?php echo esc_attr( $pev ); ?>" <?php selected( $pev, $settings['pinterest_event'] ?? '' ); ?>><?php echo esc_html( ucfirst( $pev ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="cp-tracking-info"><small><?php esc_html_e( 'Der Formularname wird als value_name übergeben.', 'huishu-cf7-borlabs' ); ?></small></p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 4. Speichert die Einstellungen, validiert Berechtigungen & bereinigt den Cache.
 */
function hu_cf7b_save_contact_form( $contact_form ) {
    // Sicherheitsprüfung: Nonce und Berechtigungen
    if ( ! isset( $_POST['hu_cf7b_nonce'] ) || ! wp_verify_nonce( $_POST['hu_cf7b_nonce'], 'hu_cf7b_save_data' ) ) {
        return;
    }
    
    if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) && ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $raw = $_POST['hu_cf7b_tracking'] ?? [];
    
    // Daten bereinigen
    $sanitized = [
        'active'          => isset( $raw['active'] ) ? 1 : 0,
        'ga4_enabled'     => isset( $raw['ga4_enabled'] ) ? 1 : 0,
        'ads_id'          => sanitize_text_field( $raw['ads_id'] ?? '' ),
        'meta_event'      => sanitize_text_field( $raw['meta_event'] ?? '' ),
        'tiktok_event'    => sanitize_text_field( $raw['tiktok_event'] ?? '' ),
        'matomo_cat'      => sanitize_text_field( $raw['matomo_cat'] ?? '' ),
        'matomo_act'      => sanitize_text_field( $raw['matomo_act'] ?? '' ),
        'pinterest_event' => sanitize_text_field( $raw['pinterest_event'] ?? '' ),
    ];

    update_post_meta( $contact_form->id(), '_hu_cf7b_tracking_settings', $sanitized );
    delete_transient( 'hu_cf7b_active_configs' );
}
add_action( 'wpcf7_save_contact_form', 'hu_cf7b_save_contact_form' );

/**
 * 5. Frontend-Ausgabe der Tracking-Skripte.
 */
function hu_cf7b_render_frontend_scripts() {
    $form_configs = get_transient( 'hu_cf7b_active_configs' );

    if ( false === $form_configs ) {
        $all_forms = get_posts( [ 'post_type' => 'wpcf7_contact_form', 'numberposts' => -1 ] );
        $form_configs = [];
        foreach ( $all_forms as $f ) {
            $meta = get_post_meta( $f->ID, '_hu_cf7b_tracking_settings', true );
            if ( ! empty( $meta['active'] ) ) {
                $form_configs[ $f->ID ] = [
                    'title'    => $f->post_title,
                    'settings' => $meta
                ];
            }
        }
        set_transient( 'hu_cf7b_active_configs', $form_configs, DAY_IN_SECONDS );
    }

    if ( empty( $form_configs ) ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('wpcf7mailsent', function(event) {
        // Sichere Übergabe der PHP-Variablen an JavaScript
        const configs = <?php echo wp_json_encode( $form_configs ); ?>;
        const currentForm = configs[event.detail.contactFormId];

        if (!currentForm || !currentForm.settings) return;

        const s = currentForm.settings;
        const title = currentForm.title;

        // Borlabs 3 API Consent Check
        const isAllowed = (serviceId) => {
            if (typeof window.BorlabsCookie === 'undefined' || typeof window.BorlabsCookie.Consents === 'undefined') {
                return true;
            }
            return window.BorlabsCookie.Consents.hasConsent(serviceId);
        };

        // Tracking Events
        if (s.ga4_enabled && isAllowed('google-analytics')) {
            if (typeof gtag === 'function') gtag('event', 'generate_lead', { 'lead_source': title });
        }

        if (s.ads_id && isAllowed('google-ads')) {
            if (typeof gtag === 'function') gtag('event', 'conversion', { 'send_to': s.ads_id });
        }

        if (s.meta_event && isAllowed('meta-pixel')) {
            if (typeof fbq === 'function') fbq('track', s.meta_event, { content_name: title });
        }

        if (s.tiktok_event && isAllowed('tiktok-pixel')) {
            if (typeof ttq === 'object') ttq.track(s.tiktok_event, { description: title });
        }

        if (s.matomo_cat && s.matomo_act && isAllowed('matomo')) {
            if (typeof _paq !== 'undefined') _paq.push(['trackEvent', s.matomo_cat, s.matomo_act, title]);
        }

        if (s.pinterest_event && isAllowed('pinterest')) {
            if (typeof pintrk === 'function') {
                pintrk('track', s.pinterest_event, { value_name: title });
            }
        }
    }, false);
    </script>
    <?php
}
add_action( 'wp_footer', 'hu_cf7b_render_frontend_scripts' );

/**
 * 6. Registriert die Status-Spalte in der CF7 Formular-Übersicht.
 */
function hu_cf7b_register_custom_column( $cols ) {
    $cols['tracking_status'] = esc_html__( 'Tracking', 'huishu-cf7-borlabs' );
    return $cols;
}
add_filter( 'manage_wpcf7_contact_forms_columns', 'hu_cf7b_register_custom_column' );

/**
 * 7. Rendert den Inhalt der Status-Spalte in der CF7 Formular-Übersicht.
 */
function hu_cf7b_render_custom_column( $column, $post_id ) {
    if ( $column === 'tracking_status' ) {
        $settings = get_post_meta( $post_id, '_hu_cf7b_tracking_settings', true );
        if ( ! empty( $settings['active'] ) ) {
            echo '<span style="color: #46b450; font-weight: bold;">✓ Aktiv</span>';
        } else {
            echo '<span style="color: #ccc;">-</span>';
        }
    }
}
add_action( 'manage_wpcf7_contact_forms_custom_column', 'hu_cf7b_render_custom_column', 10, 2 );

/**
 * 8. Migration: Übernahme der alten Daten in das neue Namensschema.
 *
 * Führt einmalig ein Update durch, um Metadaten aus '_cp_tracking_settings'
 * in das neue Schema '_hu_cf7b_tracking_settings' zu überführen.
 */
function hu_cf7b_migrate_legacy_data() {
    // Prüfen, ob die Migration bereits erfolgreich durchgeführt wurde.
    if ( get_option( 'hu_cf7b_migration_1_2_0_done' ) ) {
        return;
    }

    // Alle Contact Form 7 Formulare abrufen.
    $all_forms = get_posts( [
        'post_type'   => 'wpcf7_contact_form',
        'numberposts' => -1,
        'post_status' => 'any',
    ] );

    if ( ! empty( $all_forms ) ) {
        foreach ( $all_forms as $form ) {
            // Alte Konfiguration abrufen
            $legacy_settings = get_post_meta( $form->ID, '_cp_tracking_settings', true );

            // Wenn alte Daten existieren und noch keine neuen Daten vorhanden sind
            if ( ! empty( $legacy_settings ) && is_array( $legacy_settings ) ) {
                $new_settings_exist = get_post_meta( $form->ID, '_hu_cf7b_tracking_settings', true );

                if ( empty( $new_settings_exist ) ) {
                    // Übertragen der alten Werte in das neue Meta-Feld
                    update_post_meta( $form->ID, '_hu_cf7b_tracking_settings', $legacy_settings );
                }

                // Altes Meta-Feld zur Bereinigung der Datenbank löschen
                delete_post_meta( $form->ID, '_cp_tracking_settings' );
            }
        }
    }

    // Alten Transient löschen, falls dieser noch existiert.
    delete_transient( 'cp_tracking_active_configs' );

    // Neuen Transient löschen, um eine saubere Neuerstellung zu erzwingen.
    delete_transient( 'hu_cf7b_active_configs' );

    // Option setzen, damit dieses Skript nicht erneut ausgeführt wird.
    update_option( 'hu_cf7b_migration_1_2_0_done', true );
}
add_action( 'admin_init', 'hu_cf7b_migrate_legacy_data' );