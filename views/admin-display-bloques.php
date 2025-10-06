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

    <?php if (!empty($blocked_slots)) : ?>
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
                <?php foreach ($blocked_slots as $slot) :
                    $delete_url = esc_url(wp_nonce_url(add_query_arg(array(
                        'action' => 'reservations_delete_blocked_slot',
                        'id'     => $slot->id
                    ), admin_url('admin-post.php')), 'delete_blocked_slot_' . $slot->id));
                ?>
                    <tr>
                        <td><strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($slot->blocked_date))); ?></strong></td>
                        <td><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($slot->blocked_time))); ?></td>
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