# Przewodnik importu ofert z pliku CSV

## Wymagania

Aby zaimportować oferty z pliku CSV, musisz mieć uprawnienia administratora WordPress.

## Format pliku CSV

Plik CSV powinien mieć:
- **Kodowanie**: UTF-8 (zalecane)
- **Separator**: przecinek (`,`)
- **Pierwsza linia**: nagłówki kolumn
- **Kolejne linie**: dane ofert

## Wymagane kolumny

Następujące kolumny są **wymagane** do utworzenia oferty:

- `title` - Tytuł oferty (np. "BMW X5 3.0d xDrive")
- `car_brand_slug` - Slug marki (np. "bmw", "toyota", "volkswagen") - musi istnieć w `config.json`
- `car_model` - Model samochodu (np. "X5", "Corolla", "Golf") - musi istnieć w `config.json` dla danej marki

## Opcjonalne kolumny

### Podstawowe informacje
- `body_type` - Typ nadwozia (np. "SUV", "Sedan", "Hatchback", "Kombi", "Coupe")
- `fuel_type` - Rodzaj paliwa (np. "diesel", "petrol", "hybrid", "electric")
- `year` - Rocznik (liczba całkowita, np. 2022)
- `horsepower` - Moc w KM (liczba całkowita, np. 286)
- `engine_capacity` - Pojemność silnika w cm³ (liczba całkowita, np. 2998)
- `engine` - Oznaczenie silnika (np. "3.0d xDrive", "1.8 Hybrid")
- `transmission` - Skrzynia biegów ("manual" lub "automatic")
- `drivetrain` - Napęd (np. "FWD", "AWD", "RWD")
- `color` - Kolor (np. "Czarny metalik", "Biały")
- `seats` - Liczba miejsc (liczba całkowita, np. 5)
- `doors` - Liczba drzwi (np. "5", "4")
- `description` - Opis oferty (może zawierać wiele linii)

### Ceny

Możesz importować ceny na trzy sposoby:

#### 1. Indywidualne kolumny (ZALECANE - najłatwiejsze)
Użyj kolumn w formacie `price_PERIOD_LIMIT`, gdzie:
- `PERIOD` to liczba miesięcy (np. 12, 24, 36, 48)
- `LIMIT` to roczny limit kilometrów (np. 10000, 15000, 20000)

**Przykłady kolumn:**
- `price_12_10000` - cena za 12 miesięcy z limitem 10000 km/rok
- `price_12_15000` - cena za 12 miesięcy z limitem 15000 km/rok
- `price_24_10000` - cena za 24 miesiące z limitem 10000 km/rok
- `price_24_15000` - cena za 24 miesiące z limitem 15000 km/rok
- itd.

**Przykład w CSV:**
```csv
title,car_brand_slug,car_model,price_12_10000,price_12_15000,price_24_10000,price_24_15000
BMW X5,bmw,X5,2200,2250,2100,2150
```

#### 2. Macierz cen (JSON)
- `price_matrix` - Macierz cen w formacie JSON

**Przykład price_matrix (JSON):**
```json
{"12_10000":2200,"12_15000":2250,"12_20000":2300,"24_10000":2100,"24_15000":2150,"24_20000":2200}
```

#### 3. Automatyczne generowanie
- `lowest_price` - Najniższa cena w zł/miesiąc (liczba, np. 2200.00)
- `rental_periods` - Okresy wynajmu w miesiącach, oddzielone przecinkami (np. "12,24,36,48")
- `mileage_limits` - Roczne limity kilometrów, oddzielone przecinkami (np. "10000,15000,20000")

Jeśli podasz tylko `lowest_price`, system automatycznie wygeneruje ceny dla wszystkich kombinacji okresów i limitów.

### Flagi i statusy
- `new_car` - Nowy samochód ("1" lub "0", lub "true"/"false")
- `available_immediately` - Dostępny od ręki ("1" lub "0", lub "true"/"false")
- `most_popular` - Najczęściej wybierany ("1" lub "0", lub "true"/"false")
- `coming_soon` - Dostępny wkrótce ("1" lub "0", lub "true"/"false")
- `coming_soon_date` - Data dostępności (format: YYYY-MM-DD, np. "2024-06-01")

### Wyposażenie
- `standard_equipment` - Wyposażenie standardowe (może zawierać wiele linii)
- `additional_equipment` - Wyposażenie dodatkowe (może zawierać wiele linii)

## Przykładowy plik CSV

Zobacz plik `import-offers-example.csv` w katalogu głównym wtyczki.

## Ważne uwagi

1. **Marki i modele**: `car_brand_slug` i `car_model` muszą istnieć w pliku `config.json`. Jeśli marka lub model nie istnieje, oferta zostanie pominięta.

2. **Duplikaty**: System sprawdza, czy oferta o danym tytule już istnieje. Jeśli tak, oferta zostanie pominięta.

3. **Walidacja**: Jeśli wiersz nie ma wymaganych pól (title, car_brand_slug, car_model), zostanie pominięty.

4. **Ceny**: Jeśli nie podasz `price_matrix`, system automatycznie wygeneruje ceny na podstawie `lowest_price` i standardowych okresów/limitów.

5. **Kodowanie**: Upewnij się, że plik CSV jest zapisany w kodowaniu UTF-8, aby polskie znaki były poprawnie wyświetlane.

## Jak zaimportować

1. Przejdź do **FlexMile** → **Dashboard** w panelu administracyjnym WordPress
2. Znajdź sekcję **"Import ofert z pliku CSV"**
3. Kliknij **"Wybierz plik"** i wybierz swój plik CSV
4. Kliknij **"Importuj CSV"**
5. Poczekaj na komunikat o wyniku importu

## Obsługa błędów

Jeśli import się nie powiedzie:
- Sprawdź, czy plik ma rozszerzenie `.csv`
- Sprawdź, czy pierwsza linia zawiera nagłówki kolumn
- Sprawdź, czy wszystkie wymagane kolumny są obecne
- Sprawdź, czy marki i modele istnieją w `config.json`
- Sprawdź kodowanie pliku (powinno być UTF-8)

## Przykład minimalnego pliku CSV

```csv
title,car_brand_slug,car_model
BMW X5 3.0d,bmw,X5
Toyota Corolla 1.8 Hybrid,toyota,Corolla
```

## Przykład pełnego pliku CSV

### Z indywidualnymi cenami (ZALECANE):
```csv
title,car_brand_slug,car_model,body_type,fuel_type,year,horsepower,transmission,price_12_10000,price_12_15000,price_24_10000,price_24_15000,new_car,available_immediately
BMW X5 3.0d xDrive,bmw,X5,SUV,diesel,2022,286,automatic,2200,2250,2100,2150,0,1
Toyota Corolla 1.8 Hybrid,toyota,Corolla,Sedan,hybrid,2023,122,automatic,1500,1550,1400,1450,1,1
```

### Z lowest_price (automatyczne generowanie):
```csv
title,car_brand_slug,car_model,body_type,fuel_type,year,horsepower,transmission,lowest_price,rental_periods,mileage_limits,new_car,available_immediately
BMW X5 3.0d xDrive,bmw,X5,SUV,diesel,2022,286,automatic,2200,"12,24,36,48","10000,15000,20000",0,1
Toyota Corolla 1.8 Hybrid,toyota,Corolla,Sedan,hybrid,2023,122,automatic,1500,"12,24,36","10000,15000",1,1
```
