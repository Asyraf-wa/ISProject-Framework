# Run artisan inside the sandbox application, from Windows PowerShell.
#
#   .\dev.ps1 migrate
#   .\dev.ps1 isproject:crud Product
#   .\dev.ps1 isproject:crud-all --force

docker compose exec -w /var/www/sandbox app php artisan @args
