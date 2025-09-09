# 🚀 Journey Manager API

A comprehensive REST API built with Laravel 12 for managing users, trips, and carpooling bookings with JWT authentication, featuring advanced queue management, route optimization, dynamic pricing, and priority-based booking system.

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [API Documentation](#-api-documentation)
- [Booking System Flow](#-booking-system-flow)
- [Queue Management](#-queue-management)
- [Advanced Features](#-advanced-features)
- [Commands & Scheduling](#-commands--scheduling)
- [Database Schema](#-database-schema)
- [Security](#-security)
- [Testing](#-testing)
- [Project Structure](#-project-structure)
- [Deployment](#-deployment)
- [Contributing](#-contributing)
- [License](#-license)

## ✨ Features

### Core Functionality
- **User Management**: Registration, authentication, and profile management
- **Trip Management**: Complete CRUD operations for trip tracking with seat capacity
- **Priority Booking Queue**: Advanced queue system with priority scoring
- **JWT Authentication**: Secure token-based authentication system
- **Smart Authorization**: Users can view all trips but only modify their own

### Advanced Features
- **Route Optimization**: Dijkstra's algorithm for optimal route finding
- **Dynamic Pricing**: Surge pricing based on demand, time, and events
- **Priority Queue System**: Fair booking access with priority scoring
- **Transaction Safety**: Database locking prevents overbooking
- **Automatic Processing**: Background jobs for queue management
- **Real-time Processing**: Immediate booking when seats available

### Technical Features
- **Laravel 12**: Built on the latest Laravel framework
- **SQLite Database**: Lightweight database for development
- **JWT Tokens**: Secure authentication with token refresh
- **RESTful Design**: Following REST API best practices
- **Middleware Protection**: Custom JWT middleware for route protection
- **Comprehensive Testing**: Feature and unit tests with Pest framework

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

# Seed waypoints and route data
php artisan db:seed --class=WaypointSeeder

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

Update your `.env` file with the following configuration:

```env
# JWT Configuration
JWT_SECRET=your-jwt-secret-key-here
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256
JWT_LEEWAY=0
JWT_BLACKLIST_ENABLED=true
JWT_BLACKLIST_GRACE_PERIOD=0

# Database Configuration [Development] (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Queue Processing Configuration
QUEUE_PROCESSING_LIMIT=50
QUEUE_CLEANUP_DAYS=7
QUEUE_PROCESSING_INTERVAL=5
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
- `GET /api/v1/trips/{id}` - Get specific trip details
- `PUT /api/v1/trips/{id}` - Update trip (including seat capacity)
- `DELETE /api/v1/trips/{id}` - Delete trip
- `GET /api/v1/trips/available` - List trips with available seats

#### Booking Management Endpoints
- `GET /api/v1/bookings` - List user's bookings (with trip filtering)
- `POST /api/v1/bookings` - Create a new booking (priority queue system)
- `GET /api/v1/bookings/{id}` - Get specific booking
- `PUT /api/v1/bookings/{id}` - Update booking (seats/status)
- `DELETE /api/v1/bookings/{id}` - Cancel booking (restore seats)

#### Route Optimization Endpoints
- `GET /api/v1/routes/search` - Search waypoints by name/city
- `GET /api/v1/routes/find` - Find optimal route between waypoints
- `GET /api/v1/routes/cities` - Get route between cities by name
- `POST /api/v1/routes/pricing` - Calculate dynamic pricing for trip
- `POST /api/v1/routes/pricing/bulk` - Calculate pricing for multiple trips

#### Queue Management Endpoints
- `GET /api/v1/queue/status` - Get queue status for specific trip
- `GET /api/v1/queue/positions` - Get user's queue positions
- `DELETE /api/v1/queue/cancel` - Cancel queued booking request

### Base URL
```
http://localhost:8000/api/v1
```

## 🔄 Booking System Flow

### Priority Queue System

The booking system uses a sophisticated priority queue to ensure fair access to limited seats:

#### 1. Booking Request Flow

```
User Request → Validation → Add to Queue → Try Immediate Processing → Response
```

#### 2. Priority Scoring Algorithm

The system calculates priority scores based on multiple factors:

- **User Loyalty**: Based on previous bookings (up to 50 points)
- **Early Booking**: Advance booking bonuses (10-30 points)
- **Trip Creator**: High priority for trip creators (100 points)
- **Seat Quantity**: Preference for single seats (+5 points)
- **Premium Users**: Future subscription bonuses

#### 3. Processing Layers

**Layer 1: Queue Addition (Real-time)**
- When user makes booking request
- All requests are added to priority queue
- Returns 202 Accepted with queue information

**Layer 2: Background Jobs (Automated)**
- Scheduled every 5 minutes
- Processes queues when seats become available
- Handles cancellations and seat releases
- Creates actual bookings from queue items

**Layer 3: Manual Triggers (On-demand)**
- Trip creators can trigger immediate processing
- For debugging and monitoring
- Emergency processing when needed

#### 4. Response Types

**⏳ Queued Booking Request (202 Accepted):**
```json
{
  "success": true,
  "message": "Booking request added to queue",
  "data": {
    "queue_info": {
      "queue_id": 790,
      "priority_score": 30,
      "estimated_position": 3,
      "status": "pending",
      "trip_info": {
        "trip_id": 123,
        "origin": "Paris",
        "destination": "Lyon",
        "start_time": "2024-01-15T08:00:00Z",
        "available_seats": 2,
        "total_seats": 4
      }
    }
  }
}
```

## 🎯 Queue Management

### Queue States

- **pending**: Waiting to be processed
- **processing**: Currently being processed
- **completed**: Successfully processed
- **failed**: Processing failed with reason

### Queue Processing Commands

#### Manual Processing
```bash
# Process queue for specific trip
php artisan queue:process-bookings --trip-id=123

# Process all queues
php artisan queue:process-bookings

# Clean up old queue items
php artisan queue:process-bookings --cleanup
```

#### Scheduled Processing
```bash
# Process queues automatically (every 5 minutes)
php artisan queue:process-scheduled --limit=50

# Check scheduled tasks
php artisan schedule:list
```

### Queue Monitoring

```bash
# View queue status for trip
curl -X GET "http://localhost:8000/api/v1/queue/status?trip_id=123" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Get user's queue positions
curl -X GET "http://localhost:8000/api/v1/queue/positions" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 🚀 Advanced Features

### Route Optimization

The system implements Dijkstra's algorithm for finding optimal routes:

```bash
# Find route between waypoints
curl -X GET "http://localhost:8000/api/v1/routes/find?from=Paris&to=Lyon" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Get route between cities
curl -X GET "http://localhost:8000/api/v1/routes/cities?from_city=Paris&to_city=Marseille" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Dynamic Pricing

Surge pricing based on multiple factors:

- **Demand Ratio**: Based on occupancy rate (up to 50% surge)
- **Time-based**: Rush hours (+20%), weekends (+15%), late night (+10%)
- **Distance-based**: Longer trips get higher multipliers
- **Event-based**: Major holidays (+30%)
- **Weather-based**: Placeholder for future weather API integration

```bash
# Calculate pricing for trip
curl -X POST "http://localhost:8000/api/v1/routes/pricing" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"trip_id": 123}'
```

## 🛠️ Commands & Scheduling

### Available Commands

#### Queue Management
```bash
# Process booking queues
php artisan queue:process-bookings [--trip-id=123] [--cleanup]

# Process queues automatically
php artisan queue:process-scheduled [--limit=50]

# List scheduled tasks
php artisan schedule:list
```

#### Database Management
```bash
# Run migrations
php artisan migrate

# Seed waypoints
php artisan db:seed --class=WaypointSeeder

# Refresh database
php artisan migrate:refresh --seed
```

#### Development
```bash
# Generate JWT secret
php artisan jwt:secret

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Generate application key
php artisan key:generate
```

### Scheduled Tasks

The system includes automatic scheduling:

```php
// Every 5 minutes - Process booking queues
Schedule::command('queue:process-scheduled --limit=50')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Daily at 2 AM - Clean up old queue items
Schedule::command('queue:process-bookings --cleanup')
    ->dailyAt('02:00')
    ->withoutOverlapping();
```

### Enable Scheduler

Add to your crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 💡 Usage Examples

### 1. User Registration & Login

```bash
# Register
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "phone_number": "+1234567890"
  }'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### 2. Create Trip with Seat Capacity

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

### 3. Create Booking Request (Priority Queue)

```bash
curl -X POST http://localhost:8000/api/v1/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "trip_id": 1,
    "seats_reserved": 2
  }'
```

**Response (202 Accepted):**
```json
{
  "success": true,
  "message": "Booking request added to queue",
  "data": {
    "queue_info": {
      "queue_id": 123,
      "priority_score": 45,
      "estimated_position": 1,
      "status": "pending",
      "trip_info": {
        "trip_id": 1,
        "origin": "Paris",
        "destination": "Lyon",
        "start_time": "2024-01-15T08:00:00Z",
        "available_seats": 2,
        "total_seats": 4
      }
    }
  }
}
```

### 4. Find Optimal Route

```bash
curl -X GET "http://localhost:8000/api/v1/routes/find?from=Paris&to=Lyon" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 5. Calculate Dynamic Pricing

```bash
curl -X POST "http://localhost:8000/api/v1/routes/pricing" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"trip_id": 1}'
```

### 6. Check Queue Status

```bash
curl -X GET "http://localhost:8000/api/v1/queue/status?trip_id=1" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 7. Get User's Queue Positions

```bash
curl -X GET "http://localhost:8000/api/v1/queue/positions" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 8. Cancel Queued Request

```bash
curl -X DELETE "http://localhost:8000/api/v1/queue/cancel" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"queue_id": 123}'
```

## 🗄️ Database Schema

### Core Tables

#### Users Table
```sql
- id (Primary Key)
- first_name, last_name, email, phone_number
- password (hashed)
- email_verified_at, created_at, updated_at
```

#### Trips Table (Enhanced)
```sql
- id (Primary Key)
- user_id (Foreign Key to users)
- origin, destination, start_time, end_time
- distance, trip_type, status
- total_seats (Maximum seats available)
- available_seats (Currently available seats)
- base_price, surge_multiplier, final_price
- route_waypoints (JSON - optimal route waypoints)
- priority_score (For queueing)
- created_at, updated_at
```

#### Bookings Table
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

#### Booking Queue Table
```sql
- id (Primary Key)
- user_id (Foreign Key to users)
- trip_id (Foreign Key to trips)
- seats_requested (Number of seats requested)
- priority_score (Calculated priority score)
- status (pending/processing/completed/failed)
- queued_at, processed_at
- failure_reason (If processing failed)
- created_at, updated_at
```

#### Waypoints Table
```sql
- id (Primary Key)
- name (Waypoint name)
- latitude, longitude (Coordinates)
- created_at, updated_at
```

#### Route Segments Table
```sql
- id (Primary Key)
- from_waypoint_id (Foreign Key to waypoints)
- to_waypoint_id (Foreign Key to waypoints)
- distance_km (Distance in kilometers)
- time_minutes (Estimated time in minutes)
- base_price (Base price for segment)
- is_active (Whether segment is active)
- created_at, updated_at
- UNIQUE constraint on (from_waypoint_id, to_waypoint_id)
```

## 🔒 Security

### Authentication & Authorization
- **JWT Tokens**: Secure token-based authentication
- **Password Hashing**: Laravel's built-in password hashing
- **Token Expiration**: Configurable token lifetime
- **Token Refresh**: Automatic token refresh mechanism
- **Route Protection**: JWT middleware protects all endpoints

### Business Rules Enforcement
- **Self-Booking Prevention**: Users cannot book their own trips
- **Seat Availability**: Users cannot book more seats than available
- **No Double-Booking**: Users cannot book the same trip multiple times
- **Queue Management**: Users cannot have multiple pending requests
- **Transaction Safety**: Database locking prevents overbooking

### Data Protection
- **Input Validation**: Comprehensive validation on all inputs
- **SQL Injection Protection**: Laravel's Eloquent ORM protection
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

# Run specific test file
php artisan test tests/Feature/BookingTest.php
```

### Test Structure

- **Feature Tests**: API endpoint testing with queue integration
- **Unit Tests**: Individual component testing
- **Pest Framework**: Modern testing framework
- **Database Testing**: RefreshDatabase trait for clean state

### Test Coverage

The test suite covers:
- ✅ User authentication and authorization
- ✅ Trip CRUD operations with seat management
- ✅ Booking system with priority queue
- ✅ Queue processing and priority scoring
- ✅ Route optimization and dynamic pricing
- ✅ Error handling and edge cases
- ✅ Transaction safety and race conditions

## 📁 Project Structure

```
journey-manager/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── ProcessBookingQueue.php
│   │       └── ProcessBookingQueueScheduled.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php
│   │   │       ├── TripController.php
│   │   │       ├── BookingController.php
│   │   │       ├── RouteController.php
│   │   │       └── BookingQueueController.php
│   │   └── Middleware/
│   │       └── JWTMiddleware.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Trip.php
│   │   ├── Booking.php
│   │   ├── BookingQueue.php
│   │   ├── Waypoint.php
│   │   └── RouteSegment.php
│   ├── Services/
│   │   ├── BookingQueueService.php
│   │   ├── RouteOptimizationService.php
│   │   └── DynamicPricingService.php
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
│   │   ├── create_bookings_table.php
│   │   ├── create_waypoints_table.php
│   │   ├── create_route_segments_table.php
│   │   ├── add_pricing_to_trips_table.php
│   │   └── create_booking_queue_table.php
│   ├── factories/
│   │   ├── UserFactory.php
│   │   ├── TripFactory.php
│   │   ├── BookingFactory.php
│   │   ├── WaypointFactory.php
│   │   └── RouteSegmentFactory.php
│   ├── seeders/
│   │   └── WaypointSeeder.php
│   └── database.sqlite
├── public/
│   └── swagger.json
├── routes/
│   ├── api.php
│   └── console.php
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
   
   # Queue configuration
   QUEUE_PROCESSING_LIMIT=100
   QUEUE_CLEANUP_DAYS=7
   ```

2. **Optimize Application**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   ```

3. **Set Up Scheduler**
   ```bash
   # Add to crontab
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

4. **Set Permissions**
   ```bash
   chmod -R 755 storage bootstrap/cache
   ```

### Docker Deployment

```dockerfile
FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www/storage

# Expose port
EXPOSE 8000

# Start application
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
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
- Test queue processing thoroughly
- Ensure transaction safety

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🆘 Support

For support and questions:

- **Email**: andojoks@gmail.com
- **Documentation**: Check the Swagger documentation at `/swagger.json`
- **Issues**: Create an issue in the repository

## 🎯 Future Work

- [ ] Real-time notifications for queue updates
- [ ] Mobile app integration
- [ ] Payment integration for bookings
- [ ] Advanced analytics and reporting
- [ ] Multi-language support
- [ ] Weather API integration for pricing
- [ ] Machine learning for demand prediction
- [ ] Trip rating and review system
- [ ] Social features and user connections
- [ ] Advanced route optimization with traffic data

---

**Built with ❤️ using Laravel 12**

*Featuring advanced queue management, route optimization, and dynamic pricing*