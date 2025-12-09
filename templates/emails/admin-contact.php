<?php
/**
 * Szablon e-maila dla administratora - formularz kontaktowy
 *
 * Dostępne zmienne:
 * @var array $params - Parametry formularza (first_name, last_name, email, phone, monthly_budget_from, monthly_budget_to, message, consent_email, consent_phone)
 */

if (!defined('ABSPATH')) {
    exit;
}

$consent_email = !empty($params['consent_email']);
$consent_phone = !empty($params['consent_phone']);

// Imię i nazwisko
$full_name = trim(sanitize_text_field($params['first_name']) . ' ' . sanitize_text_field($params['last_name']));

// Formatowanie budżetu
$budget_display = '';
if (!empty($params['monthly_budget_from']) && !empty($params['monthly_budget_to'])) {
    $budget_display = sprintf(
        '%.2f - %.2f zł/mies.',
        floatval($params['monthly_budget_from']),
        floatval($params['monthly_budget_to'])
    );
} elseif (!empty($params['monthly_budget_from'])) {
    $budget_display = sprintf('Od: %.2f zł/mies.', floatval($params['monthly_budget_from']));
} elseif (!empty($params['monthly_budget_to'])) {
    $budget_display = sprintf('Do: %.2f zł/mies.', floatval($params['monthly_budget_to']));
}

// Data i informacje techniczne
$current_date = date_i18n('d.m.Y H:i', current_time('timestamp'));
$user_ip = !empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '—';
$source_url = !empty($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
?>
<html>
<head>
    <title>Nowe zapytanie kontaktowe - FlexMile</title>
    <meta charset="UTF-8">
</head>
<body style="margin:0; padding:0; background:#e0e0e0; font-family:Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f4" style="padding:20px 0">
        <tbody>
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:6px">
                        <tbody>
                            <tr>
                                <td style="padding: 30px 20px 20px 20px">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tbody>
                                            <tr>
                                                <td align="left" style="font-size: 44px;font-weight:bold">
                                                    <span style="color:#863087">flex</span><span style="color:#C1D342">mile</span>
                                                </td>
                                                <td align="right" style="font-size: 12px;color:#333">
                                                    <?php echo esc_html($current_date); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 46px 24px 24px 24px">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding-bottom:10px">
                                        <tbody>
                                            <tr>
                                                <td style="font-size: 18px;font-weight: bold;color: #000;padding-bottom: 12px;border-bottom: 1px solid #eeeeee">
                                                    Nowe zapytanie kontaktowe w systemie FlexMile!
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tbody>
                                            <tr>
                                                <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee">
                                                    Imię i nazwisko
                                                </td>
                                                <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold">
                                                    <?php echo esc_html($full_name); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee">
                                                    Email
                                                </td>
                                                <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold">
                                                    <a href="mailto:<?php echo esc_attr($params['email']); ?>" style="color:#863087;text-decoration:none;font-weight:bold">
                                                        <?php echo esc_html($params['email']); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee">
                                                    Telefon
                                                </td>
                                                <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold">
                                                    <a href="tel:<?php echo esc_attr($params['phone']); ?>" style="color:#333;text-decoration:none">
                                                        <?php echo esc_html($params['phone']); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php if (!empty($budget_display)): ?>
                                            <tr>
                                                <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee">
                                                    Budżet
                                                </td>
                                                <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold">
                                                    <?php echo esc_html($budget_display); ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee">
                                                    Zgoda na kontakt email:
                                                </td>
                                                <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold">
                                                    <span style="color:<?php echo $consent_email ? '#0d9488' : '#999'; ?>">
                                                        <?php echo $consent_email ? 'TAK' : 'NIE'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:12px 0;font-size:15px;color:#333">
                                                    Zgoda na kontakt telefoniczny:
                                                </td>
                                                <td align="right" style="padding:12px 0;font-size:15px;color:#333;font-weight: bold">
                                                    <span style="color:<?php echo $consent_phone ? '#0d9488' : '#999'; ?>">
                                                        <?php echo $consent_phone ? 'TAK' : 'NIE'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <?php if (!empty($params['message'])): ?>
                            <tr>
                                <td style="padding:20px">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f2f2f2;border-radius:12px">
                                        <tbody>
                                            <tr>
                                                <td style="padding: 32px 40px">
                                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        <tbody>
                                                            <tr>
                                                                <td style="font-size:12px;color:#555;padding-bottom: 18px;font-weight:bold;text-transform:uppercase;letter-spacing:0.5px">
                                                                    Wiadomość od klienta
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="font-size:15px;color:#333;line-height:1.6;">
                                                                    <?php echo nl2br(esc_html($params['message'])); ?>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding:20px 24px">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f9f9f9;border-radius:8px;border: 1px dashed #cccccc">
                                        <tbody>
                                            <tr>
                                                <td style="padding: 20px 24px">
                                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="2" style="font-size:11px;color:#666;padding-bottom: 12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.5px">
                                                                    Dane techniczne zgłoszenia
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="font-size:11px;color:#777;padding:4px 0">Data otrzymania:</td>
                                                                <td align="right" style="font-size:11px;color:#333;font-family:monospace;padding:4px 0"><?php echo esc_html($current_date); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td style="font-size:11px;color:#777;padding:4px 0">Adres IP:</td>
                                                                <td align="right" style="font-size:11px;color:#333;font-family:monospace;padding:4px 0"><?php echo esc_html($user_ip); ?></td>
                                                            </tr>
                                                            <?php if (!empty($source_url)): ?>
                                                            <tr>
                                                                <td style="font-size:11px;color:#777;padding:4px 0">Źródło (Referer):</td>
                                                                <td align="right" style="font-size:11px;color:#333;font-family:monospace;padding:4px 0;word-break:break-all"><?php echo esc_html($source_url); ?></td>
                                                            </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px;font-size:12px;color:#999;text-align:center">
                                    © <?php echo date('Y'); ?> Flexmile. Wszystkie prawa zastrzeżone.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
