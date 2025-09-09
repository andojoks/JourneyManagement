# Journey Manager API Setup

This Laravel 12 application provides a REST API for managing users and trips with JWT authentication.

## Features

- **User Management**: Registration, login, and profile management
- **Trip Management**: CRUD operations for trips with user ownership validation
- **JWT Authentication**: Secure token-based authentication
- **Pagination**: Built-in pagination for trip listings
- **Date Filtering**: Search trips by date range
- **Authorization**: Users can only access/modify their own trips

## API Endpoints

### Authentication Endpoints

- `POST /api/auth/register` - Register a new user
- `POST /api/auth/login` - Login and get JWT token
- `POST /api/auth/logout` - Logout (invalidate token)
- `POST /api/auth/refresh` - Refresh JWT token
- `GET /api/auth/me` - Get current user profile

### Trip Endpoints

- `GET /api/trips` - List user's trips (with pagination and date filtering)
- `POST /api/trips` - Create a new trip
- `GET /api/trips/{id}` - Get specific trip
- `PUT /api/trips/{id}` - Update trip
- `DELETE /api/trips/{id}` - Delete trip

## Setup Instructions

### 1. Install Dependencies

```bash
composer install
```

### 2. Environment Configuration

Create a `.env` file based on `.env.example` and add the following JWT configuration:

```env
# JWT Configuration
JWT_SECRET=your-secret-key-here
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256
JWT_LEEWAY=0
JWT_BLACKLIST_ENABLED=true
JWT_BLACKLIST_GRACE_PERIOD=0
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Generate JWT Secret

```bash
php artisan jwt:secret
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Start the Server

```bash
php artisan serve
```

## API Usage Examples

### Register a User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "phone_number": "+1234567890"
  }'
```

### Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### Create a Trip

```bash
curl -X POST http://localhost:8000/api/trips \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "origin": "Paris",
    "destination": "Lyon",
    "start_time": "2024-01-15T08:00:00Z",
    "end_time": "2024-01-15T10:00:00Z",
    "distance": 460,
    "trip_type": "business"
  }'
```

### List Trips with Pagination and Date Filtering

```bash
curl -X GET "http://localhost:8000/api/trips?page=1&limit=10&start_date=2024-01-01&end_date=2024-01-31" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Database Schema

### Users Table
- `id` - Primary key
- `first_name` - User's first name
- `last_name` - User's last name
- `email` - Unique email address
- `password` - Hashed password
- `phone_number` - Optional phone number
- `is_active` - Account status
- `role` - User role (default: 'user')
- `created_at`, `updated_at` - Timestamps

### Trips Table
- `id` - Primary key
- `user_id` - Foreign key to users table
- `origin` - Trip origin location
- `destination` - Trip destination
- `start_time` - Trip start datetime
- `end_time` - Trip end datetime
- `status` - Trip status (in-progress, completed, cancelled)
- `distance` - Trip distance in kilometers
- `trip_type` - Type of trip (personal, business)
- `created_at`, `updated_at` - Timestamps

## Security Features

- JWT token-based authentication
- Password hashing using Laravel's built-in hashing
- User ownership validation for trip operations
- Token expiration and refresh mechanisms
- Input validation and sanitization

## Testing

Run the test suite:

```bash
php artisan test
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
