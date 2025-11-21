<?php
/**
 * Szablon e-maila dla klienta - potwierdzenie zamówienia
 *
 * Zmienne:
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
    'salon' => 'Odbiór w salonie FlexMile',
    'home_delivery' => 'Dostawa pod wskazany adres',
];
$pickup_text = $pickup_labels[$pickup_location] ?? 'Nie określono';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potwierdzenie zamówienia - FlexMile</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8fafc;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(15,23,42,0.1);">
                    <tr>
                        <td style="background-color: #0f172a; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff;">FlexMile</h1>
                            <p style="margin: 10px 0 0 0; color: #94a3b8;">Potwierdzenie zamówienia</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 15px 0; color: #0f172a;">Dziękujemy za złożenie zamówienia w FlexMile.</p>
                            <p style="margin: 0 0 25px 0; color: #475569;">Nasz zespół skontaktuje się z Tobą, aby potwierdzić dostępność pojazdu oraz omówić dalsze kroki.</p>

                            <h2 style="color: #0f172a; border-bottom: 2px solid #0ea5e9; padding-bottom: 8px;">Szczegóły zamówienia</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Numer zamówienia:</td>
                                    <td style="color: #0f172a;">#<?php echo esc_html($rezerwacja_id); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Data:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html(date('Y-m-d H:i:s')); ?></td>
                                </tr>
                            </table>

                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 8px;">Samochód</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Model:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html($samochod->post_title); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Oferta:</td>
                                    <td style="color: #0ea5e9;">
                                        <a href="<?php echo esc_url(get_permalink($samochod->ID)); ?>" style="color: #0ea5e9; text-decoration: none;">
                                            Zobacz szczegóły pojazdu
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 8px;">Parametry zamówienia</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Okres wynajmu:</td>
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

                            <h2 style="color: #0f172a; margin-top: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 8px;">Twoje preferencje</h2>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td style="color: #475569; font-weight: bold; width: 40%;">Zgoda na kontakt e-mail:</td>
                                    <td style="color: #0f172a;"><?php echo $consent_email ? 'Tak' : 'Nie'; ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Zgoda na kontakt telefoniczny:</td>
                                    <td style="color: #0f172a;"><?php echo $consent_phone ? 'Tak' : 'Nie'; ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; font-weight: bold;">Preferowane miejsce wydania:</td>
                                    <td style="color: #0f172a;"><?php echo esc_html($pickup_text); ?></td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; color: #475569;">Jeśli masz dodatkowe pytania, napisz na adres <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>" style="color: #0ea5e9; text-decoration: none;"><?php echo esc_html(get_option('admin_email')); ?></a> lub zadzwoń pod numer wskazany na stronie FlexMile.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #e2e8f0; padding: 20px; text-align: center; color: #475569; font-size: 12px;">
                            <p style="margin: 0;">© <?php echo date('Y'); ?> <?php echo esc_html(get_option('blogname')); ?> - FlexMile</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

