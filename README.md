# Complaint Management System

PHP 8 + MySQL. Open `http://localhost/<folder>/public/`.

## Flow
Home has three choices.
1. **Select organization**: pick your organization, then log in or join as a **member**. Members belong to one organization and can only file complaints to it. Joining needs either an email on the organization's domain, or an email the organization added on its **Members** page. Email OTP confirms the address.
2. **Register organization**: details, optional member email domain and a proof document, then email OTP, then `pending`. The platform admin approves or rejects. The person who registered is the **Organization admin**.
3. **Admin login**: platform admins only. Approve or reject organizations.

Organization admin: **Team** page adds Focal persons (`supervisor`) and Handlers (`staff`) with a temporary password; **Members** page adds member emails; **Settings** has categories and the email domain. Members cannot file until a focal person and a handler exist.
Focal person assigns complaints to handlers. Handler updates status and writes to the complainant. The member follows the timeline and rates the result, which closes it. The org admin has a read-only overview.

## Setup
1. Import the database (`complaint_management_system.sql`) in phpMyAdmin. No schema change is needed.
2. Copy `.env.example` to `.env` (or keep your existing `.env`) and fill it in.
3. Platform admin: your database already has two (`platform_admin`). To add another: `php bin/create-admin.php you@example.com "Your Name"`.
4. `storage/uploads` must be writable by the web server. It is outside `public/` and blocked by `.htaccess`; files are only served through `download.php`, which checks permissions.

Uploaded files are stored on the machine running the app, not in the database.
