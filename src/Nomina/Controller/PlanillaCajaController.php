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

class PlanillaController extends AbstractActionController {

    public function indexAction() {
        return new ViewModel();
    }

    private $lin = "/nomina/planilla/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Planillas activas"; // Titulo listado
    private $tfor = "Generación de planilla unica"; // Titulo formulario
    private $ttab = ",id, Fecha, Periodo, Empleados ,Estado, Documento, plano, Int. salud, Int cuentas ,Eliminar, Cerrar "; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************

    public function listAction() {
        $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $valores = array
            (
            "titulo" => $this->tlis,
            "daPer" => $d->getPermisos($this->lin), // Permisos de esta opcion
            "datos" => $d->getGeneral("select a.id,a.fecha,a.ano,a.mes,
                                         b.nombre as nomgrup, a.estado, ( select count(c.id) from n_planilla_unica_e c where c.idPla = a.id ) as numEmp                                          
                                        from n_planilla_unica a 
                                        left join n_grupos b on a.idGrupo=b.id                                         
                                        where a.estado in (0,1,2) order by id desc"),
            "datPla"    =>  $d->getGeneral("select a.idPla, a.codSuc 
                                           from n_planilla_unica_e a 
                                          group by a.idPla, a.codSuc 
                                            order by a.idPla"), // Planillas unicas             
            "ttablas" => $this->ttab,
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
                    }
                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl() . $this->lin . 'g/' . $id);
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
            $salarioMinimo = $dp['formula'];         
            $swRedondeo = 1;
            // COnfiguraciones generales
            $datCf = $d->getConfiguraG(" where id = 1");
            $pagoEmpCre = $datCf['pagoEmp']; // Porcentaje pagado por la

            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $sw = 1;
                //$redondeo = -3;
                $redondeo = 0;

                if ($sw == 1) {
                    $datos = $d->getGeneral("select a.*, c.tipo  
                                           from n_planilla_unica_e a
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_tipemp c on c.id = b.idTemp  
                                               where a.idPla = " . $data->id);
                    $idPla = $data->id;
                    foreach ($datos as $dat) {
                        $id = $dat['id'];
                        $idEmp = $dat['idEmp'];
                        $pensionado = $dat['pensionado'];
                        $retornoVaca = $dat['valorRetVaca'];
                        $idVaca = $dat['idVac'];
                        $sueldoEmp = $dat['sueldo'];
                        $paternidad = 0;
                        $maternidad = 0;
                        $tipo = $dat['tipo']; // Standar 0 , Aprendiz productivo 1 , aprendiz electiva 2
                        // 1 DIAS POR AUSENTISMOS NO REMUNERADOS  
                        $datProv = $f->getAus($idPla, $idEmp);
                        $valor = 0;
                        $diasAus = $datProv['diasAus'];
                        if ($diasAus > 0) {
                            $valor = 1;
                            $campo = 'nAus';
                            $g->getPlanillaE($id, $campo, $valor);

                            $campo = 'diasAus';
                            $valor = $diasAus;
                            $g->getPlanillaE($id, $campo, $valor);
                        }

                        // 2 REGISTRO DE INCAPACIDAD 
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
                            }
                            // Accidente de trabajo
                            if ($tipoInca == 3) {
                                $valor = 1;
                                $campo = 'at';
                                $g->getPlanillaE($id, $campo, $valor);
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
                            }
                            // Maternidad
                            if ($tipoInca == 2) {
                                $campo = 'Pat';
                                $valor = 1;
                                $g->getPlanillaE($id, $campo, $valor);
                                $maternidad = 1;
                            }
                        }

                        // 2 REGISTRO DE INCAPACIDAD PRORROGA
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

                        // 3 REGISTRO DE VACACIONES 
                        $datProv = $f->getVaca($idPla, $idEmp);
                        $diasVac = 0;
                        $idVac = 0;
                        if ($datProv['diasVac'] > 0) {
                            //echo 'EMPLEADO '.$idEmp;
                            $diasVacInc = 0;
                            if ($datProv['diasInc'] > 0) // Dias de icapacidades en las vacaciones 
                                $diasVacInc = $datProv['diasInc'];

                            //$diasVac = $datProv['diasVac']-$diasVacInc;                                        
                            $diasVac = $datProv['diasVac'];
                            // if ( $datProv['idVac']==45 ) // Empanada por causa de los retornos
                            // {
                            //     $diasVac++;
                            //     $idVac = $datProv['idVac'];
                            //}
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
                        }
                        // DIAS -----------------------------------------------------------------
                        // REGISTRO DE INGRESO O SALIDAS
                        $diasContra = 0;
                        $datC = $f->getContratos($idPla, $idEmp);
                        $ingreso = 0;
                        $salida = 0;
                        foreach ($datC as $datCon) {
                            if ($datCon['contra'] == 2) { // Retiro
                                $valor = 1;
                                $campo = 'nRetiro';
                                $g->getPlanillaE($id, $campo, $valor);
                                $diasContra = $diasContra + $datCon['dias'];
                            }
                            if ($datCon['contra'] == 3) { // Inicio de contrato
                                $valor = 1;
                                $campo = 'nIngreso';
                                $g->getPlanillaE($id, $campo, $valor);
                                $diasContra = $diasContra + $datCon['dias'];
                                $ingreso = 1;
                            }
                            if ( ($datCon['contra'] == 0) and ($datCon['finContrato'] == 0) )
                            { // Sumar los otros dias
                                $diasContra = $diasContra + $datCon['dias'];
                            }
                            if ($datCon['finContrato'] == 1) {  // Retiro
                                $valor = 1;
                                $campo = 'nRetiro';
                                $g->getPlanillaE($id, $campo, $valor);
                                $diasContra = $diasContra + $datCon['dias'];
                                $salida = 1;
                            }
                        }
                        // 1. DIAS SALUD
                        $datF = $f->getDiasEmp($idPla, $idEmp);
                        $campo = 'diasSalud';
                        if ($diasContra > 0)
                            $diasLab = $diasContra - $diasAus;
                        else
                            $diasLab = $datF['valor'] - $diasAus;

                        if ($tipo == 2)// Tipo por convenio los dias por riesgos son completos
                            $g->getPlanillaE($id, $campo, 0);
                        else
                            $g->getPlanillaE($id, $campo, $diasLab);


                        // 2. DIAS PENSION                         
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

                        // 3. DIAS RIESGOS
                        $datF = $f->getDiasEmp($idPla, $idEmp);
                        $campo = 'diasRiesgos';

                        $diasRiesgos = 0;
                        // Total dias --------------------
                        $diasVacC = 0; // Para guardar retornos por retornos de vacaciones 

                        $valor = $diasLab - ( $diasInca + $diasVac - $diasVacC );

                        // $valor = 0;
                        $g->getPlanillaE($id, $campo, $valor);
                        $diasRiesgos = $valor;
                        $diasCaja = 0;
                        if ($diasVac>0) // Validar si hay vacaciones 
                            $diasCaja = $diasLab;
                        // ------------------------------------------------------- FIN DIAS
                        // 4. INGRESOS QUE NO AFECTAN SEGURIDAD SOCIAL 
                        $datF = $f->getNoLey($idPla, $idEmp);
                        $campo = 'ingresosNo';
                        $valor = $datF['valor'];
                        $valorNoLey = 0;
                        if ($valor > 0) {
                            $g->getPlanillaE($id, $campo, $valor);
                            $datF = $f->getLey($idPla, $idEmp);
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

                        // 4. IBC SALUD
                        $datF = $f->getLey($idPla, $idEmp);
                        $campo = 'ibcSalud';
                        $valor = round($datF['valor'],0) + $valorNoLey;

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
                        // 5. FONDO DE SALUD
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFs) {
                            $valor = $datFs['idFsal'];
                        }
                        $campo = 'idFonS';
                        $g->getPlanillaE($id, $campo, $valor);
                        // 6. APORTE POR SALUD
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
                        $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                
                        if (($datA['topMax'] == 0) and ( $datA['topMin'] == 0))  // Si no ha superado el tope se redondea si no se envia tal cual 
                            $g->getPlanillaE($id, $campo, $valor); // Se edita el campo 

                        $campo = 'porSalud'; 
                        if ($pagoEmp==1) // Si entra en la ley de la cre no aporta 
                            $valor =  4/100;
                        else                                      // 
                            $valor =  $datProv['por'];                        
                        $g->getPlanillaE($id, $campo, $valor);

                        // 7. IBC PENSION
                        if ($tipo == 0) { // Solo empleados 
                            $datF = $f->getLey($idPla, $idEmp);
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

                        // 8. FONDO DE PENSION
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFp) {
                            $valor = $datFp['idFpen'];
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
                            if (($datA['topMax'] == 0) and ( $datA['topMin'] == 0))  // No supera el tope se envia tal cual  
                                $valor = $e->getRedondear($datA['valor'], 2); //-----  Redondeo                                            
                            $g->getPlanillaE($id, $campo, $valor); // Vuele y se edita 

                            $campo = 'porPension';
                            if ($diasPension == 0)                                   // 
                                $valor = 0;
                            else
                                $valor = $datProv['por'];

                            $g->getPlanillaE($id, $campo, $valor);
                        }
                        // 10. Fondos de solidaridad                   
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
                        // 11. IBC RIESGOS
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

                        $datF = $f->getLeyR($idPla, $idEmp, $diasRiesgos);
                        $campo = 'ibcRiesgos';
                        $valor = $datF['valor'] + $valorNoLey;
                        if (( $diasRiesgos == 0 ) and ( $tipo == 0))// No paga caja
                            $valor = 0;

                        if ($tipo == 3) { // Aprendiz electivo     
                            $diasRiesgos = 0;
                            $valor = 0;
                        }
                        if ($valor > 0) {
                            $valor = $e->getRedondear($valor, 1); //-----  Redondeo                                          
                            $g->getPlanillaE($id, $campo, $valor);
                            $f->getTopeIbc($id, $campo, $valor, $diasRiesgos); // -- Validacion topes IBC Max y Min                                       
                        }
                        // 12. TARIFA ARL 
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
                        // 13. FONDOS RIESGOS ARL
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFr) {
                            $valor = $datFr['idFarp'];
                        }
                        $campo = 'idFonR';
                        $g->getPlanillaE($id, $campo, $valor);

                        // 14. APORTES RIESGOS ARL
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
                            // 1. DIAS RIESGOS
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
                            // 1. DIAS RIESGOS
                            $datF = $f->getDiasEmp($idPla, $idEmp);
                            $campo = 'diasCaja';
                            $g->getPlanillaE($id, $campo, 30 - $diasAus);
                        }

                        //if ( ($diasRiesgos==0) and ($diasInca>0) ) // SI los dias riesgos con cero y se tiene una incapacidad no debe pagar parafiscales
                        //{
                        //$tipo = 99 ; // 
                        //} 
                        if ($tipo == 0) { // SOLO EMPLEADOS QUE NO SON APRENDICES 
                            // 15. IBC CAJA
                            $datF = $f->getCaja($idPla, $idEmp);
                            $campo = 'ibcCaja';
                            $valor = ( $datF['valor'] + $valorNoLey);

                            if (( $diasRiesgos == 0 ) and ( $tipo == 0) and ( $diasVac == 0)) // No paga caja
                                $valor = $diasCaja;

                            if (( $diasRiesgos > 0 ) and ( $tipo == 0)) 
                            {// No paga caja
                                $valor = $datF['valor'];
                                if ($swRedondeo==1)
                                   $valor = $e->getRedondear($valor, 1); //-----  Redondeo
                            }

                            if ($valor > 0) { // Valdiar que IBC de Caja sea amyor a cero 
                                if ( ($diasRiesgos > 0) or ($diasCaja>0) ) { // No paga riesgs 
                                    $g->getPlanillaE($id, $campo, $valor);
                                    if (($retornoVaca == 0) and ( $idVaca == 0))
                                        $f->getTopeIbcCaja($id, $campo, $valor, $diasLab); // -- Validacion topes IBC Max y Min                                    
                                }
                                if ($diasInca > 0) { // Revisar este caso ejemplo melameb
                                    $g->getPlanillaE($id, $campo, $valor);
                                    if (($retornoVaca == 0) and ( $idVaca == 0) )
                                        $f->getTopeIbcCaja($id, $campo, $valor, $diasLab); // -- Validacion topes IBC Max y Min
                                }

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

                                // 18. APORTE POR SENA
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

                                // 19. APORTE POR ICBF
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
                            }// Validacion IBC caja mayor a cero 
                            else {
                                // 16. FONDOS CAJA DE COMPENSACION 
                                $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                                foreach ($datF as $datFc) {
                                    $valor = $datFc['idCaja'];
                                }
                                $campo = 'idCaja';
                                $g->getPlanillaE($id, $campo, $valor);
                                // 1. DIAS RIESGOS
                                $datF = $f->getDiasEmp($idPla, $idEmp);
                                $campo = 'diasCaja';
                                $g->getPlanillaE($id, $campo, 0);
                            }
                        } // FIn validacion aportes parafiscales

                        if ($diasAus == 0) {// Validacion no puede haber variacion si hay ausentismo
                            // 23. VST
                            $datF = $f->getLey($idPla, $idEmp);
                            $campo = 'nVst';
                            $valor = 0;
                            if ($datF['valor'] > $datF['sueldo']) {
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
                        } // Fin validacion   
                        // 23. VST VARIACION PERMANENTE DE SUELDO 
                        $datF = $f->getDsueldos($idPla, $idEmp);
                        $campo = 'nVsp';
                        if ($datF['valor'] > 0)
                            $g->getPlanillaE($id, $campo, 1);
                    } // FIN REGISTRO DE PLANILLA UNICA
                    // -- AUSENTISMOS --------------------------------------------------------------------------
                    // ----------------------------------------------------------------------------
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
                        // 5. FONDO DE SALUD
                        $datF = $d->getEmpMtotales(" and a.id =" . $idEmp);
                        foreach ($datF as $datFc) {
                            $valor = $datFc['idFsal'];
                        }
                        $campo = 'idFonS';
                        $g->getPlanillaE($id, $campo, $valor);
                        // 6. APORTE POR SALUD
                        $datProv = $d->getProviciones(' and id=5 ');
                        $valor = '(8.5/100) * ibcSalud';
                        $campo = 'aporSalud';
                        $g->getPlanillaE($id, $campo, $valor);
                        // Modificar registro con redondeo para los aportes, porque son multiplicados en el query anterior 
                        $datA = $f->getPlanillaE($id, $campo);
                        if ($swRedondeo==1)  
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
                        $valor = $e->getRedondear($valor, 1); //-----                    
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
                        $valor = 0;
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
        $form->get("id")->setAttribute("value", $id);
        $con = '';
        if ($this->getRequest()->isPost()) { // Actulizar datos
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $this->request->getPost();
                $this->dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter');
                $d = new AlbumTable($this->dbAdapter);
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
                if ($data->id2 == 1) { // Armar filtros de vista para revision de planilla
                    $id = $data->id;
                    switch ($data->filtro) {
                        case 0: // General
                            $con = '  ';
                            break;
                        case 1: // Incapacidades
                            $con = ' and ( b.nInca = 1 ) ';
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
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 )   
                          as ibcSaludAnt ,
                   ( select bb.ibcCaja  
                        from n_planilla_unica aa 
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 )   
                          as ibcCajaAnt,
                   ( select bb.ibcRiesgos   
                        from n_planilla_unica aa 
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 )   
                          as ibcRiesgosAnt,
                   ( select bb.nIngreso    
                        from n_planilla_unica aa 
                          inner join n_planilla_unica_e bb on bb.idPla = aa.id where aa.ano = (case when a.mes = 1 then a.ano-1 else a.ano end ) and aa.mes = (case when a.mes = 1 then 12 else (a.mes - 1) end ) and bb.idEmp = b.idEmp and bb.regAus = 0 )   
                          as novIng , ingresosNo, b.mat  
                            from n_planilla_unica a 
                                inner join n_planilla_unica_e b on b.idPla = a.id 
                                inner join a_empleados c on c.id = b.idEmp 
                                inner join n_cencostos d on d.id = c.idCcos
                                inner join t_cargos e on e.id = c.idCar
                                left join n_vacaciones f on f.id = b.idVac 
                                where a.id = " . $id . " " . $con . " 
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
                    "datos" => $d->getLeyD($data->idPla, $data->idEmp),
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
                    "datos" => $d->getLeyD($data->idPla, $data->idEmp),
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
                    "datos" => $d->getCajaD($data->idPla, $data->idEmp),
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
                    "datos" => $d->getLeyRD($data->idPla, $data->idEmp),
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
            foreach ($datos as $dat) 
            {
                $aporSolidaridad = $dat['aporSolidaridad'];
                $aporSolidaridad2 = $dat['aporSolidaridad9'];

                $registro = $dat['tipo'] . $dat['consecutivo'] . $dat['tipCed'] . $dat['cedula'] . $dat['tipAporte'] . $dat['blanco2'] . $dat['e00'];
                $registro.= $dat['ciuDepa'] . $dat['apellido1'] . $dat['apellido2'] . $dat['nombre1'] . $dat['nombre2'] . $dat['ingreso'];
                $registro.= $dat['retiro'] . $dat['trasSalud'] . $dat['idFsalTras'] . $dat['trasPension'] . $dat['idFpenTras'] . $dat['e1'] . $dat['nVaca'] . $dat['nVst'] . $dat['nAus'] . $dat['incGeneral'] . $dat['incMaternidad'];

                $registro.= $dat['vaca'] . $dat['blan1'] . $dat['blan2'] . $dat['acct'] . $dat['codFonPension'] . $dat['espaPension'] . $dat['codFonSalud'];
                $registro.= $dat['espaSalud'] . $dat['codCaja'] . $dat['espaCaja'] . $dat['diasPension'] . $dat['diasSalud'] . $dat['diasRiesgos'] . $dat['diasCaja'];
                $registro.= $dat['salarioBase'] . $dat['blan4'] . $dat['ibcPension'] . $dat['ibcSalud'] . $dat['ibcRiesgos'] . $dat['ibcCaja'] . $dat['porPension'];
                $registro.= $dat['aporPension'] . $dat['cerosPension'] . $dat['aporPension'] . $aporSolidaridad . $aporSolidaridad2 . $dat['cerosSolidaridad'] . $dat['porSalud'];
                $registro.= $dat['aporSalud'] . $dat['cerosSalud'] . $dat['espaciosPension'] . $dat['cerosSalud2'] . $dat['espaciosPension2'] . $dat['cerosSalud3'] . $dat['tarifaArl'];
                $registro.= $dat['cerosArl'] . $dat['claseRiesgo'] . $dat['aporRiesgos'] . $dat['porCaja'] . $dat['aporCaja'] . $dat['porSena'] . $dat['aporSena'];
                $registro.= $dat['porIcbf'] . $dat['aporIcbf'] . $dat['valor1'] . $dat['cerosFinal'] . $dat['valor2'] . $dat['cerosFinal2'] . $dat['cerosFinal3'] . $dat['ley14'];

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
            $d->modGeneral("truncate hrmcloud.n_integracion_paso_planilla_d");
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
                    if ($dat['fondo'] == 'SENA')
                        echo $dat['cuentaCred'] . ' ' . $dat['cxCxpC'] . '<br />';
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
            $erp = 1;
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
}
