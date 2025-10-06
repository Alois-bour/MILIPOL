<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <div class="reservations-admin-header">
        <h1><?php echo esc_html__('📅 Réservations', 'reservations-personnalise'); ?></h1>
        <a class="button button-primary" href="<?php echo $export_url; ?>">⬇️ <?php echo esc_html__('Exporter CSV', 'reservations-personnalise'); ?></a>
    </div>

    <?php settings_errors(); ?>

    <div class="reservations-stats">
        <div class="stat-box">
            <h3><?php echo esc_html__('Total des réservations', 'reservations-personnalise'); ?></h3>
            <div class="number"><?php echo intval($total); ?></div>
        </div>
    </div>

    <?php if ($total == 0) : ?>
        <p><?php echo esc_html__('Aucune réservation pour le moment.', 'reservations-personnalise'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Heure', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Nom', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Prénom', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Entité', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Email', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Sujet(s)', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Enregistré le', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Actions', 'reservations-personnalise'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $reservation) :
                    $delete_url = esc_url(wp_nonce_url(add_query_arg(array(
                        'action' => 'reservations_delete_reservation',
                        'id'     => $reservation->id
                    ), admin_url('admin-post.php')), 'delete_reservation_' . $reservation->id));
                ?>
                    <tr>
                        <td><strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->reservation_date))); ?></strong></td>
                        <td><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($reservation->reservation_time))); ?></td>
                        <td><?php echo esc_html($reservation->nom); ?></td>
                        <td><?php echo esc_html($reservation->prenom); ?></td>
                        <td><?php echo esc_html($reservation->entite); ?></td>
                        <td><a href="mailto:<?php echo esc_attr($reservation->email); ?>"><?php echo esc_html($reservation->email); ?></a></td>
                        <td><?php echo esc_html($reservation->sujets); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($reservation->created_at))); ?></td>
                        <td>
                            <a href="<?php echo $delete_url; ?>" onclick="return confirm('<?php echo esc_js(__('Supprimer cette réservation ?', 'reservations-personnalise')); ?>');" class="button button-small">
                                ❌ <?php esc_html_e('Supprimer', 'reservations-personnalise'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%', add_query_arg(array('page'=>'reservations-admin'), admin_url('admin.php'))),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => __('&laquo; Précédent'),
                        'next_text' => __('Suivant &raquo;'),
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>