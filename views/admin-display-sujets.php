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

    <?php
    $sujets = $this->get_sujets(false); // get all, not just defaults
    if (!empty($sujets)) :
    ?>
        <h2><?php echo esc_html__('Sujets actuellement disponibles', 'reservations-personnalise'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Sujet', 'reservations-personnalise'); ?></th>
                    <th><?php esc_html_e('Actions', 'reservations-personnalise'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // We need the raw lines to get the index for deletion
                $lines = file_exists($this->sujets_file) ? file($this->sujets_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
                foreach ($lines as $index => $line) :
                    $data = str_getcsv($line);
                    if (empty($data[0])) continue;

                    $sujet = esc_html($data[0]);
                    $delete_url = esc_url(wp_nonce_url(add_query_arg(array(
                        'page' => 'reservations-sujets',
                        'action' => 'delete_sujet',
                        'id' => $index
                    ), admin_url('admin.php')), 'delete_sujet_' . $index));
                ?>
                    <tr>
                        <td><strong><?php echo $sujet; ?></strong></td>
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