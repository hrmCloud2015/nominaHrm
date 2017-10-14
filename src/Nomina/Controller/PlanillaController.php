<?php

/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 

namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;
use Zend\Db\Adapter\Driver\ConnectionInterface;
use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Principal\Model\NominaFunc;        // Libreria de funciones nomina
use Principal\Model\PlanillaFunc;        // Libreria de funciones planilla unica
use Principal\Model\Gplanilla; // Procesos generacion de planilla
use Principal\Model\EspFunc; // Funciones especiales 
use Principal\Model\IntegrarFunc;      // Integracion de nomina
use Nomina\Model\Entity\Planilla; // (C)
use Principal\Model\Paranomina; // Parametros de nomina

use Principal\Model\ExcelFunc; // Funciones de excel 

class PlanillaController extends AbstractActionController {

    public function indexAction() {
        return new ViewModel();
    }

    private $lin = "/nomina/planilla/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Planillas activas"; // Titulo listado
    private $tfor = "Generación de planilla unica"; // Titulo formulario
    private $ttab = ",id, Fecha, Periodo, Estado, Documento, plano, Int. salud, Int cuentas ,Eliminar, Cerrar "; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************

    public function listAction() {
        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $form = new Formulario("form");
        $valores = array
            (
            "titulo" => $this->tlis,
            "form" => $form,
            "daPer" => $d->getPermisos($this->lin), // Permisos de esta opcion
            "datos" => $d->getGeneral("select a.id,a.fecha,a.ano,a.mes,
                                         b.nombre as nomgrup, a.estado,
                                     ( select count( distinct(c.idEmp) ) from n_planilla_unica_e c where c.idPla = a.id  ) as numEmp, a.integrada                                           
                                        from n_planilla_unica a 
                                        left join n_grupos b on a.idGrupo=b.id                                         
                                        where a.estado in (0,1,2) order by id desc"),
            "datPla"    =>  $d->getGeneral("select a.idPla, a.codSuc, count( distinct(a.idEmp ) ) as num , lower(d.nombre ) as nombre 
                                           from n_planilla_unica_e a 
                                               inner join a_empleados b on b.id = a.idEmp 
                                               inner join n_sucursal_e c on c.idEmp = b.id 
                                               inner join n_sucursal d on d.id = c.idSuc 
                                             where a.regAus = 0 
                                          group by a.idPla, a.codSuc 
                                            order by a.idPla, d.nombre"), // Planillas unicas             
            "ttablas" => $this->ttab,
            'url' => $this->getRequest()->getBaseUrl(),
            "lin" => $this->lin,
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado
        );
        return new ViewModel($valores);
    }

// Fin listar registros 
    // Editar y nuevos datos *********************************************************************************************
    public function listaAction() {
        $form = new Formulario("form");
        //  valores iniciales formulario   (C)
        $id = (int) $this->params()->fromRoute('id', 0);
        $form->get("id")->setAttribute("value", $id);
        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $f = new PlanillaFunc($this->dbAdapter);
        $g = new Gplanilla($this->dbAdapter);
        // Grupo de nomina
        $arreglo = '';
        $datos = $d->getGrupo2();
        foreach ($datos as $dat) {
            $idc = $dat['id'];
            $nom = $dat['nombre'];
            $arreglo[$idc] = $nom;
        }
        //$form->get("idGrupo")->setValueOptions($arreglo);                         
        //       
        $valores = array
            (
            "titulo" => $this->tfor,
            "form" => $form,
            'url' => $this->getRequest()->getBaseUrl(),
            "datos" => $d->getGeneral1('select ano, mes as mes  
                               from n_planilla_unica_h where estado = 0
                                order by ano, mes desc'),
            'id' => $id,
            "lin" => $this->lin
        );
        // ------------------------ Fin valores del formulario 

        if ($this->getRequest()->isPost()) { // Actulizar datos
            $request = $this->getRequest();
            if ($request->isPost()) {
                // Zona de validacion del fomrulario  --------------------
                $album = new ValFormulario();
                // Fin validacion de formulario ---------------------------
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $u = new Planilla($this->dbAdapter); // ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Consultar fechas del calendario
                $d = new AlbumTable($this->dbAdapter);
                $p = new Gplanilla($this->dbAdapter);
                // Buscar periodo activo
                $datos = $d->getGeneral1('select ano, mes as mes  
                               from n_planilla_unica_h 
                               where estado = 0 
                                order by ano, mes desc');
                $ano = $datos['ano'];
                $mes = $datos['mes'];
                if ($mes == 12) {
                    $mes = 1;
                    $ano++;
                } else
                    $mes ++;

                // INICIO DE TRANSACCIONES
                $connection = null;
                try {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();
                    // Generacion cabecera
                    if ($data->id == 0) {
                        $id = $u->actRegistro($data, $ano, $mes);
                        // Generacion empleados
                        $p->getNominaE($id, 0);  // Generacion de empleados   
                        // Recorrer informacion de ingresos y retiros

                    //$d->modGeneral("delete from n_planilla_unica_e where idPla = ".$id." and idEmp in (4254, 2082)");  

                    $datos = $d->getGeneral("select a.*, c.tipo  
                                           from n_planilla_unica_e a
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_tipemp c on c.id = b.idTemp  
                                           where a.idPla = " . $id);
                    $idPla = $id;
                    foreach ($datos as $dat) 
                    {
                        $id = $dat['id'];
                        $idEmp = $dat['idEmp'];
                        // DIAS -----------------------------------------------------------------
                        // REGISTRO DE INGRESO O SALIDAS
                        $diasContra = 0;
                        $datC = $f->getContratos($idPla, $idEmp);
                        $ingreso = 0;
                        $salida = 0;
                        $swContra = 0; // Para validar si primero fue el fin de contrato o inicio del contrato 
                        $itPla = 0;
                        $swPre = 0; // Swit para creacion de otros registros por doble linea
                        $diasEmp = 0;
                        $swRet = 0;
                        foreach ($datC as $datCon) 
                        {
                           $diasEmp = $diasEmp + $datCon['dias'];// se suman los dias de contratos por si antes de la liquidacion hay alguno
                           $idNom = $datCon['idNom'];
                           if ( ($datCon['contra'] == 2) or ($datCon['idTnom']==6) )  
                           { // Retiro
                                $valor = 1;
                                $campo = 'nRetiro';
                                $g->getPlanillaE($id, $campo, $valor);
                                // Guardar dias                                   
                                //$valor = $datCon['dias'];
                                $valor = $diasEmp;
                                $campo = 'diasRetiro';
                                $g->getPlanillaE($id, $campo, $valor);                                
                                $valor = $datCon['idNom']; // Captura del id de liquidacion 
                                $campo = 'idNomRet';
                                if ($valor != NULL )
                                {  
                                    $g->getPlanillaE($id, $campo, $valor);                     
                                    $valor = $datCon['fechaFcontrato'];
                                    $d->modGeneral("update n_planilla_unica_e 
                                      set fechaR = '".$valor."'  
                                        where id =".$id);                       
                                }                            
                                // Validar si el primer registro es por primero retiro
                                if ($itPla == 0)
                                {    
                                   $swContra = 1;
                                   //ordenIng
                                   $valor = 1;
                                   $campo = 'priRetiro';
                                   $g->getPlanillaE($id, $campo, $valor);                    
                                   $itPla == 1;
                                }
                                
                            }
                            if ($datCon['contra'] == 3) 
                            {  // Inicio de contrato
                               if ($swContra == 0)  
                               {   
                                  $valor = 1;
                                  $campo = 'nIngreso';
                                  $g->getPlanillaE($id, $campo, $valor);
                                  $ingreso = 1;
                                  // Guardar dias                                   
                                  $valor = $datCon['dias'];
                                  $campo = 'diasIngreso';
                                  $g->getPlanillaE($id, $campo, $valor);
                                  $itPla++; // Cuano cuent       

                                  $campo = 'fechaI';
                                  $valor = $datCon['fechaIcontrato'];
                                  $d->modGeneral("update n_planilla_unica_e 
                                  set fechaI = '".$valor."'  
                                     where id =".$id);                           

                               }else{
                                 $swPre = 1; // Lo marca en 1 porque encontro un ingreso despues de un retiro                                  
                               }
                               $swRet = 1;
                             }else{// Fin validacion se de contratos  
                                if ( ($swPre == 0) and ($swRet == 1) )  
                                {    
                                  $valor = $diasEmp;
                                  $campo = 'diasIngreso';
                                  $g->getPlanillaE($id, $campo, $valor);
                                }                                  
                             }   
                             if ( $swPre == 1 )
                             {
                                 $valor = $datCon['dias'];
                                 $d->modGeneral("insert into n_planilla_unica_e 
                  ( idPla, idEmp, sueldo, pensionado, diasRetVaca, valorRetVaca, aprendiz, codSuc, priRetiro, idNomRet, diasIngreso, nIngreso ) 
                  select idPla, idEmp , sueldo, pensionado, diasRetVaca, valorRetVaca, aprendiz,codSuc, 2, ".$idNom.", ".$valor.", 1      
                         from n_planilla_unica_e a 
                            where a.priRetiro > 0 and a.diasRetiro < 30 and a.idPla =".$idPla." and idEmp=".$idEmp." limit 1" );                               
                             }   
                        }// FIN RECORRIDO DE CONTRATOS 
                      }// Fin recorrido empleados 
                      
                    }
                    // Armar nuevo contrato para los que finalizan y los que inician en el mismo mes
                    $datos = $d->getGeneral("select a.*, c.tipo  
                                           from n_planilla_unica_e a
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_tipemp c on c.id = b.idTemp  
                                        where a.priRetiro = 2  and a.idPla = " . $idPla);
                    foreach ($datos as $dat) 
                    {
                        $id = $dat['id'];
                        $idEmp = $dat['idEmp'];
                        // DIAS -----------------------------------------------------------------
                        // REGISTRO DE INGRESO O SALIDAS
                        $datC = $f->getContratosRenova($idPla, $idEmp);
                        $itPla = 1;
                        foreach ($datC as $datCon) 
                        {
                            if ($datCon['contra'] == 3) 
                            { // Inicio de contrato
                                $valor = 1;
                                $campo = 'nIngreso';
                              //  $g->getPlanillaE($id, $campo, $valor);
                                $ingreso = 1;
                                // Guardar dias                                   
                                $valor = $datCon['dias'];
                                $campo = 'diasIngreso';
                              //  $g->getPlanillaE($id, $campo, $valor);                                
                            }
                        }// FIN RECORRIDO DE CONTRATOS 
                      }// Fin recorrido empleados 
                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl() . $this->lin . 'g/' . $idPla);
                }// Fin try casth   
                catch (\Exception $e) {
                    if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                        $connection->rollback();
                        echo $e;
                    }
                    /* Other error handling */
                }// FIN TRANSACCION                                   
            }
        }
        if ($id > 0) { // Cuando ya hay un registro asociado
            $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
            $u = new Planilla($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("idGrupo")->setAttribute("value", $datos['idGrupo']);
        }
        return new ViewModel($valores);
    }

// Fin actualizar datos 
    // Eliminar dato ********************************************************************************************
    public function listdAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        if ($id > 0) {
            $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);
            // Consultar nomina
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                // REGISTRO LIBRO DE CESANTIAS
                //$c->delRegistro($id); 
                // Borrar tablas inferiores               
                $dat = $d->getGeneral1("select id + 1 as id 
                                         from n_planilla_unica_e order by id desc limit 1");
                $datos = $d->modGeneral("delete from n_planilla_unica_e where idPla=" . $id);
                $datos = $d->modGeneral("alter table n_planilla_unica_e auto_increment=" . $dat['id']);

                $datos = $d->modGeneral("delete from n_planilla_unica where id=" . $id);
                $datos = $d->modGeneral("alter table n_planilla_unica auto_increment=" . $id);
                $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) {
                if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                    $connection->rollback();
                    echo $e;
                }
                /* Other error handling */
            }// FIN TRANSACCION                    
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl() . $this->lin);
        }
    }

    //----------------------------------------------------------------------------------------------------------
    // GENERACION PLANILLA UNICA -------------------------------------------------------------------------------
    //----------------------------------------------------------------------------------------------------------
    public function listgAction() {
        $form = new Formulario("form");
        $id = (int) $this->params()->fromRoute('id', 0);
        $form->get("id")->setAttribute("value", $id);

        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);

        $valores = array
            (
            "form" => $form,
            'url' => $this->getRequest()->getBaseUrl(),
            "titulo" => $this->tlis,
            "datos" => $d->getGeneral("select b.id, a.CedEmp, a.nombre,a.apellido, a.idVac ,
                       c.nombre as nomCar, d.nombre as nomCcos, b.incluido, e.fechaI, e.fechaF                        
                       from a_empleados a 
                       inner join n_nomina_e b on a.id=b.idEmp 
                       left join t_cargos c on c.id=a.idCar
                       inner join n_cencostos d on d.id=a.idCcos
                       left join n_vacaciones e on e.id=b.idVac and e.estado=1 
                       where b.idNom=" . $id),
            "lin" => $this->lin
        );
        return new ViewModel($valores);
    }

    // GENERACION PLANILLA UNICA GENERAL-------------------------------------
    public function listpAction() {
        if ($this->getRequest()->isPost()) { // Actulizar datos
            $request = $this->getRequest();
            $data = $this->request->getPost();
            $id = $data->id; // ID de la nomina                  
            $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);
            $f = new PlanillaFunc($this->dbAdapter);
            $g = new Gplanilla($this->dbAdapter);
            $e = new EspFunc($this->dbAdapter);
            $pn = new Paranomina($this->dbAdapter);
            $dp = $pn->getGeneral1(1);
            $salarioMinimo=$dp['formula'];         

            // COnfiguraciones generales
            $datCf = $d->getConfiguraG(" where id = 1");
            $pagoEmpCre = $datCf['pagoEmp']; // Porcentaje pagado por la
            $swRedondeo = $datCf['redondeoPlanilla']; // 

            $dat = $d->getGeneral1("select * from c_general_pla2");

            $saludAusen = $dat['saludAusentismo'];
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $sw = 1;
                //$redondeo = -3;
                $redondeo = 0;

                if ($sw == 1) {
                    $datos = $d->getGeneral("select a.*, c.tipo, b.integral   
                                           from n_planilla_unica_e a
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_tipemp c on c.id = b.idTemp  
                                           where a.idPla = " . $data->id);
                    $idPla = $data->id;
                    foreach ($datos as $dat) 
                    {
                        $id    = $dat['id'];
                        $idEmp = $dat['idEmp'];
                        $pensionado = $dat['pensionado'];
                        $retornoVaca = $dat['valorRetVaca'];
                        $idVaca = $dat['idVac'];
                        $sueldoEmp = $dat['sueldo'];
                        $paternidad = 0;
                        $maternidad = 0;
                        $tipo = $dat['tipo']; // Standar 0 , Aprendiz productivo 1 , aprendiz electiva 2
                        $ingreso = $dat['nIngreso'];
                        $salida  = $dat['nRetiro'];

                        $priRetiro = $dat['priRetiro']; // Si primero se retira y luego retorna 
                        $diasRetiro = $dat['diasRetiro']+$dat['diasIngreso'];
                        $integral = $dat['integral'];

                        $campo = 'integral';
                        $g->getPlanillaE($id, $campo, $integral);                        
// **+**** ------------- DIAS Y RESGITROS A
                        // 1 DIAS POR AUSENTISMOS NO REMUNERADOS ---- 
                        $datProv = $f->getAus($idPla, $idEmp);
                        $valor = 0;
                        $diasAus = $datProv['diasAus'];
                        if ($diasAus > 0) 
                        {
                            $valor = 1;
                            $campo = 'nAus';
                            $g->getPlanillaE($id, $campo, $valor);

                            $campo = 'diasAus';
                            $valor = $diasAus;
                            if ($valor>30)
                                $valor = 30;
                            $g->getPlanillaE($id, $campo, $valor);
                            $valor = $datProv['fechaI'];
                                $d->modGeneral("update n_planilla_unica_e 
                                  set fechaIsln = '".$valor."'  
                                     where id =".$id);                            
                                $valor = $datProv['fechaF'];
                                $d->modGeneral("update n_planilla_unica_e 
                                   set fechaFsln = '".$valor."'  
                                     where id =".$id);                           

                        }
                        // FIN 1 DIAS POR AUSENTISMOS NO REMUNERADOS ---- 

                        // 2 REGISTRO DE INCAPACIDAD ----------
                        $datProv = $f->getInca($idPla, $idEmp);
                        $valor = 0;
                        $diasInca = 0;
                        $diasInca = $datProv['diasInc'];
                        $tipoInca = $datProv['tipo']; // 1 paternidad, 2 maternidad 
                        if ($diasInca > 0) {
                            // General
                            if ($tipoInca == 0) {
                                $valor = 1;
                                $campo = 'nInca';
                                $g->getPlanillaE($id, $campo, $valor);
                                $valor = $datProv['fechaI'];
                                $d->modGeneral("update n_planilla_unica_e 
                                  set fechaIige = '".$valor."'  
                                     where id =".$id);                            
                                $valor = $datProv['fechaF'];
                                $d->modGeneral("update n_planilla_unica_e 
                                   set fechaFige = '".$valor."'  
                                     where id =".$id);                                
                            }
                            // Accidente de trabajo
                            if ($tipoInca == 3) {
                                $valor = 1;
                                $campo = 'at';
                                $g->getPlanillaE($id, $campo, $valor);
                                $valor = $datProv['fechaI'];
                                $d->modGeneral("update n_planilla_unica_e 
                                     set fechaIige = '".$valor."'  
                                       where id =".$id);                             
                                $valor = $datProv['fechaF'];
                                $d->modGeneral("update n_planilla_unica_e 
                                  set fechaFige = '".$valor."'  
                                     where id =".$id);                               
                            }
                            $campo = 'diasInc';
                            $valor = $diasInca;
                            $g->getPlanillaE($id, $campo, $valor);
                            // Paternidad 
                            if ($tipoInca == 1) {
                                $campo = 'Mat';
                                $valor = 1;
                                $g->getPlanillaE($id, $campo, $valor);
                                $paternidad = 1;
                                $valor = $datProv['fechaI'];
                                $d->modGeneral("update n_planilla_unica_e 
                                     set fechaIlma = '".$valor."'  
                                       where id =".$id);                             
                                $valor = $datProv['fechaF'];
                                $d->modGeneral("update n_planilla_unica_e 
                                  set fechaFlma = '".$valor."'  
                                     where id =".$id);                                
                            }
                            // Maternidad
                            if ($tipoInca == 2) {
                                $campo = 'Pat';
                                $valor = 1;
                                $g->getPlanillaE($id, $campo, $valor);
                                $maternidad = 1;
                            }

                            
                        }
                        // FIN 2 REGISTRO DE INCAPACIDAD ----------

                        // 3 REGISTRO DE INCAPACIDAD PRORROGA---------
                        $datProv = $f->getIncaPro($idPla, $idEmp);
                        $valor = 0;
                        $diasIncaPro = 0;
                        $diasIncaPro = $datProv['diasInc'];
                        $tipoInca = $datProv['tipo']; // 1 paternidad, 2 maternidad 
                        if ($diasIncaPro > 0) {
                            $diasInca = $diasInca + $diasIncaPro;
                            // General
                            if ($tipoInca == 0) {
                                $valor = 1;
                                $campo = 'nInca';
                                $g->getPlanillaE($id, $campo, $valor);
                            }
                            // Accidente de trabajo
                            if ($tipoInca == 3) {
                                $valor = 1;
                                $campo = 'at';
                                $g->getPlanillaE($id, $campo, $valor);
                            }
                            $campo = 'diasInc';
                            $valor = $diasInca;
//                      echo $diasInca.' '.$diasIncaPro.'<br />';
                            $g->getPlanillaE($id, $campo, $valor);
                            // Paternidad 
                            if ($tipoInca == 1) {
                                $campo = 'Mat';
                                $valor = 1;
                                $g->getPlanillaE($id, $campo, $valor);
                                $paternidad = 1;
                            }
                            // Maternidad
                            if ($tipoInca == 2) {
                                $campo = 'Pat';
                                $valor = 1;
                                $g->getPlanillaE($id, $campo, $valor);
                                $maternidad = 1;
                            }
                        }
                        // FIN 3 REGISTRO DE INCAPACIDAD PRORROGA ----

                        // 4 REGISTRO DE VACACIONES Y DIAS -------------------
                        $datProv = $f->getVaca($idPla, $idEmp);
                        $diasVac = 0;
                        $idVac = 0;
                        if ($datProv['diasVac'] > 0) {
                            //echo 'EMPLEADO '.$idEmp;
                            $diasVacInc = 0;
                            if ($datProv['diasInc'] > 0) // Dias de icapacidades en las vacaciones 
                                $diasVacInc = $datProv['diasInc'];

                            $diasVac = $datProv['diasVac'];

                            $valor = 1;
                            $campo = 'nVaca';
                            $g->getPlanillaE($id, $campo, $valor);

                            $campo = 'diasVaca';
                            $valor = $diasVac;

                            $g->getPlanillaE($id, $campo, $valor);

                            $campo = 'idVac';
                            $valor = $datProv['idVac'];
                            $g->getPlanillaE($id, $campo, $valor);

                            $campo = 'valorUniVaca';
                            $valor = $datProv['valorVac'];
                            $g->getPlanillaE($id, $campo, $valor);

                            $valor = $datProv['fechaI'];
                            $d->modGeneral("update n_planilla_unica_e 
                                  set fechaIvac = '".$valor."'  
                                     where id =".$id);                            
                            $valor = $datProv['fechaF'];
                            $d->modGeneral("update n_planilla_unica_e 
                                  set fechaFvac = '".$valor."'  
                                     where id =".$id);                            
                        }
                        // FIN 4 REGISTRO DE VACACIONES Y DIAS -------------------

                        // DIAS LABORADOS o DE CONTRATO 
                        $diasContra = $diasRetiro;
//                        $ingreso = 0;
  //                      $salida = 0;                      

                        // 5 DIAS SALUD -----------------
                        $datF = $f->getDiasEmp($idPla, $idEmp);
                        $campo = 'diasSalud';
                        // Si hay dias por contratos los toma 
                        if ($diasContra > 0)
                            $diasLab = $diasContra - $diasAus;
                        else
                            $diasLab = $datF['valor'] - $diasAus;

                        if ($tipo == 2)// Tipo por convenio los dias por riesgos son completos
                            $g->getPlanillaE($id, $campo, 0);
                        else
                            $g->getPlanillaE($id, $campo, $diasLab);
                        // 5 FIN DIAS SALUD -----------------

                        // 6 DIAS PENSION -------------                        
                        $datF = $f->getDiasEmp($idPla, $idEmp);
                        $campo = 'diasPension';
                        if ($diasContra > 0)
                            $diasPension = $diasContra - $diasAus;
                        else
                            $diasPension = $datF['valor'] - $diasAus;
                        // Valdiar tipo de empleado      
                        if ($tipo != 0)
                            $diasPension = 0;

                        if ($pensionado == 1) // Si es pensionado no paga dias de pension                    
                            $diasPension = 0;

                        $g->getPlanillaE($id, $campo, $diasPension);
                        // FIN 6 DIAS PENSION -------------                        

                        // 7 DIAS RIESGOS ---------------------
                        $datF = $f->getDiasEmp($idPla, $idEmp);
                        $campo = 'diasRiesgos';
                        $diasRiesgos = 0;
                        // Total dias --------------------
                        $diasVacC = 0; // Para guardar retornos por retornos de vacaciones 
                        $valor = $diasLab - ( $diasInca + $diasVac - $diasVacC );
                        if ( $valor < 0)
                             $valor = 0;
                        // $valor = 0;
                        $g->getPlanillaE($id, $campo, $valor);
                        $diasRiesgos = $valor;
                        $diasCaja = 0;
                        if ($diasVac>0) // Validar si hay vacaciones 
                            $diasCaja = $diasLab;                        

                        // 7 DIAS RIESGOS ---------------------
                            
// **+**** ------------- DIAS Y RESGITROS A

// **+**** ------------- IBC Y CALCULOS B                         
                        // 1. INGRESOS QUE NO AFECTAN SEGURIDAD SOCIAL 
                        $datF = $f->getNoLey($idPla, $idEmp);
                        $campo = 'ingresosNo';
                        $valor = $datF['valor'];
                        $valorNoLey = 0;
                        if ($valor > 0) {
                            $g->getPlanillaE($id, $campo, $valor);
                            $datF = $f->getLey($idPla, $idEmp, $id);
                            $vlrIbc = $datF['valor'];
                            $baseNo40 = ( $valor + $vlrIbc ) * (40 / 100);
                            $val = $valor - $baseNo40;
                            if ($val > 0) {
                                $valorNoLey = $val;
                                $campo = 'aplica40';
                                $valor = $val;
                                $g->getPlanillaE($id, $campo, $valor);
                            }
                        }
                        // FIN 1. INGRESOS QUE NO AFECTAN SEGURIDAD SOCIAL ----- 
                        // 2. IBC SALUD -------------------------------------
                        $datF = $f->getLey($idPla, $idEmp, $id);
                        $campo = 'ibcSalud';
                        $valor = round($datF['valor'],0) + $valorNoLey;
                  //if ($idEmp==433)   
                   //   echo ' idPla : '.$idPla.' idEMp : '.$idEmp.' id : '.$id.'pension : '.$valor.' ley : '.$valorNoLey.' <br />';

                        if ($tipo == 2)// Est convenio los dias por riesgos son completos
                            $valor = 0;
                        if ($swRedondeo==1)
                            $valor = $e->getRedondear($valor, 1); //-----  Redondeo                                          
                        $g->getPlanillaE($id, $campo, $valor);
                        
                        if ($tipo != 2)// diferente de convenio 
                            $f->getTopeIbc($id, $campo, $valor, $diasLab); // -- Validacion topes IBC Max y Min                    

                        // Validacion de la cree para no pagar Salu, pnsion y parafiscales 
                        $pagoEmp=0;
                        if ( ($pagoEmpCre==1) and ( $tipo == 0 ) )// Validar que no sea aprendiz
                        { 
                            if ( $valor < ($salarioMinimo*10) )
                            {  
                                $pagoEmp=1;// Activa la variable para aplicar la exnoreacin
                                $campo = 'pagoEmp';                   
                                $g->getPlanillaE($id, $campo, 1 );                     
                            }
                        }                               
                        // 2.1 FONDO DE SALUD
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFs) {
                            $valor = $datFs['idFsal'];
                        }
                        $campo = 'idFonS';
                        $g->getPlanillaE($id, $campo, $valor);
                        // 2.2 APORTE POR SALUD
                        $datProv = $d->getProviciones(' and id=5 ');
                        if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                            $valor =  '(4/100) * ibcSalud';
                        else 
                            $valor =  $datProv['por'].' * ibcSalud';                      
                        $campo = 'aporSalud';
                        if ($tipo == 2)// Tipo por convenio los dias por riesgos son completos
                            $valor = 0;
                        $g->getPlanillaE($id, $campo, $valor);

                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        if ($swRedondeo==1)
                            $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                
                        if (($datA['topMax'] == 0) and ( $datA['topMin'] == 0))  // Si no ha superado el tope se redondea si no se envia tal cual 
                            $g->getPlanillaE($id, $campo, $valor); // Se edita el campo 

                        $campo = 'porSalud'; 
                        if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                            $valor =  4/100;
                        else                                      // 
                            $valor =  $datProv['por'];                        
                        $g->getPlanillaE($id, $campo, $valor);
                        // FIN 2. IBC SALUD -------------------------------------

                        // 3. IBC PENSION ----------------------------------------
                        if ($tipo == 0) { // Solo empleados 
                            $datF = $f->getLey($idPla, $idEmp, $id);
                            $campo = 'ibcPension';
                            $valor = $datF['valor'] + $valorNoLey;
                            if ($swRedondeo==1)                      
                                $valor = $e->getRedondear($valor, 1); //-----  Redondeo                    
                            if ($pensionado == 1) // Si es pensionado no paga dias de pension                                       
                                $valor = 0;
                            $g->getPlanillaE($id, $campo, $valor);
                            if ($valor > 0)
                                $f->getTopeIbc($id, $campo, $valor, $diasPension); // -- Validacion topes IBC Max y Min                    
                        }
                        // 3.1 FONDO DE PENSION
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFp) {
                            $valor = $datFp['idFpen'];
                        }

                        $campo = 'idFonP';
                        if ($pensionado == 0) // Si es pensionado no paga dias de pension                    
                            $g->getPlanillaE($id, $campo, $valor);

                        // 3.2 APORTE POR PENSION
                        if ($pensionado == 0) { // Si es pensionado no paga dias de pension                    
                            $datProv = $d->getProviciones(' and id=6 ');
                            $valor = $datProv['por'] . ' * ibcPension';
                            $campo = 'aporPension';
                            $g->getPlanillaE($id, $campo, $valor);
                            // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                            $datA = $f->getPlanillaE($id, $campo); // Consultar valor de un campo en planilla unica
                            if (($datA['topMax'] == 0) and ( $datA['topMin'] == 0))  // No supera el tope se envia tal cual  
                            {
                                if ($swRedondeo==1)                                
                                    $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                            
                            }
                            $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                            $campo = 'porPension';
                            if ($diasPension == 0)                                   // 
                                $valor = 0;
                            else
                                $valor = $datProv['por'];

                            $g->getPlanillaE($id, $campo, $valor);
                        }
                        // 3.3 Fondos de solidaridad                   
                        $datF = $f->getSolidaridad($idPla, $idEmp);
                        $valor = ( $datF['valor'] ); // La solidaridad se divide entre 2 
                        $campo = 'aporSolidaridad';
                        if ($valor > 0) {
                            if ($diasVac > 0) { // Validacion especial vacaciones 
                                $datF = $f->getValSolidaridad($idPla, $idEmp);
                                $valor = $datF['valor'];
                                //echo $valor.'<br />';
                            }

                            $g->getPlanillaE($id, $campo, $valor);
                            // SOLIDARIDAD ENTRE 2
                            $valor = $valor / 2;
                            $campo = 'aporSol1';
                            $valor = $e->getRedondear($valor, 4); //-----
                            $g->getPlanillaE($id, $campo, $valor);

                            $campo = 'aporSol2';
                            $g->getPlanillaE($id, $campo, $valor);
                        }
                        // FIN 3 IBC PENSION ----------------------------------------

                        // 4 IBC RIESGOS -------------------------------------------
                        // Retornos de vacaciones
                        if (($dat["nVaca"] != '0') and ( $dat["diasRetVaca"] > 0)) {
                            $diasRiesgos = $dat["diasRiesgos"]; // Los dias de riesgos son los mismos trabajados
                        }
                        if ($tipo == 2)// Tipo por convenio los dias por riesgos son completos
                            $diasRiesgos = 30;

                        if ($tipo == 3) { // Aprendiz electivo     
                            $diasRiesgos = 0;
                            $valor = 0;
                        }
                        $datF = $f->getLeyR($idPla, $idEmp, $id);
                        $campo = 'ibcRiesgos' ;
                        if ($integral==1)
                           $valorIbc = $datF['valorInt'];   
                        else
                           $valorIbc = $datF['valorInt'];   
                        

                        $valor = $valorIbc + $valorNoLey;
                        if (( $diasRiesgos == 0 ) and ( $tipo == 0))// No paga caja
                            $valor = 0;

                        if ($tipo == 3) { // Aprendiz electivo     
                            $diasRiesgos = 0;
                            $valor = 0;
                        }


                        if ($valor > 0) {
                          if ($swRedondeo==1)
                              $valor = $e->getRedondear($valor, 1); //-----  Redondeo                                          
                            $g->getPlanillaE($id, $campo, $valor);
                            $f->getTopeIbc($id, $campo, $valor, $diasRiesgos); // -- Validacion topes IBC Max y Min                                       
                        }

                        // 4.1 TARIFA ARL------------------------------------- 
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        $porArl = 0;
                        $valor = 0;
                        foreach ($datF as $datTr) {
                            $valor = $datTr['porc'];
                            $porArl = $datTr['porc'] / 100;
                        }
                        if ($diasRiesgos > 0) {
                            $campo = 'tarifaArl';
                            $g->getPlanillaE($id, $campo, $valor);
                        }
                        // 4.2 FONDOS RIESGOS ARL
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFr) {
                            $valor = $datFr['idFarp'];
                        }
                        $campo = 'idFonR';
                        $g->getPlanillaE($id, $campo, $valor);

                        // 4.3 APORTES RIESGOS ARL
                        $valor = $porArl . ' * ibcRiesgos';
                        $campo = 'aporRiesgos';
                        $g->getPlanillaE($id, $campo, $valor);
                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        if (($datA['topMax'] == 0))  // No supera el tope se envia tal cual   
                        {
                            if ($swRedondeo==1)                       
                                $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                     
                        }    
                        $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 
                        // CALCULOS ------------------------------------

                        if (($tipo == 1) or ( $tipo == 3)) {  // SI ES APRENDIZ PRODUCTO LOS DIAS CAJA SON 0 
                            // 1. DIAS RIESGOS PARA CAJA
                            $datF = $f->getDiasEmp($idPla, $idEmp);
                            $campo = 'diasCaja';
                            $g->getPlanillaE($id, $campo, 0);
                        } else {
                            // Ingresos y retiros 
                            if (( $ingreso == 1 ) or ( $salida == 1 )) {
                                $campo = 'diasCaja';
                                $g->getPlanillaE($id, $campo, $diasRiesgos);
                            }
                        }
                        // Restar ausentismos a los dias de caja
                        if ($diasAus > 0) {
                            // 1. DIAS RIESGOS PARA CAJA
                            $datF = $f->getDiasEmp($idPla, $idEmp);
                            $campo = 'diasCaja';
                            $g->getPlanillaE($id, $campo, 30 - $diasAus);
                        }
                        if ($diasVac==0)  
                        {  
                            // 1. DIAS RIESGOS PARA CAJA CUANDO ES MENOR A 30 DIAS
                            $campo = 'diasCaja';
                            if ( ($diasRiesgos>0) or ($diasRiesgos<=30) )
                            {
                                $diasCaja = $diasRiesgos + $diasInca ;
                                $g->getPlanillaE($id, $campo, $diasCaja );                           
                            }
                        }
                        if (($tipo == 1) or ( $tipo == 3)) {  // SI ES APRENDIZ PRODUCTO LOS DIAS CAJA SON 0 
                            // 1. DIAS RIESGOS PARA CAJA
                            $datF = $f->getDiasEmp($idPla, $idEmp);
                            $campo = 'diasCaja';
                            $g->getPlanillaE($id, $campo, 0);
                        }                        

                        // FIN 4 IBC RIESGOS -------------------------------------------

                        //if ( ($diasRiesgos==0) and ($diasInca>0) ) // SI los dias riesgos con cero y se tiene una incapacidad no debe pagar parafiscales
                        //{
                        //$tipo = 99 ; // 
                        //} 
                        if ($tipo == 0) { // SOLO EMPLEADOS QUE NO SON APRENDICES 
                            // 5 IBC CAJA-------------------------------------------
                            $datF = $f->getCaja($idPla, $idEmp, $id);
                            $campo = 'ibcCaja';
                            $valor = ( $datF['valor'] + $valorNoLey);

                            if (( $diasRiesgos == 0 ) and ( $tipo == 0) and ($diasVac == 0)) // No paga caja
                                $valor = $diasCaja;

                            if (( $diasRiesgos > 0 ) and ( $tipo == 0)) 
                            {// No paga caja
                                $valor = $datF['valor'];
                                if ($swRedondeo==1)
                                   $valor = $e->getRedondear($valor, 1); //-----  Redondeo
                            }

                            if ($valor > 0) { // Valdiar que IBC de Caja sea amyor a cero 
                                // Si hay incapacidad el ibc de riesgos 
                                // es igual al ibc de caja                                
                                if ( ($diasRiesgos > 0) and ($diasInca == 0) ) { // No paga riesgs 
                                    $g->getPlanillaE($id, $campo, $valor);
                                    if (($retornoVaca == 0) and ( $idVaca == 0))
                                        $f->getTopeIbcCaja($id, $campo, $valor, $diasLab); // -- Validacion topes IBC Max y Min                                    
                                }

                                if ($diasInca > 0) { // Revisar este caso ejemplo 
                                    $g->getPlanillaE($id, $campo, $valor);
                                    //if (($retornoVaca == 0) and ( $idVaca == 0))
                                        //$f->getTopeIbcCaja($id, $campo, $valor, $diasLab); // -- Validacion topes IBC Max y Min
                                }

                                // 5.1 FONDOS CAJA DE COMPENSACION 
                                $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                                foreach ($datF as $datFc) {
                                    $valor = $datFc['idCaja'];
                                }
                                $campo = 'idCaja';
                                $g->getPlanillaE($id, $campo, $valor);

                                // 5.2 APORTE POR CAJA DE COMPENSACION
                                $datProv = $d->getProviciones(' and id=7 ');
                                $valor = $datProv['por'] . ' * ibcCaja';
                                $campo = 'aporCaja';
                                $g->getPlanillaE($id, $campo, $valor);
                                // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                                $datA = $f->getPlanillaE($id, $campo);
                                if ($swRedondeo==1) 
                                    $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                     
                                $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                                $campo = 'porCaja';                   // 
                                $valor = $datProv['por'];
                                $g->getPlanillaE($id, $campo, $valor);
                        // FIN 5 IBC CAJA-------------------------------------------
                        // 6 APORTE POR SENA-----------------------
                                $datProv = $d->getProviciones(' and id=8 ');
                                if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                                    $valor = 0;
                                else     
                                    $valor = $datProv['por'].' * ibcCaja';                                
                                $campo = 'aporSena';
                                $g->getPlanillaE($id, $campo, $valor);
                                // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                                $datA = $f->getPlanillaE($id, $campo);
                                $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                        
                                $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                                $campo = 'porSena';           
                                if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                                    $valor =  0;
                                else                                 
                                    $valor = $datProv['por'];

                                $g->getPlanillaE($id, $campo, $valor);
                          // FIN 6 APORTE POR SENA-----------------------
                          // 7 APORTE POR ICBF-------------------------------
                                $datProv = $d->getProviciones(' and id=9 ');
                                if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                                    $valor = 0;
                                else                                                         $valor = $datProv['por'] . ' * ibcCaja';
                                $campo = 'aporIcbf';
                                $g->getPlanillaE($id, $campo, $valor);
                                // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                                $datA = $f->getPlanillaE($id, $campo);
                                $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                     
                                $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                                $campo = 'porIcbf'; 
                                if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                                    $valor =  0;
                                else                                                    
                                    $valor = $datProv['por'];
                                $g->getPlanillaE($id, $campo, $valor);
                              // FIN 7 APORTE POR ICBF-------------------------------                                
                            }// Validacion IBC caja mayor a cero 
                            else {
                                // 7.1 FONDOS CAJA DE COMPENSACION 
                                $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                                foreach ($datF as $datFc) {
                                    $valor = $datFc['idCaja'];
                                }
                                $campo = 'idCaja';
                                $g->getPlanillaE($id, $campo, $valor);
                                // 1. DIAS RIESGOS LOS MISMOS DE CAJA 
                                $datF = $f->getDiasEmp($idPla, $idEmp);
                                $campo = 'diasCaja';
                                //$g->getPlanillaE($id, $campo, 0);
                            }
                        } // FIn validacion aportes parafiscales

                        if ($diasAus == 0) {// Validacion no puede haber variacion si hay ausentismo
                            // 23. VST
                            $datF = $f->getLey($idPla, $idEmp, $id);
                            $campo = 'nVst';
                            $valor = 0;
                            if ($tipo==0)
                            {  
                              if ($datF['valor'] > $datF['sueldo']) 
                              {
                                if (( $datF['valor'] - $datF['sueldo'] ) > 10) { // sI LA VARIACION ES DE MAS DE 10 PESOS
                                    $valor = 1;
                                    $g->getPlanillaE($id, $campo, $valor);
                                    // VARIACION EN SUELDO
                                    $campo = 'varSueldo';
                                    if (( $datF['valor'] - $datF['sueldo'] ) > 0) {
                                        $valor = round($datF['valor'] - $datF['sueldo'], 0);
                                        $g->getPlanillaE($id, $campo, $valor);
                                    }
                                }
                              }// MAyor que el sueldo 
                            }  
                        } // Fin validacion   
                        // 23. VST VARIACION PERMANENTE DE SUELDO 
                        $datF = $f->getDsueldos($idPla, $idEmp);
                        $campo = 'nVsp';
                        if ($datF['valor'] > 0)
                        {  
                            $g->getPlanillaE($id, $campo, 1);

                            $campo = 'fechaVsp';
                            $valor = $datF['fecDoc'];
                            $d->modGeneral("update n_planilla_unica_e 
                                  set fechaVsp = '".$valor."'  
                                     where id =".$id);                            

                        }    
                    } // FIN REGISTRO DE PLANILLA UNICA

// -------------------------------------------------                    
// -- AUSENTISMOS ----------------------------------                    
// -------------------------------------------------                    
                    $d->modGeneral("insert into n_planilla_unica_e 
                  ( idPla, idEmp, sueldo, pensionado, diasRetVaca, valorRetVaca, aprendiz , diasSalud, diasPension , diasCaja, diasRiesgos, regAus, nAus, codSuc ) 
                     select idPla, idEmp, sueldo, pensionado, diasRetVaca, valorRetVaca, aprendiz , a.diasAus, a.diasAus , a.diasAus, a.diasAus, 1,0, codSuc    
                         from n_planilla_unica_e a 
                            where a.diasAus > 0 and a.idPla = " . $idPla);

                    $datos = $d->getGeneral("select a.*, c.tipo, d.ano, d.mes   
                                           from n_planilla_unica_e a
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_tipemp c on c.id = b.idTemp  
                                              inner join n_planilla_unica d on d.id = a.idPla 
                                               where a.regAus > 0  AND a.idPla = " . $data->id);
                    $idPla = $data->id;
                    foreach ($datos as $dat) {
                        $id = $dat['id'];
                        $idEmp = $dat['idEmp'];
                        $pensionado = $dat['pensionado'];
                        $tipo = $dat['tipo']; // Standar 0 , Aprendiz productivo 1 , aprendiz electiva 2
                        $diasAus = $dat['diasSalud']; // Viene con los dias de                   
                        $ano = $dat['ano'];
                        $mes = $dat['mes'];
                        // 0. REGISTRO DE INCAPACIDAD 
                        $diasInca = 0;

                        // 0. REGISTRO DE VACACIONES 
                        $diasVac = 0;

                        // DIAS -----------------------------------------------------------------
                        // REGISTRO DE INGRESO O SALIDAS
                        $diasContra = 0;

                        $diasLab = $diasAus;

                        $diasPension = $diasAus;
                        // Total dias --------------------
                        $valor = $diasLab - ( $diasInca + $diasVac );
                        $diasRiesgos = $valor;

                        // ------------------------------------------------------- FIN DIAS
                        //echo $diasAus.' '.$id.' diasiii  <br />';
                        // 4. IBC SALUD PARA AUSENTISMOS
                        $datF = $f->getIbcAnt($idEmp, $ano, $mes);
                        $campo = 'ibcSalud';
                        $valor = ( ( $datF['ibcSalud'] / 30) * $diasLab );
                        if ($swRedondeo==1)                                           
                            $valor = $e->getRedondear($valor, 1); //-----  Redondeo                                          
                        $g->getPlanillaE($id, $campo, $valor);
                   
                         // Validacion de la cree para no pagar Salu, pnsion y parafiscales 
                         $pagoEmp=0;
                         if ( ($pagoEmpCre==1) and ( $tipo == 0 ) )// Validar que no sea aprendiz
                         { 
                            if ( $valor < ($salarioMinimo*10) )
                            {  
                               $pagoEmp=1;// Activa la variable para aplicar la exnoreacin
                               $campo = 'pagoEmp';                   
                               $g->getPlanillaE($id, $campo, 1 );              
                            }
                         }    
                         $ibcSaludAus = $valor;                        
                        // 5. FONDO DE SALUD
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFc) {
                            $valor = $datFc['idFsal'];
                        }
                        $campo = 'idFonS';
                        $g->getPlanillaE($id, $campo, $valor);
                        // 6. APORTE POR SALUD
                        $datProv = $d->getProviciones(' and id=5 ');
                        if ($pagoEmpCre==1)
                            $valor = '(4/100) * ibcSalud';
                        else
                            $valor = '(8.5/100) * ibcSalud';  

                        if ($saludAusen==1)
                            $valor = 0;

                        $campo = 'aporSalud';
                        $g->getPlanillaE($id, $campo, $valor);
                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        if ( ($swRedondeo==1) and ($saludAusen==0) )  
                            $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                        
                        $g->getPlanillaE($id, $campo, $valor); // Se edita el campo 

                        $campo = 'porSalud';                   // 
                        $valor = 8.5 / 100; // Se paga este porcentaje para ausentismos 
                        $g->getPlanillaE($id, $campo, $valor);

                        // 7. IBC PENSION PARA AUSENTISMOS 
                        if ($tipo == 0) { // Solo empleados 
                            //$datF = $f->getLeyAus($idPla, $idEmp, $diasAus);
                            $datF = $f->getIbcAnt($idEmp, $ano, $mes);
                            $campo = 'ibcPension';

                            $valor = ( ( $datF['ibcSalud'] / 30) * $diasLab );
                            if ($swRedondeo==1)  
                                $valor = $e->getRedondear($valor, 1); //-----  Redondeo                    
                            $valor = $ibcSaludAus;
                            if ($pensionado == 1) // Si es pensionado no paga dias de pension                                       
                                $valor = 0;
                            $g->getPlanillaE($id, $campo, $valor);
//                      if ($valor > 0)
                            //                        $f->getTopeIbc($id, $campo, $valor, $diasPension );// -- Validacion topes IBC Max y Min                    
                        }

                        // 8. FONDO DE PENSION
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFc) {
                            $valor = $datFc['idFpen'];
                        }

                        $campo = 'idFonP';
                        if ($pensionado == 0) // Si es pensionado no paga dias de pension                    
                            $g->getPlanillaE($id, $campo, $valor);

                        // 9. APORTE POR PENSION
                        if ($pensionado == 0) { // Si es pensionado no paga dias de pension                    
                            $datProv = $d->getProviciones(' and id=6 ');
                            $valor = $datProv['por'] . ' * ibcPension';
                            $campo = 'aporPension';
                            $g->getPlanillaE($id, $campo, $valor);
                            // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                            $datA = $f->getPlanillaE($id, $campo); // Consultar valor de un campo en planilla unica 
                            if ($swRedondeo==1)  
                                $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                            
                            $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                            $campo = 'porPension';                   // 
                            $valor = $datProv['por'];
                            $g->getPlanillaE($id, $campo, $valor);
                        }
                        // 10. Fondos de solidaridad                   
                        $datF = $f->getSolidaridad($idPla, $idEmp);
                        $valor = ( $datF['valor'] ); // La solidaridad se divide entre 2 
                        $valor = 0; // La solidaridad se divide entre 2 
                        $campo = 'aporSolidaridad';
                        if ($swRedondeo==1)  
                            $valor = $e->getRedondear($valor, 2); //-----  Redondeo

                        $g->getPlanillaE($id, $campo, $valor);                                                                                                 // 11. IBC RIESGOS
                        $datF = $f->getIbcAnt($idEmp, $ano, $mes);
                        $campo = 'ibcRiesgos';
                        $valor = ( ( $datF['ibcSalud'] / 30) * $diasLab );
                        if ($swRedondeo==1)
                            $valor = $e->getRedondear($valor, 1); //-----                 
                        $valor = $ibcSaludAus;       
                        $g->getPlanillaE($id, $campo, $valor);

                        // 12. TARIFA ARL 
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        $porArl = 0;
                        $valor = 0;
                        foreach ($datF as $datFc) {
                            $valor = 0;
                            $porArl = $datFc['porc'] / 100;
                        }
                        $porArl = 0;
                        $campo = 'tarifaArl';
                        $g->getPlanillaE($id, $campo, $valor);

                        // 13. FONDOS RIESGOS ARL
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFc) {
                            $valor = $datFc['idFarp'];
                        }
                        $campo = 'idFonR';
                        $g->getPlanillaE($id, $campo, $valor);

                        // 14. APORTES RIESGOS ARL
                        $valor = $porArl . ' * ibcRiesgos';
                        $campo = 'aporRiesgos';
                        $g->getPlanillaE($id, $campo, $valor);
                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                     
                        $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 
                        // CALCULOS ------------------------------------
                        // 15. IBC CAJA
                        $datF = $f->getIbcAnt($idEmp, $ano, $mes);
                        $valor = ( ( $datF['ibcSalud'] / 30) * $diasLab );
                        $valor = $e->getRedondear($valor, 1); //-----                    
                        $campo = 'ibcCaja';
                        $valor = $ibcSaludAus;
                        $g->getPlanillaE($id, $campo, $valor);

                        // 16. FONDOS CAJA DE COMPENSACION 
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFc) {
                            $valor = $datFc['idCaja'];
                        }
                        $campo = 'idCaja';
                        $g->getPlanillaE($id, $campo, $valor);

                        // 17. APORTE POR CAJA DE COMPENSACION
                        $datProv = $d->getProviciones(' and id=7 ');
                        $valor = $datProv['por'] . ' * ibcCaja';
                        //$valor = 0;
                        $campo = 'aporCaja';
                        $g->getPlanillaE($id, $campo, $valor);
                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                     
                        $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                        $campo = 'porCaja';                   // 
                        $valor = $datProv['por'];
                        $valor = 0;

                        $g->getPlanillaE($id, $campo, $valor);

                        // 18. APORTE POR SENA
                        $datProv = $d->getProviciones(' and id=8 ');
                        if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                            $valor =  0;
                        else                    
                            $valor = $datProv['por'].' * ibcCaja';                       

                        $valor = 0;
                        $campo = 'aporSena';
                        $g->getPlanillaE($id, $campo, $valor);
                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                        
                        $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                        $campo = 'porSena';                   // 
                        $valor = $datProv['por'];
                        $valor = 0;

                        $g->getPlanillaE($id, $campo, $valor);

                        // 19. APORTE POR ICBF
                        $datProv = $d->getProviciones(' and id=9 ');
                        if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                            $valor =  0;
                        else                                            
                             $valor = $datProv['por'] . ' * ibcCaja';

                        $campo = 'aporIcbf';
                        $g->getPlanillaE($id, $campo, $valor);
                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                     
                        $valor = 0;
                        $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                        $campo = 'porIcbf';                   // 
                        $valor = $datProv['por'];
                        $valor = 0;
                        $g->getPlanillaE($id, $campo, $valor);
                    } // FIN REGISTRO DE PLANILLA UNICA PARA AUSENTISMOS


                    $d->modGeneral("update n_planilla_unica set estado = 1 where id = " . $idPla);
                }// Sw e prueba ojo

                $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) {
                if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                    $connection->rollback();
                    echo $e;
                }
                /* Other error handling */
            }// FIN TRANSACCION        
        }

        $view = new ViewModel();
        $this->layout('layout/blanco'); // Layout del login
        return $view;
    }

// Fin generacion nomina
    // Validar que la nomina no este generada ********************************************************************************************
    public function listvpAction() {
        if ($this->getRequest()->isPost()) { // Actulizar datos
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $id = $data->id; // ID de la nomina                          
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $d = new AlbumTable($this->dbAdapter);
                $datos = $d->getGeneral1("select estado from n_planilla_unica where id=" . $id);
                $valido = '';
                if ($datos['estado'] == 1)
                    $valido = 1;
                $valores = array
                    (
                    "valido" => $valido,
                );
                $view = new ViewModel($valores);
                $this->layout('layout/blancoB'); // Layout del login
                return $view;
            }
        }
    }

// Fin listar registros     
    // Listado de planilla ********************************************************************************************
    public function listiAction() {
        $form = new Formulario("form");
        $id = (int) $this->params()->fromRoute('id', 0);
        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);        
        $form->get("id")->setAttribute("value", $id);
        $con = '';
        $conS = '';
        if ($this->getRequest()->isPost()) { // Actulizar datos
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');

                if ($data->id2 == 0) { // Validacion si guarda estado y cerra planilla
                    $dat = $d->getGeneral1("Select * from n_planilla_unica where id=" . $data->id);
                    // INICIO DE TRANSACCIONES
                    $connection = null;
                    try {
                        $connection = $this->dbAdapter->getDriver()->getConnection();
                        $connection->beginTransaction();
                        $d->modGeneral("update n_planilla_unica set estado=" . $data->estado . " where id=" . $data->id);
                        // Mover periodo de nomina 
                        $ano = $dat['ano'];
                        $mes = $dat['mes'];
                        if ($data->estado == 1)
                            $d->modGeneral("update n_planilla_unica_h set ano=" . $ano . ", mes=" . $mes . " where idGrupo=" . $dat['idGrupo']);
                        $connection->commit();
                        return $this->redirect()->toUrl($this->getRequest()->getBaseUrl() . $this->lin);
                    }// Fin try casth   
                    catch (\Exception $e) {
                        if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                            $connection->rollback();
                            echo $e;
                        }
                    }// FIN TRANSACCION        
                } // Fin validacion grabar estado
                // Valdiar sucursal 
                if ($data->tipoC>0)
                    $conS = " and b.codSuc='".$data->tipoC."'"; 

                if ($data->id2 == 1) { // Armar filtros de vista para revision de planilla
                    $id = $data->id;
                    switch ($data->filtro) {
                        case 0: // General
                            $con = '  ';
                            break;
                        case 1: // Incapacidades
                            $con = ' and ( ( b.nInca = 1 ) or ( b.Pat = 1 )or ( b.Mat = 1 ) )';
                            break;
                        case 2: // Vacaciones
                            $con = ' and ( b.idVac>0 ) ';
                            break;
                        case 3: // Variaciones 
                            $con = ' and ( b.diasSalud<30 or b.diasPension<30 or b.diasRiesgos<30 ) ';
                            break;
                        case 4: // Aprendices 
                            $con = ' and ( b.aprendiz = 1) ';
                            break;
                        case 5: // Retiros
                            $con = ' and ( b.nRetiro = 1) ';
                            break;
                        case 6: // Ingresos
                            $con = ' and ( b.nIngreso = 1) ';
                            break;
                        case 7: // Ausentismos
                            $con = ' and ( ( b.nAus = 1) or (b.regAus = 1) ) ';
                            break;
                        default:
                        case 8: // Fondos de solidaridad
                            $con = ' and ( b.aporSolidaridad > 0 ) ';
                            break;
                        # code...
                        case 9: // Dias riesgos cero 
                            $con = ' and ( b.diasRiesgos = 0 ) ';
                            break;
                    }
                }// Fin armado filtros de vista para revision de planilla
            }// Fin validacion post
        }
      $datos = $d->getGeneral("select a.idPla, a.codSuc, count( distinct(a.idEmp ) ) as num , lower(d.nombre ) as nombre 
                                           from n_planilla_unica_e a 
                                               inner join a_empleados b on b.id = a.idEmp 
                                               inner join n_sucursal_e c on c.idEmp = b.id 
                                               inner join n_sucursal d on d.id = c.idSuc 
                                             where a.regAus = 0 and a.idPla = ".$id." 
                                          group by a.idPla, a.codSuc 
                                            order by a.idPla, d.nombre");// Listado de cuentas
      $arreglo='';
      $arreglo[0]='Todas las sucursales';
      foreach ($datos as $dat){
          $idc=$dat['codSuc'];$nom = $dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      if ($arreglo != '')
         $form->get("tipoC")->setValueOptions($arreglo);                                                                                                                
        $d = new AlbumTable($this->dbAdapter);
        $valores = array
            (
            "titulo" => "Planilla N°" . $id,
            "daPer" => $d->getPermisos($this->lin), // Permisos de esta opcion
            "datos" => $d->getGeneral("select a.fecha, a.ano, a.mes, b.*,
                            c.id as idEmp, c.CedEmp, c.nombre as nomEmp, c.apellido, d.nombre as nomCcos, 
                            e.nombre as nomCar, f.fechaI as fecIniVac, date_sub( f.fechaR , INTERVAL 1 DAY)  as fecFinVac, c.finContrato,
                   ( select bb.ibcSalud 
                        from n_planilla_unica aa 
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 order by aa.id limit 1)   
                          as ibcSaludAnt ,
                   ( select bb.ibcCaja  
                        from n_planilla_unica aa 
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 order by aa.id limit 1)   
                          as ibcCajaAnt,
                   ( select bb.ibcRiesgos   
                        from n_planilla_unica aa 
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 order by aa.id limit 1)   
                          as ibcRiesgosAnt,
                   ( select bb.nIngreso    
                        from n_planilla_unica aa 
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 order by aa.id limit 1)   
                          as novIng , ingresosNo, b.mat,
( select count( aa.id )     
                        from n_planilla_unica_e aa where aa.idPla = a.id and aa.idEmp = b.idEmp ) as numReg, b.integral                              
                            from n_planilla_unica a 
                                inner join n_planilla_unica_e b on b.idPla = a.id 
                                inner join a_empleados c on c.id = b.idEmp 
                                inner join n_cencostos d on d.id = c.idCcos
                                inner join t_cargos e on e.id = c.idCar
                                left join n_vacaciones f on f.id = b.idVac 
                                left join n_sucursal g on g.codigo = b.codSuc 
                        where a.id = " . $id . " " . $con . " ". $conS ."
                                order by d.nombre, c.nombre"),
            "ttablas" => "Empleado,Sueldo, Variación,Dias salud, Dias pension,Dias riesgos, IBC Salud,"
            . "Aporte salud, IBC Pension, Aporte de pension, Aporte de solidaridad, IBC Riesgos, Tarifa Arl, Aporte riesgos, IBC Caja,"
            . "Aporte caja, Aporte Sena, Aporte Icbf, Ok ",
            "datInc" => $d->getGeneral("select d.idEmp, e.nombre as nomTinc    
                      from n_planilla_unica a 
                        inner join n_nomina b on year(b.fechaI) = a.ano and month(b.fechaI) = a.mes 
                        inner join n_nomina_e_i c on c.idNom = b.id 
                        inner join n_incapacidades d on d.id = c.idInc
                        inner join n_tipinc e on e.id = d.idInc 
                                where a.id = " . $id . "  
                                group by d.idEmp , e.nombre "),
            "datError" => $d->getGeneral("
         #------------------------------------------------------------ Incapacidades (1)
                       select 'Incapacidad' as tipo, b.CedEmp, b.nombre, b.apellido, a.* 
                              from n_planilla_unica_e a 
                                    inner join a_empleados b on b.id = a.idEmp 
                              where a.diasInc > 0 and ( ( a.ibcSalud != a.ibcPension ) or ( a.ibcRiesgos != a.ibcCaja ) ) 
                             and idPla = 6
         #------------------------------------------------------------ Vacaciones salidas (2)
         union all 
                       select 'Vacaciones salida' as tipo, b.CedEmp, b.nombre, b.apellido, a.* 
                              from n_planilla_unica_e a 
                                    inner join a_empleados b on b.id = a.idEmp 
                              where a.nVaca > 0 and a.pensionado=0 and a.diasRetVaca = 0 and 
                    (  ( a.diasRiesgos + a.diasVaca) > 30 or ( a.ibcSalud != a.ibcPension ) or ( a.ibcRiesgos >= a.ibcCaja ) or ( a.ibcRiesgos >= a.ibcSalud ) ) 
                             and idPla = 6                              
         #------------------------------------------------------------ Vacaciones retorno (3)
         union all 
                       select 'Vacaciones salida' as tipo, b.CedEmp, b.nombre, b.apellido, a.* 
                              from n_planilla_unica_e a 
                                    inner join a_empleados b on b.id = a.idEmp 
                              where a.nVaca > 0 and a.pensionado=0 and a.diasRetVaca > 0 and 
                    ( ( a.diasRiesgos + a.diasVaca) > 30 or ( a.ibcSalud != a.ibcPension ) or ( a.ibcRiesgos >= a.ibcCaja ) or ( a.ibcRiesgos >= a.ibcSalud ) ) 
                             and idPla = 6"),
            "datFon" => $d->getFondosPlanilla($id),
            'url' => $this->getRequest()->getBaseUrl(),
            "lin" => $this->lin,
            "id" => $id,
            "form" => $form,
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado
        );
        $view = new ViewModel($valores);
        $this->layout('layout/layoutTurnos');
        //$this->layout('layout/layoutPlanilla');
        return $view;
    }

// Fin listar registros     
    // Revision de registro de planilla unica
    public function listirAction() {
        if ($this->getRequest()->isPost()) { // Actulizar datos
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $id = $data->id; // ID de la nomina                          
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $d = new AlbumTable($this->dbAdapter);
                $datos = $d->getGeneral1("update n_planilla_unica_e set revisado=" . $data->valor . " where id=" . $id);
                $view = new ViewModel();
                $this->layout('layout/blancoC'); // Layout del login
                return $view;
            }
        }
    }

    // Variacion en sueldo 
    public function listvarAction() {
        if ($this->getRequest()->isPost()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $id = $data->id; // ID de la nomina                          
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $d = new PlanillaFunc($this->dbAdapter);
                $valores = array
                    (
                    "datos" => $d->getLeyD($data->id, $data->idPla, $data->idEmp),
                );
                $view = new ViewModel($valores);
                $this->layout('layout/blancoC'); // Layout del login
                return $view;
            }
        }
    }

    // Detallado Ley 100
    public function listleyAction() {
        if ($this->getRequest()->isPost()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $id = $data->id; // ID de la nomina                          
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $d = new PlanillaFunc($this->dbAdapter);
                $valores = array
                    (
                    "datos" => $d->getLeyD($data->id, $data->idPla, $data->idEmp),
                );
                $view = new ViewModel($valores);
                $this->layout('layout/blancoC'); // Layout del login
                return $view;
            }
        }
    }

    // Detallado Ley 100 Parafiscales
    public function listleyparaAction() {
        if ($this->getRequest()->isPost()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $id = $data->id; // ID de la nomina                          
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $d = new PlanillaFunc($this->dbAdapter);
                $valores = array
                    (
                    "datos" => $d->getCajaD($data->id, $data->idPla, $data->idEmp),
                );
                $view = new ViewModel($valores);
                $this->layout('layout/blancoC'); // Layout del login
                return $view;
            }
        }
    }

    // Detallado Ley 100 Riesgos
    public function listleyriesgosAction() {
        if ($this->getRequest()->isPost()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $id = $data->id; // ID de la nomina                          
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $d = new PlanillaFunc($this->dbAdapter);
                $valores = array
                    (
                    "datos" => $d->getLeyRD($data->id, $data->idPla, $data->idEmp),
                );
                $view = new ViewModel($valores);
                $this->layout('layout/blancoC'); // Layout del login
                return $view;
            }
        }
    }

    // ARCHIVO PLANO 1
    public function listplanoAction() 
    {
        $idi = $this->params()->fromRoute('id', 0);
        $pos = strpos($idi, '.');
        $idSuc = 0;
        if ( $pos > 0 )
        {    
           $id = substr($idi, 0, $pos);            
           $idSuc = substr($idi, $pos+1, 100);
        }else
           $id = $idi;            

        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $p = new PlanillaFunc($this->dbAdapter);
        $e = new EspFunc($this->dbAdapter);
        // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            // CONSULTA TIPO 01 
            $datos = $p->getCon01($id, $idSuc);
            // Armar achivo plano del banco
            $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
            $rutaP = $datGen['ruta']; // Ruta padre                    
            $ruta = $rutaP . '/archivo.txt';
            $archivo = fopen($ruta, "w") or die("Error");
            foreach ($datos as $dat) 
            {
                $registro = $dat['tipo'] . $dat['consecutivo'] . $dat['empresa'] . $dat['nit'] . $dat['nitEmpresa'] . $dat['e'];
                $registro.= $dat['blanco20'] . $dat['s'] . $dat['e01'] . $dat['blanco8'] . $dat['e02'] . $dat['blanco38'] . $dat['e1425'];
                $registro.= $dat['blanco1'] . $dat['perPension'] . $dat['perSalud'] . $dat['blanco9'] . $dat['e1'] . $dat['blanco11'] . $dat['numEmp'] . $dat['valNomina'] . $dat['e101'];
                fwrite($archivo, $registro . PHP_EOL);
            }
            // CONSULTA TIPO 02 ----------------------------------------------------------
            $datos = $p->getCon02($id, $idSuc);
            $itemCon = 1;
            foreach ($datos as $dat) 
            {
                $conse = str_pad($itemCon, 5, "0", STR_PAD_LEFT);  $itemCon++;
                $aporSolidaridad = $dat['aporSolidaridad'];
                $aporSolidaridad2 = $dat['aporSolidaridad9'];

                $registro = $dat['tipo'] . $conse . $dat['tipCed'] . $dat['cedula'] . $dat['tipAporte'] . $dat['blanco2'] . $dat['e00'];
                $registro.= $dat['ciuDepa'] . $dat['apellido1'] . $dat['apellido2'] . $dat['nombre1'] . $dat['nombre2'] . $dat['ingreso'];
                $registro.= $dat['retiro'] . $dat['trasSalud'] . $dat['idFsalTras'] . $dat['trasPension'] . $dat['idFpenTras'] . $dat['e1'] . $dat['nVaca'] . $dat['nVst'] . $dat['nAus'] . $dat['incGeneral'] . $dat['incMaternidad'];

                $registro.= $dat['vaca'] . $dat['blan1'] . $dat['blan2'] . $dat['acct'] . $dat['codFonPension'] . $dat['espaPension'] . $dat['codFonSalud'];
                $registro.= $dat['espaSalud'] . $dat['codCaja'] . $dat['espaCaja'] . $dat['diasPension'] . $dat['diasSalud'] . $dat['diasRiesgos'] . $dat['diasCaja'];
                $registro.= $dat['salarioBase'] . $dat['blan4'] . $dat['ibcPension'] . $dat['ibcSalud'] . $dat['ibcRiesgos'] . $dat['ibcCaja'] . $dat['porPension'];
                $registro.= $dat['aporPension'] . $dat['cerosPension'] . $dat['aporPension'] . $aporSolidaridad . $aporSolidaridad2 . $dat['cerosSolidaridad'] . $dat['porSalud'];
                $registro.= $dat['aporSalud'] . $dat['cerosSalud'] . $dat['espaciosPension'] . $dat['cerosSalud2'] . $dat['espaciosPension2'] . $dat['cerosSalud3'] . $dat['tarifaArl'];
                $registro.= $dat['cerosArl'] . $dat['claseRiesgo'] . $dat['aporRiesgos'] . $dat['porCaja'] . $dat['aporCaja'] . $dat['porSena'] . $dat['aporSena'];
                $registro.= $dat['porIcbf'] . $aporIcbf . $dat['valor1'] . $dat['cerosFinal'] . $dat['valor2'] . $dat['cerosFinal2'] . $dat['cerosFinal3'] . $dat['ley14'];

                fwrite($archivo, $registro . PHP_EOL);
//                        echo $registro.'<br />';
            }
            fclose($archivo);

            $connection->commit();
            //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
        }// Fin try casth   
        catch (\Exception $e) {
            if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                $connection->rollback();
                echo $e;
            }
            /* Other error handling */
        }// FIN TRANSACCION                          
        //        header('Content-Type: application/vnd.ms-excel');
        //        header('Content-Disposition: attachment;filename="reportTabla2.xlsx"');
        //       $objWriter->save('php://output');                                     

        $file = $ruta;
        header("Content-disposition: attachment; filename=$file");
        header("Content-type: application/octet-stream");
        readfile($file);

        // return new ViewModel();        
    }

// Fin archivo plano planilla unica         

    public function listplanonAction() 
    {
        $idi = $this->params()->fromRoute('id', 0);
        $pos = strpos($idi, '.');
        $idSuc = '';
        if ( $pos > 0 )
        {    
           $id = substr($idi, 0, $pos);            
           $idSuc = substr($idi, $pos+1, 100);
        }else
           $id = $idi;            

        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $p = new PlanillaFunc($this->dbAdapter);
        $e = new EspFunc($this->dbAdapter);
        // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            // Armar achivo plano del banco
            $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
            $rutaP = $datGen['ruta']; // Ruta padre                    
            $ruta = $rutaP . '/archivo.txt';
            $archivo = fopen($ruta, "w") or die("Error");


            $dat = $d->getGeneral1("select round(sum( cc.devengado ),0)  as valor 
                               from n_nomina aa
                                  inner join n_nomina_e bb on bb.idNom = aa.id
                                  inner join n_nomina_e_d cc on cc.idInom = bb.id 
                                  inner join a_empleados dd on dd.id = bb.idEmp 
                           where cc.devengado > 0 and year(aa.fechaI ) = 2017 and month(aa.fechaI)=5");
            $valNomina = $dat['valor'];
            $datos = $p->getCon02($id, $idSuc);
            $itemCon = 1;
            $totEmp = 1;
            $swEmp = 0;
            foreach ($datos as $dat) 
            {
               if ($swEmp != $dat['idEmp'])
                   $totEmp++;
            }      
            // CONSULTA TIPO 01 
            $codSuc = '';
            if ($idSuc!='')
                $codSuc = $idSuc;

            $nomSuc = '';
            $datos = $p->getCon01nuevo();            

            foreach ($datos as $dat) 
            {
                $conse = "0110001"; // 1 y 2 
                $razon = str_pad( ltrim($dat['razon']) , 200, " ", STR_PAD_RIGHT); // 3 
                $tipo = 'NI'; // 4
                $nit = str_pad( $dat['nit'] , 16, " ", STR_PAD_RIGHT); // 5 
                $divRazon = str_pad( $dat['divRazon'] , 1, " ", STR_PAD_RIGHT); // 6 
                $forma = str_pad( $dat['forma'] , 1, "", STR_PAD_RIGHT); // 7
                $tipoPla = 'E'; // 8
                $numPla = str_pad( '' ,10, " ", STR_PAD_RIGHT); // 12
                $codSuc = str_pad( ltrim($codSuc) ,2, " ", STR_PAD_RIGHT); // 9     
                $nomSuc = str_pad( ltrim($nomSuc) , 48, " ", STR_PAD_RIGHT); // 10  sucursal      
                $codigoArl = str_pad( ltrim( $dat['codigoArl'] ) , 6, " ", STR_PAD_RIGHT);//11
                $periodoSal = str_pad( $dat['periodoSal'] ,7, " ", STR_PAD_RIGHT); // 10 

                $periodoOsal = str_pad( $dat['periodoOsal'] ,7, " ", STR_PAD_RIGHT); // 10 

                $numRadicado = str_pad( '' ,10, "0", STR_PAD_RIGHT); // 12
                $fecPago = str_pad( '' ,10, " ", STR_PAD_RIGHT);// 13 en blanco cuando fecha esta en blanco 
                //$fecPagoPla = str_pad( $dat['fecha'] ,10, " ", STR_PAD_RIGHT);
                $fecPagoPla = str_pad( "" ,10, " ", STR_PAD_RIGHT);

                $numEmp = str_pad( $totEmp ,5, "0", STR_PAD_LEFT); // 12
                $valNomina = str_pad( $valNomina ,12, "0", STR_PAD_LEFT);// 15

                $numPen = str_pad( ' ' ,7, " ", STR_PAD_RIGHT);// 14 
                
                $tipoPen = str_pad( '', 2, " ", STR_PAD_RIGHT); // 16 
                $codigoOpe = str_pad( $dat['codigoOpe'],2, " ", STR_PAD_RIGHT); // 16                
                $tipoAporte = str_pad( $dat['tipoAporte'],2, " ", STR_PAD_RIGHT);

                $fecha = str_pad( $dat['fecha'] , 10 , " ", STR_PAD_RIGHT);
                $fecha = str_pad( '' , 10 , " ", STR_PAD_RIGHT);

                $registro = $conse.$razon.$tipo.$nit.$divRazon.$tipoPla.$numPla.$fecPago.$forma.$codSuc.$nomSuc.$codigoArl.$periodoOsal.$periodoSal.$numRadicado.$fecPagoPla.$numEmp.$valNomina.$tipoAporte.$codigoOpe; 

                fwrite($archivo, $registro . PHP_EOL);

            }
            // ARMADO DEL CUERPO TIPO 02

            $datos = $p->getCon02($id, $idSuc);
            $itemCon = 1;
            $swAus = 0;
            foreach ($datos as $dat) 
            {

if ($dat['diasSalud']>0)
{              
                $conse = str_pad($itemCon, 5, "0", STR_PAD_LEFT);  $itemCon++;

                //$aporSolidaridad = $dat['aporSolidaridad'];
                //$aporSolidaridad2 = $dat['aporSolidaridad9'];

                $ibcSalud = $dat['ibcSalud'];
                $ibcPension = $dat['ibcPension'];
                $ibcRiesgos = $dat['ibcRiesgos'];
                $ibcCaja = $dat['ibcCaja'];

                $diasPension = $dat['diasPension'];
                $diasSalud   = $dat['diasSalud'];
                $diasRiesgos = $dat['diasRiesgos'];
                $diasCaja = $dat['diasCaja'];      

                $aporPension2 = $dat['aporPension2'];
                $aporSolidaridad2   = $dat['aporSolidaridad2'];
                $aporSolidaridad92 = $dat['aporSolidaridad92'];
                $aporSalud2 = $dat['aporSalud2'];      
                $aporRiesgos2 = $dat['aporRiesgos2'];      
                $aporCaja2 = $dat['aporCaja2'];      
                $aporIcbf2 = $dat['aporIcbf2'];                      
                $aporSena2 = $dat['aporSena']; 

                $diasSaludReal   = $dat['diasSalud'];
                $diasRiesgosReal = $dat['diasRiesgos'];                                          
                $ingresoReal = $dat['ingreso'];
                $vspReal = $dat['e1'];

                $nVst =$dat['nVst'];
                if ($dat['vaca']=='X')
                {
                   $diasPension = $dat['diasVaca'];
                   $diasSalud   = $dat['diasVaca'];
                   $diasRiesgos = $dat['diasVaca'];
                   $diasCaja = $dat['diasVaca'];
                   $ibc = $ibcSalud;
                   $ibcSalud = round( ($ibcSalud/30)*($diasRiesgos), 0 );
                   $ibcPension = round( ($ibcPension/30)*($diasRiesgos), 0 );
                   $ibcRiesgos = round( ($ibc/30)*($diasRiesgos), 0 );
                   $ibcCaja = round( ($ibcCaja/30)*($diasRiesgos), 0 ); 

                   $aporPension2 = round( ($dat['aporPension2']/30)*($diasRiesgos), 0 );
                   $aporSolidaridad2   = round( ($dat['aporSolidaridad2']/30)*($diasRiesgos), 0 );
                   $aporSolidaridad92 = round( ($dat['aporSolidaridad92']/30)*($diasRiesgos), 0 );
                   $aporSalud2 = round( ($dat['aporSalud2']/30)*($diasRiesgos), 0 );      
                   $aporRiesgos2 = round( ($dat['aporRiesgos2']/30)*($diasRiesgos), 0 );      
                   $aporCaja2 = round( ($dat['aporCaja2']/30)*($diasRiesgos), 0 );      
                   $aporIcbf2 = round( ($dat['aporIcbf2']/30)*($diasRiesgos), 0 );                      
                   $aporSena2 = round( ($dat['aporSena']/30)*($diasRiesgos), 0 ); 

                   $nVst = ' ' ;                 
                }
                $swInca = 0;
                if ( ($dat['diasInc']>0) and ($dat['diasInc']<30) )
                {
                   $swInca = 1;
                   $diasPension = $dat['diasInc'];
                   $diasSalud   = $dat['diasInc'];
                   $diasRiesgos = $dat['diasInc'];
                   $diasCaja = $dat['diasInc'];
                   $ibc = $ibcSalud;
                   $ibcSalud = round( ($ibcSalud/30)*($diasSalud), 0 );
                   $ibcPension = round( ($ibc/30)*($diasPension), 0 );
                   $ibcRiesgos = round( ($ibc/30)*($diasSalud), 0 );
                   $ibcCaja = round( ($ibc/30)*($diasSalud), 0 );
                   $aporPension2 = round( ($dat['aporPension2']/30)*($diasPension), 0 );
                   $aporSolidaridad2   = round( ($dat['aporSolidaridad2']/30)*($diasPension), 0 );
                   $aporSolidaridad92 = round( ($dat['aporSolidaridad92']/30)*($diasPension), 0 );
                   $aporSalud2 = round( ($dat['aporSalud2']/30)*($diasSalud), 0 );      
                   $aporRiesgos2 = round( ($dat['aporRiesgos2']/30)*($diasRiesgos), 0 );      
                   $aporCaja2 = round( ($dat['aporCaja2']/30)*($diasRiesgos), 0 );      
                   $aporIcbf2 = round( ($dat['aporIcbf2']/30)*($diasRiesgos), 0 );                      
                   $aporSena2 = round( ($dat['aporSena']/30)*($diasRiesgos), 0 );                    
                }
                // Variacion permanente de trabajo 
                $swVsp = 0;
                $vsp = $dat['e1'];
                if ($dat['diasRetro'] < $diasSaludReal)
                    $vsp = ' ';

                if ( ($vsp=='X') and ($swInca == 0 ) )
                {
                   $swVsp = 1; 
                   $diasPension = $dat['diasRetro'];
                   $diasSalud   = $dat['diasRetro'];
                   $diasRiesgos = $dat['diasRetro'];
                   $diasCaja = $dat['diasRetro'];
                   $ibc = $ibcSalud;
                   $ibcSalud = round( ($ibcSalud/30)*($diasRiesgos), 0 );
                   $ibcPension = round( ($ibcPension/30)*($diasRiesgos), 0 );
                   $ibcRiesgos = round( ($ibc/30)*($diasRiesgos), 0 );
                   $ibcCaja = round( ($ibcCaja/30)*($diasRiesgos), 0 ); 

                   $aporPension2 = round( ($dat['aporPension2']/30)*($diasRiesgos), 0 );
                   $aporSolidaridad2   = round( ($dat['aporSolidaridad2']/30)*($diasRiesgos), 0 );
                   $aporSolidaridad92 = round( ($dat['aporSolidaridad92']/30)*($diasRiesgos), 0 );
                   $aporSalud2 = round( ($dat['aporSalud2']/30)*($diasRiesgos), 0 );      
                   $aporRiesgos2 = round( ($dat['aporRiesgos2']/30)*($diasRiesgos), 0 );      
                   $aporCaja2 = round( ($dat['aporCaja2']/30)*($diasRiesgos), 0 );      
                   $aporIcbf2 = round( ($dat['aporIcbf2']/30)*($diasRiesgos), 0 );                      
                   $aporSena2 = round( ($dat['aporSena']/30)*($diasRiesgos), 0 );                    
                   $nVst = ' ' ;                 
                }                                

                $ibcSalud = str_pad( $ibcSalud , 9, "0", STR_PAD_LEFT);
                $ibcPension = str_pad( $ibcPension , 9, "0", STR_PAD_LEFT);
                $ibcRiesgos = str_pad( $ibcRiesgos , 9, "0", STR_PAD_LEFT);
                $ibcCaja = str_pad( $ibcCaja , 9, "0", STR_PAD_LEFT);

                $diasPension = str_pad( $diasPension , 2, "0", STR_PAD_LEFT);
                $diasSalud = str_pad( $diasSalud , 2, "0", STR_PAD_LEFT);
                $diasRiesgos = str_pad( $diasRiesgos , 2, "0", STR_PAD_LEFT);
                $diasCaja = str_pad( $diasCaja , 2, "0", STR_PAD_LEFT);
              

                $aporPension = str_pad( $aporPension2 , 10, "0", STR_PAD_LEFT);
                $aporSolidaridad = str_pad( $aporSolidaridad2 , 9, "0", STR_PAD_LEFT);
                $aporSolidaridad2 = str_pad( $aporSolidaridad92 , 9, "0", STR_PAD_LEFT);                
                $aporSalud = str_pad( $aporSalud2 , 10, "0", STR_PAD_LEFT);
                $aporRiesgos = str_pad( $aporRiesgos2 , 9, "0", STR_PAD_LEFT);
                $aporCaja = str_pad( $aporCaja2 , 10, "0", STR_PAD_LEFT);
                $aporIcbf = str_pad( $aporIcbf2 , 10, "0", STR_PAD_LEFT);
                $aporSena = str_pad( $aporSena2 , 10, "0", STR_PAD_LEFT);                                                //$vsp = $dat['e1'];

                if ( ($ingresoReal=='X') and ($vsp=='X') )
                   $vsp = ' ';  

                $vaca = $dat['vaca'];
                //if ($dat['nVaca']=='X')
                    //$vaca = ' ';

                $registro = $dat['tipo'] . $conse . $dat['tipCed'] . $dat['cedula'] . $dat['tipAporte'] . $dat['blanco2'] . $dat['e00'];
                $registro.= $dat['ciuDepa'] . $dat['apellido1'] . $dat['apellido2'] . $dat['nombre1'] . $dat['nombre2'] . $dat['ingreso'];
                $registro.= $dat['retiro'] . $dat['trasSalud'] . $dat['idFsalTras'] . $dat['trasPension'] . $dat['idFpenTras'] . $vsp . $dat['nVaca'] . $nVst . $dat['nAus'] . $dat['incGeneral'] . $dat['incMaternidad'];

                $registro.= $vaca . $dat['blan1'] . $dat['blan2'] . $dat['acct'] . $dat['codFonPension'] . $dat['espaPension'] . $dat['codFonSalud'];
                $registro.= $dat['espaSalud'] . $dat['codCaja'] . $dat['espaCaja'] . $diasPension . $diasSalud . $diasRiesgos . $diasCaja;

                $registro.= $dat['salarioBase'] . $dat['blan4'] . $ibcPension . $ibcSalud . $ibcRiesgos. $ibcCaja . $dat['porPension'];
                $registro.= $aporPension . $dat['cerosPension'] . $aporPension . $aporSolidaridad . $aporSolidaridad2 . $dat['cerosSolidaridad'] . $dat['porSalud'];
                $registro.= $aporSalud . $dat['cerosSalud'] . $dat['espaciosPension'] . $dat['cerosSalud2'] . $dat['espaciosPension2'] . $dat['cerosSalud3'] . $dat['tarifaArl'];
                $registro.= $dat['cerosArl'] . $dat['claseRiesgo'] . $aporRiesgos . $dat['porCaja'] . $aporCaja . $dat['porSena'] . $aporSena;
                $registro.= $dat['porIcbf'] . $aporIcbf . $dat['valor1'] . $dat['cerosFinal'] . $dat['valor2'] . $dat['cerosFinal2'] . $dat['cerosFinal3'] . $dat['ley14'];
                // Nuevos campos
                $registro = $registro . $dat['codRiesgo']. $dat['idNriesgo']. $dat['tipoTarifa'];


   $fechaI = str_pad( $dat['fechaI'],10, " ", STR_PAD_RIGHT); 
   $fechaF = str_pad( $dat['fechaR'],10, " ", STR_PAD_RIGHT);   
if ($ingresoReal=='X')
    $fechaVsp = str_pad( "" ,10, " ", STR_PAD_RIGHT); 
else
 { 
    if ( $vsp == 'X' ) 
    {
       if ($swInca == 0 )  
          $fechaVsp = str_pad( $dat['fechaVsp'],10, " ", STR_PAD_RIGHT);    
       else
          $fechaVsp = str_pad( "" ,10, " ", STR_PAD_RIGHT);     
    }   
    else
       $fechaVsp = str_pad( " " ,10, " ", STR_PAD_RIGHT);     
  }  

$fechaIsln = str_pad( $dat['fechaIsln'],10, " ", STR_PAD_RIGHT); 
$fechaFsln = str_pad( $dat['fechaFsln'],10, " ", STR_PAD_RIGHT); 
$fechaIige = str_pad( $dat['fechaIige'],10, " ", STR_PAD_RIGHT); 
$fechaFige = str_pad( $dat['fechaFige'],10, " ", STR_PAD_RIGHT); 
   
$fechaIlma = str_pad( $dat['fechaIlma'],10, " ", STR_PAD_RIGHT); 
$fechaFlma = str_pad( $dat['fechaFlma'],10, " ", STR_PAD_RIGHT); 

$fechaIvac = str_pad( $dat['fechaIvac'],10, " ", STR_PAD_RIGHT); 
$fechaFvac = str_pad( $dat['fechaFvac'],10, " ", STR_PAD_RIGHT); 
if ($dat['vaca']=='X')
{
   $fechaIvct = str_pad( "",10, " ", STR_PAD_RIGHT); 
   $fechaFvct = str_pad( "" ,10, " ", STR_PAD_RIGHT); 
}else{
   $fechaIvct = str_pad( $dat['fechaIvct'],10, " ", STR_PAD_RIGHT); 
   $fechaFvct = str_pad( $dat['fechaFvct'],10, " ", STR_PAD_RIGHT);   
}   
$fechaIirl = str_pad( $dat['fechaIirl'],10, " ", STR_PAD_RIGHT); 
$fechaFirl = str_pad( $dat['fechaFirl'],10, " ", STR_PAD_RIGHT); 

if ( $dat['ley14'] == 'S' )
   $ibcOtPara = str_pad( "" , 9, "0", STR_PAD_LEFT);
else  
   $ibcOtPara = $ibcCaja;

if ($dat['tipAporte']=='01')
    $horas = str_pad( ( $dat['diasRiesgos'] * 8 )  ,3, "0", STR_PAD_LEFT); 
else   
    $horas = str_pad( ''  ,3, "0", STR_PAD_LEFT); 


                $registro = $registro. $fechaI. $fechaF. $fechaVsp. $fechaIsln. $fechaFsln. $fechaIige. $fechaFige. $fechaIlma. $fechaFlma. $fechaIvac. $fechaFvac. $fechaIvct. $fechaFvct. $fechaIirl. $fechaFirl. $ibcOtPara. $horas. $fecha ;                        

if ($swAus==0)  
              fwrite($archivo, $registro . PHP_EOL);

if ( ($dat['idEmp']==373) and  ($swAus == 0) ) 
{
 // $swAus = 1;
}
  
// LINEAS VACACIONES 
                if ($dat['vaca']=='X') // Se crea otro registro para el mismo empleados por los dias de vacaciones 
                {
                   $ibcSalud = $dat['ibcSalud'];
                   $ibcPension = $dat['ibcPension'];
                   $ibcRiesgos = $dat['ibcRiesgos'];
                   $ibcCaja = $dat['ibcCaja'];                  
                   $conse = str_pad($itemCon, 5, "0", STR_PAD_LEFT);  $itemCon++;

                   $diasPension = $dat['diasRiesgos'];
                   $diasSalud   = $dat['diasRiesgos'];
                   $diasRiesgos = $dat['diasRiesgos'];
                   $diasCaja = $dat['diasRiesgos'];
                   $ibc = $ibcSalud ;
                   $ibcSalud = round( ($ibc/30)*$diasRiesgos, 0 );
                   $ibcPension = round( ($ibc/30)*$diasRiesgos, 0 );
                   $ibcRiesgos = round( ($ibc/30)*$diasRiesgos, 0 );
                   $ibcCaja = round( ($ibc/30)*$diasRiesgos, 0 );             

                   $aporPension2 = round( ($dat['aporPension2']/30)*($diasRiesgos), 0 );
                   $aporSolidaridad2  = round( ($dat['aporSolidaridad2']/30)*($diasRiesgos), 0 );
                   $aporSolidaridad92 = round( ($dat['aporSolidaridad92']/30)*($diasRiesgos), 0 );
                   $aporSalud2 = round( ($dat['aporSalud2']/30)*($diasRiesgos), 0 );      
                   $aporRiesgos2 = round( ($dat['aporRiesgos2']/30)*($diasRiesgos), 0 );      
                   $aporCaja2 = round( ($dat['aporCaja2']/30)*($diasRiesgos), 0 );      
                   $aporIcbf2 = round( ($dat['aporIcbf2']/30)*($diasRiesgos), 0 );                      
                   $aporSena2 = round( ($dat['aporSena']/30)*($diasRiesgos), 0 ); 


                   $ibcSalud = str_pad( $ibcSalud , 9, "0", STR_PAD_LEFT);
                   $ibcPension = str_pad( $ibcPension , 9, "0", STR_PAD_LEFT);
                   $ibcRiesgos = str_pad( $ibcRiesgos , 9, "0", STR_PAD_LEFT);
                   $ibcCaja = str_pad( $ibcCaja , 9, "0", STR_PAD_LEFT);

                   $diasPension = str_pad( $diasPension , 2, "0", STR_PAD_LEFT);
                   $diasSalud = str_pad( $diasSalud , 2, "0", STR_PAD_LEFT);
                   $diasRiesgos = str_pad( $diasRiesgos , 2, "0", STR_PAD_LEFT);
                   $diasCaja = str_pad( $diasCaja , 2, "0", STR_PAD_LEFT);

                $aporPension = str_pad( $aporPension2 , 10, "0", STR_PAD_LEFT);
                $aporSolidaridad = str_pad( $aporSolidaridad2 , 9, "0", STR_PAD_LEFT);
                $aporSolidaridad2 = str_pad( $aporSolidaridad92 , 9, "0", STR_PAD_LEFT);                
                $aporSalud = str_pad( $aporSalud2 , 10, "0", STR_PAD_LEFT);
                $aporRiesgos = str_pad( $aporRiesgos2 , 9, "0", STR_PAD_LEFT);
                $aporCaja = str_pad( $aporCaja2 , 10, "0", STR_PAD_LEFT);
                $aporIcbf = str_pad( $aporIcbf2 , 10, "0", STR_PAD_LEFT);
                $aporSena = str_pad( $aporSena2 , 10, "0", STR_PAD_LEFT);

                $vaca = ' ';
                if ($dat['vaca']=='X')
                    $vaca = ' ';
                //$nVst = $dat['nVst'];
                 // $dat['e1']
                $nVst = " ";
                $nVsp = " ";
                $registro = $dat['tipo'] . $conse . $dat['tipCed'] . $dat['cedula'] . $dat['tipAporte'] . $dat['blanco2'] . $dat['e00'];
                $registro.= $dat['ciuDepa'] . $dat['apellido1'] . $dat['apellido2'] . $dat['nombre1'] . $dat['nombre2'] . $dat['ingreso'];
                $registro.= $dat['retiro'] . $dat['trasSalud'] . $dat['idFsalTras'] . $dat['trasPension'] . $dat['idFpenTras'] . $nVsp . $vaca . $nVst . " " . " " . $dat['incMaternidad'];
                $codCt = ' ';
                // Se coloca variacion transitoria de centro de trabajo.
                $registro.= ' ' . $dat['blan1'] . $codCt . $dat['acct'] . $dat['codFonPension'] . $dat['espaPension'] . $dat['codFonSalud'];
                $registro.= $dat['espaSalud'] . $dat['codCaja'] . $dat['espaCaja'] . $diasPension . $diasSalud . $diasRiesgos . $diasCaja;

                $registro.= $dat['salarioBase'] . $dat['blan4'] . $ibcPension . $ibcSalud . $ibcRiesgos. $ibcCaja . $dat['porPension'];
                $registro.= $aporPension . $dat['cerosPension'] . $aporPension . $aporSolidaridad . $aporSolidaridad2 . $dat['cerosSolidaridad'] . $dat['porSalud'];
                $registro.= $aporSalud . $dat['cerosSalud'] . $dat['espaciosPension'] . $dat['cerosSalud2'] . $dat['espaciosPension2'] . $dat['cerosSalud3'] . $dat['tarifaArl'];
                $registro.= $dat['cerosArl'] . $dat['claseRiesgo'] . $aporRiesgos . $dat['porCaja'] . $aporCaja . $dat['porSena'] . $aporSena;
                $registro.= $dat['porIcbf'] . $aporIcbf . $dat['valor1'] . $dat['cerosFinal'] . $dat['valor2'] . $dat['cerosFinal2'] . $dat['cerosFinal3'] . $dat['ley14'];
                // Nuevos campos
                $registro = $registro . $dat['codRiesgo']. $dat['idNriesgo']. $dat['tipoTarifa'];

$fechaI = str_pad( $dat['fechaI'],10, " ", STR_PAD_RIGHT); 
$fechaF = str_pad( $dat['fechaR'],10, " ", STR_PAD_RIGHT); 
$fechaVsp = str_pad( "" ,10, " ", STR_PAD_RIGHT); // Vsp desactivado 
$fechaIsln = str_pad( $dat['fechaIsln'],10, " ", STR_PAD_RIGHT); 
$fechaFsln = str_pad( $dat['fechaFsln'],10, " ", STR_PAD_RIGHT); 
$fechaIige = str_pad( $dat['fechaIige'],10, " ", STR_PAD_RIGHT); 
$fechaFige = str_pad( $dat['fechaFige'],10, " ", STR_PAD_RIGHT); 
$fechaIlma = str_pad( $dat['fechaIlma'],10, " ", STR_PAD_RIGHT); 
$fechaFlma = str_pad( $dat['fechaFlma'],10, " ", STR_PAD_RIGHT); 

$fechaIvac = str_pad( "",10, " ", STR_PAD_RIGHT); 
$fechaFvac = str_pad( "",10, " ", STR_PAD_RIGHT); 
$fechaIvct = str_pad( "",10, " ", STR_PAD_RIGHT); 
$fechaFvct = str_pad( "",10, " ", STR_PAD_RIGHT); 
$fechaIirl = str_pad( $dat['fechaIirl'],10, " ", STR_PAD_RIGHT); 
$fechaFirl = str_pad( $dat['fechaFirl'],10, " ", STR_PAD_RIGHT); 

if ( $dat['ley14'] == 'S' )
   $ibcOtPara = str_pad( "" , 9, "0", STR_PAD_LEFT);
else  
   $ibcOtPara = $ibcCaja;

if ($dat['tipAporte']=='01')
    $horas = str_pad( ( $dat['diasRiesgos'] * 8 )  ,3, "0", STR_PAD_LEFT); 
else   
    $horas = str_pad( ''  ,3, "0", STR_PAD_LEFT); 


                $registro = $registro. $fechaI. $fechaF. $fechaVsp. $fechaIsln. $fechaFsln. $fechaIige. $fechaFige. $fechaIlma. $fechaFlma. $fechaIvac. $fechaFvac. $fechaIvct. $fechaFvct. $fechaIirl. $fechaFirl. $ibcOtPara. $horas. $fecha ;
                  fwrite($archivo, $registro . PHP_EOL);                                             

                }
 // LINEAS INCAPACIDADES 
                if ( ($dat['diasInc'] > 0 )  and ($dat['diasInc']<30) )
                {
                   $conse = str_pad($itemCon, 5, "0", STR_PAD_LEFT);  $itemCon++;

                   $ibcSalud = $dat['ibcSalud'];
                   $ibcPension = $dat['ibcPension'];
                   $ibcRiesgos = $dat['ibcRiesgos'];
                   $ibcCaja = $dat['ibcCaja'];                  

                   $diasPension = $dat['diasSalud']-$dat['diasInc'];
                   $diasSalud   = $dat['diasSalud']-$dat['diasInc'];
                   $diasRiesgos = $dat['diasSalud']-$dat['diasInc'];
                   $diasCaja = $dat['diasSalud']-$dat['diasInc'];
                   $ibc = $ibcSalud ;
                   $ibcSalud = round( ($ibc/30)*($diasSalud), 0 );
                   $ibcPension = round( ($ibc/30)*($diasPension) , 0 );
                   $ibcRiesgos = round( ($ibc/30)*($diasRiesgos), 0 );
                   $ibcCaja = round( ($ibc/30)*($diasCaja), 0 );

                   $aporPension2 = round( ($dat['aporPension2']/30)*($diasPension), 0 );
                   $aporSolidaridad2   = round( ($dat['aporSolidaridad2']/30)*($diasPension), 0 );
                   $aporSolidaridad92 = round( ($dat['aporSolidaridad92']/30)*($diasPension), 0 );
                   $aporSalud2 = round( ($dat['aporSalud2']/30)*($diasSalud), 0 );      
                   $aporRiesgos2 = round( ($dat['aporRiesgos2']/30)*($diasRiesgos), 0 );      
                   $aporCaja2 = round( ($dat['aporCaja2']/30)*($diasRiesgos), 0 );      
                   $aporIcbf2 = round( ($dat['aporIcbf2']/30)*($diasRiesgos), 0 );                      
                   $aporSena2 = round( ($dat['aporSena']/30)*($diasRiesgos), 0 );


                $ibcSalud = str_pad( $ibcSalud , 9, "0", STR_PAD_LEFT);
                $ibcPension = str_pad( $ibcPension , 9, "0", STR_PAD_LEFT);
                $ibcRiesgos = str_pad( $ibcRiesgos , 9, "0", STR_PAD_LEFT);
                $ibcCaja = str_pad( $ibcCaja , 9, "0", STR_PAD_LEFT);

                $diasPension = str_pad( $diasPension , 2, "0", STR_PAD_LEFT);
                $diasSalud = str_pad( $diasSalud , 2, "0", STR_PAD_LEFT);
                $diasRiesgos = str_pad( $diasSalud , 2, "0", STR_PAD_LEFT);
                $diasCaja = str_pad( $diasCaja , 2, "0", STR_PAD_LEFT);

                $aporPension = str_pad( $aporPension2 , 10, "0", STR_PAD_LEFT);
                $aporSolidaridad = str_pad( $aporSolidaridad2 , 9, "0", STR_PAD_LEFT);
                $aporSolidaridad2 = str_pad( $aporSolidaridad92 , 9, "0", STR_PAD_LEFT);                
                $aporSalud = str_pad( $aporSalud2 , 10, "0", STR_PAD_LEFT);
                $aporRiesgos = str_pad( $aporRiesgos2 , 9, "0", STR_PAD_LEFT);
                $aporCaja = str_pad( $aporCaja2 , 10, "0", STR_PAD_LEFT);
                $aporIcbf = str_pad( $aporIcbf2 , 10, "0", STR_PAD_LEFT);
                $aporSena = str_pad( $aporSena2 , 10, "0", STR_PAD_LEFT);
     $vsp = " ";

                $registro = $dat['tipo'] . $conse . $dat['tipCed'] . $dat['cedula'] . $dat['tipAporte'] . $dat['blanco2'] . $dat['e00'];
                $registro.= $dat['ciuDepa'] . $dat['apellido1'] . $dat['apellido2'] . $dat['nombre1'] . $dat['nombre2'] . $dat['ingreso'];
                $registro.= $dat['retiro'] . $dat['trasSalud'] . $dat['idFsalTras'] . $dat['trasPension'] . $dat['idFpenTras'] . $vsp . $dat['nVaca'] . $dat['nVst'] . $dat['nAus'] . ' ' . $dat['incMaternidad'];
                // Se coloca variacion transitoria de centro de trabajo.
                $registro.= ' ' . $dat['blan1'] . ' ' . '00' . $dat['codFonPension'] . $dat['espaPension'] . $dat['codFonSalud'];
                $registro.= $dat['espaSalud'] . $dat['codCaja'] . $dat['espaCaja'] . $diasPension . $diasSalud . $diasRiesgos . $diasCaja;

                $registro.= $dat['salarioBase'] . $dat['blan4'] . $ibcPension . $ibcSalud . $ibcRiesgos. $ibcCaja . $dat['porPension'];
                $registro.= $aporPension . $dat['cerosPension'] . $aporPension . $aporSolidaridad . $aporSolidaridad2 . $dat['cerosSolidaridad'] . $dat['porSalud'];
                $registro.= $aporSalud . $dat['cerosSalud'] . $dat['espaciosPension'] . $dat['cerosSalud2'] . $dat['espaciosPension2'] . $dat['cerosSalud3'] . $dat['tarifaArl'];
                $registro.= $dat['cerosArl'] . $dat['claseRiesgo'] . $aporRiesgos . $dat['porCaja'] . $aporCaja . $dat['porSena'] . $aporSena;
                $registro.= $dat['porIcbf'] . $aporIcbf . $dat['valor1'] . $dat['cerosFinal'] . $dat['valor2'] . $dat['cerosFinal2'] . $dat['cerosFinal3'] . $dat['ley14'];
                // Nuevos campos
                $registro = $registro . $dat['codRiesgo']. $dat['idNriesgo']. $dat['tipoTarifa'];

                $fechaI = str_pad( $dat['fechaI'],10, " ", STR_PAD_RIGHT); 
                $fechaF = str_pad( $dat['fechaR'],10, " ", STR_PAD_RIGHT); 
                if ( $swInca == 0 )
                  $fechaVsp = str_pad( $dat['fechaVsp'],10, " ", STR_PAD_RIGHT); 
                else
                  $fechaVsp = str_pad( "",10, " ", STR_PAD_RIGHT); 

                $fechaIsln = str_pad( $dat['fechaIsln'],10, " ", STR_PAD_RIGHT); 
                $fechaFsln = str_pad( $dat['fechaFsln'],10, " ", STR_PAD_RIGHT); 
                if ( ($dat['diasInc']>0) and ($dat['diasInc']<30) )
       {
   $fechaIige = str_pad( "",10, " ", STR_PAD_RIGHT); 
   $fechaFige = str_pad( "",10, " ", STR_PAD_RIGHT); 
}else{
   $fechaIige = str_pad( $dat['fechaIige'],10, " ", STR_PAD_RIGHT); 
   $fechaFige = str_pad( $dat['fechaFige'],10, " ", STR_PAD_RIGHT);   
}   
$fechaIlma = str_pad( $dat['fechaIlma'],10, " ", STR_PAD_RIGHT); 
$fechaFlma = str_pad( $dat['fechaFlma'],10, " ", STR_PAD_RIGHT); 

$fechaIvac = str_pad( "",10, " ", STR_PAD_RIGHT); 
$fechaFvac = str_pad( "" ,10, " ", STR_PAD_RIGHT); 
$fechaIvct = str_pad( $dat['fechaIvct'],10, " ", STR_PAD_RIGHT); 
$fechaFvct = str_pad( $dat['fechaFvct'],10, " ", STR_PAD_RIGHT); 

    $fechaIirl = str_pad( '',10, " ", STR_PAD_RIGHT); 
  $fechaFirl = str_pad( '',10, " ", STR_PAD_RIGHT);

if ( $dat['ley14'] == 'S' )
   $ibcOtPara = str_pad( "" , 9, "0", STR_PAD_LEFT);
else  
   $ibcOtPara = $ibcCaja;

if ($dat['tipAporte']=='01')
    $horas = str_pad( ( $dat['diasRiesgos'] * 8 )  ,3, "0", STR_PAD_LEFT); 
else   
    $horas = str_pad( ''  ,3, "0", STR_PAD_LEFT); 


                $registro = $registro. $fechaI. $fechaF. $fechaVsp. $fechaIsln. $fechaFsln. $fechaIige. $fechaFige. $fechaIlma. $fechaFlma. $fechaIvac. $fechaFvac. $fechaIvct. $fechaFvct. $fechaIirl. $fechaFirl. $ibcOtPara. $horas. $fecha ;
                  fwrite($archivo, $registro . PHP_EOL);

            } // Final plano de incapacidades           


// LINEAS AUMENTO DE SUELDO
                if ( ( $vsp == 'X' ) and ( $swInca == 0 ) )
                {
                   $conse = str_pad($itemCon, 5, "0", STR_PAD_LEFT);  $itemCon++;

                   $ibcSalud = $dat['ibcSalud'];
                   $ibcPension = $dat['ibcPension'];
                   $ibcRiesgos = $dat['ibcRiesgos'];
                   $ibcCaja = $dat['ibcCaja'];                  

                   $diasPension = $diasSaludReal-$dat['diasRetro'];
                   $diasSalud   = $diasSaludReal-$dat['diasRetro'];
                   $diasRiesgos = $diasRiesgosReal-$dat['diasRetro'];
                   $diasCaja = $diasRiesgosReal-$dat['diasRetro'];
                   $ibc = $ibcSalud ;
                   $ibcSalud = round( ($ibc/30)*($diasSalud), 0 );
                   $ibcPension = round( ($ibc/30)*($diasPension) , 0 );
                   $ibcRiesgos = round( ($ibc/30)*($diasRiesgos), 0 );
                   $ibcCaja = round( ($ibc/30)*($diasCaja), 0 );

                   $aporPension2 = round( ($dat['aporPension2']/30)*($diasPension), 0 );
                   $aporSolidaridad2   = round( ($dat['aporSolidaridad2']/30)*($diasPension), 0 );
                   $aporSolidaridad92 = round( ($dat['aporSolidaridad92']/30)*($diasPension), 0 );
                   $aporSalud2 = round( ($dat['aporSalud2']/30)*($diasSalud), 0 );      
                   $aporRiesgos2 = round( ($dat['aporRiesgos2']/30)*($diasRiesgos), 0 );      
                   $aporCaja2 = round( ($dat['aporCaja2']/30)*($diasRiesgos), 0 );      
                   $aporIcbf2 = round( ($dat['aporIcbf2']/30)*($diasRiesgos), 0 );                      
                   $aporSena2 = round( ($dat['aporSena']/30)*($diasRiesgos), 0 );
                $ibcSalud = str_pad( $ibcSalud , 9, "0", STR_PAD_LEFT);
                $ibcPension = str_pad( $ibcPension , 9, "0", STR_PAD_LEFT);
                $ibcRiesgos = str_pad( $ibcRiesgos , 9, "0", STR_PAD_LEFT);
                $ibcCaja = str_pad( $ibcCaja , 9, "0", STR_PAD_LEFT);

                $diasPension = str_pad( $diasPension , 2, "0", STR_PAD_LEFT);
                $diasSalud = str_pad( $diasSalud , 2, "0", STR_PAD_LEFT);
                $diasRiesgos = str_pad( $diasSalud , 2, "0", STR_PAD_LEFT);
                $diasCaja = str_pad( $diasCaja , 2, "0", STR_PAD_LEFT);

                $aporPension = str_pad( $aporPension2 , 10, "0", STR_PAD_LEFT);
                $aporSolidaridad = str_pad( $aporSolidaridad2 , 9, "0", STR_PAD_LEFT);
                $aporSolidaridad2 = str_pad( $aporSolidaridad92 , 9, "0", STR_PAD_LEFT);                
                $aporSalud = str_pad( $aporSalud2 , 10, "0", STR_PAD_LEFT);
                $aporRiesgos = str_pad( $aporRiesgos2 , 9, "0", STR_PAD_LEFT);
                $aporCaja = str_pad( $aporCaja2 , 10, "0", STR_PAD_LEFT);
                $aporIcbf = str_pad( $aporIcbf2 , 10, "0", STR_PAD_LEFT);
                $aporSena = str_pad( $aporSena2 , 10, "0", STR_PAD_LEFT);

                $vsp = ' ';
                if ($ingresoReal=='X')
                    $vsp = $dat['e1'];
                $ingreso = " ";
                $registro = $dat['tipo'] . $conse . $dat['tipCed'] . $dat['cedula'] . $dat['tipAporte'] . $dat['blanco2'] . $dat['e00'];
                $registro.= $dat['ciuDepa'] . $dat['apellido1'] . $dat['apellido2'] . $dat['nombre1'] . $dat['nombre2'] . $ingreso;
                $registro.= $dat['retiro'] . $dat['trasSalud'] . $dat['idFsalTras'] . $dat['trasPension'] . $dat['idFpenTras'] . $vsp . $dat['nVaca'] . $dat['nVst'] . $dat['nAus'] . ' ' . $dat['incMaternidad'];
                // Se coloca variacion transitoria de centro de trabajo.
                $registro.= ' ' . $dat['blan1'] . ' ' . '00' . $dat['codFonPension'] . $dat['espaPension'] . $dat['codFonSalud'];
                $registro.= $dat['espaSalud'] . $dat['codCaja'] . $dat['espaCaja'] . $diasPension . $diasSalud . $diasRiesgos . $diasCaja;

                $registro.= $dat['salarioBase'] . $dat['blan4'] . $ibcPension . $ibcSalud . $ibcRiesgos. $ibcCaja . $dat['porPension'];
                $registro.= $aporPension . $dat['cerosPension'] . $aporPension . $aporSolidaridad . $aporSolidaridad2 . $dat['cerosSolidaridad'] . $dat['porSalud'];
                $registro.= $aporSalud . $dat['cerosSalud'] . $dat['espaciosPension'] . $dat['cerosSalud2'] . $dat['espaciosPension2'] . $dat['cerosSalud3'] . $dat['tarifaArl'];
                $registro.= $dat['cerosArl'] . $dat['claseRiesgo'] . $aporRiesgos . $dat['porCaja'] . $aporCaja . $dat['porSena'] . $aporSena;
                $registro.= $dat['porIcbf'] . $aporIcbf . $dat['valor1'] . $dat['cerosFinal'] . $dat['valor2'] . $dat['cerosFinal2'] . $dat['cerosFinal3'] . $dat['ley14'];
                // Nuevos campos
                $registro = $registro . $dat['codRiesgo']. $dat['idNriesgo']. $dat['tipoTarifa'];

                $fechaI = str_pad( "",10, " ", STR_PAD_RIGHT); 
                $fechaF = str_pad( "" ,10, " ", STR_PAD_RIGHT); 
                if ($ingresoReal=='X')                
                    $fechaVsp = str_pad( $dat['fechaVsp'] ,10, " ", STR_PAD_RIGHT); 
                else
                    $fechaVsp = str_pad( "" ,10, " ", STR_PAD_RIGHT);

                $fechaIsln = str_pad( "" ,10, " ", STR_PAD_RIGHT); 
                $fechaFsln = str_pad( $dat['fechaFsln'],10, " ", STR_PAD_RIGHT); 

                $fechaIige = str_pad( "",10, " ", STR_PAD_RIGHT); 
                $fechaFige = str_pad( "",10, " ", STR_PAD_RIGHT); 

                $fechaIlma = str_pad( $dat['fechaIlma'],10, " ", STR_PAD_RIGHT); 
                $fechaFlma = str_pad( $dat['fechaFlma'],10, " ", STR_PAD_RIGHT); 

$fechaIvac = str_pad( "",10, " ", STR_PAD_RIGHT); 
$fechaFvac = str_pad( "" ,10, " ", STR_PAD_RIGHT); 
$fechaIvct = str_pad( $dat['fechaIvct'],10, " ", STR_PAD_RIGHT); 
$fechaFvct = str_pad( $dat['fechaFvct'],10, " ", STR_PAD_RIGHT); 

    $fechaIirl = str_pad( '',10, " ", STR_PAD_RIGHT); 
  $fechaFirl = str_pad( '',10, " ", STR_PAD_RIGHT);

if ( $dat['ley14'] == 'S' )
   $ibcOtPara = str_pad( "" , 9, "0", STR_PAD_LEFT);
else  
   $ibcOtPara = $ibcCaja;

if ($dat['tipAporte']=='01')
    $horas = str_pad( ( $dat['diasRiesgos'] * 8 )  ,3, "0", STR_PAD_LEFT); 
else   
    $horas = str_pad( ''  ,3, "0", STR_PAD_LEFT); 

                $registro = $registro. $fechaI. $fechaF. $fechaVsp. $fechaIsln. $fechaFsln. $fechaIige. $fechaFige. $fechaIlma. $fechaFlma. $fechaIvac. $fechaFvac. $fechaIvct. $fechaFvct. $fechaIirl. $fechaFirl. $ibcOtPara. $horas. $fecha ;
                  fwrite($archivo, $registro . PHP_EOL);
} // FInal validacion dias mayor a
            } // Final plano de vsp             

            }            
            fclose($archivo);

            //$connection->commit();
            //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
        }// Fin try casth   
        catch (\Exception $e) {
            if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                $connection->rollback();
  //              echo $e;
            }
            /* Other error handling */
        }// FIN TRANSACCION                          

        $sw = 1;
        if ($sw == 0)
        { 
           $file = $ruta;

        }else{
        $valores = array
            (
            "titulo" => $this->tlis,
            "lin" => $this->lin,
        );
        return new ViewModel($valores);

        }

    }
    // Armado cadena  
    public function getCadena($dat = array(), $conse, $ibcSalud, $ibcPension, $ibcRiesgos, $ibcCaja, $diasPension, $diasSalud, $diasCaja) 
    {  
                $registro = $dat['tipo'] . $conse . $dat['tipCed'] . $dat['cedula'] . $dat['tipAporte'] . $dat['blanco2'] . $dat['e00'];
                $registro.= $dat['ciuDepa'] . $dat['apellido1'] . $dat['apellido2'] . $dat['nombre1'] . $dat['nombre2'] . $dat['ingreso'];
                $registro.= $dat['retiro'] . $dat['trasSalud'] . $dat['idFsalTras'] . $dat['trasPension'] . $dat['idFpenTras'] . $dat['e1'] . $dat['nVaca'] . $dat['nVst'] . $dat['nAus'] . $dat['incGeneral'] . $dat['incMaternidad'];

                $registro.= $dat['vaca'] . $dat['blan1'] . $dat['blan2'] . $dat['acct'] . $dat['codFonPension'] . $dat['espaPension'] . $dat['codFonSalud'];
                $registro.= $dat['espaSalud'] . $dat['codCaja'] . $dat['espaCaja'] . $diasPension . $diasSalud . $diasRiesgos . $diasCaja;

                $registro.= $dat['salarioBase'] . $dat['blan4'] . $ibcPension . $ibcSalud . $ibcRiesgos. $ibcCaja . $dat['porPension'];
                $registro.= $dat['aporPension'] . $dat['cerosPension'] . $dat['aporPension'] . $aporSolidaridad . $aporSolidaridad2 . $dat['cerosSolidaridad'] . $dat['porSalud'];
                $registro.= $dat['aporSalud'] . $dat['cerosSalud'] . $dat['espaciosPension'] . $dat['cerosSalud2'] . $dat['espaciosPension2'] . $dat['cerosSalud3'] . $dat['tarifaArl'];
                $registro.= $dat['cerosArl'] . $dat['claseRiesgo'] . $dat['aporRiesgos'] . $dat['porCaja'] . $dat['aporCaja'] . $dat['porSena'] . $dat['aporSena'];
                $registro.= $dat['porIcbf'] . $aporIcbf . $dat['valor1'] . $dat['cerosFinal'] . $dat['valor2'] . $dat['cerosFinal2'] . $dat['cerosFinal3'] . $dat['ley14'];
                // Nuevos campos
                $registro = $registro . $dat['codRiesgo']. $dat['idNriesgo']. $dat['tipoTarifa'];

$fechaI = str_pad( $dat['fechaI'],10, " ", STR_PAD_RIGHT); 
$fechaF = str_pad( $dat['fechaR'],10, " ", STR_PAD_RIGHT); 
$fechaVsp = str_pad( $dat['fechaVsp'],10, " ", STR_PAD_RIGHT); 
$fechaIsln = str_pad( $dat['fechaIsln'],10, " ", STR_PAD_RIGHT); 
$fechaFsln = str_pad( $dat['fechaFsln'],10, " ", STR_PAD_RIGHT); 
$fechaIige = str_pad( $dat['fechaIige'],10, " ", STR_PAD_RIGHT); 
$fechaFige = str_pad( $dat['fechaFige'],10, " ", STR_PAD_RIGHT); 
$fechaIlma = str_pad( $dat['fechaIlma'],10, " ", STR_PAD_RIGHT); 
$fechaFlma = str_pad( $dat['fechaFlma'],10, " ", STR_PAD_RIGHT); 

$fechaIvac = str_pad( $dat['fechaIvac'],10, " ", STR_PAD_RIGHT); 
$fechaFvac = str_pad( $dat['fechaFvac'],10, " ", STR_PAD_RIGHT); 
$fechaIvct = str_pad( $dat['fechaIvct'],10, " ", STR_PAD_RIGHT); 
$fechaFvct = str_pad( $dat['fechaFvct'],10, " ", STR_PAD_RIGHT); 
$fechaIirl = str_pad( $dat['fechaIirl'],10, " ", STR_PAD_RIGHT); 
$fechaFirl = str_pad( $dat['fechaFirl'],10, " ", STR_PAD_RIGHT); 

$ibcOtPara = str_pad( $dat['ibcOtPara'],9, " ", STR_PAD_RIGHT); 

if ( $dat['ley14'] == 'S' )
   $ibcOtPara = str_pad( "" , 9, "0", STR_PAD_LEFT);
else  
   $ibcOtPara = $ibcCaja;

if ($dat['tipAporte']=='01')
    $horas = str_pad( ( $dat['diasRiesgos'] * 8 )  ,3, "0", STR_PAD_LEFT); 
else   
    $horas = str_pad( ''  ,3, "0", STR_PAD_LEFT); 


                $registro = $registro. $fechaI. $fechaF. $fechaVsp. $fechaIsln. $fechaFsln. $fechaIige. $fechaFige. $fechaIlma. $fechaFlma. $fechaIvac. $fechaFvac. $fechaIvct. $fechaFvct. $fechaIirl. $fechaFirl. $ibcOtPara. $horas. $fecha ;                        
                
    }

// Final archivo plano 2

    //----------------------------------------------------------------------------------------------------------
    // CIERRE DE PLANILLA UNICA --------------------------------------------------------------------------------
    //----------------------------------------------------------------------------------------------------------
    public function listcAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $f = new IntegrarFunc($this->dbAdapter);
        // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            //$d->modGeneral("truncate hrmcloud.n_integracion_paso_planilla_d");
            $connection->beginTransaction();
            // $d->modGeneral("update n_planilla_unica set estado=2 where id= ".$id);

            $datos = $f->getIntegrarPlanilla($id); // Consulta de datos 
            
            foreach ($datos as $dat) {
                $valor = 0;
                if ($dat['valor'] > 0)
                    $valor = $dat['valor'];

                if ($dat['valorF'] > 0)
                    $valor = $dat['valorF'];

                if ($valor > 0) {

                    $d->modGeneral("insert into n_integracion_paso_planilla_d  
                  ( idPla, ano, mes , nit, nombre, codCc, codigoCta, debito , fondo, cxCxp ) values(
                  " . $id . "," . $dat['ano'] . "," . $dat['mes'] . ",'" . $dat['nitDeb'] . "','" . $dat['nombre'] .
                            "', '" . $dat['codCcosD'] . "' ," . $dat['cuentaDeb'] . "," . $valor . ", '" . $dat['fondo'] . "', " . $dat['cxCxpD'] . " )");

                    $credito = $dat['valor'];
                    //if ($dat['fondo'] == 'SENA')
                       // echo $dat['cuentaCred'] . ' ' . $dat['cxCxpC'] . '<br />';
                    $d->modGeneral("insert into n_integracion_paso_planilla_d  
                  ( idPla, ano, mes , nit, nombre,codCc, codigoCta, credito , fondo, cxCxp ) values(
                  " . $id . "," . $dat['ano'] . "," . $dat['mes'] . ",'" . $dat['nitCred'] . "', '" . $dat['nombre'] .
                            "', '" . $dat['codCcosC'] . "'," . $dat['cuentaCred'] . "," . $valor . ", '" . $dat['fondo'] . "', " . $dat['cxCxpC'] . " )");
                }
                //              echo $registro.'<br />';
            }

            // INTEGRACION CUENTA POR PAGAR
            $d->modGeneral("delete from n_integracion_paso_planilla_r where idPla = " . $id);
            $f->getIntegrarPlanillaResum($id); // Integracion planilla resumida
            // PASAR A CONTABILIDAD DE CAJAMAG -------------- 
            $erp = 0;
            switch ($erp) {
                case 1:// CAJAMAG
                    // ENVIO DE DATOS NOMINA A BASE DE DATOS DE CONTABILIDAD (2)
                    $res = $d->getGeneral("select a.codcop, cast((lpad( a.ultnum ,7,'0' )) as char(7)) as num , now(),'107','107','A',a.cnt 
                      from empresa.conta11 a where codcop='01' and cnt='02'");
                    // Realziar inseerccion planilla unica         
                    $compro = '01';
                    $numero = $res[0]['num'];
                    $origen = 1;
                    $d->modGeneral("call n_integracion_planilla ('" . $compro . "', '" . $numero . "' )");
// Fin recorrido comprobante                                             

                    $d->modGeneral("update empresa.conta11 set ultnum = ultnum + 1  
                                   where codcop='01' and cnt='02';");


                    // FIN VALIDACION COMPROBANTE
                    // FIN VALIDACION COMPROBANTE                    


                    break;

                default:
                    # code...
                    break;
            } // FIN ENVIO DE DATOS A CONTABILIDAD

            $connection->commit();
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl() . $this->lin);
        }// Fin try casth   
        catch (\Exception $e) {
            if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                $connection->rollback();
                echo $e;
            }
            /* Other error handling */
        }// FIN TRANSACCION                          
    }
   // Fin generar novedades automaticas    

    // NOMINA A EXCEL PROVISIONES 
    public function listexcelprovAction() {
        if ($this->getRequest()->isPost()) { // Actulizar datos
            $request = $this->getRequest();
            $data = $this->request->getPost();
            $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);

            // CONSULTA MAESTRA 
            $datos = $d->getGeneral("select a.idPla as IDPLA, a.codigoCta as CUENTA, 
concat( d.ano , ' - ' ,d.mes ) as PERIODO, b.nombre as DETALLE, round(a.debito,0) as DEBITO , round(a.credito,0) as CREDITO,
a.nit as NIT, a.nombre as TERCERO, a.fondo as FONDO , e.nombre as CENTRO 
from n_integracion_paso_planilla_d a 
   inner join n_plan_cuentas b on b.codigo = a.codigoCta
   left join n_terceros c on c.codigo = a.nit 
   left join n_planilla_unica d on d.id = a.idPla  
   left join n_cencostos e on e.codigo = a.codCc 
where a.idPla = " . $data->id );
            $c = new ExcelFunc();
            //print_r($datos);
            $c->listexcel($datos, "Integración nomina");

            $valores = array("datos" => $datos);
            $view = new ViewModel($valores);
            return $view;
        }
    }            
}
