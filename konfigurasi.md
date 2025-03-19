HOWTO :
1. php artisan make:model NamaMenu --migration
    https://laravel.com/docs/11.x/migrations#available-column-types
2. buat file : resources/themes/anchor/pages/NamaMenu/index.blade.php

NGROK :
1. ngrok http http://127.0.0.1:8000/ --response-header-add='Content-Security-Policy: upgrade-insecure-requests'

DONE :
1. konfigurasi email menggunakan https://mailtrap.io/ dan setting .env
2. hapus menu API di profil user /home/indra/Project/omseller-wave/resources/themes/anchor/components/app/settings-layout.blade.php
3. gunakan ngrok untuk publish local dev, gunakan : ngrok http http://127.0.0.1:8000 --response-header-add='Content-Security-Policy: upgrade-insecure-requests'
4. koneksi ke paddle
5. invoice paddle

TODO :
1. ubah logo di https://devdojo.com/wave/docs/customizations
2. ubah tampilan setelah berhasil beli : 
This is your customer's successful purchase welcome screen. After a user upgrades their account they will be redirected to this page after a successful transaction.
You can modify this view inside your theme folder at pages/subscription/welcome.
3. Manage your subscription by clicking below. Edit this page from the following file: resources/views/anchor/pages/settings/subscription.blade.php
