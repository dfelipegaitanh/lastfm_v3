---
name: wayfinder-development
description: "Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task"
license: MIT
metadata:
  author: laravel
---

# Wayfinder development

## Documentation

Use `search-docs` for detailed Wayfinder patterns and documentation.

## Quick reference

### Generate routes

If the Vite plugin isn't installed, run this command after route changes:

```bash
php artisan wayfinder:generate --no-interaction
```

To generate form helpers, add the `--with-form` flag:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

### Import patterns

<!-- Controller Action Imports -->
```typescript
// Named imports for tree-shaking (preferred)...
import { show, store, update } from '@/actions/App/Http/Controllers/PostController'

// Named route imports...
import { show as postShow } from '@/routes/post'
```

### Common methods

<!-- Wayfinder Methods -->
```typescript
// Get route object...
show(1) // { url: "/posts/1", method: "get" }

// Get URL string...
show.url(1) // "/posts/1"

// Specific HTTP methods...
show.get(1)
store.post()
update.patch(1)
destroy.delete(1)

// Form attributes for HTML forms...
store.form() // { action: "/posts", method: "post" }

// Query parameters...
show(1, { query: { page: 1 } }) // { url: "/posts/1?page=1", method: "get" }
```

## Wayfinder + Inertia

Use Wayfinder with the `<Form>` component:
<!-- Wayfinder Form (React) -->
```typescript
<Form {...store.form()}><input name="title" /></Form>
```

## Verification

1. If the Vite plugin isn't installed, run `php artisan wayfinder:generate` to regenerate routes
2. Check that TypeScript imports resolve
3. Verify that route URLs match the expected paths

## Common pitfalls

- Using default imports instead of named imports (breaks tree-shaking)
- Forgetting to regenerate after route changes
- Not using type-safe parameter objects for route model binding
