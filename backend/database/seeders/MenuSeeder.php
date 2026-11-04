<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {

        Menu::create(['ruta' => '/dashboard', 'nombre' => 'Dashboard']);
        Menu::create(['ruta' => '/production/kanban/history', 'nombre' => 'Reporte His Kanban']);
        Menu::create(['ruta' => '/production/kanban_create', 'nombre' => 'Crear Kanban']);
        Menu::create(['ruta' => '/production/kanbans', 'nombre' => 'Listado Kanbans']);
        Menu::create(['ruta' => '/production/kanban_print', 'nombre' => 'Reimprimir Kanban']);

        Menu::create(['ruta' => '/production/buffer', 'nombre' => 'Ingreso buffer']);
        Menu::create(['ruta' => '/production/buffer_corte', 'nombre' => 'Estado buffer']);
        Menu::create(['ruta' => '/production/subassy', 'nombre' => 'Ingreso Pre ensamble']);
        Menu::create(['ruta' => '/production/assy', 'nombre' => 'Ingeso ensamble']);
        Menu::create(['ruta' => '/production/corte', 'nombre' => 'Ingreso corte']);
        Menu::create(['ruta' => '/production/lectras', 'nombre' => 'SIN USO']);
        Menu::create(['ruta' => '/production/panel_operaciones', 'nombre' => 'Panel de operaciones']);
        Menu::create(['ruta' => '/production/panel_operaciones_linea/user', 'nombre' => 'Polivalencias']);
        Menu::create(['ruta' => '/production/panel_operaciones_linea', 'nombre' => 'Operaciones linea']);

        Menu::create(['ruta' => '/master/models', 'nombre' => 'ABM - Modelos']);

        Menu::create(['ruta' => '/import/piezas', 'nombre' => 'Importar - Piezas']);
        Menu::create(['ruta' => '/import/materiales_piezas', 'nombre' => 'Importar - Mats. Piezas']);
        Menu::create(['ruta' => '/import/dados', 'nombre' => 'Importar - Dados']);
        Menu::create(['ruta' => '/import/materiales_piezas_prov', 'nombre' => 'Importar - Mats. Piezas Prov.']);
        Menu::create(['ruta' => '/import/datos_corte', 'nombre' => 'Importar - Dados Corte']);
        Menu::create(['ruta' => '/import/fallas', 'nombre' => 'Importar - Fallas']);
        Menu::create(['ruta' => '/import/tienda', 'nombre' => 'Importar - Tienda']);

        Menu::create(['ruta' => '/stock/piezas', 'nombre' => 'Stock - Piezas']);
        Menu::create(['ruta' => '/stock/egreso/tienda', 'nombre' => 'Tienda - Egreso']);
        Menu::create(['ruta' => '/stock/ingreso/tienda', 'nombre' => 'Tienda - Ingreso']);
        Menu::create(['ruta' => '/stock/ingreso/manual/tienda', 'nombre' => 'Tienda - Ingreso manual']);
        Menu::create(['ruta' => '/stock/tienda', 'nombre' => 'Tienda']);

        Menu::create(['ruta' => '/stock/inventario/materiales', 'nombre' => 'PC - Inv. Telas']);
        Menu::create(['ruta' => '/stock/inventario/cueros', 'nombre' => 'PC - Inv. Cueros']);
        Menu::create(['ruta' => '/stock/inventario/materiales/resultados', 'nombre' => 'PC - Resultados telas']);
        Menu::create(['ruta' => '/stock/inventario/cueros/resultado', 'nombre' => 'PC - Resultados cueros']);
        Menu::create(['ruta' => '/stock/inventario/materiales/editar', 'nombre' => 'PC - Editar pesajes tela']);

        Menu::create(['ruta' => '/tablas', 'nombre' => 'ABM Tablas']);
        Menu::create(['ruta' => '/lineas', 'nombre' => 'ABM Lineas']);

        Menu::create(['ruta' => '/logistica/stock', 'nombre' => 'PC - Stock']);
        Menu::create(['ruta' => '/logistica/reporte', 'nombre' => 'PC - Reporte Stock']);

        Menu::create(['ruta' => '/calidad/cuarentena', 'nombre' => 'QC - Cuarentena']);
        Menu::create(['ruta' => '/calidad/fin_de_linea', 'nombre' => 'QC - EOL']);
        Menu::create(['ruta' => '/calidad/reporte_defectos', 'nombre' => 'QC - Defectos']);
        Menu::create(['ruta' => '/calidad/control_strap', 'nombre' => 'QC - Control Strap']);
        Menu::create(['ruta' => '/calidad/registro_egreso_strap', 'nombre' => 'QC - Registro egreso strap']);
        Menu::create(['ruta' => '/calidad/impresion_et', 'nombre' => 'QC - Impresión etiquetas']);
        Menu::create(['ruta' => '/calidad/reporte_trazabilidad', 'nombre' => 'QC - Reporte traza']);

        Menu::create(['ruta' => '/corte', 'nombre' => 'Lectra - Corte']);
        Menu::create(['ruta' => '/corte/estado', 'nombre' => 'Lectra - Estado']);
        Menu::create(['ruta' => '/corte/plan', 'nombre' => 'Plan de corte']);
        Menu::create(['ruta' => '/corte/gestion', 'nombre' => 'Gestión Dados']);

        Menu::create(['ruta' => '/tienda/etiquetas', 'nombre' => 'Tienda - Etiquetas']);
        Menu::create(['ruta' => '/tienda/pedido', 'nombre' => 'Tienda - Pedido reposición']);
        Menu::create(['ruta' => '/tienda/egreso', 'nombre' => 'Tienda - Egreso']);

        Menu::create(['ruta' => '/pc/planificacion', 'nombre' => 'PC - Planificacion']);
        Menu::create(['ruta' => '/pc/plan_semanal', 'nombre' => 'PC - Plan semanal', 'activo' => 0]);
        Menu::create(['ruta' => '/pc/minimos', 'nombre' => 'PC - Minimos por modelo']);
        Menu::create(['ruta' => '/pc/layout', 'nombre' => 'PC - Layout deposito']);
        Menu::create(['ruta' => '/pc/trasvaso', 'nombre' => 'PC - Trasvaso']);
        Menu::create(['ruta' => '/pc/despacho', 'nombre' => 'PC - Carga despacho']);
        Menu::create(['ruta' => '/pc/almacenar', 'nombre' => 'PC - Almacenar']);
        Menu::create(['ruta' => '/pc/reporte_stock', 'nombre' => 'PC - Reporte Stock']);
        // Menu::create(['ruta' => '/pc/plan_semanal', 'nombre' => 'PC - Reporte Stock']);

        Menu::create(['ruta' => '/config/buffer', 'nombre' => 'Config - Buffer']);
    }
}
