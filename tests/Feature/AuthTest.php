<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Registration', function () {
    it('can register a new user with valid data', function () {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Password@1',
            'phone_number' => '+1234567890'
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'phone_number',
                        'created_at',
                        'updated_at',
                        'id'
                    ],
                    'token',
                    'token_type',
                    'expires_in'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john@example.com',
                        'phone_number' => '+1234567890'
                    ],
                    'token_type' => 'bearer'
                ]
            ]);

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone_number' => '+1234567890',
            'is_active' => true,
            'role' => 'user'
        ]);

        // Verify password is hashed
        $user = User::where('email', 'john@example.com')->first();
        expect($user->password)->not->toBe('Password@1');
    });

    it('requires all mandatory fields for registration', function () {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
    });

    it('validates email format and uniqueness', function () {
        // Create existing user
        User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'Password@1'
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test invalid email format
        $userData['email'] = 'invalid-email';
        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates password minimum length', function () {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => '123' // Too short
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('validates string length limits', function () {
        $userData = [
            'first_name' => str_repeat('a', 256), // Too long
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Password@1',
            'phone_number' => str_repeat('1', 21) // Too long
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'phone_number']);
    });

    it('allows registration without optional phone number', function () {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Password@1'
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'user' => [
                        'phone_number' => null
                    ]
                ]
            ]);
    });
});

describe('User Login', function () {
    it('can login with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('Password@1')
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'Password@1'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                    'token_type',
                    'expires_in'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => 'john@example.com'
                    ],
                    'token_type' => 'bearer'
                ]
            ]);
    });

    it('rejects login with invalid credentials', function () {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('Password@1')
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
    });

    it('validates login request data', function () {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });
});

describe('User Profile', function () {
    it('can get authenticated user profile', function () {
        $user = User::factory()->create();
        $token = auth()->login($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email
                    ]
                ]
            ]);
    });

    it('requires authentication to get profile', function () {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    });
});

describe('User Logout', function () {
    it('can logout authenticated user', function () {
        $user = User::factory()->create();
        $token = auth()->login($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
    });

    it('requires authentication to logout', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    });
});

describe('Token Refresh', function () {
    it('can refresh JWT token', function () {
        $user = User::factory()->create();
        $token = auth()->login($user);

        \Log::info(['Token: ' => $token]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'expires_in'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'bearer'
                ]
            ]);

        // Verify new token is different
        $newToken = $response->json('data.token');
        expect($newToken)->not->toBe($token);
    });

    it('requires authentication to refresh token', function () {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
    });
});
