# Produkcyjny obraz nginx – zawiera katalog public/ (statyki + index.php do try_files)
# oraz konfigurację. Żądania PHP przekazywane są przez fastcgi do serwisu "app:9000".
FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Kod publiczny potrzebny nginx do serwowania statyk i rozwiązywania try_files.
COPY public/ /var/www/html/public/

EXPOSE 80
