# VoltVision - Electricity Consumption & ML

## Setup (XAMPP)
1. Copy this `VoltVision` folder into `C:\\xampp\\htdocs\\`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Import `database/electricity_ml.sql`.
5. In `config/db.php`, set the MySQL host/database/user/password as needed.
6. Open `http://localhost/VoltVision/`.

## Current modules
- Login/Register/Forgot password/Reset password (local demo reset-link workflow)
- Dashboard placeholder
- Planned OCR, appliance estimation, Isolation Forest and Random Forest modules

## Security note
The reset link is displayed locally for demonstration. In production, send it by email and never expose reset tokens in the page.
