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
- **CPT Samochody** z polami:
  - Rocznik, przebieg, moc, pojemność
  - Skrzynia biegów, kolor, liczba miejsc, VIN
  - Kalkulator ceny (cena bazowa + dopłata za km)
  - Status rezerwacji
- **CPT Rezerwacje** z:
  - Danymi klienta
  - Parametrami wynajmu
  - Statusami (pending/approved/rejected/completed)
  - Automatycznym oznaczaniem samochodów jako zarezerwowane
- **Taksonomie**: Marka, Typ nadwozia, Rodzaj paliwa
- **REST API** z filtrowaniem i infinite scroll
- **System maili** (do admina i klienta po rezerwacji)
- **Dashboard administracyjny** ze statystykami
- **Import przykładowych danych** - jednym kliknięciem dodajesz 30 marek, 10 typów nadwozia, 7 rodzajów paliwa i 3 przykładowe samochody

## 📦 Import przykładowych danych

Po aktywacji wtyczki w **FlexMile Dashboard** zobaczysz przycisk **"Importuj przykładowe dane"**.

Jeden klik doda:
- ✅ **30 marek** samochodów (BMW, Audi, Toyota, Mercedes-Benz, Volkswagen...)
- ✅ **10 typów nadwozia** (SUV, Sedan, Kombi, Hatchback, Coupe...)
- ✅ **7 rodzajów paliwa** (Benzyna, Diesel, Hybryda, Elektryczny...)
- ✅ **3 przykładowe samochody** z pełnymi danymi:
  - BMW X5 3.0d xDrive (2022, SUV, Diesel)
  - Toyota Corolla 1.8 Hybrid (2023, Sedan, Hybryda)
  - Volkswagen Golf 1.5 TSI (2021, Hatchback, Benzyna)

Import nie nadpisuje istniejących danych - możesz go uruchomić bezpiecznie w każdej chwili!

## 🔌 REST API Endpoints

### 1. Lista samochodów
```
GET /wp-json/flexmile/v1/samochody
```

**Parametry filtrowania:**
- `marka` - slug marki
- `typ_nadwozia` - slug typu nadwozia
- `paliwo` - slug rodzaju paliwa
- `rocznik_od` - rocznik od
- `rocznik_do` - rocznik do
- `przebieg_max` - maksymalny przebieg
- `cena_od` - cena minimalna
- `cena_do` - cena maksymalna
- `page` - numer strony (infinite scroll)
- `per_page` - liczba wyników (max 100)

**Przykład:**
```
GET /wp-json/flexmile/v1/samochody?marka=bmw&rocznik_od=2020&page=1&per_page=10
```

**Odpowiedź:**
```json
[
  {
    "id": 123,
    "nazwa": "BMW X5 3.0d",
    "opis": "Opis samochodu...",
    "slug": "bmw-x5-30d",
    "obrazek_glowny": "https://...",
    "miniaturka": "https://...",
    "galeria": [...],
    "parametry": {
      "rocznik": 2022,
      "przebieg": 50000,
      "moc": 286,
      "pojemnosc": 2993,
      "skrzynia": "automatic",
      "kolor": "czarny",
      "liczba_miejsc": 5,
      "numer_vin": "..."
    },
    "marka": {
      "id": 1,
      "nazwa": "BMW",
      "slug": "bmw"
    },
    "typ_nadwozia": {...},
    "paliwo": {...},
    "ceny": {
      "cena_bazowa": 2500.00,
      "cena_za_km": 0.50
    },
    "dostepny": true
  }
]
```

**Headers:**
- `X-WP-Total` - łączna liczba wyników
- `X-WP-TotalPages` - liczba stron

### 2. Pojedynczy samochód
```
GET /wp-json/flexmile/v1/samochody/{id}
```

### 3. Tworzenie rezerwacji
```
POST /wp-json/flexmile/v1/rezerwacje
Content-Type: application/json

{
  "samochod_id": 123,
  "imie": "Jan",
  "nazwisko": "Kowalski",
  "email": "jan@example.com",
  "telefon": "+48 123 456 789",
  "ilosc_miesiecy": 12,
  "ilosc_km": 15000,
  "wiadomosc": "Dodatkowe pytanie..."
}
```

**Odpowiedź:**
```json
{
  "success": true,
  "message": "Rezerwacja została złożona pomyślnie",
  "rezerwacja_id": 456,
  "cena_calkowita": 32500.00
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

**WAŻNE:** W produkcji zmień `*` lub `localhost:4200` na faktyczny adres Twojej aplikacji Angular!

## 📧 Konfiguracja maili

Wtyczka wysyła maile po każdej rezerwacji:
- **Do administratora** - pełne szczegóły rezerwacji
- **Do klienta** - potwierdzenie rezerwacji

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
- **Samochody** - zarządzanie flotą
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
│   │   ├── Samochody.php           # CPT Samochody
│   │   └── Rezerwacje.php          # CPT Rezerwacje
│   ├── API/
│   │   ├── Samochody_Endpoint.php  # API dla aut
│   │   └── Rezerwacje_Endpoint.php # API rezerwacji
│   └── Admin/
│       └── Admin_Menu.php          # Panel admina
└── README.md
```

## 🚦 Następne kroki

### Frontend (Angular):
1. Stwórz serwis do komunikacji z API
2. Lista samochodów z infinite scroll
3. Filtry (marka, rocznik, cena)
4. Kalkulator ceny (na podstawie km i miesięcy)
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

- API jest publiczne dla GET (samochody)
- POST (rezerwacje) ma walidację danych
- Lista rezerwacji wymaga uprawnień admina
- Frontend całkowicie zablokowany
- Wszystkie dane są sanitizowane

## 📝 Licencja

MIT License - użyj jak chcesz!

---

**Autor:** FlexMile Team  
**Wersja:** 1.1.0  
**Wymaga:** WordPress 5.8+, PHP 7.4+
