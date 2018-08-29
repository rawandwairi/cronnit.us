
# Cronnit

A free service for making scheduled posts to Reddit. It's available at
https://cronnit.us or you can download the code and host it yourself!

## Hosting

If you want to host your own version of Cronnit you can:

    sudo apt install php-cli php-sqlite3 composer
    git clone git@github.com:/krisives/cronnit.us.git
    cd cronnit
    composer update
    cp config.php.example config.php
    nano config.php
    cd public_html/
    php -S localhost:8080

If you don't have MySQL you can use SQLite instead in your `config.php` file:

    'dbdsn' => 'sqlite:foo.db',
    'dbuser' => '',
    'dbpass' => ''

For `client_id` and `client_secret` you will need to
[create a Reddit app](https://www.reddit.com/prefs/apps) using a callback URL
of `http://localhost:8080/`

## Donate

If you find Cronnit useful as a tool or source please consider
[making a donation](https://paypal.me/krisives)!
