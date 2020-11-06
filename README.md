# MIP_museumsinformationportal_backend

MIP Backend is the user interface for MIP.

## Installation

**Prerequisites**
- PHP >=7.2
- Composer


After you have pulled the code to your own computer, run (in backend folder)

1. Run composer install
2. Set up the database
    1. Create the database and database user(s)
    2. Add extensions postgis and hstore
    3. Add the database connection string and credentials to the .env file
    4. Run database/data/20201103_mip_create_tables.sql script in the created database to create the schema and tables for MIP. NOTE: Replace the <user> in the file with with proper db users!
    5. Run the seeder database/seeds/system_kayttaja_seder.php to create the system and admin user ( php artisan db:seed --class=system_kayttaja_seeder )
    6. Use the database/data/20201103_valintalista_data.txt to insert data to the selection lists (optional)

Start the included http server in development environment:
* php artisan serve


For production environment, Apache (or similar) web server is recommended

---

## Environment settings

* The .env.example file contains example values for the application settings. Create .env file for your application with custom values.
* Related PHP settings (in php.ini):
    * Uncomment ;extension=pdo_pgsql (for postgresql)
    * Uncomment ;extension=gd2
    * Adjust post_max_size value (e.g. 52M)
    * Adjust upload_max_filesize value (e.g. 51M)
    * Adjust memory limit (if needed)


## Other notes

* Postgresql database with PostGIS and hstore extensions is recommended
* ImageMagick (https://www.php.net/manual/en/book.imagick.php) is used by default for thumbnail creation. If this is not installed, the GD image driver can be used (configure in app/config/image.php


## Geoserver configuration (http://geoserver.org/).

* MIP utilizes some features of Geoserver for showing e.g. features on MIP map and by publishing data through Geoserver instance.
* MIP related configuration for Geoserver
    * Add the Geoserver url and credentials to the .env file.
    * Create datastores and workspaces for mip application and mip publications
    * There should be at least following layers created to the Geoserver mip datastore:
        * alueet
        * ark_kohteet
        * arvoalueet
        * arvoalue_kiinteistot
        * kiinteistot
        * rakennus_ja_tyypit
    * The queries used can be found the repository geoserver/layer/
    * The styles used with the layers can be found in the repository geoserver/style


---

**Laravel documentation**

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable, creative experience to be truly fulfilling. Laravel attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as authentication, routing, sessions, queueing, and caching.

Laravel is accessible, yet powerful, providing powerful tools needed for large, robust applications. A superb inversion of control container, expressive migration system, and tightly integrated unit testing support give you the tools you need to build any application with which you are tasked.

Documentation for the framework can be found on the [Laravel website](http://laravel.com/docs).
