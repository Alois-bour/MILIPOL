<?php
/*
Plugin Name: Réservations sur mesure (Amélioré)
Description: Plugin complet de réservation avec gestion des créneaux bloqués, notifications HTML, logs, AJAX de disponibilité, pagination admin et design responsive.
Version: 3.1
Author: Votre Nom
Text Domain: reservations-personnalise
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit;

class ReservationsPlugin {
    private $upload_dir;
    private $reservations_file;
    private $bloques_file;
    private $sujets_file;
    private $log_file;
    private $per_page = 20; // pagination admin

    public function __construct() {
        // charger textdomain
        add_action('init', array($this, 'load_textdomain'));

        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/reservations-plugin';
        $this->reservations_file = $this->upload_dir . "/reservations.csv";
        $this->bloques_file = $this->upload_dir . "/bloques.csv";
        $this->sujets_file = $this->upload_dir . "/sujets.csv";
        $this->log_file = $this->upload_dir . "/reservations.log";

        $this->ensure_storage_ready();
        $this->init_hooks();
    }

    public function load_textdomain() {
        load_plugin_textdomain('reservations-personnalise', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function ensure_storage_ready() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
        // create files if missing
        foreach (array($this->reservations_file, $this->bloques_file, $this->sujets_file, $this->log_file) as $f) {
            if (!file_exists($f)) {
                $fp = @fopen($f, 'c');
                if ($fp) fclose($fp);
            }
        }
        // add default sujets if file empty
        if (file_exists($this->sujets_file)) {
            $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (empty($lines)) {
                $defaults = array('Consultation générale', 'Rendez-vous de suivi', 'Première visite', 'Urgence');
                $fp = fopen($this->sujets_file, 'a');
                if ($fp) {
                    foreach ($defaults as $d) fputcsv($fp, array($d));
                    fclose($fp);
                }
            }
        }
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_shortcode('reservation_form', array($this, 'render_form'));
        add_action('init', array($this, 'handle_form_submission'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Export via admin_post
        add_action('admin_post_reservations_export', array($this, 'handle_csv_export'));
        // Admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_init', array($this, 'register_settings'));
        // ajax availability
        add_action('wp_ajax_reservations_check_slot', array($this, 'ajax_check_slot'));
        add_action('wp_ajax_nopriv_reservations_check_slot', array($this, 'ajax_check_slot'));
    }

    // ----------------------
    // Settings registration
    // ----------------------
    public function register_settings() {
        register_setting('reservations_email_settings', 'reservations_email_client_subject');
        register_setting('reservations_email_settings', 'reservations_email_client_message');
        register_setting('reservations_email_settings', 'reservations_email_admin_subject');
        register_setting('reservations_email_settings', 'reservations_email_admin_message');
        register_setting('reservations_email_settings', 'reservations_admin_email');
        register_setting('reservations_email_settings', 'reservations_enable_html_email');
        register_setting('reservations_email_settings', 'reservations_enable_recaptcha');
        register_setting('reservations_email_settings', 'reservations_recaptcha_site_key');
        register_setting('reservations_email_settings', 'reservations_recaptcha_secret_key');
    }

    // ----------------------
    // Styles Frontend
    // ----------------------
    public function enqueue_styles() {
        wp_enqueue_style('reservations-style', plugin_dir_url(__FILE__) . 'css/frontend-style.css', array(), '3.1.0');
        wp_enqueue_script('reservations-frontend', plugin_dir_url(__FILE__) . 'js/frontend.js', array('jquery'), '3.1.0', true);
        // localize availability data for AJAX
        wp_localize_script('reservations-frontend', 'ReservationsData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reservations_check_slot'),
        ));
    }

    // ----------------------
    // Styles Admin
    // ----------------------
    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'reservations') === false) return;
        wp_enqueue_style('reservations-admin-style', plugin_dir_url(__FILE__) . 'css/admin-style.css', array(), '3.1.0');
    }

    // ----------------------
    // Configuration dates et heures
    // ----------------------
    private function get_dates() {
        // keep short list but could be dynamic
        return array(
            "2025-11-18" => "Mardi 18 novembre 2025",
            "2025-11-19" => "Mercredi 19 novembre 2025",
            "2025-11-20" => "Jeudi 20 novembre 2025",
            "2025-11-21" => "Vendredi 21 novembre 2025",
        );
    }

    private function get_heures() {
        return array("09:00", "10:00", "11:00", "14:00", "15:00", "16:00");
    }

    // ----------------------
    // Gestion des sujets de visite
    // ----------------------
    private function get_sujets() {
        $sujets = array();
        if (!file_exists($this->sujets_file)) return array('Consultation générale');
        $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (!empty($data[0])) $sujets[] = $data[0];
        }
        return empty($sujets) ? array('Consultation générale') : $sujets;
    }

    // ----------------------
    // Gestion des créneaux bloqués
    // ----------------------
    private function get_bloques() {
        $bloques = array();
        if (!file_exists($this->bloques_file)) return $bloques;
        $lines = file($this->bloques_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) >= 2) $bloques[] = $data[0] . "_" . $data[1];
        }
        return $bloques;
    }

    // ----------------------
    // Vérifier si créneau disponible
    // ----------------------
    private function is_slot_available($date, $heure) {
        $bloques = $this->get_bloques();
        if (in_array($date . "_" . $heure, $bloques, true)) return false;

        if (!file_exists($this->reservations_file)) return true;
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) >= 2 && $data[0] == $date && $data[1] == $heure) return false;
        }
        return true;
    }

    // ----------------------
    // Formulaire de réservation (shortcode)
    // ----------------------
    public function render_form() {
        $dates = $this->get_dates();
        $heures = $this->get_heures();
        $sujets = $this->get_sujets();

        // calculer disponibilités
        $disponibilites = array();
        foreach ($dates as $date_val => $date_label) {
            $disponibilites[$date_val] = array();
            foreach ($heures as $heure) {
                if ($this->is_slot_available($date_val, $heure)) $disponibilites[$date_val][] = $heure;
            }
        }

        $has_slots = false;
        foreach ($disponibilites as $slots) { if (count($slots) > 0) { $has_slots = true; break; } }

        ob_start();
        ?>
        <div class="reservation-form-wrapper">
            <?php if (!$has_slots): ?>
                <div class="reservation-message error"><?php _e('Désolé, aucun créneau n\'est disponible pour le moment.', 'reservations-personnalise'); ?></div>
            <?php else: ?>
                <?php if (isset($_GET['reservation_success'])): ?>
                    <div class="reservation-message success"><?php _e('Merci ! Votre rendez-vous a été enregistré.', 'reservations-personnalise'); ?></div>
                <?php endif; ?>

                <form method="post" class="reservation-form" id="reservation-form">
                    <?php wp_nonce_field('reservation_action', 'reservation_nonce'); ?>
                    <input type="hidden" name="action" value="reservations_submit">

                    <label><?php echo esc_html(__('Choisissez une date :', 'reservations-personnalise')); ?></label>
                    <div class="date-buttons">
                        <?php
                        $first_available = null;
                        foreach ($dates as $val => $label):
                            $has_availability = count($disponibilites[$val]) > 0;
                            if ($has_availability && $first_available === null) $first_available = $val;
                            $date_obj = DateTime::createFromFormat('Y-m-d', $val);
                            $day_name = array('Dim','Lun','Mar','Mer','Jeu','Ven','Sam')[$date_obj->format('w')];
                            $day_num = $date_obj->format('d');
                            $month_name = array('','Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc')[(int)$date_obj->format('n')];
                        ?>
                            <label class="date-button <?php echo !$has_availability ? 'disabled' : ''; ?> <?php echo $val === $first_available ? 'selected' : ''; ?>">
                                <input type="radio" name="date" value="<?php echo esc_attr($val); ?>" <?php echo !$has_availability ? 'disabled' : ''; ?> <?php echo $val === $first_available ? 'checked' : ''; ?> required>
                                <div class="day"><?php echo esc_html($day_name); ?></div>
                                <div class="date-num"><?php echo esc_html($day_num); ?></div>
                                <div class="month"><?php echo esc_html($month_name); ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label for="heure"><?php echo esc_html(__('Choisissez une heure :', 'reservations-personnalise')); ?></label>
                    <select name="heure" id="heure" required></select>

                    <label for="nom"><?php echo esc_html(__('Nom :', 'reservations-personnalise')); ?></label>
                    <input type="text" name="nom" id="nom" placeholder="Nom" required minlength="2">

                    <label for="prenom"><?php echo esc_html(__('Prénom :', 'reservations-personnalise')); ?></label>
                    <input type="text" name="prenom" id="prenom" placeholder="Prénom" required minlength="2">

                    <label for="entite"><?php echo esc_html(__('Entité :', 'reservations-personnalise')); ?></label>
                    <input type="text" name="entite" id="entite" placeholder="Entité" required minlength="2">

                    <label for="email"><?php echo esc_html(__('Votre email :', 'reservations-personnalise')); ?></label>
                    <input type="email" name="email" id="email" placeholder="exemple@email.com" required>

                    <label><?php echo esc_html(__('Sujet de la visite :', 'reservations-personnalise')); ?></label>
                    <div class="sujets-container">
                        <?php foreach ($sujets as $index => $sujet): ?>
                            <label class="sujet-checkbox">
                                <input type="checkbox" name="sujets[]" value="<?php echo esc_attr($sujet); ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                                <span><?php echo esc_html($sujet); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if (get_option('reservations_enable_recaptcha')): ?>
                        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(get_option('reservations_recaptcha_site_key')); ?>"></div>
                    <?php endif; ?>

                    <input type="submit" name="reserver" value="<?php echo esc_attr(__('Réserver mon créneau', 'reservations-personnalise')); ?>">
                </form>

                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const disponibilites = <?php echo json_encode($disponibilites); ?>;
                    const selectHeure = document.getElementById('heure');

                    function updateHeures() {
                        const selected = document.querySelector("input[name='date']:checked");
                        if (!selected) return;
                        const heures = disponibilites[selected.value] || [];
                        selectHeure.innerHTML = '';
                        if (heures.length === 0) {
                            const opt = document.createElement('option');
                            opt.textContent = '<?php echo esc_js(__('Aucun créneau disponible', 'reservations-personnalise')); ?>';
                            opt.disabled = true; selectHeure.appendChild(opt); return;
                        }
                        heures.forEach(function(h){ const o = document.createElement('option'); o.value = h; o.textContent = h; selectHeure.appendChild(o); });
                    }

                    document.querySelectorAll("input[name='date']").forEach(function(r){ r.addEventListener('change', updateHeures); });
                    updateHeures();

                    // optional: check slot availability via AJAX before submit
                    const form = document.getElementById('reservation-form');
                    form.addEventListener('submit', function(e){
                        // basic client validation already in HTML
                        // We'll check via AJAX synchronously (prevent default then submit when done)
                        e.preventDefault();
                        const date = document.querySelector("input[name='date']:checked").value;
                        const heure = document.getElementById('heure').value;

                        const data = new FormData();
                        data.append('action','reservations_check_slot');
                        data.append('date', date);
                        data.append('heure', heure);
                        data.append('_ajax_nonce', '<?php echo wp_create_nonce('reservations_check_slot'); ?>');

                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: data, credentials: 'same-origin' }).then(r=>r.json()).then(function(resp){
                            if (resp && resp.available) {
                                form.submit(); // allowed
                            } else {
                                alert('<?php echo esc_js(__('Désolé, ce créneau n\'est plus disponible.', 'reservations-personnalise')); ?>');
                            }
                        }).catch(function(){
                            // on fail, still try to submit (server will check again)
                            form.submit();
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ----------------------
    // Traitement du formulaire
    // ----------------------
    public function handle_form_submission() {
        // intercept custom action
        if (isset($_POST['action']) && $_POST['action'] === 'reservations_submit') {
            // Vérification nonce
            if (!isset($_POST['reservation_nonce']) || !wp_verify_nonce($_POST['reservation_nonce'], 'reservation_action')) {
                $this->show_message(__('Erreur de sécurité. Veuillez réessayer.', 'reservations-personnalise'), 'error');
                return;
            }

            // captcha vérif if enabled
            if (get_option('reservations_enable_recaptcha')) {
                // optional: verify recaptcha here using secret key
                // omitted to keep plugin generic; implement if you enable recaptcha
            }

            $date = sanitize_text_field($_POST['date'] ?? '');
            $heure = sanitize_text_field($_POST['heure'] ?? '');
            $nom = sanitize_text_field($_POST['nom'] ?? '');
            $prenom = sanitize_text_field($_POST['prenom'] ?? '');
            $entite = sanitize_text_field($_POST['entite'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $sujets = isset($_POST['sujets']) ? array_map('sanitize_text_field', (array)$_POST['sujets']) : array();

            // validations
            if (empty($date) || empty($heure) || empty($nom) || empty($prenom) || empty($entite) || empty($email)) {
                $this->show_message(__('Tous les champs sont obligatoires.', 'reservations-personnalise'), 'error'); return;
            }
            if (empty($sujets)) { $this->show_message(__('Veuillez sélectionner au moins un sujet de visite.', 'reservations-personnalise'), 'error'); return; }
            if (!is_email($email)) { $this->show_message(__('Adresse email invalide.', 'reservations-personnalise'), 'error'); return; }
            if (strlen($nom) < 2 || strlen($prenom) < 2 || strlen($entite) < 2) { $this->show_message(__('Le nom, prénom et entité doivent contenir au moins 2 caractères.', 'reservations-personnalise'), 'error'); return; }

            // validation date/heure autorisés
            $allowed_dates = $this->get_dates(); $allowed_heures = $this->get_heures();
            if (!isset($allowed_dates[$date]) || !in_array($heure, $allowed_heures, true)) { $this->show_message(__('Date ou heure invalide.', 'reservations-personnalise'), 'error'); return; }

            // vérifier disponibilité
            if (!$this->is_slot_available($date, $heure)) { $this->show_message(__('Désolé, ce créneau n\'est plus disponible.', 'reservations-personnalise'), 'error'); return; }

            if ($this->save_reservation($date, $heure, $nom, $prenom, $entite, $email, $sujets)) {
                $this->send_notifications($date, $heure, $nom, $prenom, $entite, $email, $sujets);
                // log
                $this->log("Reservation saved: $date $heure - $prenom $nom <$email>");
                // redirect PRG pattern
                $redirect = add_query_arg('reservation_success', '1', wp_get_referer() ?: home_url());
                wp_safe_redirect($redirect);
                exit;
            } else {
                $this->show_message(__('Erreur lors de l\'enregistrement. Veuillez réessayer.', 'reservations-personnalise'), 'error');
            }
        }
    }

    // ----------------------
    // Sauvegarder réservation (avec verrou)
    // ----------------------
    private function save_reservation($date, $heure, $nom, $prenom, $entite, $email, $sujets) {
        $allowed_dates = $this->get_dates(); $allowed_heures = $this->get_heures();
        if (!isset($allowed_dates[$date]) || !in_array($heure, $allowed_heures, true)) return false;

        $sujets = array_map(function($s){ return mb_substr(sanitize_text_field($s), 0, 200); }, (array)$sujets);
        $sujets_str = implode(', ', $sujets);

        $fp = @fopen($this->reservations_file, 'a');
        if ($fp === false) return false;

        if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }

        $result = fputcsv($fp, array($date, $heure, $nom, $prenom, $entite, $email, $sujets_str, current_time('mysql')));

        fflush($fp); flock($fp, LOCK_UN); fclose($fp);

        return $result !== false;
    }

    // ----------------------
    // Envoyer notifications (HTML optionnel)
    // ----------------------
    private function send_notifications($date, $heure, $nom, $prenom, $entite, $email, $sujets) {
        $sujets_str = implode(', ', $sujets);

        $subject_client = get_option('reservations_email_client_subject', __('Confirmation de votre rendez-vous', 'reservations-personnalise'));
        $message_client_template = get_option('reservations_email_client_message', "Bonjour {prenom} {nom},\n\nVotre rendez-vous est confirmé pour le {date} à {heure}.\n\nEntité : {entite}\nSujet(s) : {sujets}\n\nNous vous attendons avec plaisir !\n\nCordialement,\nL'équipe");

        $message_client = str_replace(
            array('{nom}','{prenom}','{entite}','{date}','{heure}','{sujets}','{email}'),
            array($nom,$prenom,$entite,$date,$heure,$sujets_str,$email),
            $message_client_template
        );

        $admin_email = get_option('reservations_admin_email', get_option('admin_email'));
        $subject_admin = get_option('reservations_email_admin_subject', sprintf(__('Nouvelle réservation : %s à %s', 'reservations-personnalise'), $date, $heure));
        $message_admin_template = get_option('reservations_email_admin_message', "Nouvelle réservation enregistrée :\n\nNom : {nom}\nPrénom : {prenom}\nEntité : {entite}\nEmail : {email}\nDate : {date}\nHeure : {heure}\nSujet(s) : {sujets}");
        $message_admin = str_replace(array('{nom}','{prenom}','{entite}','{date}','{heure}','{sujets}','{email}'), array($nom,$prenom,$entite,$date,$heure,$sujets_str,$email), $message_admin_template);

        // headers
        $enable_html = get_option('reservations_enable_html_email', 1);
        $headers = array();
        if ($enable_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $message_client_html = nl2br(esc_html($message_client));
            $message_admin_html = nl2br(esc_html($message_admin));
            wp_mail($email, $subject_client, $message_client_html, $headers);
            wp_mail($admin_email, $subject_admin, $message_admin_html, $headers);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            wp_mail($email, $subject_client, $message_client, $headers);
            wp_mail($admin_email, $subject_admin, $message_admin, $headers);
        }
    }

    // ----------------------
    // Logs
    // ----------------------
    private function log($message) {
        $time = current_time('mysql');
        $line = "[$time] $message\n";
        $fp = @fopen($this->log_file, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    // ----------------------
    // Afficher message
    // ----------------------
    private function show_message($message, $type = 'success') {
        add_action('wp_footer', function() use ($message, $type) {
            echo '<div class="reservation-message ' . esc_attr($type) . '">' . esc_html($message) . '</div>';
        }, 5);
    }

    // ----------------------
    // Menu admin
    // ----------------------
    public function add_admin_menu() {
        add_menu_page('Réservations', 'Réservations', 'manage_options', 'reservations-admin', array($this, 'display_reservations'), 'dashicons-calendar-alt', 20);
        add_submenu_page('reservations-admin', 'Créneaux bloqués', 'Créneaux bloqués', 'manage_options', 'reservations-bloques', array($this, 'display_bloques'));
        add_submenu_page('reservations-admin', 'Sujets de visite', 'Sujets de visite', 'manage_options', 'reservations-sujets', array($this, 'display_sujets'));
        add_submenu_page('reservations-admin', 'Configuration emails', 'Configuration emails', 'manage_options', 'reservations-emails', array($this, 'display_email_settings'));
    }

    // ----------------------
    // Afficher réservations admin (avec pagination)
    // ----------------------
    public function display_reservations() {
        if (!current_user_can('manage_options')) return;

        // suppression
        if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_reservation')) {
                $this->delete_reservation(intval($_GET['delete']));
                echo '<div class="notice notice-success"><p>' . esc_html__('Réservation supprimée.', 'reservations-personnalise') . '</p></div>';
            }
        }

        echo '<div class="wrap"><div class="reservations-admin-header"><h1>' . esc_html__('📅 Réservations', 'reservations-personnalise') . '</h1>';
        $export_url = esc_url(admin_url('admin-post.php?action=reservations_export&_wpnonce=' . wp_create_nonce('export_reservations')));
        echo '<a class="button button-primary" href="' . $export_url . '">⬇️ ' . esc_html__('Exporter CSV', 'reservations-personnalise') . '</a>';
        echo '</div>';

        $total = $this->count_reservations();
        echo '<div class="reservations-stats"><div class="stat-box"><h3>' . esc_html__('Total des réservations', 'reservations-personnalise') . '</h3><div class="number">' . intval($total) . '</div></div></div>';

        if ($total == 0) { echo '<p>' . esc_html__('Aucune réservation pour le moment.', 'reservations-personnalise') . '</p></div>'; return; }

        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        // pagination
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $this->per_page;
        $page_lines = array_slice($lines, $offset, $this->per_page);

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Date</th><th>Heure</th><th>Nom</th><th>Prénom</th><th>Entité</th><th>Email</th><th>Sujet(s)</th><th>Enregistré le</th><th>Actions</th></tr></thead><tbody>';

        foreach ($page_lines as $index => $line) {
            $global_index = $offset + $index;
            $data = str_getcsv($line);
            if (count($data) < 7) continue;
            $date = esc_html($data[0]); $heure = esc_html($data[1]); $nom = esc_html($data[2]); $prenom = esc_html($data[3]); $entite = esc_html($data[4]); $email = esc_html($data[5]); $sujets = esc_html($data[6]); $created = isset($data[7]) ? esc_html($data[7]) : 'N/A';
            $delete_url = esc_url(add_query_arg(array('page'=>'reservations-admin','delete'=>$global_index,'_wpnonce'=>wp_create_nonce('delete_reservation')), admin_url('admin.php')));
            echo '<tr>'; echo "<td><strong>$date</strong></td>"; echo "<td>$heure</td>"; echo "<td>$nom</td>"; echo "<td>$prenom</td>"; echo "<td>$entite</td>"; echo "<td><a href='mailto:$email'>$email</a></td>"; echo "<td>$sujets</td>"; echo "<td>$created</td>"; echo "<td><a href='$delete_url' onclick='return confirm(\'Supprimer cette réservation ?\');' class='button button-small'>❌ " . esc_html__('Supprimer', 'reservations-personnalise') . "</a></td>"; echo '</tr>';
        }

        echo '</tbody></table>';

        // pagination links
        $total_pages = ceil($total / $this->per_page);
        if ($total_pages > 1) {
            $base = add_query_arg(array('page'=>'reservations-admin'), admin_url('admin.php'));
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i=1;$i<=$total_pages;$i++) {
                $link = add_query_arg('paged', $i, $base);
                if ($i === $paged) echo '<span class="page-numbers current">' . $i . '</span> '; else echo '<a class="page-numbers" href="' . esc_url($link) . '">' . $i . '</a> ';
            }
            echo '</div></div>';
        }

        echo '</div>'; // wrap
    }

    private function count_reservations() {
        if (!file_exists($this->reservations_file)) return 0;
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return count($lines);
    }

    private function delete_reservation($index) {
        if (!file_exists($this->reservations_file)) return;
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (isset($lines[$index])) {
            unset($lines[$index]);
            file_put_contents($this->reservations_file, implode("\n", $lines) . "\n");
        }
    }

    // ----------------------
    // Export CSV (admin_post)
    // ----------------------
    public function handle_csv_export() {
        if (!current_user_can('manage_options')) wp_die(__('Accès interdit', 'reservations-personnalise'));
        $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
        if (!wp_verify_nonce($nonce, 'export_reservations')) wp_die(__('Erreur de sécurité', 'reservations-personnalise'));
        if (!file_exists($this->reservations_file)) wp_die(__('Aucune réservation à exporter', 'reservations-personnalise'));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reservations-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, array('Date','Heure','Nom','Prénom','Entité','Email','Sujet(s)','Enregistré le'));
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) { $data = str_getcsv($line); fputcsv($output, $data); }
        fclose($output);
        exit;
    }

    // ----------------------
    // Afficher créneaux bloqués
    // ----------------------
    public function display_bloques() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['bloquer']) && check_admin_referer('bloquer_creneau','bloquer_nonce')) {
            $date = sanitize_text_field($_POST['date']); $heure = sanitize_text_field($_POST['heure']);
            $fp = fopen($this->bloques_file, 'a'); if ($fp) { fputcsv($fp, array($date,$heure)); fclose($fp); echo '<div class="notice notice-success"><p>' . esc_html__('Créneau bloqué : ', 'reservations-personnalise') . esc_html($date . ' ' . $heure) . '</p></div>'; }
        }
        if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) { if (wp_verify_nonce($_GET['_wpnonce'],'delete_bloque')) { $index = intval($_GET['delete']); $lines = file($this->bloques_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); if (isset($lines[$index])) { unset($lines[$index]); file_put_contents($this->bloques_file, implode("\n", $lines) . "\n"); echo '<div class="notice notice-success"><p>' . esc_html__('Créneau débloqué.', 'reservations-personnalise') . '</p></div>'; } } }

        echo '<div class="wrap"><h1>' . esc_html__('🚫 Créneaux bloqués', 'reservations-personnalise') . '</h1>';
        echo '<div class="bloquer-form"><h2>' . esc_html__('Bloquer un nouveau créneau', 'reservations-personnalise') . '</h2>';
        echo '<form method="post">'; wp_nonce_field('bloquer_creneau','bloquer_nonce'); echo '<label>' . esc_html__('Date :', 'reservations-personnalise') . ' <input type="date" name="date" required></label> '; echo '<label>' . esc_html__('Heure :', 'reservations-personnalise') . ' <select name="heure">'; foreach ($this->get_heures() as $h) echo '<option value="' . esc_attr($h) . '">' . esc_html($h) . '</option>'; echo '</select></label> '; echo '<input type="submit" name="bloquer" class="button button-primary" value="' . esc_attr__('Bloquer ce créneau','reservations-personnalise') . '">'; echo '</form></div>';

        $lines = file($this->bloques_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($lines) > 0) {
            echo '<h2>' . esc_html__('Créneaux actuellement bloqués', 'reservations-personnalise') . '</h2>';
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Date</th><th>Heure</th><th>Actions</th></tr></thead><tbody>';
            foreach ($lines as $index => $line) { $data = str_getcsv($line); if (count($data) < 2) continue; $date = esc_html($data[0]); $heure = esc_html($data[1]); $delete_url = esc_url(add_query_arg(array('page'=>'reservations-bloques','delete'=>$index,'_wpnonce'=>wp_create_nonce('delete_bloque')), admin_url('admin.php'))); echo '<tr><td><strong>' . $date . '</strong></td><td>' . $heure . '</td><td><a href="' . $delete_url . '" onclick="return confirm(\'Débloquer ce créneau ?\');" class="button button-small">✅ ' . esc_html__('Débloquer','reservations-personnalise') . '</a></td></tr>'; }
            echo '</tbody></table>';
        } else { echo '<p>' . esc_html__('Aucun créneau bloqué actuellement.', 'reservations-personnalise') . '</p>'; }
        echo '</div>';
    }

    // ----------------------
    // Afficher sujets de visite
    // ----------------------
    public function display_sujets() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['ajouter_sujet']) && check_admin_referer('ajouter_sujet','sujet_nonce')) { $sujet = sanitize_text_field($_POST['sujet']); if (!empty($sujet)) { $fp = fopen($this->sujets_file,'a'); if ($fp) { fputcsv($fp,array($sujet)); fclose($fp); echo '<div class="notice notice-success"><p>' . esc_html__('Sujet ajouté : ', 'reservations-personnalise') . esc_html($sujet) . '</p></div>'; } } }
        if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) { if (wp_verify_nonce($_GET['_wpnonce'],'delete_sujet')) { $index = intval($_GET['delete']); if (file_exists($this->sujets_file)) { $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); if (isset($lines[$index])) { unset($lines[$index]); file_put_contents($this->sujets_file, implode("\n", $lines) . "\n"); echo '<div class="notice notice-success"><p>' . esc_html__('Sujet supprimé.', 'reservations-personnalise') . '</p></div>'; } } } }

        echo '<div class="wrap"><h1>' . esc_html__('📋 Sujets de visite', 'reservations-personnalise') . '</h1>'; echo '<div class="bloquer-form"><h2>' . esc_html__('Ajouter un nouveau sujet', 'reservations-personnalise') . '</h2>'; echo '<form method="post">'; wp_nonce_field('ajouter_sujet','sujet_nonce'); echo '<label>' . esc_html__('Nom du sujet :', 'reservations-personnalise') . ' <input type="text" name="sujet" required style="width:300px"></label> '; echo '<input type="submit" name="ajouter_sujet" class="button button-primary" value="' . esc_attr__('Ajouter ce sujet','reservations-personnalise') . '">'; echo '</form></div>';

        $sujets = $this->get_sujets();
        if (!empty($sujets)) {
            echo '<h2>' . esc_html__('Sujets actuellement disponibles','reservations-personnalise') . '</h2>'; echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Sujet</th><th>Actions</th></tr></thead><tbody>';
            if (file_exists($this->sujets_file)) { $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); foreach ($lines as $index => $line) { $data = str_getcsv($line); if (empty($data[0])) continue; $sujet = esc_html($data[0]); $delete_url = esc_url(add_query_arg(array('page'=>'reservations-sujets','delete'=>$index,'_wpnonce'=>wp_create_nonce('delete_sujet')), admin_url('admin.php'))); echo '<tr><td><strong>' . $sujet . '</strong></td><td><a href="' . $delete_url . '" onclick="return confirm(\'Supprimer ce sujet ?\');" class="button button-small">❌ ' . esc_html__('Supprimer','reservations-personnalise') . '</a></td></tr>'; } } else { foreach ($sujets as $sujet) { echo '<tr><td><strong>' . esc_html($sujet) . '</strong></td><td><em>' . esc_html__('Sujet par défaut','reservations-personnalise') . '</em></td></tr>'; } }
            echo '</tbody></table>';
        } else { echo '<p>' . esc_html__('Aucun sujet configuré.', 'reservations-personnalise') . '</p>'; }
        echo '</div>';
    }

    // ----------------------
    // Afficher configuration emails
    // ----------------------
    public function display_email_settings() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['save_email_settings']) && check_admin_referer('save_email_settings','email_settings_nonce')) {
            update_option('reservations_admin_email', sanitize_email($_POST['admin_email']));
            update_option('reservations_email_client_subject', sanitize_text_field($_POST['client_subject']));
            update_option('reservations_email_client_message', sanitize_textarea_field($_POST['client_message']));
            update_option('reservations_email_admin_subject', sanitize_text_field($_POST['admin_subject']));
            update_option('reservations_email_admin_message', sanitize_textarea_field($_POST['admin_message']));
            update_option('reservations_enable_html_email', isset($_POST['enable_html_email']) ? 1 : 0);
            update_option('reservations_enable_recaptcha', isset($_POST['enable_recaptcha']) ? 1 : 0);
            update_option('reservations_recaptcha_site_key', sanitize_text_field($_POST['recaptcha_site'] ?? ''));
            update_option('reservations_recaptcha_secret_key', sanitize_text_field($_POST['recaptcha_secret'] ?? ''));
            echo '<div class="notice notice-success"><p>' . esc_html__('Configuration des emails mise à jour.', 'reservations-personnalise') . '</p></div>';
        }

        $admin_email = get_option('reservations_admin_email', get_option('admin_email'));
        $client_subject = get_option('reservations_email_client_subject', __('Confirmation de votre rendez-vous', 'reservations-personnalise'));
        $client_message = get_option('reservations_email_client_message', "Bonjour {prenom} {nom},\n\nVotre rendez-vous est confirmé pour le {date} à {heure}.\n\nEntité : {entite}\nSujet(s) : {sujets}\n\nNous vous attendons avec plaisir !\n\nCordialement,\nL'équipe");
        $admin_subject = get_option('reservations_email_admin_subject', __('Nouvelle réservation : {date} à {heure}', 'reservations-personnalise'));
        $admin_message = get_option('reservations_email_admin_message', "Nouvelle réservation enregistrée :\n\nNom : {nom}\nPrénom : {prenom}\nEntité : {entite}\nEmail : {email}\nDate : {date}\nHeure : {heure}\nSujet(s) : {sujets}");
        $enable_html = get_option('reservations_enable_html_email', 1);
        $enable_recaptcha = get_option('reservations_enable_recaptcha', 0);
        $recaptcha_site = get_option('reservations_recaptcha_site_key', '');
        $recaptcha_secret = get_option('reservations_recaptcha_secret_key', '');

        echo '<div class="wrap"><h1>' . esc_html__('📧 Configuration des emails', 'reservations-personnalise') . '</h1>';
        echo '<div class="email-variables-info"><h3>' . esc_html__('Variables disponibles', 'reservations-personnalise') . '</h3><p>' . esc_html__('Vous pouvez utiliser ces variables dans vos templates :', 'reservations-personnalise') . '</p><ul><li><code>{nom}</code></li><li><code>{prenom}</code></li><li><code>{entite}</code></li><li><code>{email}</code></li><li><code>{date}</code></li><li><code>{heure}</code></li><li><code>{sujets}</code></li></ul></div>';

        echo '<form method="post" class="email-settings-form">'; wp_nonce_field('save_email_settings','email_settings_nonce');
        echo '<h2>' . esc_html__('📬 Email de notification admin','reservations-personnalise') . '</h2><table class="form-table">';
        echo '<tr><th scope="row"><label for="admin_email">' . esc_html__('Email administrateur','reservations-personnalise') . '</label></th><td><input type="email" id="admin_email" name="admin_email" value="' . esc_attr($admin_email) . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="admin_subject">' . esc_html__('Sujet de l\'email admin','reservations-personnalise') . '</label></th><td><input type="text" id="admin_subject" name="admin_subject" value="' . esc_attr($admin_subject) . '" class="large-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="admin_message">' . esc_html__('Message de l\'email admin','reservations-personnalise') . '</label></th><td><textarea id="admin_message" name="admin_message" rows="8" class="large-text" required>' . esc_textarea($admin_message) . '</textarea></td></tr>';
        echo '</table>';

        echo '<h2>' . esc_html__('👤 Email de confirmation client','reservations-personnalise') . '</h2><table class="form-table">';
        echo '<tr><th scope="row"><label for="client_subject">' . esc_html__('Sujet de l\'email client','reservations-personnalise') . '</label></th><td><input type="text" id="client_subject" name="client_subject" value="' . esc_attr($client_subject) . '" class="large-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="client_message">' . esc_html__('Message de l\'email client','reservations-personnalise') . '</label></th><td><textarea id="client_message" name="client_message" rows="8" class="large-text" required>' . esc_textarea($client_message) . '</textarea></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Envoyer en HTML ?','reservations-personnalise') . '</th><td><label><input type="checkbox" name="enable_html_email" ' . checked(1,$enable_html,false) . '> ' . esc_html__('Oui', 'reservations-personnalise') . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Activer reCAPTCHA (optionnel)','reservations-personnalise') . '</th><td><label><input type="checkbox" name="enable_recaptcha" ' . checked(1,$enable_recaptcha,false) . '> ' . esc_html__('Oui', 'reservations-personnalise') . '</label><p>' . esc_html__('Si activé, renseignez les clés ci-dessous.', 'reservations-personnalise') . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('Clé site reCAPTCHA','reservations-personnalise') . '</th><td><input type="text" name="recaptcha_site" value="' . esc_attr($recaptcha_site) . '" class="regular-text"></td></tr>';
        echo '<tr><th>' . esc_html__('Clé secrète reCAPTCHA','reservations-personnalise') . '</th><td><input type="text" name="recaptcha_secret" value="' . esc_attr($recaptcha_secret) . '" class="regular-text"></td></tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" name="save_email_settings" class="button button-primary" value="' . esc_attr__('💾 Enregistrer la configuration','reservations-personnalise') . '"></p>';
        echo '</form></div>';
    }

    // ----------------------
    // Ajax check slot
    // ----------------------
    public function ajax_check_slot() {
        check_ajax_referer('reservations_check_slot');
        $date = sanitize_text_field($_POST['date'] ?? '');
        $heure = sanitize_text_field($_POST['heure'] ?? '');
        $available = $this->is_slot_available($date, $heure);
        wp_send_json(array('available' => $available));
    }
}

// Hook submission handler via init priority later
add_action('init', function(){
    // instantiate once
    $GLOBALS['ReservationsPluginInstance'] = new ReservationsPlugin();
});

// Additional: handle submission via admin-post for better separation (if you prefer POST targets)
add_action('admin_post_nopriv_reservations_submit', function(){ if (isset($GLOBALS['ReservationsPluginInstance'])) $GLOBALS['ReservationsPluginInstance']->handle_form_submission(); });
add_action('admin_post_reservations_submit', function(){ if (isset($GLOBALS['ReservationsPluginInstance'])) $GLOBALS['ReservationsPluginInstance']->handle_form_submission(); });

// For the shortcode handler to work, ensure instance exists early
if (!isset($GLOBALS['ReservationsPluginInstance'])) $GLOBALS['ReservationsPluginInstance'] = new ReservationsPlugin();

?>
