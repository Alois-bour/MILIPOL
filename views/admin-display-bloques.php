<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php echo esc_html__('🚫 Créneaux bloqués', 'reservations-personnalise'); ?></h1>

    <?php settings_errors(); ?>

    <div class="bloquer-form card">
        <h2><?php echo esc_html__('Bloquer un nouveau créneau', 'reservations-personnalise'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="reservations_add_blocked_slot">
            <?php wp_nonce_field('add_blocked_slot', 'add_blocked_nonce'); ?>

            <label for="date_bloque"><?php echo esc_html__('Date :', 'reservations-personnalise'); ?></label>
            <input type="date" id="date_bloque" name="date" required>

            <label for="heure_bloque"><?php echo esc_html__('Heure :', 'reservations-personnalise'); ?></label>
            <select id="heure_bloque" name="heure">
                <?php foreach ($this->get_heures() as $h) : ?>
                    <option value="<?php echo esc_attr($h); ?>"><?php echo esc_html($h); ?></option>
                <?php endforeach; ?>
            </select>

            <?php submit_button(__('Bloquer ce créneau', 'reservations-personnalise')); ?>
        </form>
    </div>

    <?php
    $lines = $this->get_file_content($this->bloques_file);
    if (!empty($lines)) :
    ?>
        <h2><?php echo esc_html__('Créneaux actuellement bloqués', 'reservations-personnalise'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Heure', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Actions', 'reservations-personnalise'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $index => $line) :
                    $data = str_getcsv($line);
                    if (count($data) < 2) continue;

                    $date = esc_html($data[0]);
                    $heure = esc_html($data[1]);

                    $delete_url = esc_url(wp_nonce_url(add_query_arg(array(
                        'page' => 'reservations-bloques',
                        'action' => 'delete_bloque',
                        'id' => $index
                    ), admin_url('admin.php')), 'delete_bloque_' . $index));
                ?>
                    <tr>
                        <td><strong><?php echo $date; ?></strong></td>
                        <td><?php echo $heure; ?></td>
                        <td>
                            <a href="<?php echo $delete_url; ?>" onclick="return confirm('<?php echo esc_js(__('Débloquer ce créneau ?', 'reservations-personnalise')); ?>');" class="button button-small">
                                ✅ <?php esc_html_e('Débloquer', 'reservations-personnalise'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo esc_html__('Aucun créneau bloqué actuellement.', 'reservations-personnalise'); ?></p>
    <?php endif; ?>
</div>