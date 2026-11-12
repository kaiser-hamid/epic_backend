# Feature: User & Permission Management Module

## Objective

Implement a standalone **User Management and Permission Management module** under the **Config** menu of the application.

The module should provide a simple interface for managing application users, defining permission groups, assigning permissions to groups, and controlling individual user access.

Follow the existing application's architecture, coding conventions, UI patterns, and technology stack. Do not introduce unnecessary abstractions or dependencies.

---

## 1. User Management

Create a user management interface under:

**Config → User Management**

### Features

- List all application users.
- Create a new user.
- Edit existing user information.
- Enable or disable a user account.
- Reset a user's password according to current flow.
- Manage the user's permission group assignments.
- Manage individual permission overrides.

### User Fields

Follow the existing user model and authentication structure. Reuse existing fields wherever possible.

At minimum, support:
- Name
- Username (based on the existing authentication implementation)
- Password (for new users)
- Account status (Active / Disabled)
- Assigned permission groups

### Password Management

- Allow administrators to reset a user's password.
- Passwords must be securely hashed using the application's existing authentication mechanism.
- Never expose existing passwords.
- Follow the existing password validation rules.

### Account Status

- Disabled users must not be able to authenticate.
- Enforce account status on the backend, not just in the frontend.

---

### Suggested API Responsibilities

**User Management**
- List users
- Create user
- Get user details
- Update user
- Enable / Disable user
- Reset password

### Backend Requirements

- Implement proper request validation.
- Use database transactions where multiple related records are modified.
- Ensure disabled users cannot authenticate.
- Avoid N+1 database queries.
- Follow existing pagination and filtering conventions.
- Return consistent API response structures.

---

## 6. Frontend Implementation

Build the complete frontend for user management interfaces.

Follow the existing React architecture, component structure, design system, form conventions, and API integration patterns.

### User Management Screen

Include:
- User listing with search and pagination.
- Create user form.
- Edit user form.
- Active / Disabled status indicator.
- Enable / Disable action.
- Password reset action.

---

Do not modify unrelated modules or introduce breaking changes.

Create a migration to start ids from 1000 instead of 1.
