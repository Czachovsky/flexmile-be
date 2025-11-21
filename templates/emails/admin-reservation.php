<?php
/**
 * Szablon e-maila dla administratora - nowa rezerwacja
 * 
 * Dostępne zmienne:
 * @var int $rezerwacja_id - ID rezerwacji
 * @var object $samochod - Obiekt post samochodu
 * @var array $params - Parametry rezerwacji (company_name, tax_id, email, phone, rental_months, annual_mileage_limit, message)
 * @var float $cena_miesieczna - Cena miesięczna
 * @var float $cena_calkowita - Cena całkowita
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nowa rezerwacja - FlexMile</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #2c3e50; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">FlexMile</h1>
                            <p style="margin: 10px 0 0 0; color: #ecf0f1; font-size: 16px;">Nowa rezerwacja w systemie</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <h2 style="color: #2c3e50; margin-top: 0; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Szczegóły rezerwacji</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold; width: 40%;">Numer rezerwacji:</td>
                                    <td style="color: #2c3e50;"><strong>#<?php echo esc_html($rezerwacja_id); ?></strong></td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">Data:</td>
                                    <td style="color: #2c3e50;"><?php echo esc_html(date('Y-m-d H:i:s')); ?></td>
                                </tr>
                            </table>

                            <h2 style="color: #2c3e50; margin-top: 30px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Samochód</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold; width: 40%;">Nazwa:</td>
                                    <td style="color: #2c3e50;"><?php echo esc_html($samochod->post_title); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">Link:</td>
                                    <td style="color: #3498db;">
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $samochod->ID . '&action=edit')); ?>" style="color: #3498db; text-decoration: none;">
                                            Zobacz w panelu administracyjnym
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="color: #2c3e50; margin-top: 30px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Klient</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold; width: 40%;">Nazwa firmy:</td>
                                    <td style="color: #2c3e50;"><?php echo esc_html($params['company_name']); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">NIP:</td>
                                    <td style="color: #2c3e50;"><?php echo esc_html($params['tax_id']); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">Email:</td>
                                    <td style="color: #2c3e50;">
                                        <a href="mailto:<?php echo esc_attr($params['email']); ?>" style="color: #3498db; text-decoration: none;">
                                            <?php echo esc_html($params['email']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">Telefon:</td>
                                    <td style="color: #2c3e50;">
                                        <a href="tel:<?php echo esc_attr($params['phone']); ?>" style="color: #3498db; text-decoration: none;">
                                            <?php echo esc_html($params['phone']); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="color: #2c3e50; margin-top: 30px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Parametry wynajmu</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold; width: 40%;">Okres:</td>
                                    <td style="color: #2c3e50;"><?php echo esc_html($params['rental_months']); ?> miesięcy</td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">Roczny limit km:</td>
                                    <td style="color: #2c3e50;"><?php echo esc_html(number_format($params['annual_mileage_limit'], 0, ',', ' ')); ?> km</td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">Cena miesięczna:</td>
                                    <td style="color: #27ae60; font-size: 18px; font-weight: bold;">
                                        <?php echo number_format($cena_miesieczna, 2, ',', ' '); ?> zł
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #7f8c8d; font-weight: bold;">Cena całkowita:</td>
                                    <td style="color: #27ae60; font-size: 20px; font-weight: bold;">
                                        <?php echo number_format($cena_calkowita, 2, ',', ' '); ?> zł
                                    </td>
                                </tr>
                            </table>

                            <?php if (!empty($params['message'])): ?>
                            <h2 style="color: #2c3e50; margin-top: 30px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Wiadomość od klienta</h2>
                            <div style="background-color: #ecf0f1; padding: 15px; border-radius: 4px; border-left: 4px solid #3498db; color: #2c3e50; white-space: pre-wrap;">
                                <?php echo esc_html($params['message']); ?>
                            </div>
                            <?php endif; ?>

                            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1; text-align: center;">
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $rezerwacja_id . '&action=edit')); ?>" 
                                   style="display: inline-block; background-color: #3498db; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                                    Zarządzaj rezerwacją
                                </a>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #ecf0f1; padding: 20px; text-align: center; color: #7f8c8d; font-size: 12px;">
                            <p style="margin: 0;">FlexMile - System zarządzania wynajmem samochodów</p>
                            <p style="margin: 5px 0 0 0;">© <?php echo date('Y'); ?> <?php echo esc_html(get_option('blogname')); ?></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

