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
                <?php foreach ($page_lines as $index => $line) :
                    $global_index = $offset + $index;
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

                    $delete_url = esc_url(add_query_arg(array(
                        'page' => 'reservations-admin',
                        'action' => 'delete_reservation',
                        'id' => $global_index,
                        '_wpnonce' => wp_create_nonce('delete_reservation_' . $global_index)
                    ), admin_url('admin.php')));
                ?>
                    <tr>
                        <td><strong><?php echo $date; ?></strong></td>
                        <td><?php echo $heure; ?></td>
                        <td><?php echo $nom; ?></td>
                        <td><?php echo $prenom; ?></td>
                        <td><?php echo $entite; ?></td>
                        <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo $email; ?></a></td>
                        <td><?php echo $sujets; ?></td>
                        <td><?php echo $created; ?></td>
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