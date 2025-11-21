# Przykłady API - Rezerwacje

## Endpoint

**POST** `/wp-json/flexmile/v1/reservations`

## Przykładowy obiekt JSON do wysłania

### Minimalny request (wszystkie wymagane pola)

```json
{
  "offer_id": 123,
  "company_name": "Jan Kowalski Sp. z o.o.",
  "tax_id": "1234567890",
  "email": "jan.kowalski@example.com",
  "phone": "+48123456789",
  "rental_months": 24,
  "annual_mileage_limit": 15000
}
```

### Pełny request (z opcjonalną wiadomością)

```json
{
  "offer_id": 123,
  "company_name": "Jan Kowalski Sp. z o.o.",
  "tax_id": "1234567890",
  "email": "jan.kowalski@example.com",
  "phone": "+48123456789",
  "rental_months": 36,
  "annual_mileage_limit": 20000,
  "message": "Czy możliwe jest odbiór samochodu w sobotę rano?"
}
```

## Szczegółowy opis pól

| Pole | Typ | Wymagane | Opis | Przykład |
|------|-----|----------|------|----------|
| `offer_id` | integer | ✅ | ID oferty samochodu (post type: `offer`) | `123` |
| `company_name` | string | ✅ | Nazwa firmy klienta | `"Jan Kowalski Sp. z o.o."` |
| `tax_id` | string | ✅ | Numer NIP firmy klienta | `"1234567890"` |
| `email` | string (email) | ✅ | Adres e-mail klienta | `"jan@example.com"` |
| `phone` | string | ✅ | Numer telefonu klienta | `"+48123456789"` |
| `rental_months` | integer | ✅ | Okres wynajmu w miesiącach (minimum: 1) | `12`, `24`, `36` |
| `annual_mileage_limit` | integer | ✅ | Roczny limit kilometrów (minimum: 0) | `10000`, `15000`, `20000` |
| `message` | string | ❌ | Dodatkowa wiadomość od klienta | `"Mam pytanie o..."` |

## Przykłady użycia

### JavaScript (Fetch API)

```javascript
const reservationData = {
  offer_id: 123,
  company_name: "Jan Kowalski Sp. z o.o.",
  tax_id: "1234567890",
  email: "jan.kowalski@example.com",
  phone: "+48123456789",
  rental_months: 24,
  annual_mileage_limit: 15000,
  message: "Czy możliwe jest odbiór w sobotę?"
};

fetch('https://twoja-strona.pl/wp-json/flexmile/v1/reservations', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(reservationData)
})
  .then(response => response.json())
  .then(data => {
    console.log('Sukces:', data);
    // {
    //   "success": true,
    //   "message": "Rezerwacja została złożona pomyślnie",
    //   "reservation_id": 456,
    //   "pricing": {
    //     "monthly_price": 1299.99,
    //     "total_price": 31199.76,
    //     "rental_months": 24,
    //     "annual_mileage_limit": 15000
    //   }
    // }
  })
  .catch(error => {
    console.error('Błąd:', error);
  });
```

### JavaScript (Axios)

```javascript
import axios from 'axios';

const reservationData = {
  offer_id: 123,
  company_name: "Jan Kowalski Sp. z o.o.",
  tax_id: "1234567890",
  email: "jan.kowalski@example.com",
  phone: "+48123456789",
  rental_months: 24,
  annual_mileage_limit: 15000
};

axios.post('https://twoja-strona.pl/wp-json/flexmile/v1/reservations', reservationData)
  .then(response => {
    console.log('Sukces:', response.data);
  })
  .catch(error => {
    console.error('Błąd:', error.response.data);
  });
```

### cURL

```bash
curl -X POST https://twoja-strona.pl/wp-json/flexmile/v1/reservations \
  -H "Content-Type: application/json" \
  -d '{
    "offer_id": 123,
    "company_name": "Jan Kowalski Sp. z o.o.",
    "tax_id": "1234567890",
    "email": "jan.kowalski@example.com",
    "phone": "+48123456789",
    "rental_months": 24,
    "annual_mileage_limit": 15000,
    "message": "Czy możliwe jest odbiór w sobotę?"
  }'
```

### PHP

```php
$reservationData = [
    'offer_id' => 123,
    'company_name' => 'Jan Kowalski Sp. z o.o.',
    'tax_id' => '1234567890',
    'email' => 'jan.kowalski@example.com',
    'phone' => '+48123456789',
    'rental_months' => 24,
    'annual_mileage_limit' => 15000,
    'message' => 'Czy możliwe jest odbiór w sobotę?'
];

$response = wp_remote_post('https://twoja-strona.pl/wp-json/flexmile/v1/reservations', [
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode($reservationData),
]);

$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);

if ($data['success']) {
    echo "Rezerwacja utworzona! ID: " . $data['reservation_id'];
}
```

### Python (requests)

```python
import requests

reservation_data = {
    "offer_id": 123,
    "company_name": "Jan Kowalski Sp. z o.o.",
    "tax_id": "1234567890",
    "email": "jan.kowalski@example.com",
    "phone": "+48123456789",
    "rental_months": 24,
    "annual_mileage_limit": 15000,
    "message": "Czy możliwe jest odbiór w sobotę?"
}

response = requests.post(
    'https://twoja-strona.pl/wp-json/flexmile/v1/reservations',
    json=reservation_data
)

if response.status_code == 201:
    data = response.json()
    print(f"Rezerwacja utworzona! ID: {data['reservation_id']}")
    print(f"Cena miesięczna: {data['pricing']['monthly_price']} zł")
    print(f"Cena całkowita: {data['pricing']['total_price']} zł")
else:
    print(f"Błąd: {response.json()}")
```

## Odpowiedź API

### Sukces (201 Created)

```json
{
  "success": true,
  "message": "Rezerwacja została złożona pomyślnie",
  "reservation_id": 456,
  "pricing": {
    "monthly_price": 1299.99,
    "total_price": 31199.76,
    "rental_months": 24,
    "annual_mileage_limit": 15000
  }
}
```

### Błąd walidacji (400 Bad Request)

Przykładowe błędy:

```json
{
  "code": "invalid_car",
  "message": "Nieprawidłowy ID samochodu",
  "data": {
    "status": 400
  }
}
```

```json
{
  "code": "car_reserved",
  "message": "Ten samochód jest już zarezerwowany",
  "data": {
    "status": 400
  }
}
```

```json
{
  "code": "invalid_period",
  "message": "Wybrany okres wynajmu nie jest dostępny dla tego samochodu",
  "data": {
    "status": 400
  }
}
```

## Ważne uwagi

1. **`offer_id`** - Musi istnieć i być typem post `offer`
2. **`rental_months`** - Musi być jednym z dostępnych okresów dla danego samochodu (zdefiniowane w `_pricing_config`)
3. **`annual_mileage_limit`** - Musi być jednym z dostępnych limitów dla danego samochodu (zdefiniowane w `_pricing_config`)
4. **Kombinacja** - Kombinacja `rental_months` + `annual_mileage_limit` musi mieć przypisaną cenę w konfiguracji
5. **Dostępność** - Samochód nie może być już zarezerwowany (`_reservation_active` nie może być `1`)

