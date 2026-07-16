# Debugowanie problemu: WordPress zwraca HTML zamiast JSON

## Problem

Gdy wchodzisz na `http://api.flexmile.pl/wp-json/flexmile/v1/offers/`, WordPress zwraca HTML zamiast JSON.

## Możliwe przyczyny

1. **Permalinki nie są włączone** - WordPress wymaga włączonych permalinków
2. **.htaccess nie działa** - mod_rewrite może być wyłączony lub .htaccess nie jest czytany
3. **WordPress nie rozpoznaje REST API** - żądanie nie jest przekierowywane do REST API

## Kroki debugowania

### Krok 1: Sprawdź czy permalinki są włączone

1. Zaloguj się do WordPress Admin
2. Przejdź do **Ustawienia → Permalinki**
3. **JEŚLI** widzisz "Plain" (zwykłe), zmień na **"Nazwa wpisu"** lub inną opcję
4. Kliknij **Zapisz zmiany**

### Krok 2: Sprawdź czy .htaccess istnieje i działa

1. Sprawdź czy plik `.htaccess` istnieje w głównym katalogu WordPress
2. Sprawdź uprawnienia:
   ```bash
   ls -la .htaccess
   ```
   Powinno być: `-rw-r--r--` (644)

3. Sprawdź czy mod_rewrite jest włączony:
   ```bash
   # W pliku .htaccess dodaj na początku:
   # Test mod_rewrite
   RewriteEngine On
   RewriteRule ^test-rewrite$ /index.php?test=1 [L]
   ```
   Jeśli to nie działa, mod_rewrite może być wyłączony - skontaktuj się z hostingiem.

### Krok 3: Sprawdź endpoint diagnostyczny

Spróbuj wejść na:
```
http://api.flexmile.pl/wp-json/flexmile/v1/diagnostics
```

Jeśli też zwraca HTML, problem jest z podstawową konfiguracją REST API.

### Krok 4: Sprawdź podstawowy endpoint WordPress REST API

Spróbuj:
```
http://api.flexmile.pl/wp-json/
```

Jeśli zwraca JSON z listą namespace'ów - REST API działa, ale nasze endpointy nie.
Jeśli zwraca HTML - problem z podstawową konfiguracją WordPress.

### Krok 5: Sprawdź logi błędów

Włącz debugowanie w `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Następnie sprawdź `wp-content/debug.log` po próbie wejścia na endpoint.

### Krok 6: Sprawdź czy wtyczka jest aktywna

1. Przejdź do **Wtyczki → Zainstalowane wtyczki**
2. Upewnij się, że **FlexMile** jest aktywna
3. **Deaktywuj** i **ponownie aktywuj** wtyczkę

### Krok 7: Sprawdź konfigurację serwera

Jeśli używasz **nginx**, upewnij się że konfiguracja zawiera:
```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

## Szybkie rozwiązanie

Jeśli nic nie pomaga, wykonaj te kroki w kolejności:

1. **Włącz permalinki** w WordPress Admin (Ustawienia → Permalinki)
2. **Skopiuj .htaccess** z katalogu wtyczki do głównego katalogu WordPress
3. **Ustaw uprawnienia:** `chmod 644 .htaccess`
4. **Deaktywuj i aktywuj** wtyczkę FlexMile
5. **Wyczyść cache** (jeśli używasz cache'u)
6. **Sprawdź endpoint:** `http://api.flexmile.pl/wp-json/flexmile/v1`

## Jeśli nadal nie działa

Sprawdź czy:
- Serwer używa Apache (nie nginx) - jeśli nginx, potrzebna jest inna konfiguracja
- mod_rewrite jest włączony na serwerze
- WordPress jest zainstalowany w głównym katalogu (nie w podkatalogu)
- Nie ma innych wtyczek, które mogą blokować REST API

