# Paw Net

A PHP-backed pet adoption dashboard for cats, dogs, and hamsters. It includes registration and sign-in, an adopt-or-list choice, a seller dashboard for publishing and managing pet listings, a searchable pet catalog, adoption applications, favorites, adopter profile, buyer-seller conversations, support messages, and saved preferences. Listings include the pet's sex and pickup location. Buyers can start a private seller conversation from a pet listing or choose a seller-listed pet from the Messages module; both sides see their contact and can discuss the adoption before the seller marks the pet sold. Sold pets are removed from the adoption catalog.

## Run locally on Windows

1. Install PHP (for example, with XAMPP).
2. Open PowerShell in this project folder.
3. Start PHP's local development server:

   ```powershell
   & "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t .
   ```

   If PHP is available on your PATH, use `php -S 127.0.0.1:8000 -t .` instead.
4. Open <http://127.0.0.1:8000/> to choose **Sign in** or **Create an account**.

Use the same host name (`127.0.0.1`) while signing in and browsing so the PHP session cookie stays available. The `data` directory must be writable by PHP so the app can save accounts and activity.

## Data and privacy

Pet listings are stored in `data/pets.json`. Account credentials, adoption requests, and support messages are private local data and are excluded from Git by `.gitignore`. Do not publish account or adopter data. The app creates its account and activity data files as they are saved.

## Deployment note

GitHub stores the project source but does not execute PHP. GitHub Pages alone cannot run registration, login, or the API endpoints. For a live site, deploy this repository to a PHP-capable host and configure its document root to this project directory.
