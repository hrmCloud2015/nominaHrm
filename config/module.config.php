<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Nomina\Controller\Index'      => 'Nomina\Controller\IndexController',
            'Nomina\Controller\Grupos'     => 'Nomina\Controller\GruposController',
            'Nomina\Controller\Conceptos'  => 'Nomina\Controller\ConceptosController',
            'Nomina\Controller\Tipcalen'   => 'Nomina\Controller\TipcalenController',            
            'Nomina\Controller\Tipaus'     => 'Nomina\Controller\TipausController',
            'Nomina\Controller\Subgrupos'  => 'Nomina\Controller\SubgruposController',
            'Nomina\Controller\Cencostos'  => 'Nomina\Controller\CencostosController',
            'Nomina\Controller\Prefijo'    => 'Nomina\Controller\PrefijoController',
            'Nomina\Controller\Niveles'    => 'Nomina\Controller\NivelesController',
            'Nomina\Controller\Gnomina'    => 'Nomina\Controller\GnominaController', // Generar nomina
            'Nomina\Controller\Nomina'     => 'Nomina\Controller\NominaController',  // Novedades en Nomina            
            'Nomina\Controller\Cnomina'    => 'Nomina\Controller\CnominaController', // Cerrar nomina
            'Nomina\Controller\Tipnomina'  => 'Nomina\Controller\TipnominaController',
            'Nomina\Controller\Formulas'   => 'Nomina\Controller\FormulasController',// Formulas
            'Nomina\Controller\Variables'  => 'Nomina\Controller\VariablesController',// Variables
            'Nomina\Controller\Auto'       => 'Nomina\Controller\AutoController',
            'Nomina\Controller\Tipauto'    => 'Nomina\Controller\TipautoController',
            'Nomina\Controller\Tipautopr'  => 'Nomina\Controller\TipautoprController',            
            'Nomina\Controller\Empleados'  => 'Nomina\Controller\EmpleadosController',
            'Nomina\Controller\Procesos'   => 'Nomina\Controller\ProcesosController', // Procesos de nomina
            'Nomina\Controller\Tipemp'     => 'Nomina\Controller\TipempController',
            'Nomina\Controller\Presta'     => 'Nomina\Controller\PrestaController', // Prestamos o anticipos
            'Nomina\Controller\Ausentismos'=> 'Nomina\Controller\AusentismosController', // Ausentismo
            'Nomina\Controller\Tipinca'    => 'Nomina\Controller\TipincaController', // Tipo de incapacidad
            'Nomina\Controller\Incapacidad'=> 'Nomina\Controller\IncapacidadController', // Incapacidades
            'Nomina\Controller\Tipmatriz'  => 'Nomina\Controller\TipmatrizController', // Tipos de matrices para registro de novedades
            'Nomina\Controller\Novedades'  => 'Nomina\Controller\NovedadesController', // Novedades
            'Nomina\Controller\Bancos'     => 'Nomina\Controller\BancosController', // Bancos
            'Nomina\Controller\Vacaciones' => 'Nomina\Controller\VacacionesController', // Vacaciones
            'Nomina\Controller\Tippresta'  => 'Nomina\Controller\TipprestaController', // Tipos de prestamos
            'Nomina\Controller\Salarios'   => 'Nomina\Controller\SalariosController', // Salarios
            'Nomina\Controller\Asalarial'  => 'Nomina\Controller\AsalarialController', // Aumento salarial
            'Nomina\Controller\Embargos'   => 'Nomina\Controller\EmbargosController', // Embargos
            'Nomina\Controller\Tipembargo' => 'Nomina\Controller\TipembargoController', // Tipos de embargos
            'Nomina\Controller\Primantigua' => 'Nomina\Controller\PrimantiguaController', // Prima de antiguedad
            'Nomina\Controller\Tipliqu'     => 'Nomina\Controller\TipliquController', // Tipos de liquidaciones
            'Nomina\Controller\Liquidacion'  => 'Nomina\Controller\LiquidacionController', // Liquidaciones            
            'Nomina\Controller\Motretiro'  => 'Nomina\Controller\MotretiroController', // Motivos de retiro             
            'Nomina\Controller\Terceros'  => 'Nomina\Controller\TercerosController', // Terceros
            'Nomina\Controller\Plancuentas'  => 'Nomina\Controller\PlancuentasController', // Plan de cuentas            
            'Nomina\Controller\Proviciones'  => 'Nomina\Controller\ProvicionesController', // Proviciones             
            'Nomina\Controller\Planilla'  => 'Nomina\Controller\PlanillaController', // Planilla unica            
            'Nomina\Controller\Integrar'  => 'Nomina\Controller\IntegrarController', // Integracion de nomina
            'Nomina\Controller\Tarifa'  => 'Nomina\Controller\TarifaController', // Tarifas arl
            'Nomina\Controller\Tipcontra'  => 'Nomina\Controller\TipcontraController', // Tipos de contrato 
            'Nomina\Controller\Reteconceptos'  => 'Nomina\Controller\ReteconceptosController', // Conceptos de retencion en la fuente            
            'Nomina\Controller\Retefuente'  => 'Nomina\Controller\RetefuenteController', // Retencion en la fuente            
            'Nomina\Controller\Gruposemp'  => 'Nomina\Controller\GruposempController', // Grupos de empeados, convenciones
            'Nomina\Controller\Gcesantias'  => 'Nomina\Controller\GcesantiasController', // Generacion de las cesantias              
            'Nomina\Controller\Excepciones'  => 'Nomina\Controller\ExcepcionesController', // Excepciones de conceptos de nomina para empleados
            'Nomina\Controller\Reemplazos'  => 'Nomina\Controller\ReemplazosController', // Reeemplazos
            'Nomina\Controller\Proyectos'  => 'Nomina\Controller\ProyectosController', // Proyectos
            'Nomina\Controller\Aumentosueldo' => 'Nomina\Controller\AumentosueldoController', // Aumento de sueldo            
            'Nomina\Controller\Prestamosprimas' => 'Nomina\Controller\PrestamosprimasController', // Descuentos colectivos en primas
            'Nomina\Controller\Anticipocesantias' => 'Nomina\Controller\AnticipocesantiasController', // Anticipo cesantias
            'Nomina\Controller\Tipcesantias' => 'Nomina\Controller\TipcesantiasController', // Tipos de anticipos de cesantias            
            'Nomina\Controller\Pnomina'    => 'Nomina\Controller\PnominaController', // Pagos de nomina            
            'Nomina\Controller\Gnominaretro' => 'Nomina\Controller\GnominaretroController', // Retro nominas solo para sistemas            
            'Nomina\Controller\Zonas'    => 'Nomina\Controller\ZonasController', // Zonas                        
            'Nomina\Controller\Supervisores'    => 'Nomina\Controller\SupervisoresController', // Supervisores                                    
            'Nomina\Controller\Programaturnos'    => 'Nomina\Controller\ProgramaturnosController', // Programacion de turnos Supervisores                                    
            'Nomina\Controller\Programaturnoshist'    => 'Nomina\Controller\ProgramaturnoshistController', // Programacion de turnos Supervisores                                                
            'Nomina\Controller\Tipanticipo'    => 'Nomina\Controller\TipanticipoController', // Tipos de anticipos 
            'Nomina\Controller\Anticipos'    => 'Nomina\Controller\AnticiposController', // Anticipos de nomina 
            'Nomina\Controller\Turnos'    => 'Nomina\Controller\TurnosController', // Horarios 
            'Nomina\Controller\Turnosgrupos'  => 'Nomina\Controller\TurnosgruposController', // Turnos
            'Nomina\Controller\Sucursales'  => 'Nomina\Controller\SucursalesController', // Sucursales            
            'Nomina\Controller\Gruposconceptos'  => 'Nomina\Controller\GruposconceptosController', // Grupos de conceptos             
            'Nomina\Controller\Tiplabor'  => 'Nomina\Controller\TiplaborController', // Tipos de labores                        
            'Nomina\Controller\Labores'  => 'Nomina\Controller\LaboresController', // Tipos de labores                                    
            'Nomina\Controller\Unidades'  => 'Nomina\Controller\UnidadesController', // Tipos de labores                                                
            'Nomina\Controller\Conceptosv'  => 'Nomina\Controller\ConceptosvController', // Conceptos            
            'Nomina\Controller\Clientes'  => 'Nomina\Controller\ClientesController', // Conceptos                        
            'Nomina\Controller\Bienestar'  => 'Nomina\Controller\BienestarController', // Conceptos                                    
            'Nomina\Controller\Conchorarios'  => 'Nomina\Controller\ConchorariosController', // Horarios                                                 
            'Nomina\Controller\Modalidad'  => 'Nomina\Controller\ModalidadController', // Modalidades            

            'Nomina\Controller\Sindicatos'  => 'Nomina\Controller\SindicatosController', // Sinditicados           

            'Nomina\Controller\Puestos'  => 'Nomina\Controller\PuestosController', // Sinditicados                      
            'Nomina\Controller\Tiponoveprog'  => 'Nomina\Controller\TiponoveprogController', // Tipos de novedades 

        ),
    ),
    'router' => array(
        'routes' => array(
            'nomina' => array(
                'type'    => 'Literal',
                'options' => array(
                    // Change this to something specific to your module
                    'route'    => '/nomina',
                    'defaults' => array(
                        // Change this value to reflect the namespace in which
                        // the controllers for your module are found
                        '__NAMESPACE__' => 'Nomina\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    // This route is a sane default when developing a module;
                    // as you solidify the routes for your module, however,
                    // you may want to remove it and replace it with more
                    // specific routes.
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*'
                             ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ), 
    'view_manager' => array(
        'template_path_stack' => array(
            'Nomina' => __DIR__ . '/../view',
        ),
    ),
);
