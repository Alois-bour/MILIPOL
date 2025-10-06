<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php echo esc_html__('📧 Configuration des emails', 'reservations-personnalise'); ?></h1>

    <div class="email-variables-info card">
        <h3><?php echo esc_html__('Variables disponibles', 'reservations-personnalise'); ?></h3>
        <p><?php echo esc_html__('Vous pouvez utiliser ces variables dans vos templates :', 'reservations-personnalise'); ?></p>
        <ul>
            <li><code>{nom}</code></li>
            <li><code>{prenom}</code></li>
            <li><code>{entite}</code></li>
            <li><code>{email}</code></li>
            <li><code>{date}</code></li>
            <li><code>{heure}</code></li>
            <li><code>{sujets}</code></li>
        </ul>
    </div>

    <form method="post" action="options.php" class="email-settings-form">
        <?php
        settings_fields('reservations_email_settings');
        do_settings_sections('reservations-emails');
        ?>

        <h2><?php esc_html_e('Expéditeur des emails', 'reservations-personnalise'); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <label for="reservations_mail_from_name"><?php esc_html_e('Nom de l\'expéditeur', 'reservations-personnalise'); ?></label>
                </th>
                <td>
                    <input type="text" id="reservations_mail_from_name" name="reservations_mail_from_name" value="<?php echo esc_attr(get_option('reservations_mail_from_name', get_bloginfo('name'))); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Ex: "Service de réservation"', 'reservations-personnalise'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="reservations_mail_from_email"><?php esc_html_e('Email de l\'expéditeur', 'reservations-personnalise'); ?></label>
                </th>
                <td>
                    <input type="email" id="reservations_mail_from_email" name="reservations_mail_from_email" value="<?php echo esc_attr(get_option('reservations_mail_from_email', get_option('admin_email'))); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Ex: "no-reply@votresite.com"', 'reservations-personnalise'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('📬 Email de notification admin', 'reservations-personnalise'); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <label for="reservations_admin_email"><?php esc_html_e('Email administrateur', 'reservations-personnalise'); ?></label>
                </th>
                <td>
                    <input type="email" id="reservations_admin_email" name="reservations_admin_email" value="<?php echo esc_attr(get_option('reservations_admin_email', get_option('admin_email'))); ?>" class="regular-text" required />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="reservations_email_admin_subject"><?php esc_html_e('Sujet de l\'email admin', 'reservations-personnalise'); ?></label>
                </th>
                <td>
                    <input type="text" id="reservations_email_admin_subject" name="reservations_email_admin_subject" value="<?php echo esc_attr(get_option('reservations_email_admin_subject', __('Nouvelle réservation : {date} à {heure}', 'reservations-personnalise'))); ?>" class="large-text" required />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="reservations_email_admin_message"><?php esc_html_e('Message de l\'email admin', 'reservations-personnalise'); ?></label>
                </th>
                <td>
                    <textarea id="reservations_email_admin_message" name="reservations_email_admin_message" rows="8" class="large-text" required><?php echo esc_textarea(get_option('reservations_email_admin_message', "Nouvelle réservation enregistrée :\n\nNom : {nom}\nPrénom : {prenom}\nEntité : {entite}\nEmail : {email}\nDate : {date}\nHeure : {heure}\nSujet(s) : {sujets}")); ?></textarea>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('👤 Email de confirmation client', 'reservations-personnalise'); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <label for="reservations_email_client_subject"><?php esc_html_e('Sujet de l\'email client', 'reservations-personnalise'); ?></label>
                </th>
                <td>
                    <input type="text" id="reservations_email_client_subject" name="reservations_email_client_subject" value="<?php echo esc_attr(get_option('reservations_email_client_subject', __('Confirmation de votre rendez-vous', 'reservations-personnalise'))); ?>" class="large-text" required />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="reservations_email_client_message"><?php esc_html_e('Message de l\'email client', 'reservations-personnalise'); ?></label>
                </th>
                <td>
                    <textarea id="reservations_email_client_message" name="reservations_email_client_message" rows="8" class="large-text" required><?php echo esc_textarea(get_option('reservations_email_client_message', "Bonjour {prenom} {nom},\n\nVotre rendez-vous est confirmé pour le {date} à {heure}.\n\nEntité : {entite}\nSujet(s) : {sujets}\n\nNous vous attendons avec plaisir !\n\nCordialement,\nL'équipe")); ?></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Options d\'envoi', 'reservations-personnalise'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="reservations_enable_html_email" <?php checked(1, get_option('reservations_enable_html_email', 1), true); ?> />
                        <?php esc_html_e('Envoyer les emails en format HTML', 'reservations-personnalise'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(__('💾 Enregistrer la configuration', 'reservations-personnalise')); ?>
    </form>

    <div class="card">
        <h2><?php esc_html_e('Tester la configuration email', 'reservations-personnalise'); ?></h2>
        <p><?php esc_html_e("Cliquez sur le bouton pour envoyer un email de test à l'adresse de l'administrateur.", 'reservations-personnalise'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="reservations_send_test_email">
            <?php wp_nonce_field('send_test_email', 'send_test_email_nonce'); ?>
            <?php submit_button(__('Envoyer un email de test', 'reservations-personnalise'), 'secondary'); ?>
        </form>
    </div>
</div>