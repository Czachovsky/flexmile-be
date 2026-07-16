# Instrukcja instalacji .htaccess

## Szybki start

1. **Skopiuj plik `.htaccess`** z katalogu wtyczki do **głównego katalogu WordPress** (tam gdzie jest `wp-config.php`)

2. **Ustaw uprawnienia:**
   ```bash
   chmod 644 .htaccess
   ```

3. **Sprawdź czy działa:**
   ```
   GET http://api.flexmile.pl/wp-json/flexmile/v1
   ```

## Ważne informacje

- Plik `.htaccess` **MUSI** być w głównym katalogu WordPress (tam gdzie `wp-config.php`)
- **NIE** umieszczaj go w katalogu wtyczki
- Jeśli używasz **nginx**, nie potrzebujesz `.htaccess` - zobacz `REST_API_FIX.md`

## Co zawiera plik .htaccess?

Plik zawiera podstawowe reguły WordPress dla:
- Permalinków (ładne URL-e)
- REST API (`/wp-json/...`)
- Przekierowania wszystkich żądań do `index.php`

## Jeśli masz już plik .htaccess

Jeśli masz już plik `.htaccess` w WordPress, **NIE** zastępuj go całkowicie. Zamiast tego:

1. Otwórz istniejący plik `.htaccess`
2. Sprawdź czy zawiera sekcję `# BEGIN WordPress`
3. Jeśli zawiera, upewnij się że reguły są poprawne (zobacz `REST_API_FIX.md`)
4. Jeśli nie zawiera, dodaj zawartość z pliku `.htaccess` z katalogu wtyczki

## Problemy?

Zobacz pełną dokumentację w pliku `REST_API_FIX.md`

