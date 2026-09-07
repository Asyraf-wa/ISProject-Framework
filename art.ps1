# Run artisan inside the sandbox application, from Windows PowerShell.
#
#   .\art.ps1 migrate
#   .\art.ps1 isproject:crud Product
#   .\art.ps1 isproject:crud-all --force

docker compose exec -w /var/www/sandbox app php artisan @args
