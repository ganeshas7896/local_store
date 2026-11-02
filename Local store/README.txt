LocalStore
==========

Requirements
------------
- PHP 8.1 or later with mysqli, mysqlnd, fileinfo, and the MySQL extension enabled.
- MySQL 8.0+ or a compatible MariaDB release.
- HTTPS enabled for all public traffic (required for browser location access).

Local setup
-----------
XAMPP on Windows
----------------
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Copy the whole project folder to C:\xampp\htdocs\localstore. The PHP files, logo.png,
   style.css, app.js, schema.sql, and .htaccess must all be directly inside that folder.
3. Open http://localhost/phpmyadmin. Choose Databases, create a database named localstore
   with the utf8mb4_unicode_ci collation, then click localstore in the left sidebar.
4. With localstore selected, choose Import, select
   C:\xampp\htdocs\localstore\schema.sql, and click Import/Go. Import into a new empty
   database; these CREATE TABLE statements are not a database upgrade script.
5. Do not create config.local.php for default XAMPP. The app defaults to MySQL host
   localhost, database localstore, user root, and an empty password when APP_ENV is not
   production. If you set MySQL's root password, configure LOCALSTORE_DB_PASS in your PHP
   environment (or in a private config.local.php file).
6. Open http://localhost/localstore/. Register as a store owner, create a store using
   "Use my current location", then add products. Register a customer account in another
   browser/private window to test shopping and orders.
7. Sign in separately as a User or Admin. Users get a dashboard with store browsing,
   cart, and recent orders. Admins manage multiple stores, products, and orders from
   the admin dashboard. Store owners can accept or pause Cash on Delivery. Customers
   see current per-product prices and select Cash on Delivery at checkout; admins can
   mark delivered cash orders as collected. Online/UPI payments are not active until
   a payment gateway is integrated.
8. Location access works on localhost. If image uploads fail, check that PHP can write to
   C:\xampp\htdocs\localstore and lower the image size.

Never use XAMPP's root/empty-password database settings on a public host.

Updating an existing database
-----------------------------
Do not re-import schema.sql into a database that already has tables. The schema is for
new, empty databases. To allow an existing store owner to create additional stores, run
the following once in phpMyAdmin after selecting the app database:

    ALTER TABLE stores DROP INDEX owner_id, ADD INDEX idx_stores_owner_id (owner_id);

This preserves existing stores and replaces the single-store unique index with an
ordinary owner lookup index. If the index is already non-unique, no migration is needed.

To add store-controlled Cash on Delivery and payment tracking to an older database, run
these statements once in phpMyAdmin after selecting the app database:

    ALTER TABLE stores ADD COLUMN accept_cod TINYINT(1) NOT NULL DEFAULT 1;
    ALTER TABLE orders ADD COLUMN payment_method ENUM('cash_on_delivery','upi','card') NOT NULL DEFAULT 'cash_on_delivery';
    ALTER TABLE orders ADD COLUMN payment_status ENUM('pending','paid') NOT NULL DEFAULT 'pending';

Do not run a statement again if its column already exists. UPI/card are reserved for a
future payment-gateway integration; customers can currently pay Cash on Delivery only.

InfinityFree setup
------------------
1. Create an InfinityFree hosting account and open its Control Panel.
2. In MySQL Databases, create a database and database user. Copy the exact MySQL hostname,
   full database name, username, and password shown there. Do not assume the hostname is
   "localhost"; free hosts commonly use a separate database server.
3. Open phpMyAdmin from the hosting Control Panel, click the database you created in its
   left sidebar so it is selected, and import schema.sql. This file intentionally does not
   create or select a database because shared hosting assigns database names.
4. In the local project, copy config.local.php.example to config.local.php. Fill in the
   exact four MySQL values from the Control Panel and leave APP_ENV set to production.
   Never send or publish the database password. The application reads this private file;
   the included .htaccess denies web requests for it.
5. Upload the application files directly into the hosting account's web directory
   (usually htdocs). Include the PHP files, app.js, style.css, logo.png, schema.sql,
   .htaccess, and config.local.php. Do not upload README.txt or the example config.
   Ensure your FTP/file manager is configured to show and upload hidden dotfiles so
   .htaccess is not skipped. Keep config.local.php private and do not share it.
6. In the Control Panel, enable HTTPS/SSL for the site and visit the HTTPS URL. Browser
   location access will not work on an unsecured public HTTP site.
7. Test registration, login, owner store creation, product image upload, and customer
   checkout. If image uploads fail, try a smaller image and check the host's PHP upload
   limit and whether PHP is permitted to write to the project directory.

Public hosting
--------------
- Set APP_ENV=production and configure the four LOCALSTORE_DB_* environment variables.
  Alternatively, use the private config.local.php file described above when the host
  does not let you configure environment variables. Production mode has no hardcoded
  database credentials and requires all four values, refuses the MySQL root account
  and an empty database password, and does not use the local XAMPP defaults.
  Production mode also marks session cookies Secure; serve the site over HTTPS.
- Create a restricted MySQL user for this app only; do not use a hosting-wide or root
  database account.
- Shared hosting often does not grant permission to create databases from an SQL import.
  Create the database in the hosting panel, select it in phpMyAdmin before importing, and
  set LOCALSTORE_DB_NAME to the exact database name assigned by your host.
- Point the site's document root at this project only if the host prevents public access
  to config.php. The included .htaccess rules deny that file on Apache. For
  Nginx or another server, configure equivalent deny rules before deployment.
- Enforce HTTPS at the hosting provider or reverse proxy. Keep PHP display_errors disabled
  in production and send PHP errors to a private server log.
- Keep the project root writable by PHP for image uploads. The included .htaccess serves
  validated upload files as static content on Apache; configure equivalent upload execution
  blocking on other servers.
- Back up the database and project root regularly. The schema file is for a new, empty database;
  do not import it over an existing production database.
- Never upload database backups, credentials, logs, or development files into the public
  document root. All application files, including the logo and uploaded images, are kept
  directly in the project root.

Security behavior
-----------------
- Passwords are hashed; registration requires a 12-character password.
- Forms that change data use session-bound CSRF tokens.
- Image uploads are limited to 5 MB and validated as image files. Remote image URLs must
  use HTTPS. Uploaded images are limited to 40 megapixels.
- Carts are limited to 100 different products, and orders exceeding the database's
  supported total are rejected before checkout.
- Product, order, and store ownership is checked server-side; order totals are recalculated
  from the current product records.
- Use a dedicated database account with access only to this application's database.
