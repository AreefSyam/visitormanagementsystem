# Implementation Plan: Professional Authentication System

## Overview

This plan implements a comprehensive authentication system for the Laravel visitor management application, including user registration, email verification, secure login/logout, password reset, session management, rate limiting, and audit logging. The implementation follows Laravel 11.x conventions and security best practices.

## Tasks

- [x]   1. Set up project foundation and configuration
    - Update configuration files for session, authentication, and mail settings
    - Verify database tables exist (users, sessions, password_reset_tokens, cache)
    - Configure environment variables for production-ready settings
    - _Requirements: 10, 26_

- [x]   2. Create custom validation rules and form requests
    - [x] 2.1. Create PasswordComplexity validation rule
        - Create `app/Rules/PasswordComplexity.php` with regex validation
        - Implement `passes()` method checking uppercase, lowercase, number, special character
        - Implement `message()` method returning user-friendly error message
        - _Requirements: 18.1-18.7_
    - [x] 2.2. Create LoginRequest form request class
        - Create `app/Http/Requests/Auth/LoginRequest.php`
        - Add validation rules for email (required, email) and password (required, min:8)
        - Add custom error messages for each validation rule
        - Implement `authenticate()` method with credential validation
        - Implement `ensureIsNotRateLimited()` method checking rate limiter
        - Implement `throttleKey()` method returning unique rate limit key
        - _Requirements: 1, 2, 3_
    - [x] 2.3. Create RegisterRequest form request class
        - Create `app/Http/Requests/Auth/RegisterRequest.php`
        - Add validation rules for name, email, password, password_confirmation, terms
        - Use PasswordComplexity rule for password validation
        - Add `prepareForValidation()` method to convert email to lowercase
        - Add custom error messages for each validation rule
        - _Requirements: 16, 17, 18_
    - [x] 2.4. Create ForgotPasswordRequest form request class
        - Create `app/Http/Requests/Auth/ForgotPasswordRequest.php`
        - Add validation rules for email field
        - Implement `ensureIsNotRateLimited()` method (3 attempts per 60 minutes)
        - Implement `throttleKey()` method for password reset rate limiting
        - _Requirements: 23, 24_
    - [x] 2.5. Create ResetPasswordRequest form request class
        - Create `app/Http/Requests/Auth/ResetPasswordRequest.php`
        - Add validation rules for token, email, password, password_confirmation
        - Use PasswordComplexity rule for new password validation
        - Add custom error messages
        - _Requirements: 25_

- [x]   3. Update User model and create service classes
    - [x] 3.1. Update User model for authentication features
        - Modify `app/Models/User.php` to implement `MustVerifyEmail` interface
        - Override `sendEmailVerificationNotification()` method
        - Override `sendPasswordResetNotification()` method
        - Add `isAdmin()` helper method checking role field
        - Update `casts` array to include `email_verified_at` as datetime
        - _Requirements: 19, 20, 22_
    - [x] 3.2. Create EmailVerificationService
        - Create `app/Services/Auth/EmailVerificationService.php`
        - Implement `sendVerificationEmail(User $user)` method generating signed URL
        - Implement `verify(User $user)` method marking email as verified
        - Implement `generateVerificationToken()` method creating 24-hour signed URL
        - Add audit logging for verification events
        - _Requirements: 20, 21, 26_

- [x]   4. Create middleware for authentication and security
    - [x] 4.1. Update RedirectIfAuthenticated middleware
        - Modify `app/Http/Middleware/RedirectIfAuthenticated.php`
        - Change redirect destination to route('dashboard')
        - Ensure middleware applies to 'web' guard
        - _Requirements: 13.4-13.5_
    - [x] 4.2. Create ValidateSession middleware
        - Create `app/Http/Middleware/ValidateSession.php`
        - Implement `handle()` method checking session integrity
        - Verify user agent matches session record
        - Check session hasn't exceeded timeout (120 minutes)
        - Destroy session if validation fails and redirect to login
        - Update `last_activity` timestamp on successful validation
        - _Requirements: 5, 7_
    - [x] 4.3. Register middleware in Kernel
        - Update `bootstrap/app.php` or `app/Http/Kernel.php` (Laravel 11 structure)
        - Register ValidateSession in web middleware group
        - Ensure auth, guest, verified middleware are properly configured
        - _Requirements: 13_

- [x]   5. Implement authentication controllers
    - [x] 5.1. Create AuthController for login/logout
        - Create `app/Http/Controllers/Auth/AuthController.php`
        - Implement `showLoginForm()` method returning login view
        - Implement `login(LoginRequest)` method handling authentication
        - Call `$request->authenticate()` for credential validation
        - Regenerate session ID after successful login
        - Handle "Remember Me" token creation
        - Redirect to intended URL or dashboard
        - Add success flash message with user name
        - Log successful and failed login attempts
        - Implement `logout(Request)` method invalidating session
        - Delete remember tokens on logout
        - Regenerate CSRF token on logout
        - Log logout events
        - _Requirements: 2, 3, 4, 6, 8, 11, 12_
    - [x] 5.2. Create RegisterController for user registration
        - Create `app/Http/Controllers/Auth/RegisterController.php`
        - Implement `showRegistrationForm()` method returning registration view
        - Implement `register(RegisterRequest)` method creating user
        - Hash password with bcrypt cost factor 12
        - Store email as lowercase
        - Set `email_verified_at` to null
        - Call EmailVerificationService to send verification email
        - Log registration event (email, IP, timestamp)
        - Redirect to verification notice with success message
        - _Requirements: 17, 19, 20, 26_
    - [x] 5.3. Create EmailVerificationController
        - Create `app/Http/Controllers/Auth/EmailVerificationController.php`
        - Implement `notice()` method displaying verification notice page
        - Implement `verify(Request, $id, $hash)` method processing verification
        - Validate signed URL signature
        - Check token expiration (24 hours)
        - Call EmailVerificationService to mark email as verified
        - Create authenticated session after verification
        - Log verification event
        - Redirect to dashboard with success message
        - Implement `resend(Request)` method for resending verification email
        - Check if already verified
        - Apply rate limiting (3 attempts per 60 minutes)
        - Invalidate old tokens before generating new one
        - _Requirements: 20, 21, 22, 26_
    - [x] 5.4. Create PasswordResetController
        - Create `app/Http/Controllers/Auth/PasswordResetController.php`
        - Implement `showLinkRequestForm()` method returning forgot password view
        - Implement `sendResetLinkEmail(ForgotPasswordRequest)` method
        - Validate email and check rate limiting
        - Generate secure reset token using Laravel's Password broker
        - Hash token before storing in password_reset_tokens table
        - Send reset email via notification system (queued)
        - Display generic success message (even if email doesn't exist)
        - Log reset request event
        - Implement `showResetForm(Request, $token)` method displaying reset form
        - Validate token exists and hasn't expired
        - Pre-populate email from token
        - Implement `reset(ResetPasswordRequest)` method processing password reset
        - Validate token and new password
        - Hash new password with bcrypt cost factor 12
        - Update user password
        - Delete reset token from database
        - Delete all remember tokens for user
        - Log password change event
        - Redirect to login with success message
        - _Requirements: 23, 24, 25, 26_

- [x]   6. Create email notification classes
    - [x] 6.1. Create VerifyEmailNotification
        - Create `app/Notifications/Auth/VerifyEmailNotification.php`
        - Implement `ShouldQueue` interface for background processing
        - Implement `via()` method returning ['mail']
        - Implement `toMail()` method building email message
        - Generate temporary signed route with 24-hour expiration
        - Include user name in greeting
        - Add verification button with clear call-to-action
        - Include expiration notice and "didn't create account" disclaimer
        - _Requirements: 20_
    - [x] 6.2. Create ResetPasswordNotification
        - Create `app/Notifications/Auth/ResetPasswordNotification.php`
        - Implement `ShouldQueue` interface for background processing
        - Accept token in constructor
        - Implement `via()` method returning ['mail']
        - Implement `toMail()` method building email message
        - Generate password reset URL with token and email parameters
        - Include user name in greeting
        - Add reset button with clear call-to-action
        - Include 60-minute expiration notice
        - Include "didn't request reset" disclaimer
        - _Requirements: 24_

- [x]   7. Configure routes for authentication
    - [x] 7.1. Add guest-only authentication routes
        - Update `routes/web.php` with guest middleware group
        - Add GET and POST routes for /login (login form and submission)
        - Add GET and POST routes for /register (registration form and submission)
        - Add GET and POST routes for /forgot-password (forgot password form and email sending)
        - Add GET /reset-password/{token} route (reset form display)
        - Add POST /reset-password route (password reset processing)
        - Assign route names for all routes (login, register, password.request, etc.)
        - _Requirements: 1, 16, 23, 25_
    - [x] 7.2. Add authenticated routes for email verification and logout
        - Add POST /logout route with auth middleware
        - Add GET /email/verify route with auth middleware (verification notice)
        - Add GET /email/verify/{id}/{hash} route with auth and signed middleware
        - Add throttle:6,1 to verification verify route
        - Add POST /email/verification-notification route with auth and throttle:3,1
        - Assign route names (logout, verification.notice, verification.verify, verification.send)
        - _Requirements: 6, 20, 21_
    - [x] 7.3. Update protected routes to require email verification
        - Wrap /dashboard and existing visitor management routes in auth and verified middleware
        - Remove temporary auto-login route if it exists
        - _Requirements: 13, 22_

- [x]   8. Create Blade view templates
    - [x] 8.1. Create login view
        - Create `resources/views/auth/login.blade.php`
        - Add form with POST method to route('login.submit')
        - Include @csrf token
        - Add email input field with type="email", autocomplete="email"
        - Add password input field with type="password", autocomplete="current-password"
        - Add "Remember Me" checkbox
        - Add "Login" submit button
        - Display validation errors with @error directives
        - Add "Forgot Password?" link to password.request route
        - Add "Don't have an account? Register" link to register route
        - Apply responsive CSS classes for mobile/desktop
        - Add WCAG-compliant aria-labels and roles
        - Preserve email value with old('email') after failed submission
        - _Requirements: 1, 9, 14, 15_
    - [x] 8.2. Create registration view
        - Create `resources/views/auth/register.blade.php`
        - Add form with POST method to route('register.submit')
        - Include @csrf token
        - Add name input field with validation error display
        - Add email input field with type="email", autocomplete="email"
        - Add password input field with type="password", autocomplete="new-password"
        - Add password confirmation input field with autocomplete="new-password"
        - Add Terms of Service acceptance checkbox with link to terms page
        - Add password strength indicator div (can be enhanced with JavaScript)
        - Add "Register" submit button
        - Display validation errors for all fields
        - Add "Already have an account? Login" link
        - Apply responsive CSS for mobile/desktop
        - Add WCAG-compliant accessibility attributes
        - _Requirements: 16, 18_
    - [x] 8.3. Create email verification notice view
        - Create `resources/views/auth/verify-email.blade.php`
        - Display message "Please verify your email address"
        - Show user's registered email address from Auth::user()->email
        - Add form with POST method to route('verification.send')
        - Include @csrf token
        - Add "Resend Verification Email" button
        - Add "Logout" link to logout route
        - Display success/error flash messages
        - _Requirements: 21, 22_
    - [x] 8.4. Create forgot password view
        - Create `resources/views/auth/forgot-password.blade.php`
        - Add form with POST method to route('password.email')
        - Include @csrf token
        - Add email input field with type="email"
        - Add "Send Password Reset Link" button
        - Display validation errors
        - Add "Back to Login" link
        - Display success message for reset email sent
        - _Requirements: 23, 24_
    - [x] 8.5. Create password reset view
        - Create `resources/views/auth/reset-password.blade.php`
        - Add form with POST method to route('password.update')
        - Include @csrf token
        - Add hidden token input field with value from route parameter
        - Add hidden email input field with value from request
        - Add new password input field with type="password", autocomplete="new-password"
        - Add password confirmation input field
        - Add password strength indicator
        - Add "Reset Password" button
        - Display validation errors
        - Show error message if token is invalid/expired
        - Add "Request New Link" button if token expired
        - _Requirements: 25_
    - [x] 8.6. Update main layout for authentication
        - Update `resources/views/layouts/app.blade.php`
        - Add @auth / @guest conditionals in navigation
        - Display "Welcome, {{ Auth::user()->name }}" for authenticated users
        - Add logout form with POST method and @csrf for authenticated users
        - Display "Login" and "Register" links for guests
        - Add flash message display sections for success and error messages
        - Ensure CSRF token included in logout form
        - _Requirements: 8, 9_

- [x]   9. Checkpoint - Manual testing of authentication flow
    - Ensure all tests pass, ask the user if questions arise.
    - Verify registration form displays correctly on desktop and mobile
    - Test registration with valid data creates user record
    - Test email verification email is sent (check queue/logs)
    - Test login form displays and accepts credentials
    - Test logout destroys session properly
    - Test forgot password flow sends reset email
    - Test password reset form works with valid token

- [x]   10. Write integration tests for complete authentication flows
    - [x] 10.1. Write registration flow tests
        - Create `tests/Feature/Auth/RegistrationTest.php`
        - Test successful registration with valid data creates user
        - Test registration sends verification email
        - Test registration with existing email shows error
        - Test registration with weak password shows validation error
        - Test registration without terms acceptance fails
        - Test registration preserves input values on error (except password)
        - _Requirements: 17, 19, 20_
    - [x] 10.2. Write login flow tests
        - Create `tests/Feature/Auth/LoginTest.php`
        - Test successful login with valid credentials redirects to dashboard
        - Test login with invalid credentials shows generic error
        - Test login with unverified email redirects to verification notice
        - Test rate limiting blocks after 5 failed attempts
        - Test successful login resets rate limit counter
        - Test "Remember Me" creates remember token
        - Test logout destroys session and remember token
        - _Requirements: 2, 3, 4, 6, 12_
    - [x] 10.3. Write email verification flow tests
        - Create `tests/Feature/Auth/EmailVerificationTest.php`
        - Test verification link marks email as verified
        - Test expired verification link shows error
        - Test invalid verification signature shows error
        - Test resend verification email generates new token
        - Test resend rate limiting (3 per hour)
        - Test already verified user redirects to dashboard
        - _Requirements: 20, 21, 22_
    - [x] 10.4. Write password reset flow tests
        - Create `tests/Feature/Auth/PasswordResetTest.php`
        - Test forgot password request sends reset email
        - Test forgot password with non-existent email shows generic success
        - Test password reset rate limiting (3 per hour)
        - Test reset link displays password reset form
        - Test valid token allows password reset
        - Test expired token shows error with "Request New Link" button
        - Test successful reset updates password and deletes remember tokens
        - Test successful reset redirects to login
        - _Requirements: 24, 25_
    - [x] 10.5. Write session security tests
        - Create `tests/Feature/Auth/SessionSecurityTest.php`
        - Test session regenerates ID on login
        - Test session validates user agent on each request
        - Test changed user agent destroys session
        - Test session timeout after 120 minutes of inactivity
        - Test session updates last_activity on each request
        - _Requirements: 4, 5, 7_

- [x]   11. Write unit tests for validation rules and components
    - [x] 11.1. Write PasswordComplexity rule tests
        - Create `tests/Unit/Rules/PasswordComplexityTest.php`
        - Test password with no uppercase fails validation
        - Test password with no lowercase fails validation
        - Test password with no number fails validation
        - Test password with no special character fails validation
        - Test password meeting all requirements passes validation
        - Test custom error message displays correctly
        - _Requirements: 18_
    - [x] 11.2. Write rate limiting unit tests
        - Create `tests/Unit/Auth/RateLimitingTest.php`
        - Test login rate limiter increments on failed attempt
        - Test login rate limiter clears on successful login
        - Test login rate limiter blocks after 5 attempts
        - Test password reset rate limiter blocks after 3 attempts
        - Test email verification resend rate limiter blocks after 3 attempts
        - _Requirements: 3, 24.14_
    - [x] 11.3. Write EmailVerificationService unit tests
        - Create `tests/Unit/Services/EmailVerificationServiceTest.php`
        - Test sendVerificationEmail generates signed URL
        - Test sendVerificationEmail queues notification
        - Test verify method updates email_verified_at timestamp
        - Test verify method logs verification event
        - _Requirements: 20_

- [x]   12. Update configuration files for production readiness
    - [x] 12.1. Update session configuration
        - Review `config/session.php` settings
        - Ensure driver is set to 'database'
        - Set lifetime to 120 minutes
        - Enable encryption (encrypt: true)
        - Set secure cookie flag based on environment
        - Set http_only to true
        - Set same_site to 'lax'
        - _Requirements: 4, 5, 10_
    - [x] 12.2. Update authentication configuration
        - Review `config/auth.php` settings
        - Configure password reset token expiration to 60 minutes
        - Verify default guard is 'web'
        - Verify user provider uses Eloquent with User model
        - _Requirements: 24, 25_
    - [x] 12.3. Update hashing configuration
        - Review `config/hashing.php` settings
        - Ensure bcrypt driver is default
        - Set bcrypt rounds to 12 for cost factor
        - _Requirements: 2, 19, 25_
    - [x] 12.4. Configure mail for email notifications
        - Review `config/mail.php` settings
        - Set default mailer to smtp or configured service
        - Configure from address and name
        - Ensure queue connection is configured for notification jobs
        - _Requirements: 20, 24_
    - [x] 12.5. Update .env.example for deployment
        - Add all required environment variables with descriptions
        - Include SESSION_DRIVER, SESSION_LIFETIME, SESSION_SECURE_COOKIE
        - Include MAIL\_\* variables for email configuration
        - Include QUEUE_CONNECTION for background jobs
        - Include CACHE_STORE for rate limiting
        - _Requirements: 10_

- [x]   13. Add security headers middleware
    - [x] 13.1. Create SecurityHeaders middleware
        - Create `app/Http/Middleware/SecurityHeaders.php`
        - Implement `handle()` method adding security headers to response
        - Add X-Frame-Options: DENY header
        - Add X-Content-Type-Options: nosniff header
        - Add Referrer-Policy: no-referrer header
        - Add X-XSS-Protection: 1; mode=block header
        - Register middleware in global web middleware stack
        - _Requirements: 10_

- [x]   14. Implement audit logging for security events
    - [x] 14.1. Add logging to authentication events
        - Update AuthController login method to log attempts
        - Log email, IP address, timestamp, success/failure
        - Log failure reason (invalid credentials, rate limited, etc.)
        - Update logout method to log logout events
        - Ensure passwords and tokens are never logged
        - _Requirements: 11, 26_
    - [x] 14.2. Add logging to registration and password reset
        - Update RegisterController to log registration events
        - Log email, IP address, timestamp on registration
        - Update EmailVerificationController to log verification events
        - Update PasswordResetController to log reset requests and completions
        - Use Laravel's Log facade with 'info' level for security events
        - _Requirements: 26_

- [x]   15. Final checkpoint - Comprehensive testing and security review
    - Ensure all tests pass, ask the user if questions arise.
    - Run full test suite with `php artisan test`
    - Manually test complete registration → verification → login flow
    - Test password reset flow end-to-end
    - Verify rate limiting works for all protected endpoints
    - Check session timeout behavior
    - Verify email notifications are sent via queue
    - Test on multiple browsers and devices
    - Review security headers in browser developer tools
    - Check audit logs contain expected security events
    - Verify CSRF protection on all forms
    - Test accessibility with screen reader
    - Verify responsive design on mobile devices

## Notes

- All password fields should never be logged or displayed in error messages
- Rate limiting uses cache storage (Redis/Memcached in production, database for development)
- Email notifications are queued for background processing to prevent blocking
- Session validation happens on every authenticated request via ValidateSession middleware
- All forms include CSRF protection via Laravel's VerifyCsrfToken middleware
- Email verification required before accessing protected routes (verified middleware)
- Generic success messages used for security-sensitive operations (password reset, registration with existing email)
- Audit logging captures security events but never sensitive data (passwords, tokens)
- Responsive design implemented using CSS framework compatible with existing app styles
- WCAG AA accessibility standards maintained for all authentication pages

## Task Dependency Graph

```json
{
    "waves": [
        {
            "id": 0,
            "tasks": ["1"]
        },
        {
            "id": 1,
            "tasks": ["2.1", "3.1"]
        },
        {
            "id": 2,
            "tasks": ["2.2", "2.3", "2.4", "2.5", "3.2", "4.1", "4.2"]
        },
        {
            "id": 3,
            "tasks": ["4.3", "6.1", "6.2"]
        },
        {
            "id": 4,
            "tasks": ["5.1", "5.2", "5.3", "5.4"]
        },
        {
            "id": 5,
            "tasks": ["7.1", "7.2", "7.3"]
        },
        {
            "id": 6,
            "tasks": ["8.1", "8.2", "8.3", "8.4", "8.5", "8.6"]
        },
        {
            "id": 7,
            "tasks": [
                "10.1",
                "10.2",
                "10.3",
                "10.4",
                "10.5",
                "11.1",
                "11.2",
                "11.3"
            ]
        },
        {
            "id": 8,
            "tasks": [
                "12.1",
                "12.2",
                "12.3",
                "12.4",
                "12.5",
                "13.1",
                "14.1",
                "14.2"
            ]
        }
    ]
}
```
