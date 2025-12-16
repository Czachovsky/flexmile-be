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

        // Pobierz domenę strony (bez http/https/www)
        $site_url = get_site_url();
        $site_domain = $this->extract_domain($site_url);
        $site_name = get_option('blogname', 'FlexMile');
        $admin_email = get_option('admin_email');

        // Użyj domeny strony dla adresu From (zamiast proton.me)
        // Jeśli admin_email jest z innej domeny, użyj noreply@domena-strony
        $from_email = $this->get_from_email($admin_email, $site_domain);
        $from_name = $site_name;

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
}

