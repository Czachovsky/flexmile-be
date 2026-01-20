<?php
namespace FlexMile\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Konfiguracja nagłówków emailowych dla poprawnej dostarczalności
 * Zapobiega trafianiu emaili do spamu poprzez właściwą konfigurację SPF/DKIM/DMARC
 */
class Email_Config {

    public function __construct() {
        // Filtruj nagłówki wp_mail dla wszystkich emaili
        add_filter('wp_mail', [$this, 'configure_email_headers'], 10, 1);

        // Loguj błędy wysyłki emaili
        add_action('wp_mail_failed', [$this, 'log_mail_failure'], 10, 1);

        // Konfiguruj SMTP jeśli jest włączone
        add_action('phpmailer_init', [$this, 'configure_smtp'], 10, 1);
        
        // Przechwytuj szczegóły błędów PHPMailera przed wp_mail_failed
        add_action('phpmailer_init', [$this, 'capture_phpmailer_errors'], 20, 1);
    }

    /**
     * Konfiguruje nagłówki emailowe dla lepszej dostarczalności
     * 
     * @param array $args Argumenty funkcji wp_mail
     * @return array Zmodyfikowane argumenty
     */
    public function configure_email_headers($args) {
        $headers = isset($args['headers']) ? $args['headers'] : [];
        
        // Konwertuj nagłówki do tablicy jeśli są stringiem
        if (is_string($headers)) {
            $headers = explode("\n", $headers);
            $headers = array_map('trim', $headers);
            $headers = array_filter($headers);
        }

        // Nazwa strony
        $site_name = get_option('blogname', 'FlexMile');
        $admin_email = get_option('admin_email');

        // Jeśli SMTP jest włączone i ustawiono własny adres nadawcy,
        // użyj go jako From, zamiast automatycznego noreply@domena
        $smtp_enabled    = get_option('flexmile_smtp_enabled', false);
        $smtp_from_email = get_option('flexmile_smtp_from_email', '');
        $smtp_from_name  = get_option('flexmile_smtp_from_name', $site_name);

        if ($smtp_enabled && !empty($smtp_from_email) && is_email($smtp_from_email)) {
            $from_email = $smtp_from_email;
            $from_name  = $smtp_from_name;
        } else {
            // Pobierz domenę strony (bez http/https/www)
            $site_url   = get_site_url();
            $site_domain = $this->extract_domain($site_url);

            // Użyj domeny strony dla adresu From (zamiast zewnętrznych domen)
            // Jeśli admin_email jest z innej domeny, użyj noreply@domena-strony
            $from_email = $this->get_from_email($admin_email, $site_domain);
            $from_name  = $site_name;
        }

        // Sprawdź czy nagłówek From już istnieje
        $has_from = false;
        foreach ($headers as $key => $header) {
            if (stripos($header, 'From:') === 0) {
                // Zastąp istniejący nagłówek From
                $headers[$key] = sprintf('From: %s <%s>', $from_name, $from_email);
                $has_from = true;
                break;
            }
        }

        // Dodaj nagłówek From jeśli nie istnieje
        if (!$has_from) {
            $headers[] = sprintf('From: %s <%s>', $from_name, $from_email);
        }

        // Dodaj Reply-To na adres administratora (jeśli różny od From)
        if ($admin_email !== $from_email) {
            $has_reply_to = false;
            foreach ($headers as $header) {
                if (stripos($header, 'Reply-To:') === 0) {
                    $has_reply_to = true;
                    break;
                }
            }
            
            if (!$has_reply_to) {
                $headers[] = sprintf('Reply-To: %s <%s>', $site_name, $admin_email);
            }
        }

        // Dodaj nagłówek Content-Type jeśli nie istnieje
        $has_content_type = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $has_content_type = true;
                break;
            }
        }

        if (!$has_content_type) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        // Dodaj nagłówek X-Mailer dla identyfikacji
        $has_mailer = false;
        foreach ($headers as $header) {
            if (stripos($header, 'X-Mailer:') === 0) {
                $has_mailer = true;
                break;
            }
        }

        if (!$has_mailer) {
            $headers[] = 'X-Mailer: FlexMile WordPress Plugin';
        }

        $args['headers'] = $headers;
        return $args;
    }

    /**
     * Wyciąga domenę z URL
     * 
     * @param string $url URL strony
     * @return string Domena (np. flexmile.pl)
     */
    private function extract_domain($url) {
        $parsed = parse_url($url);
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        
        // Jeśli nie udało się wyciągnąć domeny, spróbuj z $_SERVER
        if (empty($host) && isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        }
        
        // Fallback - użyj admin_email jeśli wszystko inne zawiedzie
        if (empty($host)) {
            $admin_email = get_option('admin_email');
            if (is_email($admin_email)) {
                $parts = explode('@', $admin_email);
                if (count($parts) === 2) {
                    $host = $parts[1];
                }
            }
        }
        
        // Usuń www. jeśli istnieje
        $host = preg_replace('/^www\./', '', $host);
        
        // Usuń port jeśli istnieje
        $host = preg_replace('/:\d+$/', '', $host);
        
        return $host ?: 'localhost';
    }

    /**
     * Określa adres From na podstawie domeny strony
     * Jeśli admin_email jest z innej domeny, używa noreply@domena-strony
     * 
     * @param string $admin_email Email administratora
     * @param string $site_domain Domena strony
     * @return string Adres email do użycia jako From
     */
    private function get_from_email($admin_email, $site_domain) {
        // Wyciągnij domenę z admin_email
        $admin_domain = '';
        if (is_email($admin_email)) {
            $parts = explode('@', $admin_email);
            if (count($parts) === 2) {
                $admin_domain = $parts[1];
            }
        }

        // Jeśli admin_email jest z tej samej domeny co strona, użyj go
        if ($admin_domain === $site_domain) {
            return $admin_email;
        }

        // W przeciwnym razie użyj noreply@domena-strony
        // To zapobiega problemom z SPF/DKIM/DMARC
        return 'noreply@' . $site_domain;
    }

    /**
     * Dodaje wpis do logów emaili (statycznie, aby inne klasy mogły z tego korzystać)
     *
     * @param array $entry
     * @return void
     */
    public static function add_log_entry($entry) {
        $logs = get_option('flexmile_email_log', []);

        if (!is_array($logs)) {
            $logs = [];
        }

        $entry['time'] = isset($entry['time']) ? $entry['time'] : current_time('mysql');

        array_unshift($logs, $entry);

        // Ogranicz do 100 ostatnich wpisów
        if (count($logs) > 100) {
            $logs = array_slice($logs, 0, 100);
        }

        update_option('flexmile_email_log', $logs, false);
    }

    /**
     * Zwraca logi emaili (do wyświetlenia w panelu)
     *
     * @param int $limit
     * @return array
     */
    public static function get_logs($limit = 50) {
        $logs = get_option('flexmile_email_log', []);

        if (!is_array($logs)) {
            return [];
        }

        if ($limit > 0) {
            return array_slice($logs, 0, $limit);
        }

        return $logs;
    }
    
    /**
     * Zwraca logi debugowania SMTP
     *
     * @param int $limit
     * @return array
     */
    public static function get_smtp_debug_logs($limit = 50) {
        $logs = get_option('flexmile_smtp_debug_logs', []);

        if (!is_array($logs)) {
            return [];
        }

        // Odwróć kolejność (najnowsze na górze)
        $logs = array_reverse($logs);

        if ($limit > 0) {
            return array_slice($logs, 0, $limit);
        }

        return $logs;
    }
    
    /**
     * Czyści logi debugowania SMTP
     *
     * @return void
     */
    public static function clear_smtp_debug_logs() {
        delete_option('flexmile_smtp_debug_logs');
    }

    /**
     * Czyści logi emaili
     *
     * @return void
     */
    public static function clear_logs() {
        delete_option('flexmile_email_log');
    }

    /**
     * Loguje błąd wysyłki emaila (hook wp_mail_failed)
     *
     * @param \WP_Error $wp_error
     * @return void
     */
    public function log_mail_failure($wp_error) {
        if (!($wp_error instanceof \WP_Error)) {
            return;
        }

        $data = $wp_error->get_error_data();

        $to = isset($data['to']) ? $data['to'] : '';
        $subject = isset($data['subject']) ? $data['subject'] : '';
        $headers = isset($data['headers']) ? $data['headers'] : [];

        // Ustal kontekst na podstawie nagłówka X-FlexMile-Context (jeśli jest)
        $context = 'wp_mail';
        $context_detail = '';

        if (!empty($headers)) {
            if (is_string($headers)) {
                $headers = explode("\n", $headers);
            }

            if (is_array($headers)) {
                foreach ($headers as $header) {
                    if (stripos($header, 'X-FlexMile-Context:') === 0) {
                        $parts = explode(':', $header, 2);
                        if (isset($parts[1])) {
                            $value = trim($parts[1]);
                            $context = $value;
                        }
                        break;
                    }
                }
            }
        }

        // Dodaj podstawowe informacje o konfiguracji SMTP (bez hasła)
        $smtp_enabled    = get_option('flexmile_smtp_enabled', false);
        $smtp_host       = get_option('flexmile_smtp_host', '');
        $smtp_port       = get_option('flexmile_smtp_port', 587);
        $smtp_encryption = get_option('flexmile_smtp_encryption', 'tls');
        $smtp_username   = get_option('flexmile_smtp_username', '');

        // Pobierz wszystkie komunikaty błędów (może być kilka)
        $error_messages = [];
        $error_codes = $wp_error->get_error_codes();
        foreach ($error_codes as $code) {
            $messages = $wp_error->get_error_messages($code);
            foreach ($messages as $message) {
                $error_messages[] = $message;
            }
        }
        $full_error = implode(' | ', $error_messages);
        
        // Spróbuj wyciągnąć szczegóły z error_data jeśli są
        $error_details = '';
        if (isset($data['phpmailer_exception_code'])) {
            $error_details = 'PHPMailer Code: ' . $data['phpmailer_exception_code'];
        }
        if (isset($data['phpmailer_error_info']) && !empty($data['phpmailer_error_info'])) {
            $error_details .= ($error_details ? ' | ' : '') . 'PHPMailer Info: ' . $data['phpmailer_error_info'];
        }
        
        $entry = [
            'type'    => 'error',
            'status'  => 'failed',
            'to'      => $to,
            'subject' => $subject,
            'error'   => $full_error,
            'error_details' => $error_details,
            'context' => $context,
            'smtp'    => [
                'enabled'    => (bool) $smtp_enabled,
                'host'       => $smtp_host,
                'port'       => (int) $smtp_port,
                'encryption' => $smtp_encryption,
                'username'   => $smtp_username,
            ],
        ];

        self::add_log_entry($entry);
    }

    /**
     * Konfiguruje SMTP dla PHPMailer
     * 
     * @param object $phpmailer Instancja PHPMailer
     */
    public function configure_smtp($phpmailer) {
        // Sprawdź czy SMTP jest włączone
        $smtp_enabled = get_option('flexmile_smtp_enabled', false);
        
        if (!$smtp_enabled) {
            return;
        }

        // Pobierz ustawienia SMTP
        $smtp_host = get_option('flexmile_smtp_host', '');
        $smtp_port = get_option('flexmile_smtp_port', 587);
        $smtp_encryption = get_option('flexmile_smtp_encryption', 'tls'); // tls, ssl, lub ''
        $smtp_username = get_option('flexmile_smtp_username', '');
        $smtp_password = get_option('flexmile_smtp_password', '');
        $smtp_from_email = get_option('flexmile_smtp_from_email', '');
        $smtp_from_name = get_option('flexmile_smtp_from_name', get_option('blogname', 'FlexMile'));

        // Jeśli brak wymaganych ustawień, nie konfiguruj SMTP
        if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
            return;
        }

        // Konfiguruj SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_host;
        $phpmailer->Port = intval($smtp_port);
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $smtp_username;
        
        // Usuń białe znaki z hasła (częsty problem z hasłami aplikacji)
        $phpmailer->Password = trim($smtp_password);

        // Ustaw szyfrowanie
        if ($smtp_encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($smtp_encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
        }
        
        // Dodatkowe opcje dla Google Workspace/Gmail (rozwiązuje problemy z certyfikatami SSL/TLS)
        if ($smtp_encryption === 'tls' || $smtp_encryption === 'ssl') {
            $phpmailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
        
        // Ustaw timeout (Google czasami potrzebuje więcej czasu)
        $phpmailer->Timeout = 30;

        // Ustaw adres From - zawsze gdy SMTP jest włączone
        // Użyj smtp_from_email jeśli podany, w przeciwnym razie użyj username
        $final_from_email = '';
        $final_from_name = $smtp_from_name;
        
        if (!empty($smtp_from_email) && is_email($smtp_from_email)) {
            $final_from_email = $smtp_from_email;
        } elseif (!empty($smtp_username) && is_email($smtp_username)) {
            // Fallback: użyj username jako From jeśli from_email nie jest ustawiony
            $final_from_email = $smtp_username;
        }
        
        // Ustaw From w PHPMailer jeśli mamy adres
        if (!empty($final_from_email)) {
            // Użyj setFrom() aby wymusić adres nadawcy
            // Trzeci parametr false = nie ustawiaj automatycznie Reply-To
            $phpmailer->setFrom($final_from_email, $final_from_name, false);
            
            // Upewnij się, że właściwości są też ustawione (dla kompatybilności)
            $phpmailer->From = $final_from_email;
            $phpmailer->FromName = $final_from_name;
        }

        // Debugowanie SMTP (jeśli włączone w ustawieniach)
        $smtp_debug_enabled = get_option('flexmile_smtp_debug', false);
        if ($smtp_debug_enabled) {
            $phpmailer->SMTPDebug = 2; // 2 = pokaż wszystkie komunikaty SMTP
            $phpmailer->Debugoutput = function($str, $level) {
                // Zapisz logi SMTP do opcji WordPressa (dostępne w panelu admina)
                $smtp_logs = get_option('flexmile_smtp_debug_logs', []);
                if (!is_array($smtp_logs)) {
                    $smtp_logs = [];
                }
                
                // Dodaj nowy wpis
                $smtp_logs[] = [
                    'time' => current_time('mysql'),
                    'level' => $level,
                    'message' => trim($str),
                ];
                
                // Ogranicz do ostatnich 100 wpisów
                if (count($smtp_logs) > 100) {
                    $smtp_logs = array_slice($smtp_logs, -100);
                }
                
                update_option('flexmile_smtp_debug_logs', $smtp_logs, false);
                
                // Również loguj do error_log jeśli WP_DEBUG_LOG jest włączone
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log("PHPMailer SMTP: $str");
                }
            };
        } else {
            $phpmailer->SMTPDebug = 0; // Wyłączone domyślnie
        }
    }
    
    /**
     * Przechwytuje szczegóły błędów PHPMailera po konfiguracji
     * 
     * @param object $phpmailer Instancja PHPMailer
     */
    public function capture_phpmailer_errors($phpmailer) {
        // Zapisz referencję do phpmailer, żeby móc odczytać ErrorInfo po błędzie
        static $phpmailer_instance = null;
        $phpmailer_instance = $phpmailer;
        
        // Hook do przechwycenia błędu po próbie wysyłki
        add_action('wp_mail_failed', function($wp_error) use (&$phpmailer_instance) {
            if ($phpmailer_instance && property_exists($phpmailer_instance, 'ErrorInfo')) {
                $error_info = $phpmailer_instance->ErrorInfo;
                if (!empty($error_info) && $error_info !== '') {
                    // Dodaj szczegóły z PHPMailera do error_data
                    $data = $wp_error->get_error_data();
                    if (is_array($data)) {
                        $data['phpmailer_error_info'] = $error_info;
                        $wp_error->add_data($data);
                    }
                }
            }
        }, 5); // Wykonaj przed naszym log_mail_failure (priorytet 10)
    }
}

