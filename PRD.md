# Product Requirement Document (PRD): LMS SMK Pesat

## 1. Project Overview & Tech Stack
- **Project Name:** Learning Management System (LMS) SMK Pesat
- **Target Domain:** `lms.smkpesat.id`
- **Identity Provider (Future SSO):** `profile.smkpesat.id`
- **Target Users:** Administrator, Teachers (Guru), Students (Siswa)
- **Tech Stack:**
  - **Backend:** Laravel 11/12 + MySQL
  - **Frontend:** Inertia.js + Vue.js 3 (`<script setup>`) + Tailwind CSS
  - **Auth Starter:** Laravel Breeze (Local Auth MVP)

---

## 2. Initial Auth Strategy (Phase 1 MVP)
- **Phase 1 (Current):** Standalone Local Authentication stored in local MySQL database (`users` table with hashed passwords).
- **Phase 5 (Future Expansion):** OAuth2 / Shared Session Single Sign-On (SSO) integration with `profile.smkpesat.id`.
- **Role-Based Access Control (RBAC):** `admin`, `guru`, `siswa`.

---

## 3. Design System & UI/UX Guidelines
- **Visual Identity:** Clean, modern, highly aligned with `profile.smkpesat.id`.
- **Sidebar Layout:** Left sidebar, clean white background (`#FFFFFF`), light border (`#E2E8F0`), thin uppercase navigation labels, line icons, and orange active indicators (`#FF6B35`).
- **Color Palette:**
  - Background: Light Gray (`#F8FAFC` / `#F1F5F9`)
  - Cards & Panels: White (`#FFFFFF`) with `rounded-xl` and subtle shadows (`shadow-sm`)
  - Primary CTA & Accents: Orange (`#FF6B35` / `#FF5722`)
- **Top Header:** Minimalist top navigation bar with user profile dropdown, active role badge, and notification triggers.

---

## 4. Database Schema Requirements

### 4.1 `users`
- `id` (PK)
- `name` (string)
- `email` (string, unique)
- `password` (string)
- `role` (enum: `'admin'`, `'guru'`, `'siswa'`)
- `avatar` (string, nullable)
- `phone` (string, nullable)
- `bio` (text, nullable)
- `status` (enum: `'active'`, `'inactive'`, default: `'active'`)
- `remember_token`, `timestamps`

### 4.2 `classes`
- `id` (PK)
- `code` (string, unique 6-char alpha-numeric)
- `name` (string)
- `subject` (string)
- `description` (text, nullable)
- `teacher_id` (FK -> `users.id`)
- `academic_year` (string)
- `status` (enum: `'active'`, `'archived'`, default: `'active'`)
- `timestamps`

### 4.3 `class_user` (Pivot)
- `id` (PK)
- `class_id` (FK -> `classes.id`)
- `user_id` (FK -> `users.id`)
- `joined_at` (timestamp)
- `timestamps`

### 4.4 `materials`
- `id` (PK)
- `class_id` (FK -> `classes.id`)
- `title` (string)
- `description` (text, nullable)
- `file_path` (string, nullable)
- `file_type` (string, nullable)
- `created_by` (FK -> `users.id`)
- `timestamps`

### 4.5 `assignments`
- `id` (PK)
- `class_id` (FK -> `classes.id`)
- `title` (string)
- `description` (text)
- `file_path` (string, nullable)
- `max_score` (integer, default: 100)
- `deadline` (datetime)
- `created_by` (FK -> `users.id`)
- `timestamps`

### 4.6 `submissions`
- `id` (PK)
- `assignment_id` (FK -> `assignments.id`)
- `student_id` (FK -> `users.id`)
- `file_path` (string, nullable)
- `submission_text` (text, nullable)
- `submitted_at` (datetime)
- `status` (enum: `'submitted'`, `'late'`, `'graded'`, default: `'submitted'`)
- `score` (integer, nullable)
- `teacher_feedback` (text, nullable)
- `graded_at` (datetime, nullable)
- `timestamps`

### 4.7 `quizzes` & `quiz_questions`
- `quizzes`: `id`, `class_id` (FK), `title`, `duration_minutes`, `status`, `timestamps`
- `quiz_questions`: `id`, `quiz_id` (FK), `question_text`, `type` (`'multiple_choice'`, `'essay'`), `options` (json), `correct_answer` (string), `points` (integer), `timestamps`

### 4.8 `attendances`
- `id` (PK), `class_id` (FK), `student_id` (FK), `date` (date), `status` (`'present'`, `'absent'`, `'sick'`, `'permission'`), `timestamps`

---

## 5. Security & Validation Standards
1. **Authorization Policies:** Strictly protect routes and Inertia actions based on user roles (`admin`, `guru`, `siswa`).
2. **File Upload Security:** Validate MIME types (PDF, DOCX, XLSX, PNG, JPG, ZIP). Max file size: 10MB (assignments) / 50MB (materials). Store securely with UUIDs.
3. **Data Sanitization:** Auto-escape outputs and sanitize rich-text contents.
4. **Rate Limiting:** Throttle login attempts (5 attempts/minute).

---

## 6. Development Phasing Roadmap
- **Phase 1 (MVP):** Database Migrations, Role Middleware, Seeder (Admin, 2 Teachers, 5 Students), Shell Layout (`Sidebar.vue`, `Header.vue`, `AuthenticatedLayout.vue`), Dashboard page.
- **Phase 2:** Class Management (Create class, Join via code, Member directory) & Material / Assignment management.
- **Phase 3:** Assignment Submissions, Grading System & Automatic Quizzes.
- **Phase 4:** Attendance Recording, Academic Analytics & Excel Export.
- **Phase 5:** SSO Integration (`profile.smkpesat.id`) & Virtual Class integration.