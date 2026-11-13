# WagerTrack

A personal win/loss calendar built with PHP, MySQL, HTML, CSS, and JavaScript for a local XAMPP setup.

## Run locally with XAMPP

1. Copy the project folder into XAMPP's `htdocs` directory.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, select **Import**, and import `database.sql`.
4. Visit `http://localhost/WagerTracker/` (use the folder name you copied into `htdocs`).

If you already have WagerTrack installed, import the updated `database.sql` again to create the money-additions table. The `CREATE TABLE IF NOT EXISTS` statements preserve your existing records.

The default database settings in `config.php` match a standard local XAMPP install (`127.0.0.1`, database `wagertrack`, user `root`, no password). Set `WAGERTRACK_DB_HOST`, `WAGERTRACK_DB_NAME`, `WAGERTRACK_DB_USER`, and `WAGERTRACK_DB_PASSWORD` environment variables to use different credentials.

## Using the tracker

- Select a date in the calendar to add or edit its result.
- Enter a positive amount and choose **Win** or **Loss**; the calendar and monthly summary display the appropriate sign.
- Add an optional note, or delete an existing day's record from its edit form.
- Use the arrows to view another month. Each date has at most one record; saving an existing date replaces its result, amount, and note.
- Use **Add money** to record your starting balance or later cash additions. The current balance is all recorded money additions plus all-time wins minus all-time losses; editing or deleting a wager updates it automatically. All amounts use the same currency/units that you enter.

This local-first starter does not include user accounts. Do not expose it to the public internet without adding authentication and deployment-specific security controls.
