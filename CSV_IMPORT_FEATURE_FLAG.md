# Włączanie/Wyłączanie importu CSV

Import CSV jest funkcjonalnością płatną i domyślnie **wyłączoną**.

## Jak włączyć import CSV

Aby włączyć import CSV, edytuj plik `flexmile.php` i zmień wartość stałej:

```php
// W pliku flexmile.php, linia ~20
if (!defined('FLEXMILE_CSV_IMPORT_ENABLED')) {
    define('FLEXMILE_CSV_IMPORT_ENABLED', true); // Zmień z false na true
}
```

## Jak wyłączyć import CSV

Aby wyłączyć import CSV, ustaw wartość na `false`:

```php
define('FLEXMILE_CSV_IMPORT_ENABLED', false);
```

## Alternatywny sposób (przez wp-config.php)

Możesz również zdefiniować tę stałą w pliku `wp-config.php` przed linią `/* That's all, stop editing! Happy publishing. */`:

```php
// Włącz import CSV
define('FLEXMILE_CSV_IMPORT_ENABLED', true);
```

**Zalety tego podejścia:**
- Nie trzeba edytować plików wtyczki (łatwiejsze aktualizacje)
- Można mieć różne ustawienia dla różnych środowisk (dev/prod)

## Co się dzieje gdy import jest wyłączony?

- Formularz importu CSV **nie jest wyświetlany** w panelu administracyjnym
- Próba bezpośredniego dostępu do endpointu importu zwróci błąd
- Funkcjonalność jest całkowicie ukryta przed użytkownikami

## Uwagi

- Po zmianie wartości stałej, odśwież stronę panelu administracyjnego
- Upewnij się, że masz uprawnienia administratora przed włączeniem funkcjonalności dla klientów
- Ta funkcjonalność jest przeznaczona jako dodatek płatny
