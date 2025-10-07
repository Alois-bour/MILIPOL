<?php
/**
 * Plugin Name: Custom Reservations (Final Version)
 * Description: A comprehensive booking plugin with a custom frontend design, powered by the WordPress database, with fully functional admin controls.
 * Version: 9.0
 * Author: Jules (Corrected by AI)
 * Text Domain: reservations-personnalise
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

final class ReservationsPlugin {

    private static $instance = null;

    // Database table names
    private $table_reservations;
    private $table_blocked_slots;
    private $table_subjects;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_reservations = $wpdb->prefix . 'reservations_plugin';
        $this->table_blocked_slots = $wpdb->prefix . 'reservations_blocked_slots';
        $this->table_subjects = $wpdb->prefix . 'reservations_subjects';

        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'install_database']);

        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);

        add_shortcode('reservation_form', [$this, 'render_form']);

        add_action('admin_menu', [$this, 'add_admin_menu']);

        // All form submissions are handled by admin-post.php
        add_action('admin_post_reservations_submit', [$this, 'handle_form_submission']);
        add_action('admin_post_nopriv_reservations_submit', [$this, 'handle_form_submission']);
        add_action('admin_post_reservations_export', [$this, 'handle_csv_export']);
        add_action('admin_post_reservations_add_blocked_slot', [$this, 'handle_add_blocked_slot']);
        add_action('admin_post_reservations_delete_blocked_slot', [$this, 'handle_delete_blocked_slot']);
        add_action('admin_post_reservations_add_subject', [$this, 'handle_add_subject']);
        add_action('admin_post_reservations_delete_subject', [$this, 'handle_delete_subject']);
        add_action('admin_post_reservations_delete_reservation', [$this, 'handle_delete_reservation']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('reservations-personnalise', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function install_database() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$this->table_reservations} (id mediumint(9) NOT NULL AUTO_INCREMENT, reservation_date date NOT NULL, reservation_time time NOT NULL, nom varchar(100) NOT NULL, prenom varchar(100) NOT NULL, entite varchar(100) NOT NULL, email varchar(100) NOT NULL, sujets text NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY unique_slot (reservation_date, reservation_time)) $charset_collate;");
        dbDelta("CREATE TABLE {$this->table_blocked_slots} (id mediumint(9) NOT NULL AUTO_INCREMENT, blocked_date date NOT NULL, blocked_time time NOT NULL, PRIMARY KEY (id), UNIQUE KEY unique_blocked_slot (blocked_date, blocked_time)) $charset_collate;");
        dbDelta("CREATE TABLE {$this->table_subjects} (id mediumint(9) NOT NULL AUTO_INCREMENT, sujet varchar(255) NOT NULL, PRIMARY KEY (id), UNIQUE KEY sujet (sujet)) $charset_collate;");
    }

    public function enqueue_styles() {
        wp_register_style('reservations-style', false);
        wp_enqueue_style('reservations-style');
        $css = "
            .reservation-form-wrapper { max-width: 600px; margin: 20px auto; }
            .reservation-form { padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
            .reservation-form label { display: block; margin-bottom: 12px; font-weight: 600; color: #333; font-size: 14px; }
            .date-buttons { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 25px; }
            .date-button { padding: 20px 15px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fff; cursor: pointer; transition: all 0.3s; text-align: center; position: relative; }
            .date-button:hover { border-color: #0073aa; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,115,170,0.15); }
            .date-button.selected { border-color: #0073aa; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; }
            .date-button.disabled { opacity: 0.4; cursor: not-allowed; background: #f5f5f5; }
            .date-button.disabled:hover { border-color: #e0e0e0; transform: none; box-shadow: none; }
            .date-button .day { font-size: 12px; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
            .date-button .date-num { font-size: 24px; font-weight: bold; }
            .date-button .month { font-size: 12px; margin-top: 3px; }
            .date-button input[type='radio'] { position: absolute; opacity: 0; pointer-events: none; }
            .reservation-form input[type='text'], .reservation-form input[type='email'], .reservation-form select { width: 100%; padding: 12px; margin-bottom: 20px; border: 2px solid #e0e0e0; border-radius: 6px; box-sizing: border-box; font-size: 15px; transition: border-color 0.3s; }
            .reservation-form input:focus, .reservation-form select:focus { outline: none; border-color: #0073aa; }
            .reservation-form input[type='submit'] { width: 100%; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; border: none; padding: 14px 20px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; transition: all 0.3s; }
            .reservation-form input[type='submit']:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,115,170,0.3); }
            .reservation-message { max-width: 600px; margin: 20px auto; padding: 15px 20px; border-radius: 6px; text-align: center; font-weight: 500; }
            .reservation-message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .reservation-message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            @media (max-width: 480px) { .reservation-form { padding: 20px; } .date-buttons { grid-template-columns: 1fr; } }
        ";
        wp_add_inline_style('reservations-style', $css);
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'reservations') === false) return;
        $css = ".reservations-admin-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.reservations-stats{display:flex;gap:20px;margin-bottom:30px}.stat-box{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05);flex:1}.stat-box h3{margin:0 0 10px;font-size:14px;color:#666}.stat-box .number{font-size:32px;font-weight:700;color:#0073aa}.bloquer-form{background:#fff;padding:20px;border-radius:8px;margin-bottom:30px}.bloquer-form label{display:inline-block;margin-right:15px}";
        wp_register_style('reservations-admin-inline-style',false);wp_enqueue_style('reservations-admin-inline-style');wp_add_inline_style('reservations-admin-inline-style',$css);
    }

    private function get_dates() { return ["2025-11-18"=>"Mardi 18 nov.","2025-11-19"=>"Mercredi 19 nov.","2025-11-20"=>"Jeudi 20 nov."]; }
    private function get_heures() { return ["09:00","10:00","11:00","14:00","15:00","16:00"]; }
    private function get_sujets() { global $wpdb; $res = $wpdb->get_col("SELECT sujet FROM {$this->table_subjects} ORDER BY sujet ASC"); return empty($res) ? [] : $res; }
    private function is_slot_available($d, $h) { global $wpdb; return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_reservations} WHERE reservation_date=%s AND reservation_time=%s",$d,$h.':00'))==0 && $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_blocked_slots} WHERE blocked_date=%s AND blocked_time=%s",$d,$h.':00'))==0; }

    public function render_form() {
        $dates = $this->get_dates(); $heures = $this->get_heures(); $sujets = $this->get_sujets();
        $disponibilites = [];
        foreach ($dates as $date_val => $date_label) {
            $disponibilites[$date_val] = [];
            foreach ($heures as $heure) if ($this->is_slot_available($date_val, $heure)) $disponibilites[$date_val][] = $heure;
        }
        $has_slots = false;
        foreach ($disponibilites as $slots) if (!empty($slots)) { $has_slots = true; break; }
        ob_start();
        ?>
        <div class="reservation-form-wrapper">
             <?php if (isset($_GET['reservation_error'])): ?><div class="reservation-message error"><?php echo esc_html(urldecode($_GET['reservation_error'])); ?></div><?php elseif (isset($_GET['reservation_success'])): ?><div class="reservation-message success"><?php echo esc_html(urldecode($_GET['reservation_success'])); ?></div><?php endif; ?>
            <?php if (!$has_slots): ?><div class="reservation-message error">😔 Désolé, aucun créneau n'est disponible pour le moment.</div>
            <?php else: ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="reservation-form" id="reservation-form">
                    <input type="hidden" name="action" value="reservations_submit">
                    <?php wp_nonce_field('reservation_action', 'reservation_nonce'); ?>
                    <label>📅 Choisissez une date :</label>
                    <div class="date-buttons">
                        <?php $first_available = null; foreach ($dates as $val => $label): $has_availability = !empty($disponibilites[$val]); if ($has_availability && $first_available === null) $first_available = $val; $date_obj = DateTime::createFromFormat('Y-m-d', $val); $day_name = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'][$date_obj->format('w')]; $day_num = $date_obj->format('d'); $month_name = ['','Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'][(int)$date_obj->format('n')]; ?>
                            <label class="date-button <?php echo !$has_availability ? 'disabled' : ''; ?>"><input type="radio" name="date" value="<?php echo esc_attr($val); ?>" <?php disabled(!$has_availability); checked($val, $first_available); ?> required><div class="day"><?php echo esc_html($day_name); ?></div><div class="date-num"><?php echo esc_html($day_num); ?></div><div class="month"><?php echo esc_html($month_name); ?></div></label>
                        <?php endforeach; ?>
                    </div>
                    <label for="heure">🕐 Choisissez une heure :</label><select name="heure" id="heure" required></select>
                    <label for="nom">Nom :</label><input type="text" name="nom" id="nom" placeholder="Votre nom" required minlength="2">
                    <label for="prenom">Prénom :</label><input type="text" name="prenom" id="prenom" placeholder="Votre prénom" required minlength="2">
                    <label for="entite">Entité :</label><input type="text" name="entite" id="entite" placeholder="Votre entité" required minlength="2">
                    <label for="email">📧 Votre email :</label><input type="email" name="email" id="email" placeholder="exemple@email.com" required>
                    <label>Sujet de la visite :</label><div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;"><?php foreach ($sujets as $s):?><label style="display:flex;align-items:center;font-weight:400;"><input type="checkbox" name="sujets[]" value="<?php echo esc_attr($s);?>"><span style="margin-left:8px"><?php echo esc_html($s);?></span></label><?php endforeach;?></div>
                    <input type="submit" name="reserver" value="Réserver mon créneau">
                </form>
                <script>
                document.addEventListener("DOMContentLoaded",function(){const e=document.querySelectorAll("input[name='date']"),t=document.getElementById("heure"),n=<?php echo json_encode($disponibilites);?>;function a(){const a=document.querySelector("input[name='date']:checked");if(!a)return;const o=a.value,d=n[o]||[];if(t.innerHTML="",0===d.length){const e=document.createElement("option");e.textContent="Aucun créneau disponible",e.disabled=!0,t.appendChild(e)}else d.forEach(e=>{const n=document.createElement("option");n.value=e,n.textContent=e,t.appendChild(n)})}function o(){document.querySelectorAll(".date-button").forEach(e=>e.classList.remove("selected"));const e=document.querySelector("input[name='date']:checked");e&&e.parentElement.classList.add("selected")}e.forEach(e=>{e.addEventListener("change",()=>{o(),a()})}),o(),a()});
                </script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_form_submission() {
        if (!isset($_POST['reservation_nonce']) || !wp_verify_nonce($_POST['reservation_nonce'], 'reservation_action')) wp_die('Erreur de sécurité.');
        $date = sanitize_text_field($_POST['date']); $heure = sanitize_text_field($_POST['heure']); $nom = sanitize_text_field($_POST['nom']); $prenom = sanitize_text_field($_POST['prenom']); $entite = sanitize_text_field($_POST['entite']); $email = sanitize_email($_POST['email']); $sujets = isset($_POST['sujets']) ? array_map('sanitize_text_field', (array)$_POST['sujets']) : [];
        if (empty($date)||empty($heure)||empty($nom)||empty($prenom)||empty($entite)||!is_email($email)||empty($sujets)) { wp_safe_redirect(add_query_arg('reservation_error',urlencode('Tous les champs sont obligatoires.'),wp_get_referer())); exit; }
        if (!$this->is_slot_available($date, $heure)) { wp_safe_redirect(add_query_arg('reservation_error',urlencode('Désolé, ce créneau n\'est plus disponible.'),wp_get_referer())); exit; }
        if ($this->save_reservation(compact('date','heure','nom','prenom','entite','email','sujets'))) {
            $this->send_notifications(compact('date','heure','nom','prenom','entite','email','sujets'));
            wp_safe_redirect(add_query_arg('reservation_success',urlencode("Merci $prenom $nom! Votre rendez-vous est confirmé."),wp_get_referer())); exit;
        } else { wp_safe_redirect(add_query_arg('reservation_error',urlencode('Erreur lors de l\'enregistrement.'),wp_get_referer())); exit; }
    }

    private function save_reservation($data) { global $wpdb; return $wpdb->insert($this->table_reservations, [ 'reservation_date' => $data['date'], 'reservation_time' => $data['heure'], 'nom' => $data['nom'], 'prenom' => $data['prenom'], 'entite' => $data['entite'], 'email' => $data['email'], 'sujets' => implode(', ', $data['sujets']), 'created_at' => current_time('mysql') ]); }
    private function send_notifications($data) { extract($data); $sujets_str = implode(', ', $sujets); $subject_client = 'Confirmation de votre rendez-vous'; $message_client = "Bonjour $prenom $nom,\n\nVotre rendez-vous est confirmé pour le $date à $heure.\n\nSujet(s): $sujets_str\n\nCordialement,\nL'équipe"; wp_mail($email, $subject_client, $message_client); $admin_email = get_option('admin_email'); $subject_admin = "Nouvelle réservation : $date à $heure"; $message_admin = "Nouvelle réservation :\nNom : $prenom $nom\nEntité : $entite\nEmail : $email\nDate : $date\nHeure : $heure\nSujet(s) : $sujets_str"; wp_mail($admin_email, $subject_admin, $message_admin); }

    public function add_admin_menu() { add_menu_page('Réservations','Réservations','manage_options','reservations-admin',[$this,'display_reservations'],'dashicons-calendar-alt',20); add_submenu_page('reservations-admin','Créneaux bloqués','Créneaux bloqués','manage_options','reservations-bloques',[$this,'display_bloques']); add_submenu_page('reservations-admin','Sujets','Sujets','manage_options','reservations-sujets',[$this,'display_sujets']); }

    public function display_reservations() { global $wpdb; echo '<div class="wrap"><div class="reservations-admin-header"><h1>📅 Réservations</h1><a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=reservations_export'),'export_reservations')).'">⬇️ Exporter CSV</a></div>'; $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_reservations}"); echo '<div class="reservations-stats"><div class="stat-box"><h3>Total des réservations</h3><div class="number">'.$total.'</div></div></div>'; $results = $wpdb->get_results("SELECT * FROM {$this->table_reservations} ORDER BY reservation_date DESC, reservation_time DESC"); if(!empty($results)){ echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Nom</th><th>Prénom</th><th>Entité</th><th>Email</th><th>Date</th><th>Heure</th><th>Sujets</th><th>Actions</th></tr></thead><tbody>'; foreach($results as $row){$del_url=wp_nonce_url(admin_url('admin-post.php?action=reservations_delete_reservation&id='.$row->id),'delete_reservation_'.$row->id); echo'<tr><td>'.esc_html($row->nom).'</td><td>'.esc_html($row->prenom).'</td><td>'.esc_html($row->entite).'</td><td><a href="mailto:'.esc_attr($row->email).'">'.esc_html($row->email).'</a></td><td>'.esc_html($row->reservation_date).'</td><td>'.esc_html(substr($row->reservation_time,0,5)).'</td><td>'.esc_html($row->sujets).'</td><td><a href="'.esc_url($del_url).'" class="button button-small" onclick="return confirm(\'Confirmer la suppression?\')">Supprimer</a></td></tr>';} echo '</tbody></table>';}else{echo '<p>Aucune réservation.</p>';} echo '</div>'; }
    public function display_bloques() { global $wpdb; echo '<div class="wrap"><h1>🚫 Créneaux bloqués</h1><div class="bloquer-form"><h2>Bloquer un nouveau créneau</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="reservations_add_blocked_slot">'; wp_nonce_field('add_blocked_slot','add_blocked_nonce'); echo '<label>Date: <input type="date" name="date" required></label><label>Heure: <select name="heure">'; foreach($this->get_heures() as $h)echo'<option value="'.esc_attr($h).'">'.esc_html($h).'</option>'; echo'</select></label><button type="submit" class="button button-primary">Bloquer</button></form></div>'; $results=$wpdb->get_results("SELECT * FROM {$this->table_blocked_slots} ORDER BY blocked_date, blocked_time"); if(!empty($results)){echo '<h2>Créneaux actuellement bloqués</h2><table class="wp-list-table widefat fixed striped"><thead><tr><th>Date</th><th>Heure</th><th>Actions</th></tr></thead><tbody>'; foreach($results as $row){$del_url=wp_nonce_url(admin_url('admin-post.php?action=reservations_delete_blocked_slot&id='.$row->id),'delete_bloque_'.$row->id); echo'<tr><td>'.esc_html($row->blocked_date).'</td><td>'.esc_html(substr($row->blocked_time,0,5)).'</td><td><a href="'.esc_url($del_url).'" class="button button-small" onclick="return confirm(\'Confirmer la suppression?\')">Débloquer</a></td></tr>';} echo '</tbody></table>';}else{echo '<p>Aucun créneau bloqué.</p>';} echo '</div>'; }
    public function display_sujets() { global $wpdb; echo '<div class="wrap"><h1>📋 Sujets de visite</h1><div class="bloquer-form"><h2>Ajouter un sujet</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="reservations_add_subject">'; wp_nonce_field('add_subject_nonce','add_subject_nonce'); echo '<label for="sujet_nom">Nom du sujet :</label><input type="text" id="sujet_nom" name="sujet" required style="width:300px"><button type="submit" class="button button-primary" style="margin-left:10px">Ajouter</button></form></div>'; $results=$wpdb->get_results("SELECT * FROM {$this->table_subjects} ORDER BY sujet ASC"); if(!empty($results)){echo '<h2>Sujets actuels</h2><table class="wp-list-table widefat fixed striped"><thead><tr><th>Sujet</th><th>Actions</th></tr></thead><tbody>'; foreach($results as $row){$del_url=wp_nonce_url(admin_url('admin-post.php?action=reservations_delete_subject&id='.$row->id),'delete_subject_'.$row->id); echo'<tr><td>'.esc_html($row->sujet).'</td><td><a href="'.esc_url($del_url).'" class="button button-small" onclick="return confirm(\'Confirmer la suppression?\')">Supprimer</a></td></tr>';} echo '</tbody></table>';}else{echo '<p>Aucun sujet.</p>';} echo '</div>'; }

    public function handle_add_blocked_slot() { global $wpdb; if(isset($_POST['add_blocked_nonce'])&&wp_verify_nonce($_POST['add_blocked_nonce'],'add_blocked_slot')){$wpdb->insert($this->table_blocked_slots,['blocked_date'=>sanitize_text_field($_POST['date']),'blocked_time'=>sanitize_text_field($_POST['heure'])]);wp_safe_redirect(admin_url('admin.php?page=reservations-bloques&message=2'));exit;}}
    public function handle_delete_blocked_slot() { global $wpdb; if(isset($_GET['id'])&&isset($_GET['_wpnonce'])&&wp_verify_nonce($_GET['_wpnonce'],'delete_bloque_'.intval($_GET['id']))){$wpdb->delete($this->table_blocked_slots,['id'=>intval($_GET['id'])]);wp_safe_redirect(admin_url('admin.php?page=reservations-bloques&message=1'));exit;}}
    public function handle_add_subject() { global $wpdb; if(isset($_POST['add_subject_nonce'])&&wp_verify_nonce($_POST['add_subject_nonce'],'add_subject_nonce')){$sujet=sanitize_text_field($_POST['sujet']);if(!empty($sujet))$wpdb->insert($this->table_subjects,['sujet'=>$sujet]);wp_safe_redirect(admin_url('admin.php?page=reservations-sujets&message=1'));exit;}}
    public function handle_delete_subject() { global $wpdb; if(isset($_GET['id'])&&isset($_GET['_wpnonce'])&&wp_verify_nonce($_GET['_wpnonce'],'delete_subject_'.intval($_GET['id']))){$wpdb->delete($this->table_subjects,['id'=>intval($_GET['id'])]);wp_safe_redirect(admin_url('admin.php?page=reservations-sujets&message=2'));exit;}}
    public function handle_delete_reservation() { global $wpdb; if(isset($_GET['id'])&&isset($_GET['_wpnonce'])&&wp_verify_nonce($_GET['_wpnonce'],'delete_reservation_'.intval($_GET['id']))){$wpdb->delete($this->table_reservations,['id'=>intval($_GET['id'])]);wp_safe_redirect(admin_url('admin.php?page=reservations-admin&message=1'));exit;}}
    public function handle_csv_export() { global $wpdb; if(!isset($_GET['export'])||!current_user_can('manage_options')||!isset($_GET['_wpnonce'])||!wp_verify_nonce($_GET['_wpnonce'],'export_reservations'))return; $results=$wpdb->get_results("SELECT reservation_date,reservation_time,nom,prenom,entite,email,sujets,created_at FROM {$this->table_reservations} ORDER BY reservation_date ASC",ARRAY_A); if(empty($results))wp_die('Aucune réservation'); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=reservations-'.date('Y-m-d').'.csv'); $output=fopen('php://output','w'); fputs($output,"\xEF\xBB\xBF"); fputcsv($output,array_keys($results[0])); foreach($results as $row)fputcsv($output,$row); fclose($output); exit; }
}
ReservationsPlugin::get_instance();