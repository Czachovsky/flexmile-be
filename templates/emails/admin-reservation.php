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

$consent_email = !empty($params['consent_email']);
$consent_phone = !empty($params['consent_phone']);
$pickup_location = $params['pickup_location'] ?? '';
$pickup_labels = [
    'salon' => 'Odbiór w salonie',
    'home_delivery' => 'Dostawa pod wskazany adres',
];
$pickup_text = $pickup_labels[$pickup_location] ?? 'Nie określono';

// Dane samochodu
$car_reference_id = get_post_meta($samochod->ID, '_car_reference_id', true);
$engine = trim((string) get_post_meta($samochod->ID, '_engine', true));
$engine_capacity = (int) get_post_meta($samochod->ID, '_engine_capacity', true);
$drivetrain = trim((string) get_post_meta($samochod->ID, '_drivetrain', true));

$car_secondary_parts = array_filter([
    $engine,
    $engine_capacity ? number_format($engine_capacity, 0, ',', ' ') . ' cm3' : '',
    $drivetrain,
]);
$car_secondary_line = implode(' | ', $car_secondary_parts);
$car_reference_display = $car_reference_id ?: sprintf('ID-%d', $samochod->ID);

// Dane klienta
$rental_months = isset($params['rental_months']) ? (int) $params['rental_months'] : null;
$annual_mileage_limit = isset($params['annual_mileage_limit']) ? (int) $params['annual_mileage_limit'] : null;
$company_name = $params['company_name'] ?? '';
$tax_id = $params['tax_id'] ?? '';
$email = $params['email'] ?? '';
$phone = $params['phone'] ?? '';
$message = $params['message'] ?? '';

// Formatowanie cen i wartości
$formatted_monthly_price = number_format((float) $cena_miesieczna, 2, ',', ' ');
$formatted_total_price = number_format((float) $cena_calkowita, 2, ',', ' ');
$formatted_mileage = $annual_mileage_limit ? number_format($annual_mileage_limit, 0, ',', ' ') . ' km' : '—';
$formatted_months = $rental_months ? sprintf('%d mies.', $rental_months) : '—';

// Data i informacje techniczne
$current_date = date_i18n('d.m.Y H:i', current_time('timestamp'));
$user_ip = !empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '—';
$source_url = !empty($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';

// Linki do panelu admina
$admin_order_link = admin_url('post.php?post=' . $rezerwacja_id . '&action=edit');
$admin_car_link = admin_url('post.php?post=' . $samochod->ID . '&action=edit');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>Nowe Zamówienie - Panel Administratora</title>
</head>

<body style="margin:0; padding:0; background:#e0e0e0; font-family:Arial, sans-serif;">

  <!-- WRAPPER -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#e0e0e0" style="padding:20px 0;">
    <tbody><tr>
      <td align="center">

        <!-- MAIN CONTAINER -->
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border-radius:6px; overflow:hidden;">

          <!-- INTERNAL HEADER (NEW) -->
          <tbody>
          <tr>
             <td style="background:#333333; padding: 10px 20px; color: #ffffff; font-size: 12px; font-weight: bold; text-align: center; letter-spacing: 1px;">
                WIADOMOŚĆ WEWNĘTRZNA - NOWE ZGŁOSZENIE
             </td>
          </tr>

          <!-- HEADER -->
          <tr>
            <td style="padding: 30px 20px 10px 20px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tbody><tr>
                  <td align="left" style="font-size: 44px;font-weight:bold;">
                    <span style="color:#863087;">flex</span><span style="color:#C1D342;">mile</span>
                  </td>
                  <td align="right" style="font-size: 14px;color:#333; font-weight:bold;">
                    ID: #<?php echo esc_html($rezerwacja_id); ?>
                  </td>
                </tr>
              </tbody></table>
            </td>
          </tr>

          <!-- ADMIN ACTIONS (NEW) -->
          <tr>
            <td style="padding: 10px 20px 30px 20px; text-align:center;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px;">
                    <tr>
                        <td style="padding: 15px; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #555;">Wpłynęło nowe zapytanie o rezerwację.</p>
                            <!-- Button -->
                            <a href="<?php echo esc_url($admin_order_link); ?>" style="background-color: #863087; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: bold; display: inline-block;">
                                Przejdź do rezerwacji w panelu &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
          </tr>

          <!-- OFFER BOX (Compact version for admin) -->
          <tr>
            <td style="padding:0 20px 20px 20px;">

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f9f9f9;border-radius:12px; border: 1px solid #eeeeee;">
                <tbody><tr>
                  <td style="padding: 20px;">

                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td width="60%" valign="middle">
                             <div style="font-size:11px;color:#777;margin-bottom:4px;">POJAZD:</div>
                             <div style="font-size:18px;font-weight:bold;color:#000;">
                                <?php echo esc_html($samochod->post_title); ?>
                             </div>
                             <div style="font-size:13px;color:#555;">
                                <?php echo esc_html($car_reference_display); ?>
                                <?php if (!empty($car_secondary_line)): ?>
                                    <br><span style="font-size:12px;color:#888;"><?php echo esc_html($car_secondary_line); ?></span>
                                <?php endif; ?>
                             </div>
                             <div style="margin-top:8px;">
                                <a href="<?php echo esc_url($admin_car_link); ?>" style="font-size:11px;color:#863087;text-decoration:none;">Zobacz samochód w panelu →</a>
                             </div>
                        </td>
                        <td width="40%" valign="middle" align="right">
                             <div style="font-size:20px;font-weight:bold;color: #863087;">
                                <?php echo esc_html($formatted_monthly_price); ?> zł
                             </div>
                             <div style="font-size:11px;color: #863087;">netto / mc</div>
                        </td>
                      </tr>
                    </tbody></table>

                  </td>
                </tr>
              </tbody></table>

            </td>
          </tr>

          <!-- ORDER & CUSTOMER DETAILS GRID -->
          <tr>
            <td style="padding: 0 24px;">

              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <!-- COLUMN 1: Reservation Details -->
                    <td width="48%" valign="top" style="padding-right: 2%;">
                        <div style="font-size:14px;font-weight:bold;color:#000;padding-bottom: 8px;border-bottom: 2px solid #863087; margin-bottom:10px;">
                            Parametry
                        </div>
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:5px 0;font-size:13px;color:#666;">Umowa:</td>
                                <td align="right" style="padding:5px 0;font-size:13px;color:#000;font-weight:bold;"><?php echo esc_html($formatted_months); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:5px 0;font-size:13px;color:#666;">Limit km:</td>
                                <td align="right" style="padding:5px 0;font-size:13px;color:#000;font-weight:bold;"><?php echo esc_html($formatted_mileage); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:5px 0;font-size:13px;color:#666;">Cena/mies:</td>
                                <td align="right" style="padding:5px 0;font-size:13px;color:#863087;font-weight:bold;"><?php echo esc_html($formatted_monthly_price); ?> zł</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 0;font-size:13px;color:#666;">Cena całkowita:</td>
                                <td align="right" style="padding:5px 0;font-size:14px;color:#863087;font-weight:bold;"><?php echo esc_html($formatted_total_price); ?> zł</td>
                            </tr>
                        </table>
                    </td>

                    <!-- COLUMN 2: Customer Details -->
                    <td width="48%" valign="top" style="padding-left: 2%;">
                        <div style="font-size:14px;font-weight:bold;color:#000;padding-bottom: 8px;border-bottom: 2px solid #C1D342; margin-bottom:10px;">
                            Klient
                        </div>
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td colspan="2" style="padding:5px 0;font-size:14px;color:#000;font-weight:bold;">
                                    <?php echo esc_html($company_name); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0;font-size:13px;color:#666;">NIP:</td>
                                <td align="right" style="padding:2px 0;font-size:13px;color:#000;"><?php echo esc_html($tax_id); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0;font-size:13px;color:#666;">Tel:</td>
                                <td align="right" style="padding:2px 0;font-size:13px;color:#000;">
                                    <a href="tel:<?php echo esc_attr($phone); ?>" style="color:#000;text-decoration:none;"><?php echo esc_html($phone); ?></a>
                                </td>
                            </tr>
                             <tr>
                                <td style="padding:2px 0;font-size:13px;color:#666;">Email:</td>
                                <td align="right" style="padding:2px 0;font-size:13px;color:#000;">
                                     <a href="mailto:<?php echo esc_attr($email); ?>" style="color:#863087;text-decoration:none; font-weight:bold;"><?php echo esc_html($email); ?></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
              </table>

            </td>
          </tr>

          <!-- PREFERENCES SECTION (NEW) -->
          <?php if ($consent_email || $consent_phone || !empty($pickup_location) || !empty($message)): ?>
          <tr>
            <td style="padding: 20px 24px 0 24px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f9f9f9; border-radius: 6px; border: 1px solid #eeeeee;">
                <tr>
                  <td style="padding: 15px;">
                    <div style="font-size:14px;font-weight:bold;color:#000;padding-bottom: 8px;border-bottom: 2px solid #C1D342; margin-bottom:10px;">
                      Preferencje i dodatkowe informacje
                    </div>
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <?php if (!empty($pickup_location)): ?>
                      <tr>
                        <td style="padding:5px 0;font-size:13px;color:#666;">Miejsce odbioru:</td>
                        <td align="right" style="padding:5px 0;font-size:13px;color:#000;font-weight:bold;"><?php echo esc_html($pickup_text); ?></td>
                      </tr>
                      <?php endif; ?>
                      <tr>
                        <td style="padding:5px 0;font-size:13px;color:#666;">Zgoda na e-mail:</td>
                        <td align="right" style="padding:5px 0;font-size:13px;color:#000;">
                          <span style="color:<?php echo $consent_email ? '#0d9488' : '#999'; ?>;font-weight:bold;">
                            <?php echo $consent_email ? '✓ Tak' : '✗ Nie'; ?>
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:5px 0;font-size:13px;color:#666;">Zgoda na telefon:</td>
                        <td align="right" style="padding:5px 0;font-size:13px;color:#000;">
                          <span style="color:<?php echo $consent_phone ? '#0d9488' : '#999'; ?>;font-weight:bold;">
                            <?php echo $consent_phone ? '✓ Tak' : '✗ Nie'; ?>
                          </span>
                        </td>
                      </tr>
                      <?php if (!empty($message)): ?>
                      <tr>
                        <td colspan="2" style="padding:10px 0 5px 0;font-size:13px;color:#666;">Wiadomość od klienta:</td>
                      </tr>
                      <tr>
                        <td colspan="2" style="padding:0 0 5px 0;font-size:13px;color:#333;font-style:italic;border-left:3px solid #C1D342;padding-left:10px;">
                          <?php echo nl2br(esc_html($message)); ?>
                        </td>
                      </tr>
                      <?php endif; ?>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <?php endif; ?>

          <!-- SYSTEM DATA SECTION (NEW) -->
          <tr>
            <td style="padding: 40px 24px 24px 24px;">

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; border-radius: 6px; border: 1px dashed #cccccc;">
                <tr>
                    <td style="padding: 15px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td colspan="2" style="font-size:12px; font-weight:bold; color:#555; padding-bottom:10px; text-transform:uppercase;">
                                    Dane techniczne zgłoszenia
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:11px; color:#777; padding:3px 0;">Data otrzymania:</td>
                                <td align="right" style="font-size:11px; color:#333; font-family:monospace;"><?php echo esc_html($current_date); ?></td>
                            </tr>
                            <tr>
                                <td style="font-size:11px; color:#777; padding:3px 0;">Adres IP:</td>
                                <td align="right" style="font-size:11px; color:#333; font-family:monospace;"><?php echo esc_html($user_ip); ?></td>
                            </tr>
                            <?php if (!empty($source_url)): ?>
                            <tr>
                                <td style="font-size:11px; color:#777; padding:3px 0;">Źródło (Referer):</td>
                                <td align="right" style="font-size:11px; color:#333; font-family:monospace;"><?php echo esc_html($source_url); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </td>
                </tr>
              </table>

            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="padding:20px; font-size:11px; color:#999; text-align:center; border-top: 1px solid #eeeeee;">
              Wiadomość wygenerowana automatycznie przez system Flexmile.
            </td>
          </tr>

        </tbody></table>

      </td>
    </tr>
  </tbody></table>

</body>
</html>