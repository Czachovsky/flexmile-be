<?php
/**
 * Szablon e-maila dla administratora - nowe zamówienie
 *
 * Dostępne zmienne:
 * @var int $rezerwacja_id
 * @var object $samochod
 * @var array $params
 * @var float $cena_miesieczna
 * @var float $cena_calkowita
 */

if (!defined('ABSPATH')) {
    exit;
}

$consent_email = !empty($params['consent_email']);
$consent_phone = !empty($params['consent_phone']);
$pickup_location = $params['pickup_location'] ?? '';
$pickup_labels = [
    'salon' => 'Odbiór w salonie',
    'home_delivery' => 'Dostawa pod wskazany adres',
];
$pickup_text = $pickup_labels[$pickup_location] ?? 'Nie określono';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nowe zamówienie - FlexMile</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background-color: #0f172a; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">FlexMile</h1>
                            <p style="margin: 10px 0 0 0; color: #cbd5f5; font-size: 16px;">Nowe zamówienie w systemie</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <h2 style="color: #0f172a; margin-top: 0; border-bottom: 2px solid #0ea5e9; padding-bottom: 10px;">Szczegóły zamówienia</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Numer zamówienia:</td>
                                    <td style="color: #0f172a;"><strong>#<?php echo esc_html($rezerwacja_id); ?></strong></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Data:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html(date('Y-m-d H:i:s')); ?></td>
                                </tr>
                            </table>

                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 10px;">Samochód</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Nazwa:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html($samochod->post_title); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Link:</td>
                                    <td style="color: #0ea5e9;">
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $samochod->ID . '&action=edit')); ?>" style="color: #0ea5e9; text-decoration: none;">
                                            Zobacz w panelu administracyjnym
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 10px;">Klient</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Nazwa firmy:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html($params['company_name']); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">NIP:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html($params['tax_id']); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Email:</td>
                                    <td style="color: #0ea5e9;">
                                        <a href="mailto:<?php echo esc_attr($params['email']); ?>" style="color: #0ea5e9; text-decoration: none;">
                                            <?php echo esc_html($params['email']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Telefon:</td>
                                    <td style="color: #0ea5e9;">
                                        <a href="tel:<?php echo esc_attr($params['phone']); ?>" style="color: #0ea5e9; text-decoration: none;">
                                            <?php echo esc_html($params['phone']); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 10px;">Preferencje klienta</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Zgoda na e-mail:</td>
                                    <td style="color: #0f172a;"><?php echo $consent_email ? 'Tak' : 'Nie'; ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Zgoda na telefon:</td>
                                    <td style="color: #0f172a;"><?php echo $consent_phone ? 'Tak' : 'Nie'; ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Miejsce wydania:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html($pickup_text); ?></td>
                                </tr>
                            </table>

                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 10px;">Parametry zamówienia</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Okres:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html($params['rental_months']); ?> miesięcy</td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Roczny limit km:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html(number_format($params['annual_mileage_limit'], 0, ',', ' ')); ?> km</td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Cena miesięczna:</td>
                                    <td style="color: #0d9488; font-size: 18px; font-weight: bold;">
                                        <?php echo number_format($cena_miesieczna, 2, ',', ' '); ?> zł
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Cena całkowita:</td>
                                    <td style="color: #0d9488; font-size: 20px; font-weight: bold;">
                                        <?php echo number_format($cena_calkowita, 2, ',', ' '); ?> zł
                                    </td>
                                </tr>
                            </table>

                            <?php if (!empty($params['message'])): ?>
                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 10px;">Wiadomość od klienta</h2>
                            <div style="background-color: #e2e8f0; padding: 15px; border-radius: 4px; border-left: 4px solid #0ea5e9; color: #0f172a; white-space: pre-wrap;">
                                <?php echo esc_html($params['message']); ?>
                            </div>
                            <?php endif; ?>

                            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $rezerwacja_id . '&action=edit')); ?>"
                                   style="display: inline-block; background-color: #0ea5e9; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                                    Zarządzaj zamówieniem
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #e2e8f0; padding: 20px; text-align: center; color: #475569; font-size: 12px;">
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

