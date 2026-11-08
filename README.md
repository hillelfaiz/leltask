# Leltask — Coursework Task Management

A personal web application for university students to organize courses, tasks, schedules, notes, and tags in one dashboard.

## Features

- **Courses**: create courses with semester, schedule, and lecturer information.
- **Tasks**: add, edit, delete, and complete coursework tasks; attach files to a task.
- **Tags**: organize tasks with custom tags.
- **Notes**: write notes and format them with an AI-assisted formatting action.
- **Semesters**: set an active semester and filter the dashboard by semester/schedule.
- **Dashboard**: see upcoming tasks, courses, and schedule in one place.
- **Authentication**: register, login, email verification, password reset, and profile management.

## Tech stack

- **Backend**: PHP 8.3, Laravel 13, Inertia.js
- **Frontend**: Vue 3, Tailwind CSS, Vite
- **Database**: MySQL
- **Auth**: Laravel Breeze, Sanctum
- **Tooling**: Composer, npm

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env
php artisan migrate
npm install
npm run dev
php artisan serve
```

Run the test suite:

```bash
php artisan test
```

## Project structure

```text
app/
  Http/Controllers/   # Dashboard, Task, Course, Tag, Note
  Models/             # Task, Course, Tag, Note, User
database/migrations/  # courses, tasks, tags, notes, semesters, schedules
resources/js/
  Pages/              # Inertia pages (Dashboard, Auth, Profile)
  Components/         # Vue components (cards, inputs, modals)
routes/web.php        # Authenticated CRUD routes
```

## Notes

- Personal project, built and maintained individually.
- The AI formatting action sends note text to the configured AI provider; no API keys are committed to the repository.
