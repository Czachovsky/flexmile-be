<?php
/**
 * Szablon e-maila dla klienta - potwierdzenie rezerwacji
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

$current_date = date_i18n('d.m.Y', current_time('timestamp'));
$car_reference_display = $car_reference_id ?: sprintf('ID-%d', $samochod->ID);

$rental_months = isset($params['rental_months']) ? (int) $params['rental_months'] : null;
$annual_mileage_limit = isset($params['annual_mileage_limit']) ? (int) $params['annual_mileage_limit'] : null;
$company_name = $params['company_name'] ?? '';
$tax_id = $params['tax_id'] ?? '';
$email = $params['email'] ?? '';
$phone = $params['phone'] ?? '';

$formatted_monthly_price = number_format((float) $cena_miesieczna, 2, ',', ' ');
$formatted_total_price = number_format((float) $cena_calkowita, 2, ',', ' ');
$formatted_mileage = $annual_mileage_limit ? number_format($annual_mileage_limit, 0, ',', ' ') . ' km' : '—';
$formatted_months = $rental_months ? sprintf('%d mies.', $rental_months) : '—';
$reservation_number = sprintf('#%d', $rezerwacja_id);
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title></title>
</head>

<body style="margin:0; padding:0; background:#f4f4f4; font-family:Arial, sans-serif;">

  <!-- WRAPPER -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f4" style="padding:20px 0;">
    <tbody><tr>
      <td align="center">

        <!-- MAIN CONTAINER -->
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border-radius:6px;">

          <!-- HEADER -->
          <tbody><tr>
            <td style="padding: 30px 20px 20px 20px;">

              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tbody><tr>
                  <td align="left" style="font-size: 44px;font-weight:bold;font-family:Arial;">
                    <span style="color:#863087;">flex</span><span style="color:#C1D342;">mile</span>
                  </td>
                  <td align="right" style="font-size: 12px;color:#333;">
                    <?php echo esc_html($current_date); ?>
                  </td>
                </tr>
              </tbody></table>

            </td>
          </tr>

          <!-- OFFER BOX -->
          <tr>
            <td style="padding:20px;">

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f2f2f2;border-radius:12px;">
                <tbody><tr>
                  <td style="padding: 32px 40px;">

                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tbody><tr>
                        <td style="font-size:12px;color:#555;padding-bottom: 18px;">
                          <?php echo esc_html($car_reference_display); ?>
                        </td>
                      </tr>

                      <tr>
                        <td style="font-size:24px;font-weight:bold;color:#000;padding-bottom: 12px;">
                          <?php echo esc_html($samochod->post_title); ?>
                        </td>
                      </tr>

                      <?php if (!empty($car_secondary_line)): ?>
                      <tr>
                        <td style="font-size:15px;color:#333;padding-bottom: 20px;">
                          <?php echo esc_html($car_secondary_line); ?>
                        </td>
                      </tr>
                      <?php endif; ?>

                      <tr>
                        <td style="font-size:28px;font-weight:bold;color: #863087;">
                          <?php echo esc_html($formatted_monthly_price); ?> zł
                          <span style="font-size:14px;font-weight:normal;color: #863087;">/netto mies.</span>
                        </td>
                      </tr>
                    </tbody></table>

                  </td>
                </tr>
              </tbody></table>

            </td>
          </tr>

          <!-- ORDER DETAILS -->
          <tr>
            <td style="padding: 24px 24px 0 24px;/* border-bottom: 1px solid #999999; */">

              <!-- HEADER ROW -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding-bottom:10px;">
                <tbody><tr>
                  <td style="font-size:18px;font-weight:bold;color:#000;padding-bottom: 12px;border-bottom: 1px solid #eeeeee;">
                    Szczegóły zamówienia
                  </td>
                </tr>
              </tbody></table>

              <!-- DATA ROWS -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0">

                <tbody>
                <tr>
                  <td style="padding: 12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;">
                    Czas trwania umowy
                  </td>
                  <td align="right" style="padding: 12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold;">
                    <?php echo esc_html($formatted_months); ?>
                  </td>
                </tr>

                <tr>
                  <td style="padding: 12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;">
                    Roczny limit kilometrów
                  </td>
                  <td align="right" style="padding: 12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold;">
                    <?php echo esc_html($formatted_mileage); ?>
                  </td>
                </tr>


              </tbody></table>

            </td>
          </tr>

          <!-- COMPANY DETAILS -->
          <tr>
            <td style="padding: 46px 24px 24px 24px;">

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding-bottom:10px;">
                <tbody><tr>
                  <td style="font-size: 18px;font-weight: bold;color: #000;padding-bottom: 12px;border-bottom: 1px solid #eeeeee;">
                    Dane firmy
                  </td>
                </tr>
              </tbody></table>

              <table width="100%" cellpadding="0" cellspacing="0" border="0">

                <tbody><tr>
                  <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;">
                    Nazwa firmy
                  </td>
                  <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold;">
                    <?php echo esc_html($company_name); ?>
                  </td>
                </tr>

                <tr>
                  <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;">
                    NIP
                  </td>
                  <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold;">
                    <?php echo esc_html($tax_id); ?>
                  </td>
                </tr>

                <tr>
                  <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;">
                    Email
                  </td>
                  <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold;">
                    <a href="mailto:<?php echo esc_attr($email); ?>" style="color:#333;text-decoration:none;">
                      <?php echo esc_html($email); ?>
                    </a>
                  </td>
                </tr>

                <tr>
                  <td style="padding:12px 0; font-size:15px; color:#333;">
                    Telefon
                  </td>
                  <td align="right" style="padding:12px 0;font-size:15px;color:#333;font-weight: bold;">
                    <a href="tel:<?php echo esc_attr($phone); ?>" style="color:#333;text-decoration:none;">
                      <?php echo esc_html($phone); ?>
                    </a>
                  </td>
                </tr>

              </tbody></table>

            </td>
          </tr>

          <!-- CONSENTS -->
          <tr>
            <td style="padding: 0 24px 40px 24px;">

              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding-bottom:10px;">
                <tbody><tr>
                  <td style="font-size: 18px;font-weight: bold;color: #000;padding-bottom: 12px;border-bottom: 1px solid #eeeeee;">
                    Twoje preferencje
                  </td>
                </tr>
              </tbody></table>

              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr>
                  <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;">
                    Zgoda na kontakt e-mail
                  </td>
                  <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold;">
                    <?php echo $consent_email ? 'Tak' : 'Nie'; ?>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;">
                    Zgoda na kontakt telefoniczny
                  </td>
                  <td align="right" style="padding:12px 0;font-size:15px;color:#333;border-bottom: 1px solid #eeeeee;font-weight: bold;">
                    <?php echo $consent_phone ? 'Tak' : 'Nie'; ?>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 0;font-size:15px;color:#333;">
                    Preferowane miejsce wydania
                  </td>
                  <td align="right" style="padding:12px 0;font-size:15px;color:#333;font-weight: bold;">
                    <?php echo esc_html($pickup_text); ?>
                  </td>
                </tr>
                </tbody>
              </table>

            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="padding:20px; font-size:12px; color:#999; text-align:center;">
              © <?php echo date('Y'); ?> Flexmile. Wszystkie prawa zastrzeżone.
            </td>
          </tr>

        </tbody></table>

      </td>
    </tr>
  </tbody></table>

</body>
</html>
