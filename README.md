# FlexMile

> Zaawansowana wtyczka WordPress do zarządzania flotą samochodów z pełnym API REST dla aplikacji headless

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)
[![Version](https://img.shields.io/badge/Version-1.0.0-green.svg)](https://flexmile.pl)

---

## Spis treści

- [O projekcie](#-o-projekcie)
- [Główne funkcje](#-główne-funkcje)
- [Wymagania](#-wymagania)
- [Instalacja](#-instalacja)
- [Konfiguracja](#-konfiguracja)
- [API REST](#-api-rest)
- [Panel administracyjny](#-panel-administracyjny)
- [Struktura projektu](#-struktura-projektu)
- [Przykłady użycia](#-przykłady-użycia)
- [Email templates](#-email-templates)
- [Rozwój](#-rozwój)

---

## O projekcie

**FlexMile** to kompleksowa wtyczka WordPress przeznaczona do flexmile w modelu headless. System został zaprojektowany z myślą o integracji  poprzez REST API.

### Kluczowe cechy architektury:

- **Headless WordPress** - frontend całkowicie zablokowany
- **REST API** - pełne API dla wszystkich operacji
- **Custom Post Types** - Oferty, Rezerwacje, Zamówienia
- **Zaawansowane filtrowanie** - marka, model, typ nadwozia, paliwo, cena, rocznik
- **System cenowy** - elastyczna macierz cen z okresami wynajmu i limitami kilometrów
- **Automatyczne emaile** - powiadomienia dla klientów i administratorów
- **Statusy pojazdów** - dostępne, zarezerwowane, zamówione, dostępne wkrótce

---

## Główne funkcje

### Zarządzanie flotą

- **Pełna specyfikacja samochodów** - marka, model, rocznik, silnik, moc, skrzynia biegów
- **Galeria zdjęć** - wielokrotne zdjęcia z możliwością zmiany kolejności
- **Wyposażenie** - standardowe i dodatkowe wyposażenie
- **Reference ID** - automatyczne generowanie unikalnych ID w formacie `FLX-LA-YYYY-XXX`
- **Statusy i flagi** - nowy, dostępny od ręki, dostępny wkrótce, najczęściej wybierany

### System cenowy

- **Macierz cen** - konfiguracja cen dla różnych okresów wynajmu (12, 24, 36, 48 miesięcy)
- **Limity kilometrów** - różne ceny w zależności od rocznego limitu (10k, 15k, 20k km)
- **Automatyczne obliczanie** - najniższa cena wyświetlana na liście ofert
- **Elastyczna konfiguracja** - łatwe dodawanie nowych okresów i limitów

### Rezerwacje i zamówienia

- **Dwa typy zgłoszeń**:
  - **Rezerwacja** - dla samochodów "dostępne wkrótce" (blokuje pojazd)
  - **Zamówienie** - dla samochodów dostępnych od ręki
- **Walidacja dostępności** - automatyczne sprawdzanie czy samochód jest dostępny
- **Szczegóły klienta** - nazwa firmy, NIP, email, telefon
- **Dodatkowe opcje** - zgody marketingowe, miejsce odbioru, wiadomość

### Powiadomienia email

- **Automatyczne emaile** - wysyłane po każdej rezerwacji/zamówieniu
- **Szablony HTML** - profesjonalne szablony dla klienta i administratora
- **Pełne szczegóły** - wszystkie informacje o rezerwacji i samochodzie

### Zaawansowane filtrowanie

- **Filtry podstawowe**: marka, model, typ nadwozia, rodzaj paliwa, skrzynia biegów
- **Filtry zakresowe**: rocznik (od-do), cena (od-do)
- **Filtry dostępności**: dostępne, zarezerwowane, dostępne od ręki
- **Sortowanie**: po dacie lub cenie (rosnąco/malejąco)
- **Paginacja**: infinite scroll z limitem wyników

---

## Wymagania

- **WordPress**: 5.8 lub nowszy
- **PHP**: 7.4 lub nowszy
- **MySQL**: 5.6 lub nowszy
- **Uprawnienia**: możliwość instalacji wtyczek WordPress

---

## Instalacja

### 1. Pobierz wtyczkę

```bash
# Skopiuj folder wtyczki do katalogu WordPress
cp -r flexmile /wp-content/plugins/
```

### 2. Aktywuj wtyczkę

1. Zaloguj się do panelu administracyjnego WordPress
2. Przejdź do **Wtyczki** → **Zainstalowane wtyczki**
3. Znajdź **FlexMile - Car Rental Management**
4. Kliknij **Aktywuj**

### 3. Sprawdź instalację

Po aktywacji wtyczki:
- W menu WordPress pojawi się sekcja **FlexMile**
- Zostaną utworzone nowe typy postów: **Oferty**, **Rezerwacje**, **Zamówienia**
- API będzie dostępne pod adresem: `/wp-json/flexmile/v1/`

---

## Konfiguracja

### CORS dla aplikacji frontend

Aby aplikacja Angular/React mogła łączyć się z API, dodaj do pliku `wp-config.php`:

```php
// CORS dla headless WordPress
header('Access-Control-Allow-Origin: http://localhost:4200'); // Adres aplikacji frontend
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
```

**WAŻNE**: W produkcji zmień `localhost:4200` na faktyczny adres Twojej aplikacji!

### Konfiguracja emaili

Wtyczka używa standardowej funkcji WordPress `wp_mail()`. Aby zapewnić poprawne działanie:

1. **Sprawdź konfigurację PHP** - funkcja `mail()` musi być dostępna
2. **Lub zainstaluj plugin SMTP**:
   - [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/)
   - [Easy WP SMTP](https://wordpress.org/plugins/easy-wp-smtp/)

### Import przykładowych danych

W panelu administracyjnym:
1. Przejdź do **FlexMile** → **Dashboard**
2. Kliknij **Import przykładowych danych**
3. Zostanie wygenerowanych 100 przykładowych ofert samochodów

---

## API REST

### Base URL

```
https://twoja-strona.pl/wp-json/flexmile/v1
```

### Endpointy

#### 1. Lista ofert

```http
GET /offers
```

**Parametry zapytania:**

| Parametr | Typ | Opis | Przykład |
|----------|-----|------|----------|
| `page` | integer | Numer strony | `1` |
| `per_page` | integer | Wyników na stronę (max 100) | `10` |
| `orderby` | string | Sortowanie (`date`, `price`) | `price` |
| `order` | string | Kierunek (`ASC`, `DESC`) | `ASC` |
| `car_brand` | string | Slug marki | `bmw` |
| `car_model` | string | Model | `X5` |
| `body_type` | string | Typ nadwozia | `suv` |
| `fuel_type` | string | Rodzaj paliwa | `diesel` |
| `transmission` | string | Skrzynia (`manual`, `automatic`) | `automatic` |
| `year_from` | integer | Rocznik od | `2020` |
| `year_to` | integer | Rocznik do | `2024` |
| `price_from` | number | Cena od | `1000` |
| `price_to` | number | Cena do | `3000` |
| `available_only` | string | Tylko dostępne (`true`/`false`) | `true` |
| `available_immediately` | string | Od ręki (`true`/`false`) | `true` |

**Przykład zapytania:**

```bash
curl "https://twoja-strona.pl/wp-json/flexmile/v1/offers?car_brand=bmw&year_from=2020&page=1&per_page=10"
```

**Odpowiedź:**

```json
{
  "offers": [
    {
      "id": 123,
      "title": "BMW X5 3.0d",
      "image": {
        "thumbnail": "https://...",
        "medium": "https://...",
        "large": "https://..."
      },
      "engine": "3.0d xDrive",
      "horsepower": 286,
      "transmission": "automatic",
      "year": 2022,
      "brand": {
        "slug": "bmw",
        "name": "BMW"
      },
      "model": "X5",
      "fuel_type": "diesel",
      "price_from": 2200.00,
      "attributes": {
        "new": true,
        "available_immediately": true,
        "coming_soon": false,
        "popular": true
      },
      "available": true
    }
  ],
  "meta": {
    "total": 25,
    "total_pages": 3,
    "current_page": 1,
    "per_page": 10
  }
}
```

#### 2. Pojedyncza oferta

```http
GET /offers/{id}
```

**Przykład:**

```bash
curl "https://twoja-strona.pl/wp-json/flexmile/v1/offers/123"
```

**Odpowiedź zawiera pełne dane:**
- Szczegóły techniczne
- Galeria zdjęć
- Konfiguracja cen (macierz)
- Wyposażenie standardowe i dodatkowe
- Wszystkie atrybuty

#### 3. Lista marek

```http
GET /offers/brands
```

**Odpowiedź:**

```json
[
  {
    "slug": "bmw",
    "name": "BMW"
  },
  {
    "slug": "audi",
    "name": "Audi"
  }
]
```

#### 4. Modele dla marki

```http
GET /offers/brands/{brand_slug}/models
```

**Przykład:**

```bash
curl "https://twoja-strona.pl/wp-json/flexmile/v1/offers/brands/bmw/models"
```

#### 5. Zarezerwowane oferty

```http
GET /offers/reserved
```

Zwraca tylko zarezerwowane samochody.

#### 6. Tworzenie rezerwacji/zamówienia

```http
POST /reservations
Content-Type: application/json
```

**Body (rezerwacja):**

```json
{
  "type": "reservation",
  "offer_id": 123,
  "company_name": "Jan Kowalski Sp. z o.o.",
  "tax_id": "1234567890",
  "email": "jan@example.com",
  "phone": "+48123456789",
  "rental_months": 24,
  "annual_mileage_limit": 15000,
  "message": "Czy możliwe jest odbiór w sobotę?",
  "consent_email": true,
  "consent_phone": false,
  "pickup_location": "salon"
}
```

**Body (zamówienie):**

```json
{
  "type": "order",
  "offer_id": 123,
  "company_name": "Jan Kowalski Sp. z o.o.",
  "tax_id": "1234567890",
  "email": "jan@example.com",
  "phone": "+48123456789",
  "rental_months": 24,
  "annual_mileage_limit": 15000
}
```

**Odpowiedź sukcesu (201):**

```json
{
  "success": true,
  "message": "Rezerwacja została złożona pomyślnie",
  "reservation_id": 456,
  "type": "reservation"
}
```

**Błędy walidacji (400):**

```json
{
  "code": "car_reserved",
  "message": "Ten samochód jest już zarezerwowany",
  "data": {
    "status": 400
  }
}
```

#### 7. Lista rezerwacji (tylko admin)

```http
GET /reservations
Authorization: Bearer {token}
```

Wymaga uprawnień administratora.

---

## Panel administracyjny

### Menu FlexMile

Po aktywacji wtyczki w menu WordPress pojawiają się:

- **FlexMile Dashboard** - główny panel z statystykami
- **Oferty** - zarządzanie flotą samochodów
- **Rezerwacje** - lista rezerwacji
- **Zamówienia** - lista zamówień

### Zarządzanie ofertami

#### Dodawanie nowej oferty

1. Przejdź do **Oferty** → **Dodaj nową**
2. Wypełnij podstawowe informacje:
   - **Tytuł** - np. "BMW X5 3.0d xDrive"
   - **Marka i model** - wybierz z listy
   - **Typ nadwozia** - SUV, Sedan, Hatchback, Kombi, Coupe
   - **Rodzaj paliwa** - Diesel, Bezołowiowa, Elektryczny, Hybryda, Hybryda Plug-in
   - **Rocznik, moc, pojemność** - szczegóły techniczne
3. **Galeria** - dodaj zdjęcia samochodu
4. **Konfiguracja cen**:
   - Okresy wynajmu (np. 12, 24, 36, 48 miesięcy)
   - Limity kilometrów (np. 10000, 15000, 20000 km/rok)
   - Kliknij **Wygeneruj tabelę cen** i wypełnij ceny
5. **Statusy i flagi**:
   - Nowy samochód
   - Dostępny od ręki
   - Dostępny wkrótce (z datą)
   - Najczęściej wybierany
6. **Wyposażenie** - standardowe i dodatkowe
7. **Opublikuj**

#### Filtrowanie w liście

W liście ofert dostępne są filtry:
- **Dostępność**: Dostępne, Zarezerwowane, Zamówione, Dostępne wkrótce

#### Kolumny w liście

- **ID oferty** - unikalny Reference ID (np. `FLX-LA-2024-101`)
- **Status** - wizualne znaczniki statusu
- **Tytuł** - nazwa samochodu
- **Data** - data publikacji

### Zarządzanie rezerwacjami

1. Przejdź do **Rezerwacje**
2. Zobacz listę wszystkich rezerwacji z:
   - Dane klienta (firma, NIP, email, telefon)
   - Powiązany samochód
   - Okres wynajmu i limit kilometrów
   - Cena miesięczna i całkowita
   - Status (pending, approved, rejected)
3. Edytuj status rezerwacji - zmiana statusu na "Zatwierdzona" automatycznie blokuje samochód

---

## Struktura projektu

```
flexmile/
├── flexmile.php                    # Główny plik wtyczki
├── config.json                      # Konfiguracja marek, modeli, typów nadwozia i paliw
├── README.md                        # Dokumentacja
├── API_EXAMPLES.md                  # Przykłady użycia API
│
├── includes/
│   ├── Core/
│   │   └── Frontend_Blocker.php     # Blokada frontendu (headless mode)
│   │
│   ├── PostTypes/
│   │   ├── Offers.php               # Custom Post Type: Oferty
│   │   ├── Reservations.php         # Custom Post Type: Rezerwacje
│   │   └── Orders.php               # Custom Post Type: Zamówienia
│   │
│   ├── API/
│   │   ├── Offers_Endpoint.php      # REST API: Oferty
│   │   ├── Reservations_Endpoint.php # REST API: Rezerwacje/Zamówienia
│   │   ├── Contact_Endpoint.php     # REST API: Kontakt
│   │   └── Banners_Endpoint.php     # REST API: Bannery
│   │
│   └── Admin/
│       ├── Admin_Menu.php           # Panel administracyjny
│       └── Sample_Data_Importer.php  # Import przykładowych danych
│
├── templates/
│   └── emails/
│       ├── admin-reservation.php     # Email do admina (rezerwacja)
│       ├── admin-order.php           # Email do admina (zamówienie)
│       ├── customer-reservation.php  # Email do klienta (rezerwacja)
│       └── customer-order.php       # Email do klienta (zamówienie)
│
└── assets/
    ├── admin-styles.css             # Style panelu admina
    ├── admin-gallery.js              # JavaScript galerii
    └── admin-dropdown.js             # JavaScript dropdownów
```

---

## Przykłady użycia

### JavaScript (Fetch API)

```javascript
// Pobierz listę ofert
fetch('https://twoja-strona.pl/wp-json/flexmile/v1/offers?car_brand=bmw&page=1&per_page=10')
  .then(response => response.json())
  .then(data => {
    console.log('Oferty:', data.offers);
    console.log('Łącznie:', data.meta.total);
  });

// Pobierz pojedynczą ofertę
fetch('https://twoja-strona.pl/wp-json/flexmile/v1/offers/123')
  .then(response => response.json())
  .then(offer => {
    console.log('Oferta:', offer);
  });

// Utwórz rezerwację
fetch('https://twoja-strona.pl/wp-json/flexmile/v1/reservations', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    type: 'reservation',
    offer_id: 123,
    company_name: 'Jan Kowalski Sp. z o.o.',
    tax_id: '1234567890',
    email: 'jan@example.com',
    phone: '+48123456789',
    rental_months: 24,
    annual_mileage_limit: 15000,
    message: 'Czy możliwe jest odbiór w sobotę?'
  })
})
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Rezerwacja utworzona! ID:', data.reservation_id);
    }
  });
```

### JavaScript (Axios)

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://twoja-strona.pl/wp-json/flexmile/v1'
});

// Pobierz oferty
const offers = await api.get('/offers', {
  params: {
    car_brand: 'bmw',
    year_from: 2020,
    page: 1,
    per_page: 10
  }
});

// Utwórz zamówienie
const order = await api.post('/reservations', {
  type: 'order',
  offer_id: 123,
  company_name: 'Jan Kowalski Sp. z o.o.',
  tax_id: '1234567890',
  email: 'jan@example.com',
  phone: '+48123456789',
  rental_months: 24,
  annual_mileage_limit: 15000
});
```

### cURL

```bash
# Lista ofert
curl "https://twoja-strona.pl/wp-json/flexmile/v1/offers?car_brand=bmw"

# Pojedyncza oferta
curl "https://twoja-strona.pl/wp-json/flexmile/v1/offers/123"

# Rezerwacja
curl -X POST "https://twoja-strona.pl/wp-json/flexmile/v1/reservations" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "reservation",
    "offer_id": 123,
    "company_name": "Jan Kowalski Sp. z o.o.",
    "tax_id": "1234567890",
    "email": "jan@example.com",
    "phone": "+48123456789",
    "rental_months": 24,
    "annual_mileage_limit": 15000
  }'
```

### Python

```python
import requests

BASE_URL = 'https://twoja-strona.pl/wp-json/flexmile/v1'

# Pobierz oferty
response = requests.get(f'{BASE_URL}/offers', params={
    'car_brand': 'bmw',
    'year_from': 2020,
    'page': 1,
    'per_page': 10
})
offers = response.json()

# Utwórz rezerwację
reservation = requests.post(f'{BASE_URL}/reservations', json={
    'type': 'reservation',
    'offer_id': 123,
    'company_name': 'Jan Kowalski Sp. z o.o.',
    'tax_id': '1234567890',
    'email': 'jan@example.com',
    'phone': '+48123456789',
    'rental_months': 24,
    'annual_mileage_limit': 15000
})
```

---

## Email templates

Wtyczka automatycznie wysyła emaile po każdej rezerwacji/zamówieniu:

### Szablony

- `admin-reservation.php` - Email do administratora (rezerwacja)
- `admin-order.php` - Email do administratora (zamówienie)
- `customer-reservation.php` - Email do klienta (rezerwacja)
- `customer-order.php` - Email do klienta (zamówienie)

### Lokalizacja

Szablony znajdują się w: `templates/emails/`

### Dostosowywanie

Możesz edytować szablony bezpośrednio w plikach PHP. Każdy szablon otrzymuje następujące zmienne:

- `$rezerwacja_id` - ID rezerwacji/zamówienia
- `$samochod` - Obiekt WP_Post samochodu
- `$params` - Parametry z requestu
- `$cena_miesieczna` - Cena miesięczna
- `$cena_calkowita` - Cena całkowita
- `$entry_type` - Typ wpisu (`reservation` lub `order`)

---

## Rozwój

### Namespace

Wszystkie klasy używają namespace `FlexMile\`:

```php
namespace FlexMile\API;
namespace FlexMile\PostTypes;
namespace FlexMile\Admin;
namespace FlexMile\Core;
```

### Autoloader

Wtyczka używa automatycznego ładowania klas opartego na namespace:

```
FlexMile\API\Offers_Endpoint → includes/API/Offers_Endpoint.php
FlexMile\PostTypes\Offers → includes/PostTypes/Offers.php
```

### Hooks i filtry

Wtyczka wykorzystuje standardowe hooki WordPress:
- `rest_api_init` - rejestracja endpointów API
- `init` - rejestracja Custom Post Types
- `add_meta_boxes` - dodawanie meta boxów
- `save_post` - zapisywanie meta danych

### Testowanie

1. **Import przykładowych danych** - wygeneruj 100 ofert testowych
2. **Test API** - użyj Postman, Insomnia lub curl
3. **Test rezerwacji** - utwórz testową rezerwację przez API

---

## Changelog

### Version 1.0.0

- Dodano system rezerwacji i zamówień
- Zaawansowane filtrowanie ofert
- Macierz cen z okresami wynajmu i limitami kilometrów
- Automatyczne emaile dla klientów i administratorów
- Reference ID dla ofert (FLX-LA-YYYY-XXX)
- Statusy pojazdów (dostępne, zarezerwowane, zamówione)
- Panel administracyjny z filtrowaniem
- Refaktoryzacja kodu
- Pełna dokumentacja API

---

## Autorzy

**FlexMile Team**

- Website: [flexmile.pl](https://flexmile.pl)
- Version: 1.0.0

---

## Licencja

Wszelkie prawa zastrzeżone. Wtyczka jest własnością FlexMile.



