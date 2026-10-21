# Paw Net

A PHP-backed pet adoption dashboard for cats, dogs, and hamsters. It includes separate buyer and seller registration and sign-in, a seller dashboard for publishing and managing pet listings, a searchable pet catalog, adoption applications, favorites, adopter profile, buyer-seller conversations, support messages, and saved preferences. Each account has one fixed role: buyers browse pets and contact sellers; sellers manage listings and reply to buyer inquiries. Listings include the pet's sex and pickup location. Buyers see available listings from other members and can start a private seller conversation from a pet listing or choose another member's seller-listed pet from Messages. A member's own listings are kept out of their buyer catalog; owners cannot message themselves or apply to adopt their own listings. Accounts, pet listings, applications, conversations, and chat messages are stored in MySQL; local files provide legacy import and backup data, and support messages remain local. Sellers see buyer inquiries in their inbox, can reply, and receive unread-message indicators. Sold pets are removed from the adoption catalog.

## Run locally on Windows

1. Install PHP (for example, with XAMPP).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. In phpMyAdmin at <http://127.0.0.1/phpmyadmin>, import [database/schema.sql](./database/schema.sql), then import [database/migrations/001_pawnet_full_schema.sql](./database/migrations/001_pawnet_full_schema.sql) to create the full Paw Net tables.
4. Import [database/migrations/002_pet_adoption_profile_details.sql](./database/migrations/002_pet_adoption_profile_details.sql) once on both fresh and existing databases. It adds age units and care-status fields while keeping existing ages, and safely skips columns that are already present.
5. The default local database connection is `127.0.0.1:3306`, database `pawnet`, username `root`, and an empty password, as in a default XAMPP install. If your MySQL credentials differ, set `PAWNET_DB_HOST`, `PAWNET_DB_PORT`, `PAWNET_DB_NAME`, `PAWNET_DB_USER`, and `PAWNET_DB_PASSWORD` in the environment used to launch PHP.
6. If you already have conversation history in `data/conversations.json`, import it once after creating the schema:

   ```powershell
   & "C:\xampp\php\php.exe" scripts\migrate-json-conversations.php
   ```

7. Open PowerShell in this project folder and start the PHP development server:

   ```powershell
   & "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t .
   ```

   If PHP is on your PATH, use `php -S 127.0.0.1:8000 -t .` instead.
8. Open <http://127.0.0.1:8000/> and choose a **Buyer** or **Seller** account type. Create a separate account for each role, using a different email address for each account. Roles are fixed at registration; a buyer account cannot manage seller listings, and a seller account cannot start buyer adoption inquiries.

Use the same host name (`127.0.0.1`) while signing in and browsing so the PHP session cookie stays available. The `data` directory must be writable by PHP so the app can save accounts and activity. If MySQL is stopped or the schema is missing, the chat API reports that messaging is unavailable rather than silently saving messages to a different store.

## Data and privacy

MySQL stores account credentials, fixed buyer/seller roles, pet listings, adoption requests, and buyer-seller messages. Legacy local files may contain account or pet data for import. Support messages remain in private local storage. These data files are excluded from Git by `.gitignore`. Keep database credentials in environment variables, do not publish account or adopter data, and do not expose phpMyAdmin or the default XAMPP database account on a public server.

## Deployment note

GitHub stores the project source but does not execute PHP. GitHub Pages alone cannot run registration, login, or the API endpoints. For a live site, deploy this repository to a PHP-capable host and configure its document root to this project directory.
