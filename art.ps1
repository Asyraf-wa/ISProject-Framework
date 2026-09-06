# Run artisan inside the playground application, from Windows PowerShell.
#
#   .\art.ps1 migrate
#   .\art.ps1 isproject:crud Product
#   .\art.ps1 isproject:crud-all --force

docker compose exec -w /var/www/playground app php artisan @args
