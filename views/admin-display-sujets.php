<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php echo esc_html__('📋 Sujets de visite', 'reservations-personnalise'); ?></h1>

    <?php settings_errors(); ?>

    <div class="card">
        <h2><?php echo esc_html__('Ajouter un nouveau sujet', 'reservations-personnalise'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="reservations_add_subject">
            <?php wp_nonce_field('add_subject', 'add_subject_nonce'); ?>

            <label for="sujet_nom"><?php echo esc_html__('Nom du sujet :', 'reservations-personnalise'); ?></label>
            <input type="text" id="sujet_nom" name="sujet" required style="width:300px">

            <?php submit_button(__('Ajouter ce sujet', 'reservations-personnalise')); ?>
        </form>
    </div>

    <?php if (!empty($subjects)) : ?>
        <h2><?php echo esc_html__('Sujets actuellement disponibles', 'reservations-personnalise'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Sujet', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Actions', 'reservations-personnalise'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject) :
                    $delete_url = esc_url(wp_nonce_url(add_query_arg(array(
                        'action' => 'reservations_delete_subject',
                        'id'     => $subject->id
                    ), admin_url('admin-post.php')), 'delete_subject_' . $subject->id));
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($subject->sujet); ?></strong></td>
                        <td>
                            <a href="<?php echo $delete_url; ?>" onclick="return confirm('<?php echo esc_js(__('Supprimer ce sujet ?', 'reservations-personnalise')); ?>');" class="button button-small">
                                ❌ <?php esc_html_e('Supprimer', 'reservations-personnalise'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo esc_html__('Aucun sujet configuré.', 'reservations-personnalise'); ?></p>
    <?php endif; ?>
</div>