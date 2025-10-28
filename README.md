# FlexMile - Wtyczka WordPress do Zarządzania Komisem Online

Wtyczka do headless WordPressa dla systemu komisu samochodowego FlexMile z API dla aplikacji Angular.

## 🚀 Instalacja

1. Wypakuj folder `flexmile` do `/wp-content/plugins/`
2. Aktywuj wtyczkę w panelu WordPress
3. Wtyczka automatycznie:
    - Zablokuje frontend WordPressa (headless mode)
    - Zarejestruje CPT i taksonomie
    - Udostępni REST API endpointy
4. **NOWOŚĆ!** Przejdź do FlexMile Dashboard i kliknij "Importuj przykładowe dane" aby szybko rozpocząć

## 📋 Funkcjonalności

### ✅ Już zrobione:

- **Blokada frontendu** - WordPress działa tylko jako headless CMS
- **CPT Oferty** z polami:
    - Rocznik, przebieg, moc, pojemność silnika
    - Skrzynia biegów, kolor, liczba miejsc, VIN
    - Macierz cen (cena miesięczna zależna od okresu wynajmu i limitu km)
    - Status rezerwacji
- **CPT Rezerwacje** z:
    - Danymi klienta
    - Parametrami wynajmu (miesiące + roczny limit km)
    - Statusami (pending/approved/rejected/completed)
    - Automatycznym oznaczaniem samochodów jako zarezerwowane
- **Taksonomie**: Marka, Typ nadwozia, Rodzaj paliwa
- **REST API** z filtrowaniem i infinite scroll
- **System maili** (do admina i klienta po rezerwacji)
- **Dashboard administracyjny** ze statystykami
- **Import przykładowych danych** - jednym kliknięciem dodajesz 136 marek, 10 typów nadwozia, 7 rodzajów paliwa i 3 przykładowe samochody

## 📦 Import przykładowych danych

Po aktywacji wtyczki w **FlexMile Dashboard** zobaczysz przycisk **"Importuj przykładowe dane"**.

Jeden klik doda:
- ✅ **136 marek** samochodów (BMW, Audi, Toyota, Mercedes-Benz, Volkswagen...)
- ✅ **10 typów nadwozia** (SUV, Sedan, Kombi, Hatchback, Coupe...)
- ✅ **7 rodzajów paliwa** (Benzyna, Diesel, Hybryda, Elektryczny...)
- ✅ **3 przykładowe samochody** z pełnymi danymi:
    - BMW X5 3.0d xDrive (2022, SUV, Diesel)
    - Toyota Corolla 1.8 Hybrid (2023, Sedan, Hybryda)
    - Volkswagen Golf 1.5 TSI (2021, Hatchback, Benzyna)

Import nie nadpisuje istniejących danych - możesz go uruchomić bezpiecznie w każdej chwili!

## 🔌 REST API Endpoints

### 1. Lista ofert
```
GET /wp-json/flexmile/v1/offers
```

**Parametry filtrowania:**
- `car_brand` - slug marki
- `body_type` - slug typu nadwozia
- `fuel_type` - slug rodzaju paliwa
- `year_from` - rocznik od
- `year_to` - rocznik do
- `max_mileage` - maksymalny przebieg
- `price_from` - cena minimalna
- `price_to` - cena maksymalna
- `page` - numer strony (infinite scroll)
- `per_page` - liczba wyników (max 100)

**Przykład:**
```
GET /wp-json/flexmile/v1/offers?car_brand=bmw&year_from=2020&page=1&per_page=10
```

**Odpowiedź (lista - wersja lekka):**
```json
{
  "offers": [
    {
      "id": 123,
      "title": "BMW X5 3.0d",
      "slug": "bmw-x5-30d",
      "image": {
        "thumbnail": "https://...",
        "medium": "https://...",
        "large": "https://..."
      },
      "engine": "3.0d xDrive",
      "horsepower": 286,
      "transmission": "automatic",
      "year": 2022,
      "mileage": 50000,
      "brand": {
        "id": 1,
        "name": "BMW",
        "slug": "bmw"
      },
      "body_type": {
        "name": "SUV",
        "slug": "suv"
      },
      "fuel_type": {
        "name": "Diesel",
        "slug": "diesel"
      },
      "price_from": 2200.00,
      "attributes": {
        "new": true,
        "available_immediately": true,
        "coming_soon": false,
        "popular": true,
        "featured": true
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

**Nagłówki (wsteczna kompatybilność):**
- `X-WP-Total` - łączna liczba wyników
- `X-WP-TotalPages` - liczba stron

### 2. Pojedyncza oferta
```
GET /wp-json/flexmile/v1/offers/{id}
```

**Odpowiedź (pełne dane):**
```json
{
  "id": 123,
  "title": "BMW X5 3.0d xDrive",
  "description": "Pełny opis...",
  "slug": "bmw-x5-30d",
  "featured_image": "https://...",
  "thumbnail": "https://...",
  "gallery": [
    {
      "id": 456,
      "url": "https://...",
      "thumbnail": "https://...",
      "medium": "https://...",
      "large": "https://..."
    }
  ],
  "specs": {
    "year": 2022,
    "mileage": 50000,
    "engine": "3.0d xDrive",
    "horsepower": 286,
    "engine_capacity": 2993,
    "transmission": "automatic",
    "drivetrain": "AWD",
    "color": "Czarny metalik",
    "seats": 5,
    "doors": 4,
    "vin_number": "WBAKR810501A23456"
  },
  "brand": {
    "id": 1,
    "name": "BMW",
    "slug": "bmw"
  },
  "body_type": {
    "id": 2,
    "name": "SUV",
    "slug": "suv"
  },
  "fuel_type": {
    "id": 3,
    "name": "Diesel",
    "slug": "diesel"
  },
  "pricing": {
    "rental_periods": [12, 24, 36, 48],
    "mileage_limits": [10000, 15000, 20000],
    "price_matrix": {
      "12_10000": 2800.00,
      "12_15000": 2900.00,
      "12_20000": 3000.00,
      "24_10000": 2600.00,
      "24_15000": 2700.00,
      "24_20000": 2800.00,
      "36_10000": 2400.00,
      "36_15000": 2500.00,
      "36_20000": 2600.00,
      "48_10000": 2200.00,
      "48_15000": 2300.00,
      "48_20000": 2400.00
    },
    "lowest_price": 2200.00
  },
  "standard_equipment": [
    "ABS",
    "ESP",
    "Klimatyzacja",
    "Nawigacja GPS",
    "Bluetooth"
  ],
  "additional_equipment": [
    "Skórzana tapicerka",
    "Dach panoramiczny",
    "Kamera 360°",
    "Czujniki parkowania",
    "Tempomat adaptacyjny"
  ],
  "attributes": {
    "new": true,
    "available_immediately": true,
    "coming_soon": false,
    "popular": true,
    "featured": true
  },
  "available": true
}
```

### 3. Tylko zarezerwowane oferty
```
GET /wp-json/flexmile/v1/offers/reserved
```

Zwraca tylko zarezerwowane samochody (ta sama struktura co endpoint listy).

### 4. Tworzenie rezerwacji
```
POST /wp-json/flexmile/v1/reservations
Content-Type: application/json

{
  "offer_id": 123,
  "first_name": "Jan",
  "last_name": "Kowalski",
  "email": "jan@example.com",
  "phone": "+48 123 456 789",
  "rental_months": 12,
  "annual_mileage_limit": 15000,
  "message": "Dodatkowe pytanie..."
}
```

**Odpowiedź:**
```json
{
  "success": true,
  "message": "Rezerwacja została utworzona pomyślnie",
  "reservation_id": 456,
  "pricing": {
    "monthly_price": 2700.00,
    "total_price": 32400.00,
    "rental_months": 12,
    "annual_mileage_limit": 15000
  }
}
```

## ⚙️ Konfiguracja CORS

Aby aplikacja Angular mogła łączyć się z API, dodaj do `wp-config.php`:
```php
// CORS dla headless WordPress
header('Access-Control-Allow-Origin: http://localhost:4200'); // Adres aplikacji Angular
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
```

**WAŻNE:** W produkcji zmień `localhost:4200` na faktyczny adres Twojej aplikacji Angular!

## 📧 Konfiguracja maili

Wtyczka wysyła maile po każdej rezerwacji:
- **Do administratora** - pełne szczegóły rezerwacji
- **Do klienta** - potwierdzenie

Sprawdź czy WordPress może wysyłać maile. Jeśli nie, zainstaluj plugin jak:
- WP Mail SMTP
- Easy WP SMTP

## 🎯 Workflow zarządzania rezerwacjami

1. Klient składa rezerwację przez Angular (POST do API)
2. System tworzy wpis w WP z statusem "Oczekująca"
3. Wysyłane są maile (admin + klient)
4. Administrator sprawdza rezerwację w WordPress
5. Po zmianie statusu na "Zatwierdzona":
    - Samochód automatycznie oznaczany jako zarezerwowany
    - Znika z listy dostępnych aut w API
6. Po zmianie na inny status - samochód wraca do oferty

## 📊 Panel administracyjny

Po zainstalowaniu dostępny w menu:
- **FlexMile Dashboard** - statystyki i szybki dostęp
- **Oferty** - zarządzanie flotą
- **Rezerwacje** - lista zamówień
- **Marki / Typy nadwozia / Paliwa** - taksonomie
- **Ustawienia API** - dokumentacja i przykłady

## 🔧 Struktura plików
```
flexmile/
├── flexmile.php              # Główny plik wtyczki
├── includes/
│   ├── Core/
│   │   └── Frontend_Blocker.php    # Blokada frontendu
│   ├── PostTypes/
│   │   ├── Offers.php              # CPT Oferty
│   │   └── Reservations.php        # CPT Rezerwacje
│   ├── API/
│   │   ├── Offers_Endpoint.php     # API dla ofert
│   │   └── Reservations_Endpoint.php # API rezerwacji
│   └── Admin/
│       ├── Admin_Menu.php          # Panel admina
│       └── Sample_Data_Importer.php # Import przykładowych danych
└── README.md
```

## 🚦 Następne kroki

### Frontend (Angular):
1. Stwórz serwis do komunikacji z API
2. Lista ofert z infinite scroll
3. Filtry (marka, rocznik, cena)
4. Kalkulator ceny (na podstawie miesięcy i limitu km)
5. Formularz rezerwacji

### Backend (opcjonalnie):
- [ ] Galeria zdjęć dla samochodów
- [ ] Więcej statusów rezerwacji
- [ ] Export rezerwacji do CSV
- [ ] Powiadomienia email przy zmianie statusu
- [ ] Historia rezerwacji dla samochodu

## 📞 Support

W razie problemów sprawdź:
1. Czy wtyczka jest aktywowana
2. Czy permalinki są zapisane (Ustawienia → Permalinki → Zapisz)
3. Czy CORS jest poprawnie skonfigurowany
4. Czy endpointy działają (sprawdź w przeglądarce)

## 🔐 Bezpieczeństwo

- API jest publiczne dla GET (oferty)
- POST (rezerwacje) ma walidację danych
- Lista rezerwacji wymaga uprawnień admina
- Frontend całkowicie zablokowany
- Wszystkie dane są sanityzowane

## 📝 Licencja

MIT License - użyj jak chcesz!

---

**Autor:** FlexMile Team  
**Wersja:** 2.0.0  
**Wymaga:** WordPress 5.8+, PHP 7.4+
