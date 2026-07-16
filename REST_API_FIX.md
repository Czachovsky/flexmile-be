# Naprawa problemu z REST API na serwerze

## Problem

Na serwerze produkcyjnym endpointy REST API zwracają błąd 404:
- `http://api.flexmile.pl/wp-json/flexmile/v1/offers` → 404 `/wp-json/v1` (brakuje "flexmile")

## Przyczyny

Najczęstsze przyczyny tego problemu:

1. **Permalinki nie są włączone** - WordPress wymaga włączonych permalinków (nie "Plain") aby REST API działało poprawnie
2. **Rewrite rules nie zostały zaktualizowane** - po aktywacji wtyczki trzeba zaktualizować rewrite rules
3. **Problem z .htaccess** - może być nieprawidłowo skonfigurowany na serwerze

## Rozwiązanie

### Krok 1: Utwórz plik .htaccess (JEŚLI GO NIE MA)

**To jest najważniejszy krok!** Bez pliku `.htaccess` WordPress nie może obsługiwać permalinków i REST API.

1. **Sprawdź czy plik `.htaccess` istnieje** w głównym katalogu WordPress (tam gdzie jest `wp-config.php`)

2. **Jeśli nie istnieje**, utwórz go z następującą zawartością:

```apache
# BEGIN WordPress
# Reguły dla WordPress permalinków i REST API
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /

# Zezwól na bezpośredni dostęp do plików statycznych
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Przekieruj wszystko do index.php (w tym REST API)
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
```

3. **Zapisz plik jako `.htaccess`** (z kropką na początku) w głównym katalogu WordPress

4. **Ustaw odpowiednie uprawnienia:**
   ```bash
   chmod 644 .htaccess
   ```

5. **Przykładowy plik `.htaccess.example`** znajduje się w katalogu wtyczki - możesz go skopiować i zmienić nazwę na `.htaccess`

**UWAGA:** 
- Plik `.htaccess` musi być w **głównym katalogu WordPress** (tam gdzie `wp-config.php`), **NIE** w katalogu wtyczki
- Jeśli używasz **nginx** zamiast Apache, zobacz sekcję poniżej

### Krok 2: Sprawdź diagnostykę API

Po wgraniu nowej wersji wtyczki, sprawdź endpoint diagnostyczny:

```
GET http://api.flexmile.pl/wp-json/flexmile/v1/diagnostics
```

Ten endpoint zwróci informacje o:
- Status permalinków
- Zarejestrowane route'y FlexMile
- Rekomendacje naprawy

### Krok 2: Włącz permalinki w WordPress

1. Zaloguj się do panelu administracyjnego WordPress
2. Przejdź do **Ustawienia → Permalinki**
3. Wybierz **dowolną opcję oprócz "Plain"** (np. "Nazwa wpisu")
4. Kliknij **Zapisz zmiany**

**WAŻNE:** Wtyczka automatycznie próbuje włączyć permalinki przy aktywacji, ale jeśli to nie zadziała, zrób to ręcznie.

### Krok 3: Zaktualizuj rewrite rules

Po włączeniu permalinków:

1. **Deaktywuj** wtyczkę FlexMile
2. **Aktywuj** wtyczkę FlexMile ponownie
3. To automatycznie zaktualizuje rewrite rules

Alternatywnie, możesz użyć endpointu diagnostycznego - jeśli wykryje problemy, automatycznie spróbuje je naprawić.

### Krok 4: Sprawdź czy działa

Po wykonaniu powyższych kroków, sprawdź:

1. **Endpoint główny:**
   ```
   GET http://api.flexmile.pl/wp-json/flexmile/v1
   ```
   Powinien zwrócić listę dostępnych route'ów.

2. **Endpoint ofert:**
   ```
   GET http://api.flexmile.pl/wp-json/flexmile/v1/offers
   ```
   Powinien zwrócić listę ofert.

3. **Endpoint diagnostyczny:**
   ```
   GET http://api.flexmile.pl/wp-json/flexmile/v1/diagnostics
   ```
   Powinien pokazać, że wszystko działa poprawnie.

## Automatyczne naprawy

Wtyczka zawiera automatyczne mechanizmy naprawy:

1. **Automatyczne włączanie permalinków** - przy inicjalizacji wtyczki
2. **Weryfikacja route'ów** - sprawdza czy wszystkie route'y są zarejestrowane
3. **Automatyczny flush rewrite rules** - raz dziennie jeśli potrzeba

## Jeśli problem nadal występuje

1. **Sprawdź logi serwera** - mogą zawierać informacje o błędach
2. **Sprawdź uprawnienia plików** - `.htaccess` musi mieć odpowiednie uprawnienia
3. **Skontaktuj się z hostingiem** - mogą być potrzebne dodatkowe konfiguracje serwera
4. **Sprawdź czy mod_rewrite jest włączony** - wymagane dla permalinków WordPress

## Debugowanie

Aby włączyć tryb debugowania WordPress, dodaj do `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logi będą zapisywane w `wp-content/debug.log`.

---

## Konfiguracja dla nginx (zamiast Apache)

Jeśli używasz **nginx** zamiast Apache, nie używasz pliku `.htaccess`. Zamiast tego skonfiguruj nginx:

```nginx
server {
    listen 80;
    server_name api.flexmile.pl;
    root /ścieżka/do/wordpress;
    index index.php;

    # REST API i permalinki
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # dostosuj do swojej wersji PHP
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Blokuj dostęp do wrażliwych plików
    location ~ /\. {
        deny all;
    }
}
```

Po zmianie konfiguracji nginx, przeładuj serwer:
```bash
sudo nginx -t  # sprawdź konfigurację
sudo systemctl reload nginx  # przeładuj nginx
```

