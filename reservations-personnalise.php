<?php
/*
Plugin Name: Réservations sur mesure
Description: Plugin complet de réservation avec gestion des créneaux bloqués, notifications et design responsive.
Version: 3.0
Author: Votre Nom
Text Domain: reservations-personnalise
*/

if (!defined('ABSPATH')) exit;

class ReservationsPlugin {
    
    private $upload_dir;
    private $reservations_file;
    private $bloques_file;
    private $sujets_file;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'];
        $this->reservations_file = $this->upload_dir . "/reservations.csv";
        $this->bloques_file = $this->upload_dir . "/bloques.csv";
        $this->sujets_file = $this->upload_dir . "/sujets.csv";
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_shortcode('reservation_form', array($this, 'render_form'));
        add_action('init', array($this, 'handle_form_submission'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_csv_export'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    // ----------------------
    // Enregistrer les paramètres
    // ----------------------
    public function register_settings() {
        register_setting('reservations_email_settings', 'reservations_email_client_subject');
        register_setting('reservations_email_settings', 'reservations_email_client_message');
        register_setting('reservations_email_settings', 'reservations_email_admin_subject');
        register_setting('reservations_email_settings', 'reservations_email_admin_message');
        register_setting('reservations_email_settings', 'reservations_admin_email');
    }
    
    // ----------------------
    // Styles Frontend
    // ----------------------
    public function enqueue_styles() {
        wp_enqueue_style(
            'reservations-style',
            plugin_dir_url(__FILE__) . 'css/frontend-style.css',
            array(),
            '3.0.0'
        );
    }
    
    // ----------------------
    // Styles Admin
    // ----------------------
    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'reservations') === false) return;
        
        wp_enqueue_style(
            'reservations-admin-style',
            plugin_dir_url(__FILE__) . 'css/admin-style.css',
            array(),
            '3.0.0'
        );
    }
    
    // ----------------------
    // Configuration dates et heures
    // ----------------------
    private function get_dates() {
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
        if (!file_exists($this->sujets_file)) {
            return array(
                'Consultation générale',
                'Rendez-vous de suivi',
                'Première visite',
                'Urgence'
            );
        }
        
        $sujets = array();
        $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (!empty($data[0])) {
                $sujets[] = $data[0];
            }
        }
        
        return empty($sujets) ? array('Consultation générale') : $sujets;
    }
    
    // ----------------------
    // Gestion des créneaux bloqués
    // ----------------------
    private function get_bloques() {
        $bloques = array();
        if (!file_exists($this->bloques_file)) {
            return $bloques;
        }
        
        $lines = file($this->bloques_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) >= 2) {
                $bloques[] = $data[0] . "_" . $data[1];
            }
        }
        return $bloques;
    }
    
    // ----------------------
    // Vérifier si créneau disponible
    // ----------------------
    private function is_slot_available($date, $heure) {
        // Vérifier si bloqué
        $bloques = $this->get_bloques();
        if (in_array($date . "_" . $heure, $bloques)) {
            return false;
        }
        
        // Vérifier si déjà réservé
        if (!file_exists($this->reservations_file)) {
            return true;
        }
        
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) >= 2 && $data[0] == $date && $data[1] == $heure) {
                return false;
            }
        }
        
        return true;
    }
    
    // ----------------------
    // Formulaire de réservation
    // ----------------------
    public function render_form() {
        $dates = $this->get_dates();
        $heures = $this->get_heures();
        $sujets = $this->get_sujets();
        $bloques = $this->get_bloques();
        
        // Calculer les disponibilités
        $disponibilites = array();
        foreach ($dates as $date_val => $date_label) {
            $disponibilites[$date_val] = array();
            foreach ($heures as $heure) {
                if ($this->is_slot_available($date_val, $heure)) {
                    $disponibilites[$date_val][] = $heure;
                }
            }
        }
        
        // Vérifier s'il y a des créneaux disponibles
        $has_slots = false;
        foreach ($disponibilites as $slots) {
            if (count($slots) > 0) {
                $has_slots = true;
                break;
            }
        }
        
        ob_start();
        ?>
        <div class="reservation-form-wrapper">
            <?php if (!$has_slots): ?>
                <div class="reservation-message error">
                    😔 Désolé, aucun créneau n'est disponible pour le moment.
                </div>
            <?php else: ?>
                <form method="post" class="reservation-form" id="reservation-form">
                    <?php wp_nonce_field('reservation_action', 'reservation_nonce'); ?>
                    
                    <label>📅 Choisissez une date :</label>
                    <div class="date-buttons">
                        <?php 
                        $first_available = null;
                        foreach ($dates as $val => $label): 
                            $has_availability = count($disponibilites[$val]) > 0;
                            if ($has_availability && $first_available === null) {
                                $first_available = $val;
                            }
                            
                            // Extraire les informations de date
                            $date_obj = DateTime::createFromFormat('Y-m-d', $val);
                            $day_name = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'][$date_obj->format('w')];
                            $day_num = $date_obj->format('d');
                            $month_name = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'][(int)$date_obj->format('n')];
                        ?>
                            <label class="date-button <?php echo !$has_availability ? 'disabled' : ''; ?> <?php echo $val === $first_available ? 'selected' : ''; ?>">
                                <input type="radio" name="date" value="<?php echo esc_attr($val); ?>" 
                                       <?php echo !$has_availability ? 'disabled' : ''; ?>
                                       <?php echo $val === $first_available ? 'checked' : ''; ?>
                                       required>
                                <div class="day"><?php echo $day_name; ?></div>
                                <div class="date-num"><?php echo $day_num; ?></div>
                                <div class="month"><?php echo $month_name; ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label for="heure">🕐 Choisissez une heure :</label>
                    <select name="heure" id="heure" required>
                        <!-- Options générées dynamiquement -->
                    </select>

                    <label for="nom">👤 Nom :</label>
                    <input type="text" name="nom" id="nom" placeholder="Nom" required minlength="2">

                    <label for="prenom">👤 Prénom :</label>
                    <input type="text" name="prenom" id="prenom" placeholder="Prénom" required minlength="2">

                    <label for="entite">🏢 Entité :</label>
                    <input type="text" name="entite" id="entite" placeholder="Nom de l'organisation ou société" required minlength="2">

                    <label for="email">📧 Votre email :</label>
                    <input type="email" name="email" id="email" placeholder="exemple@email.com" required>

                    <label>📋 Sujet de la visite :</label>
                    <div class="sujets-container">
                        <?php foreach ($sujets as $index => $sujet): ?>
                            <label class="sujet-checkbox">
                                <input type="checkbox" name="sujets[]" value="<?php echo esc_attr($sujet); ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                                <span><?php echo esc_html($sujet); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <input type="submit" name="reserver" value="Réserver mon créneau">
                </form>

                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const dateButtons = document.querySelectorAll(".date-button:not(.disabled)");
                    const dateRadios = document.querySelectorAll("input[name='date']");
                    const selectHeure = document.getElementById("heure");
                    const form = document.getElementById("reservation-form");
                    
                    const disponibilites = <?php echo json_encode($disponibilites); ?>;
                    
                    function updateHeures() {
                        const selectedRadio = document.querySelector("input[name='date']:checked");
                        if (!selectedRadio) return;
                        
                        const dateChoisie = selectedRadio.value;
                        const heures = disponibilites[dateChoisie] || [];
                        
                        selectHeure.innerHTML = "";
                        
                        if (heures.length === 0) {
                            const opt = document.createElement("option");
                            opt.textContent = "Aucun créneau disponible";
                            opt.disabled = true;
                            selectHeure.appendChild(opt);
                            return;
                        }
                        
                        heures.forEach(function(h) {
                            const opt = document.createElement("option");
                            opt.value = h;
                            opt.textContent = h;
                            selectHeure.appendChild(opt);
                        });
                    }
                    
                    // Gérer le clic sur les boutons de date
                    dateButtons.forEach(function(button) {
                        button.addEventListener("click", function() {
                            dateButtons.forEach(function(btn) {
                                btn.classList.remove("selected");
                            });
                            this.classList.add("selected");
                            updateHeures();
                        });
                    });
                    
                    dateRadios.forEach(function(radio) {
                        radio.addEventListener("change", updateHeures);
                    });
                    
                    updateHeures();
                    
                    // Validation
                    form.addEventListener("submit", function(e) {
                        const nom = document.getElementById("nom").value.trim();
                        const prenom = document.getElementById("prenom").value.trim();
                        const entite = document.getElementById("entite").value.trim();
                        const email = document.getElementById("email").value.trim();
                        const sujetsChecked = document.querySelectorAll("input[name='sujets[]']:checked");
                        
                        if (nom.length < 2 || prenom.length < 2) {
                            alert("Le nom et le prénom doivent contenir au moins 2 caractères.");
                            e.preventDefault();
                            return false;
                        }
                        
                        if (entite.length < 2) {
                            alert("L'entité doit contenir au moins 2 caractères.");
                            e.preventDefault();
                            return false;
                        }
                        
                        if (!email.includes("@")) {
                            alert("Veuillez entrer une adresse email valide.");
                            e.preventDefault();
                            return false;
                        }
                        
                        if (sujetsChecked.length === 0) {
                            alert("Veuillez sélectionner au moins un sujet de visite.");
                            e.preventDefault();
                            return false;
                        }
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
        if (!isset($_POST['reserver'])) {
            return;
        }
        
        // Vérification du nonce
        if (!isset($_POST['reservation_nonce']) || !wp_verify_nonce($_POST['reservation_nonce'], 'reservation_action')) {
            $this->show_message('Erreur de sécurité. Veuillez réessayer.', 'error');
            return;
        }
        
        // Validation et nettoyage
        $date = sanitize_text_field($_POST['date']);
        $heure = sanitize_text_field($_POST['heure']);
        $nom = sanitize_text_field($_POST['nom']);
        $prenom = sanitize_text_field($_POST['prenom']);
        $entite = sanitize_text_field($_POST['entite']);
        $email = sanitize_email($_POST['email']);
        $sujets = isset($_POST['sujets']) ? array_map('sanitize_text_field', $_POST['sujets']) : array();
        
        // Validations supplémentaires
        if (empty($date) || empty($heure) || empty($nom) || empty($prenom) || empty($entite) || empty($email)) {
            $this->show_message('Tous les champs sont obligatoires.', 'error');
            return;
        }
        
        if (empty($sujets)) {
            $this->show_message('Veuillez sélectionner au moins un sujet de visite.', 'error');
            return;
        }
        
        if (!is_email($email)) {
            $this->show_message('Adresse email invalide.', 'error');
            return;
        }
        
        if (strlen($nom) < 2 || strlen($prenom) < 2 || strlen($entite) < 2) {
            $this->show_message('Le nom, prénom et entité doivent contenir au moins 2 caractères.', 'error');
            return;
        }
        
        // Vérifier disponibilité
        if (!$this->is_slot_available($date, $heure)) {
            $this->show_message('Désolé, ce créneau n\'est plus disponible.', 'error');
            return;
        }
        
        // Enregistrer la réservation
        if ($this->save_reservation($date, $heure, $nom, $prenom, $entite, $email, $sujets)) {
            $this->send_notifications($date, $heure, $nom, $prenom, $entite, $email, $sujets);
            $this->show_message("Merci $prenom $nom ! Votre rendez-vous est confirmé pour le $date à $heure.", 'success');
        } else {
            $this->show_message('Erreur lors de l\'enregistrement. Veuillez réessayer.', 'error');
        }
    }
    
    // ----------------------
    // Sauvegarder réservation
    // ----------------------
    private function save_reservation($date, $heure, $nom, $prenom, $entite, $email, $sujets) {
        $fp = fopen($this->reservations_file, 'a');
        if ($fp === false) {
            return false;
        }
        
        $sujets_str = implode(', ', $sujets);
        fputcsv($fp, array($date, $heure, $nom, $prenom, $entite, $email, $sujets_str, current_time('mysql')));
        fclose($fp);
        return true;
    }
    
    // ----------------------
    // Envoyer notifications
    // ----------------------
    private function send_notifications($date, $heure, $nom, $prenom, $entite, $email, $sujets) {
        $sujets_str = implode(', ', $sujets);
        
        // Récupérer les templates personnalisés
        $subject_client = get_option('reservations_email_client_subject', 'Confirmation de votre rendez-vous');
        $message_client_template = get_option('reservations_email_client_message', 
            "Bonjour {prenom} {nom},\n\nVotre rendez-vous est confirmé pour le {date} à {heure}.\n\nEntité : {entite}\nSujet(s) : {sujets}\n\nNous vous attendons avec plaisir !\n\nCordialement,\nL'équipe"
        );
        
        // Remplacer les variables dans le message client
        $message_client = str_replace(
            array('{nom}', '{prenom}', '{entite}', '{date}', '{heure}', '{sujets}', '{email}'),
            array($nom, $prenom, $entite, $date, $heure, $sujets_str, $email),
            $message_client_template
        );
        
        wp_mail($email, $subject_client, $message_client);
        
        // Email admin
        $admin_email = get_option('reservations_admin_email', get_option('admin_email'));
        $subject_admin = get_option('reservations_email_admin_subject', "Nouvelle réservation : {date} à {heure}");
        $subject_admin = str_replace(array('{date}', '{heure}'), array($date, $heure), $subject_admin);
        
        $message_admin_template = get_option('reservations_email_admin_message',
            "Nouvelle réservation enregistrée :\n\nNom : {nom}\nPrénom : {prenom}\nEntité : {entite}\nEmail : {email}\nDate : {date}\nHeure : {heure}\nSujet(s) : {sujets}"
        );
        
        $message_admin = str_replace(
            array('{nom}', '{prenom}', '{entite}', '{date}', '{heure}', '{sujets}', '{email}'),
            array($nom, $prenom, $entite, $date, $heure, $sujets_str, $email),
            $message_admin_template
        );
        
        wp_mail($admin_email, $subject_admin, $message_admin);
    }
    
    // ----------------------
    // Afficher message
    // ----------------------
    private function show_message($message, $type = 'success') {
        add_action('wp_footer', function() use ($message, $type) {
            echo '<div class="reservation-message ' . esc_attr($type) . '">';
            echo esc_html($message);
            echo '</div>';
        }, 5);
    }
    
    // ----------------------
    // Menu admin
    // ----------------------
    public function add_admin_menu() {
        add_menu_page(
            'Réservations',
            'Réservations',
            'manage_options',
            'reservations-admin',
            array($this, 'display_reservations'),
            'dashicons-calendar-alt',
            20
        );
        
        add_submenu_page(
            'reservations-admin',
            'Créneaux bloqués',
            'Créneaux bloqués',
            'manage_options',
            'reservations-bloques',
            array($this, 'display_bloques')
        );
        
        add_submenu_page(
            'reservations-admin',
            'Sujets de visite',
            'Sujets de visite',
            'manage_options',
            'reservations-sujets',
            array($this, 'display_sujets')
        );
        
        add_submenu_page(
            'reservations-admin',
            'Configuration emails',
            'Configuration emails',
            'manage_options',
            'reservations-emails',
            array($this, 'display_email_settings')
        );
    }
    
    // ----------------------
    // Afficher réservations admin
    // ----------------------
    public function display_reservations() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Gestion suppression
        if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_reservation')) {
                $this->delete_reservation(intval($_GET['delete']));
                echo '<div class="notice notice-success"><p>Réservation supprimée.</p></div>';
            }
        }
        
        echo '<div class="wrap">';
        echo '<div class="reservations-admin-header">';
        echo '<h1>📅 Réservations</h1>';
        echo '<a class="button button-primary" href="?page=reservations-admin&export=1&_wpnonce=' . wp_create_nonce('export_reservations') . '">⬇️ Exporter CSV</a>';
        echo '</div>';
        
        // Statistiques
        $total = $this->count_reservations();
        echo '<div class="reservations-stats">';
        echo '<div class="stat-box">';
        echo '<h3>Total des réservations</h3>';
        echo '<div class="number">' . $total . '</div>';
        echo '</div>';
        echo '</div>';
        
        if (!file_exists($this->reservations_file) || $total == 0) {
            echo '<p>Aucune réservation pour le moment.</p>';
            echo '</div>';
            return;
        }
        
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Date</th><th>Heure</th><th>Nom</th><th>Prénom</th><th>Entité</th><th>Email</th><th>Sujet(s)</th><th>Enregistré le</th><th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($lines as $index => $line) {
            $data = str_getcsv($line);
            if (count($data) < 7) continue;
            
            $date = esc_html($data[0]);
            $heure = esc_html($data[1]);
            $nom = esc_html($data[2]);
            $prenom = esc_html($data[3]);
            $entite = esc_html($data[4]);
            $email = esc_html($data[5]);
            $sujets = esc_html($data[6]);
            $created = isset($data[7]) ? esc_html($data[7]) : 'N/A';
            
            $delete_url = add_query_arg(array(
                'page' => 'reservations-admin',
                'delete' => $index,
                '_wpnonce' => wp_create_nonce('delete_reservation')
            ), admin_url('admin.php'));
            
            echo '<tr>';
            echo "<td><strong>$date</strong></td>";
            echo "<td>$heure</td>";
            echo "<td>$nom</td>";
            echo "<td>$prenom</td>";
            echo "<td>$entite</td>";
            echo "<td><a href='mailto:$email'>$email</a></td>";
            echo "<td>$sujets</td>";
            echo "<td>$created</td>";
            echo "<td><a href='$delete_url' onclick='return confirm(\"Supprimer cette réservation ?\");' class='button button-small'>❌ Supprimer</a></td>";
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    // ----------------------
    // Compter réservations
    // ----------------------
    private function count_reservations() {
        if (!file_exists($this->reservations_file)) {
            return 0;
        }
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return count($lines);
    }
    
    // ----------------------
    // Supprimer réservation
    // ----------------------
    private function delete_reservation($index) {
        if (!file_exists($this->reservations_file)) {
            return;
        }
        
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (isset($lines[$index])) {
            unset($lines[$index]);
            file_put_contents($this->reservations_file, implode("\n", $lines) . "\n");
        }
    }
    
    // ----------------------
    // Export CSV
    // ----------------------
    public function handle_csv_export() {
        if (!isset($_GET['page']) || $_GET['page'] != 'reservations-admin') {
            return;
        }
        
        if (!isset($_GET['export']) || $_GET['export'] != 1) {
            return;
        }
        
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_reservations')) {
            wp_die('Erreur de sécurité');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Accès interdit');
        }
        
        if (!file_exists($this->reservations_file)) {
            wp_die('Aucune réservation à exporter');
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reservations-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        
        fputcsv($output, array('Date', 'Heure', 'Nom', 'Prénom', 'Entité', 'Email', 'Sujet(s)', 'Enregistré le'));
        
        $lines = file($this->reservations_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            fputcsv($output, $data);
        }
        
        fclose($output);
        exit;
    }
    
    // ----------------------
    // Afficher créneaux bloqués
    // ----------------------
    public function display_bloques() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Ajouter un créneau bloqué
        if (isset($_POST['bloquer']) && check_admin_referer('bloquer_creneau', 'bloquer_nonce')) {
            $date = sanitize_text_field($_POST['date']);
            $heure = sanitize_text_field($_POST['heure']);
            
            $fp = fopen($this->bloques_file, 'a');
            fputcsv($fp, array($date, $heure));
            fclose($fp);
            
            echo '<div class="notice notice-success"><p>Créneau bloqué : ' . esc_html($date) . ' à ' . esc_html($heure) . '</p></div>';
        }
        
        // Supprimer un créneau bloqué
        if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_bloque')) {
                $index = intval($_GET['delete']);
                $lines = file($this->bloques_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (isset($lines[$index])) {
                    unset($lines[$index]);
                    file_put_contents($this->bloques_file, implode("\n", $lines) . "\n");
                    echo '<div class="notice notice-success"><p>Créneau débloqué.</p></div>';
                }
            }
        }
        
        echo '<div class="wrap">';
        echo '<h1>🚫 Créneaux bloqués</h1>';
        
        echo '<div class="bloquer-form">';
        echo '<h2>Bloquer un nouveau créneau</h2>';
        echo '<form method="post">';
        wp_nonce_field('bloquer_creneau', 'bloquer_nonce');
        echo '<label>Date : <input type="date" name="date" required></label>';
        echo '<label>Heure : <select name="heure">';
        foreach ($this->get_heures() as $h) {
            echo '<option value="' . esc_attr($h) . '">' . esc_html($h) . '</option>';
        }
        echo '</select></label>';
        echo '<input type="submit" name="bloquer" class="button button-primary" value="Bloquer ce créneau">';
        echo '</form>';
        echo '</div>';
        
        if (file_exists($this->bloques_file)) {
            $lines = file($this->bloques_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if (count($lines) > 0) {
                echo '<h2>Créneaux actuellement bloqués</h2>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Date</th><th>Heure</th><th>Actions</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($lines as $index => $line) {
                    $data = str_getcsv($line);
                    if (count($data) < 2) continue;
                    
                    $date = esc_html($data[0]);
                    $heure = esc_html($data[1]);
                    
                    $delete_url = add_query_arg(array(
                        'page' => 'reservations-bloques',
                        'delete' => $index,
                        '_wpnonce' => wp_create_nonce('delete_bloque')
                    ), admin_url('admin.php'));
                    
                    echo '<tr>';
                    echo "<td><strong>$date</strong></td>";
                    echo "<td>$heure</td>";
                    echo "<td><a href='$delete_url' onclick='return confirm(\"Débloquer ce créneau ?\");' class='button button-small'>✅ Débloquer</a></td>";
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<p>Aucun créneau bloqué actuellement.</p>';
            }
        }
        
        echo '</div>';
    }
    
    // ----------------------
    // Afficher sujets de visite
    // ----------------------
    public function display_sujets() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Mode édition
        $edit_mode = isset($_GET['edit']) ? intval($_GET['edit']) : null;
        
        // Ajouter un sujet
        if (isset($_POST['ajouter_sujet']) && check_admin_referer('ajouter_sujet', 'sujet_nonce')) {
            $sujet = sanitize_text_field($_POST['sujet']);
            
            if (!empty($sujet)) {
                $fp = fopen($this->sujets_file, 'a');
                fputcsv($fp, array($sujet));
                fclose($fp);
                
                echo '<div class="notice notice-success"><p>Sujet ajouté : ' . esc_html($sujet) . '</p></div>';
            }
        }
        
        // Modifier un sujet
        if (isset($_POST['modifier_sujet']) && check_admin_referer('modifier_sujet', 'modifier_sujet_nonce')) {
            $index = intval($_POST['sujet_index']);
            $nouveau_sujet = sanitize_text_field($_POST['nouveau_sujet']);
            
            if (!empty($nouveau_sujet) && file_exists($this->sujets_file)) {
                $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (isset($lines[$index])) {
                    $data = str_getcsv($lines[$index]);
                    $data[0] = $nouveau_sujet;
                    $lines[$index] = '"' . str_replace('"', '""', $nouveau_sujet) . '"';
                    file_put_contents($this->sujets_file, implode("\n", $lines) . "\n");
                    
                    echo '<div class="notice notice-success"><p>Sujet modifié avec succès.</p></div>';
                    $edit_mode = null;
                }
            }
        }
        
        // Supprimer un sujet
        if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_sujet')) {
                $index = intval($_GET['delete']);
                
                if (file_exists($this->sujets_file)) {
                    $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if (isset($lines[$index])) {
                        unset($lines[$index]);
                        file_put_contents($this->sujets_file, implode("\n", $lines) . "\n");
                        echo '<div class="notice notice-success"><p>Sujet supprimé.</p></div>';
                    }
                }
            }
        }
        
        echo '<div class="wrap">';
        echo '<h1>📋 Sujets de visite</h1>';
        
        echo '<div class="bloquer-form">';
        echo '<h2>Ajouter un nouveau sujet</h2>';
        echo '<form method="post">';
        wp_nonce_field('ajouter_sujet', 'sujet_nonce');
        echo '<label>Nom du sujet : <input type="text" name="sujet" required style="width: 300px;"></label>';
        echo '<input type="submit" name="ajouter_sujet" class="button button-primary" value="Ajouter ce sujet">';
        echo '</form>';
        echo '</div>';
        
        $sujets = $this->get_sujets();
        
        if (!empty($sujets)) {
            echo '<h2>Sujets actuellement disponibles</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th style="width: 70%;">Sujet</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            if (file_exists($this->sujets_file)) {
                $lines = file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $index => $line) {
                    $data = str_getcsv($line);
                    if (empty($data[0])) continue;
                    
                    $sujet = $data[0];
                    
                    // Si on est en mode édition pour ce sujet
                    if ($edit_mode === $index) {
                        echo '<tr class="edit-mode">';
                        echo '<td>';
                        echo '<form method="post" style="display: flex; gap: 10px; align-items: center;">';
                        wp_nonce_field('modifier_sujet', 'modifier_sujet_nonce');
                        echo '<input type="hidden" name="sujet_index" value="' . $index . '">';
                        echo '<input type="text" name="nouveau_sujet" value="' . esc_attr($sujet) . '" required style="width: 100%; max-width: 400px;">';
                        echo '<input type="submit" name="modifier_sujet" class="button button-primary" value="💾 Enregistrer">';
                        echo '<a href="?page=reservations-sujets" class="button">❌ Annuler</a>';
                        echo '</form>';
                        echo '</td>';
                        echo '<td></td>';
                        echo '</tr>';
                    } else {
                        $delete_url = add_query_arg(array(
                            'page' => 'reservations-sujets',
                            'delete' => $index,
                            '_wpnonce' => wp_create_nonce('delete_sujet')
                        ), admin_url('admin.php'));
                        
                        $edit_url = add_query_arg(array(
                            'page' => 'reservations-sujets',
                            'edit' => $index
                        ), admin_url('admin.php'));
                        
                        echo '<tr>';
                        echo "<td><strong>" . esc_html($sujet) . "</strong></td>";
                        echo '<td>';
                        echo '<a href="' . esc_url($edit_url) . '" class="button button-small">✏️ Modifier</a> ';
                        echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Supprimer ce sujet ?\');" class="button button-small">❌ Supprimer</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
            } else {
                // Afficher les sujets par défaut
                foreach ($sujets as $sujet) {
                    echo '<tr>';
                    echo "<td><strong>" . esc_html($sujet) . "</strong></td>";
                    echo "<td><em>Sujet par défaut (créez un fichier pour pouvoir modifier)</em></td>";
                    echo '</tr>';
                }
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>Aucun sujet configuré.</p>';
        }
        
        echo '</div>';
    }
    
    // ----------------------
    // Afficher configuration emails
    // ----------------------
    public function display_email_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Sauvegarder les paramètres
        if (isset($_POST['save_email_settings']) && check_admin_referer('save_email_settings', 'email_settings_nonce')) {
            update_option('reservations_admin_email', sanitize_email($_POST['admin_email']));
            update_option('reservations_email_client_subject', sanitize_text_field($_POST['client_subject']));
            update_option('reservations_email_client_message', sanitize_textarea_field($_POST['client_message']));
            update_option('reservations_email_admin_subject', sanitize_text_field($_POST['admin_subject']));
            update_option('reservations_email_admin_message', sanitize_textarea_field($_POST['admin_message']));
            
            echo '<div class="notice notice-success"><p>Configuration des emails mise à jour.</p></div>';
        }
        
        $admin_email = get_option('reservations_admin_email', get_option('admin_email'));
        $client_subject = get_option('reservations_email_client_subject', 'Confirmation de votre rendez-vous');
        $client_message = get_option('reservations_email_client_message', 
            "Bonjour {prenom} {nom},\n\nVotre rendez-vous est confirmé pour le {date} à {heure}.\n\nEntité : {entite}\nSujet(s) : {sujets}\n\nNous vous attendons avec plaisir !\n\nCordialement,\nL'équipe"
        );
        $admin_subject = get_option('reservations_email_admin_subject', "Nouvelle réservation : {date} à {heure}");
        $admin_message = get_option('reservations_email_admin_message',
            "Nouvelle réservation enregistrée :\n\nNom : {nom}\nPrénom : {prenom}\nEntité : {entite}\nEmail : {email}\nDate : {date}\nHeure : {heure}\nSujet(s) : {sujets}"
        );
        
        echo '<div class="wrap">';
        echo '<h1>📧 Configuration des emails</h1>';
        
        echo '<div class="email-variables-info">';
        echo '<h3>Variables disponibles</h3>';
        echo '<p>Vous pouvez utiliser les variables suivantes dans vos templates d\'emails :</p>';
        echo '<ul>';
        echo '<li><code>{nom}</code> - Nom du visiteur</li>';
        echo '<li><code>{prenom}</code> - Prénom du visiteur</li>';
        echo '<li><code>{entite}</code> - Entité/organisation</li>';
        echo '<li><code>{email}</code> - Email du visiteur</li>';
        echo '<li><code>{date}</code> - Date de la réservation</li>';
        echo '<li><code>{heure}</code> - Heure de la réservation</li>';
        echo '<li><code>{sujets}</code> - Sujet(s) de la visite</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<form method="post" class="email-settings-form">';
        wp_nonce_field('save_email_settings', 'email_settings_nonce');
        
        echo '<h2>📬 Email de notification admin</h2>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="admin_email">Email administrateur</label></th>';
        echo '<td><input type="email" id="admin_email" name="admin_email" value="' . esc_attr($admin_email) . '" class="regular-text" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="admin_subject">Sujet de l\'email admin</label></th>';
        echo '<td><input type="text" id="admin_subject" name="admin_subject" value="' . esc_attr($admin_subject) . '" class="large-text" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="admin_message">Message de l\'email admin</label></th>';
        echo '<td><textarea id="admin_message" name="admin_message" rows="8" class="large-text" required>' . esc_textarea($admin_message) . '</textarea></td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<h2>👤 Email de confirmation client</h2>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="client_subject">Sujet de l\'email client</label></th>';
        echo '<td><input type="text" id="client_subject" name="client_subject" value="' . esc_attr($client_subject) . '" class="large-text" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="client_message">Message de l\'email client</label></th>';
        echo '<td><textarea id="client_message" name="client_message" rows="8" class="large-text" required>' . esc_textarea($client_message) . '</textarea></td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="save_email_settings" class="button button-primary" value="💾 Enregistrer la configuration">';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
}

// Initialiser le plugin
new ReservationsPlugin();
