php artisan make:seeder Seeder
php artisan make:model Model -mcr

Seed
php artisan db:seed DbSeeder

php artisan migrate
php artisan passport:client --personal
php artisan passport:keys
php artisan db:seed

--
php artisan passport:install



#Campos a agregar
TABLA: tienda_pedido_items
qr - nvarchar(250)

TABLA : tienda_pedidos
linea_id - int

TABLA : kanbans_reemplazo
fecha_ingreso - datetime
fecha_plan - datetime

Crear vista VT_PIEZAS
Reconstruir VT_STOCK_MATERIALES
Crear tabla dados_piezas

TABLA : lectra_estados
es_reposicion - bit
pieza_id - bigint

TABLA piezas
p2_left - float
p2_top - float
p2_width - float
p2_height - float

TABLA scraps
turno - nvarchar(1)
falla_id - bigint

TODO CREADO.