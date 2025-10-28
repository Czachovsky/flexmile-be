# FlexMile - WordPress Plugin for Online Car Rental Management

WordPress headless plugin for FlexMile car rental system with API for Angular applications.

## 🚀 Installation

1. Extract `flexmile` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin panel
3. The plugin will automatically:
    - Block WordPress frontend (headless mode)
    - Register CPT and taxonomies
    - Expose REST API endpoints
4. **NEW!** Go to FlexMile Dashboard and click "Import Sample Data" to quickly get started

## 📋 Features

### ✅ Done:

- **Frontend blocking** - WordPress works only as headless CMS
- **CPT Offers** with fields:
    - Year, mileage, horsepower, engine capacity
    - Transmission, color, seats, VIN
    - Price matrix (monthly price based on rental period and mileage limit)
    - Reservation status
- **CPT Reservations** with:
    - Customer data
    - Rental parameters (months + annual mileage limit)
    - Status (pending/approved/rejected/completed)
    - Automatic car reservation marking
- **Taxonomies**: Car Brand, Body Type, Fuel Type
- **REST API** with filtering and infinite scroll
- **Email system** (to admin and customer after reservation)
- **Admin dashboard** with statistics
- **Sample data import** - one click to add 136 brands, 10 body types, 7 fuel types and 3 sample cars

## 📦 Sample Data Import

After activating the plugin, you'll see an **"Import Sample Data"** button in **FlexMile Dashboard**.

One click adds:
- ✅ **136 car brands** (BMW, Audi, Toyota, Mercedes-Benz, Volkswagen...)
- ✅ **10 body types** (SUV, Sedan, Wagon, Hatchback, Coupe...)
- ✅ **7 fuel types** (Petrol, Diesel, Hybrid, Electric...)
- ✅ **3 sample cars** with full data:
    - BMW X5 3.0d xDrive (2022, SUV, Diesel)
    - Toyota Corolla 1.8 Hybrid (2023, Sedan, Hybrid)
    - Volkswagen Golf 1.5 TSI (2021, Hatchback, Petrol)

Import won't overwrite existing data - you can run it safely anytime!

## 🔌 REST API Endpoints

### 1. List of offers
```
GET /wp-json/flexmile/v1/offers
```

**Filter parameters:**
- `car_brand` - brand slug
- `body_type` - body type slug
- `fuel_type` - fuel type slug
- `year_from` - year from
- `year_to` - year to
- `max_mileage` - maximum mileage
- `price_from` - minimum price
- `price_to` - maximum price
- `page` - page number (infinite scroll)
- `per_page` - results per page (max 100)

**Example:**
```
GET /wp-json/flexmile/v1/offers?car_brand=bmw&year_from=2020&page=1&per_page=10
```

**Response (list - lightweight):**
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

**Legacy Headers (backward compatibility):**
- `X-WP-Total` - total results
- `X-WP-TotalPages` - total pages

### 2. Single offer
```
GET /wp-json/flexmile/v1/offers/{id}
```

**Response (full data):**
```json
{
  "id": 123,
  "title": "BMW X5 3.0d xDrive",
  "description": "Full description...",
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
    "color": "Black metallic",
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
    "Air conditioning",
    "GPS Navigation",
    "Bluetooth"
  ],
  "additional_equipment": [
    "Leather seats",
    "Panoramic roof",
    "360° camera",
    "Parking sensors",
    "Adaptive cruise control"
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

### 3. Reserved offers only
```
GET /wp-json/flexmile/v1/offers/reserved
```

Returns only reserved offers (same structure as list endpoint).

### 4. Create reservation
```
POST /wp-json/flexmile/v1/reservations
Content-Type: application/json

{
  "offer_id": 123,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+48 123 456 789",
  "rental_months": 12,
  "annual_mileage_limit": 15000,
  "message": "Additional question..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Reservation created successfully",
  "reservation_id": 456,
  "pricing": {
    "monthly_price": 2700.00,
    "total_price": 32400.00,
    "rental_months": 12,
    "annual_mileage_limit": 15000
  }
}
```

## ⚙️ CORS Configuration

For your Angular app to connect to the API, add to `wp-config.php`:

```php
// CORS for headless WordPress
header('Access-Control-Allow-Origin: http://localhost:4200'); // Angular app URL
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
```

**IMPORTANT:** In production, change `localhost:4200` to your actual Angular app domain!

## 📧 Email Configuration

The plugin sends emails after each reservation:
- **To administrator** - full reservation details
- **To customer** - confirmation

Check if WordPress can send emails. If not, install a plugin like:
- WP Mail SMTP
- Easy WP SMTP

## 🎯 Reservation Management Workflow

1. Customer creates reservation via Angular (POST to API)
2. System creates WP post with "Pending" status
3. Emails are sent (admin + customer)
4. Administrator reviews reservation in WordPress
5. After status change to "Approved":
    - Car is automatically marked as reserved
    - Disappears from available offers list in API
6. After status change to other - car returns to offers

## 📊 Admin Panel

After installation available in menu:
- **FlexMile Dashboard** - statistics and quick access
- **Offers** - fleet management
- **Reservations** - orders list
- **Car Brands / Body Types / Fuel Types** - taxonomies
- **API Settings** - documentation and examples

## 🔧 File Structure

```
flexmile/
├── flexmile.php              # Main plugin file
├── includes/
│   ├── Core/
│   │   └── Frontend_Blocker.php    # Frontend blocking
│   ├── PostTypes/
│   │   ├── Offers.php              # CPT Offers
│   │   └── Reservations.php        # CPT Reservations
│   ├── API/
│   │   ├── Offers_Endpoint.php     # API for offers
│   │   └── Reservations_Endpoint.php # API for reservations
│   └── Admin/
│       ├── Admin_Menu.php          # Admin panel
│       └── Sample_Data_Importer.php # Sample data import
└── README.md
```

## 🚦 Next Steps

### Frontend (Angular):
1. Create service for API communication
2. Offers list with infinite scroll
3. Filters (brand, year, price)
4. Price calculator (based on months and mileage)
5. Reservation form

### Backend (optional):
- [ ] Photo gallery for cars
- [ ] More reservation statuses
- [ ] Export reservations to CSV
- [ ] Email notifications on status change
- [ ] Reservation history for car

## 📞 Support

If you encounter problems, check:
1. Is plugin activated
2. Are permalinks saved (Settings → Permalinks → Save)
3. Is CORS properly configured
4. Do endpoints work (check in browser)

## 🔐 Security

- API is public for GET (offers)
- POST (reservations) has data validation
- Reservations list requires admin permissions
- Frontend completely blocked
- All data is sanitized

## 📝 License

MIT License - use as you wish!

---

**Author:** FlexMile Team  
**Version:** 2.0.0  
**Requires:** WordPress 5.8+, PHP 7.4+
