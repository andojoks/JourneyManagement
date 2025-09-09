# 🚀 Journey Manager API

A comprehensive REST API built with Laravel 12 for managing users, trips, and carpooling bookings with JWT authentication, featuring full CRUD operations, pagination, date filtering, and seat reservation capabilities.

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [API Documentation](#-api-documentation)
- [Usage Examples](#-usage-examples)
- [Database Schema](#-database-schema)
- [Security](#-security)
- [Testing](#-testing)
- [Contributing](#-contributing)
- [License](#-license)

## ✨ Features

### Core Functionality
- **User Management**: Registration, authentication, and profile management
- **Trip Management**: Complete CRUD operations for trip tracking with seat capacity
- **Carpooling Bookings**: Seat reservation system for available trips
- **JWT Authentication**: Secure token-based authentication system
- **Smart Authorization**: Users can view all trips but only modify their own; booking access control

### Advanced Features
- **Pagination**: Built-in pagination for efficient data retrieval
- **Date Filtering**: Search trips by date range and location
- **Seat Management**: Track total and available seats for each trip
- **Booking System**: Reserve and cancel seats with automatic seat updates
- **Input Validation**: Comprehensive validation for all endpoints
- **Error Handling**: Detailed error responses with proper HTTP status codes
- **API Documentation**: Complete Swagger/OpenAPI 3.0 documentation

### Technical Features
- **Laravel 12**: Built on the latest Laravel framework
- **SQLite Database**: Lightweight database for development
- **JWT Tokens**: Secure authentication with token refresh
- **RESTful Design**: Following REST API best practices
- **Middleware Protection**: Custom JWT middleware for route protection

## 🔧 Requirements

- **PHP**: 8.2 or higher
- **Composer**: Latest version
- **Laravel**: 12.x
- **Extensions**: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/andojoks/JourneyManagement.git
cd journey-manager
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret
```

### 4. Database Setup

```bash
# Run migrations
php artisan migrate

# (Optional) Seed database with sample data
php artisan db:seed
```

### 5. Start the Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1`

## ⚙️ Configuration

### Environment Variables

Update your `.env` file with the following JWT configuration:

```env
# JWT Configuration
JWT_SECRET=your-jwt-secret-key-here
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256
JWT_LEEWAY=0
JWT_BLACKLIST_ENABLED=true
JWT_BLACKLIST_GRACE_PERIOD=0

# Database Configuration [Developement] (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### JWT Configuration

The JWT package is configured with the following defaults:
- **Token TTL**: 60 minutes
- **Refresh TTL**: 2 weeks
- **Algorithm**: HS256
- **Blacklist**: Enabled for security

## 📚 API Documentation

### Swagger Documentation

The complete API documentation is available in Swagger/OpenAPI 3.0 format:

- **Swagger JSON**: `http://localhost:8000/swagger.json`
- **Interactive Documentation**: `http://localhost:8000/api/documentation`

### API Endpoints

#### Authentication Endpoints
- `POST /api/v1/auth/register` - Register a new user
- `POST /api/v1/auth/login` - Login and get JWT token
- `GET /api/v1/auth/me` - Get current user profile
- `POST /api/v1/auth/logout` - Logout (invalidate token)
- `POST /api/v1/auth/refresh` - Refresh JWT token

#### Trip Management Endpoints
- `GET /api/v1/trips` - List all trips (with pagination & filtering)
- `POST /api/v1/trips` - Create a new trip with seat capacity
- `GET /api/v1/trips/{id}` - Get specific trip with conditional booking visibility
- `PUT /api/v1/trips/{id}` - Update trip (including seat capacity)
- `DELETE /api/v1/trips/{id}` - Delete trip
- `GET /api/v1/trips/available` - List trips with available seats

#### Booking Management Endpoints
- `GET /api/v1/bookings` - List user's bookings
- `POST /api/v1/bookings` - Create a new booking (reserve seats)
- `GET /api/v1/bookings/{id}` - Get specific booking
- `DELETE /api/v1/bookings/{id}` - Cancel booking (restore seats)

### Base URL
```
http://localhost:8000/api/v1
```

## 💡 Usage Examples

### 1. User Registration

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "phone_number": "+1234567890"
  }'
```

### 2. User Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### 3. Create a Trip with Seat Capacity

```bash
curl -X POST http://localhost:8000/api/v1/trips \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "origin": "Paris",
    "destination": "Lyon",
    "start_time": "2024-01-15T08:00:00Z",
    "end_time": "2024-01-15T10:00:00Z",
    "distance": 460,
    "trip_type": "business",
    "total_seats": 4
  }'
```

### 4. List Available Trips for Booking

```bash
curl -X GET "http://localhost:8000/api/v1/trips/available?page=1&limit=10&origin=Paris&destination=Lyon" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 5. Create a Booking (Reserve Seats)

```bash
curl -X POST http://localhost:8000/api/v1/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "trip_id": 1,
    "seats_reserved": 2
  }'
```

### 6. List User's Bookings

```bash
curl -X GET "http://localhost:8000/api/v1/bookings?page=1&limit=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 7. Cancel a Booking

```bash
curl -X DELETE http://localhost:8000/api/v1/bookings/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 8. Update Trip with New Seat Capacity

```bash
curl -X PUT http://localhost:8000/api/v1/trips/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "origin": "Paris",
    "destination": "Marseille",
    "start_time": "2024-01-15T08:00:00Z",
    "end_time": "2024-01-15T12:00:00Z",
    "distance": 770,
    "trip_type": "business",
    "total_seats": 6,
    "status": "in-progress"
  }'
```

## 🚗 Carpooling Booking System

### Business Rules

The booking system implements the following business logic:

#### Trip Visibility
- **All Users**: Can view all trips (both their own and others')
- **Trip Creators**: Can see all bookings for their trips
- **Other Users**: Can only see their own booking for a specific trip

#### Booking Rules
- **Self-Booking Prevention**: Users cannot book seats on their own trips
- **Seat Availability**: Users cannot book more seats than available
- **No Double-Booking**: Users cannot book the same trip multiple times
- **Booking Access**: Users can view/cancel bookings they made OR trips they created

#### Seat Management
- **Automatic Updates**: Available seats are automatically updated when bookings are created/cancelled
- **Capacity Validation**: Trip creators cannot reduce total seats below currently reserved seats
- **Real-time Tracking**: Available seats reflect current booking status

### Database Schema

#### Trips Table (Enhanced)
```sql
- id (Primary Key)
- user_id (Foreign Key to users)
- origin, destination, start_time, end_time
- distance, trip_type, status
- total_seats (NEW: Maximum seats available)
- available_seats (NEW: Currently available seats)
- created_at, updated_at
```

#### Bookings Table (NEW)
```sql
- id (Primary Key)
- user_id (Foreign Key to users)
- trip_id (Foreign Key to trips)
- seats_reserved (Number of seats booked)
- booking_time (When booking was made)
- status (confirmed/cancelled)
- created_at, updated_at
- UNIQUE constraint on (user_id, trip_id)
```

## 🔒 Security

### Authentication
- **JWT Tokens**: Secure token-based authentication
- **Password Hashing**: Laravel's built-in password hashing
- **Token Expiration**: Configurable token lifetime
- **Token Refresh**: Automatic token refresh mechanism

### Authorization
- **Trip Access**: Users can view all trips but only modify their own
- **Booking Access**: Users can view/cancel bookings they made or trips they created
- **Business Rules**: Users cannot book their own trips or exceed available seats
- **Route Protection**: JWT middleware protects all endpoints
- **Input Validation**: Comprehensive validation on all inputs
- **SQL Injection Protection**: Laravel's Eloquent ORM protection

### Security Headers
- **CORS**: Configurable Cross-Origin Resource Sharing
- **Rate Limiting**: Built-in rate limiting for API endpoints
- **CSRF Protection**: Laravel's CSRF protection for web routes

## 🧪 Testing

### Run Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

### Test Structure
- **Feature Tests**: API endpoint testing
- **Unit Tests**: Individual component testing
- **Pest Framework**: Modern testing framework

## 📁 Project Structure

```
journey-manager/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php
│   │   │       ├── TripController.php
│   │   │       └── BookingController.php
│   │   └── Middleware/
│   │       └── JWTMiddleware.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Trip.php
│   │   └── Booking.php
│   └── Providers/
│       └── AppServiceProvider.php
├── config/
│   ├── auth.php
│   └── jwt.php
├── database/
│   ├── migrations/
│   │   ├── create_users_table.php
│   │   ├── create_trips_table.php
│   │   ├── add_seat_capacity_to_trips_table.php
│   │   └── create_bookings_table.php
│   ├── factories/
│   │   ├── UserFactory.php
│   │   ├── TripFactory.php
│   │   └── BookingFactory.php
│   └── database.sqlite
├── public/
│   └── swagger.json
├── routes/
│   └── api.php
├── tests/
│   └── Feature/
│       ├── AuthTest.php
│       ├── TripTest.php
│       └── BookingTest.php
└── README.md
```

## 🚀 Deployment

### Production Setup

1. **Environment Configuration**
   ```bash
   # Set production environment
   APP_ENV=production
   APP_DEBUG=false
   
   # Configure database
   DB_CONNECTION=mysql
   DB_HOST=your-db-host
   DB_DATABASE=your-db-name
   DB_USERNAME=your-db-user
   DB_PASSWORD=your-db-password
   ```

2. **Optimize Application**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Set Permissions**
   ```bash
   chmod -R 755 storage bootstrap/cache
   ```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation for API changes
- Use meaningful commit messages

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🆘 Support

For support and questions:

- **Email**: andojoks@gmail.com
- **Documentation**: Check the Swagger documentation at `/swagger.json`
- **Issues**: Create an issue in the repository

## 🎯 Future Work

- [ ] Email verification for user registration
- [ ] Password reset functionality
- [ ] Real-time trip tracking
- [ ] Mobile app integration
- [ ] Advanced analytics and reporting
- [ ] Multi-language support
- [ ] API rate limiting improvements
- [ ] Push notifications for booking updates
- [ ] Trip rating and review system
- [ ] Payment integration for bookings

---

**Built with ❤️ using Laravel 12**
