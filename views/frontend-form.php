<?php
if (!defined('ABSPATH')) exit;

// Data is prepared in the calling function `render_form`
$dates = $this->get_dates();
$heures = $this->get_heures();
$sujets = $this->get_sujets();

// Calculate availabilities
$disponibilites = array();
foreach ($dates as $date_val => $date_label) {
    $disponibilites[$date_val] = array();
    foreach ($heures as $heure) {
        if ($this->is_slot_available($date_val, $heure)) {
            $disponibilites[$date_val][] = $heure;
        }
    }
}

$has_slots = false;
foreach ($disponibilites as $slots) {
    if (!empty($slots)) {
        $has_slots = true;
        break;
    }
}
?>
<div class="reservation-form-wrapper">
    <?php if (isset($_GET['reservation_success'])): ?>
        <div class="reservation-message success"><?php _e('Merci ! Votre rendez-vous a été enregistré.', 'reservations-personnalise'); ?></div>
    <?php elseif (isset($_GET['reservation_error'])): ?>
        <div class="reservation-message error">
            <?php
            $error_code = sanitize_key($_GET['reservation_error']);
            $error_messages = array(
                'fields_required' => __('Tous les champs sont obligatoires.', 'reservations-personnalise'),
                'slot_taken' => __('Désolé, ce créneau n\'est plus disponible.', 'reservations-personnalise'),
                'save_failed' => __('Erreur lors de l\'enregistrement. Veuillez réessayer.', 'reservations-personnalise'),
            );
            echo esc_html($error_messages[$error_code] ?? __('Une erreur inconnue est survenue.', 'reservations-personnalise'));
            ?>
        </div>
    <?php endif; ?>

    <?php if (!$has_slots): ?>
        <div class="reservation-message error"><?php _e('Désolé, aucun créneau n\'est disponible pour le moment.', 'reservations-personnalise'); ?></div>
    <?php else: ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="reservation-form" id="reservation-form">
            <input type="hidden" name="action" value="reservations_submit">
            <?php wp_nonce_field('reservation_action', 'reservation_nonce'); ?>

            <label><?php echo esc_html(__('Choisissez une date :', 'reservations-personnalise')); ?></label>
            <div class="date-buttons">
                <?php
                $first_available = null;
                foreach ($dates as $val => $label):
                    $has_availability = !empty($disponibilites[$val]);
                    if ($has_availability && $first_available === null) $first_available = $val;

                    $date_obj = DateTime::createFromFormat('Y-m-d', $val);
                    $day_name = wp_date('D', $date_obj->getTimestamp()); // Use wp_date for i18n
                    $day_num = $date_obj->format('d');
                    $month_name = wp_date('M', $date_obj->getTimestamp());
                ?>
                    <label class="date-button <?php echo !$has_availability ? 'disabled' : ''; ?> <?php echo $val === $first_available ? 'selected' : ''; ?>">
                        <input type="radio" name="date" value="<?php echo esc_attr($val); ?>" <?php disabled(!$has_availability); ?> <?php checked($val, $first_available); ?> required>
                        <div class="day"><?php echo esc_html($day_name); ?></div>
                        <div class="date-num"><?php echo esc_html($day_num); ?></div>
                        <div class="month"><?php echo esc_html($month_name); ?></div>
                    </label>
                <?php endforeach; ?>
            </div>

            <label for="heure"><?php echo esc_html(__('Choisissez une heure :', 'reservations-personnalise')); ?></label>
            <select name="heure" id="heure" required></select>

            <label for="nom"><?php echo esc_html(__('Nom :', 'reservations-personnalise')); ?></label>
            <input type="text" name="nom" id="nom" placeholder="<?php esc_attr_e('Nom', 'reservations-personnalise'); ?>" required minlength="2">

            <label for="prenom"><?php echo esc_html(__('Prénom :', 'reservations-personnalise')); ?></label>
            <input type="text" name="prenom" id="prenom" placeholder="<?php esc_attr_e('Prénom', 'reservations-personnalise'); ?>" required minlength="2">

            <label for="entite"><?php echo esc_html(__('Entité :', 'reservations-personnalise')); ?></label>
            <input type="text" name="entite" id="entite" placeholder="<?php esc_attr_e('Entité', 'reservations-personnalise'); ?>" required minlength="2">

            <label for="email"><?php echo esc_html(__('Votre email :', 'reservations-personnalise')); ?></label>
            <input type="email" name="email" id="email" placeholder="<?php esc_attr_e('exemple@email.com', 'reservations-personnalise'); ?>" required>

            <label><?php echo esc_html(__('Sujet de la visite :', 'reservations-personnalise')); ?></label>
            <div class="sujets-container">
                <?php foreach ($sujets as $index => $sujet): ?>
                    <label class="sujet-checkbox">
                        <input type="checkbox" name="sujets[]" value="<?php echo esc_attr($sujet); ?>" <?php checked($index, 0); ?>>
                        <span><?php echo esc_html($sujet); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <input type="submit" name="reserver" value="<?php echo esc_attr(__('Réserver mon créneau', 'reservations-personnalise')); ?>">
        </form>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const disponibilites = <?php echo json_encode($disponibilites); ?>;
            const selectHeure = document.getElementById('heure');

            function updateHeures() {
                const selectedDateRadio = document.querySelector("input[name='date']:checked");
                if (!selectedDateRadio) return;

                const selectedDate = selectedDateRadio.value;
                const heures = disponibilites[selectedDate] || [];
                selectHeure.innerHTML = '';

                if (heures.length === 0) {
                    const opt = document.createElement('option');
                    opt.textContent = '<?php echo esc_js(__('Aucun créneau disponible', 'reservations-personnalise')); ?>';
                    opt.disabled = true;
                    selectHeure.appendChild(opt);
                    return;
                }

                heures.forEach(function(h) {
                    const o = document.createElement('option');
                    o.value = h;
                    o.textContent = h;
                    selectHeure.appendChild(o);
                });
            }

            document.querySelectorAll("input[name='date']").forEach(function(radio) {
                radio.addEventListener('change', updateHeures);
            });

            // Initial population
            updateHeures();
        });
        </script>
    <?php endif; ?>
</div>