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
use Principal\Model\Retefuente; // Retefuente

use Nomina\Model\Entity\Gnomina; // (C)
use Nomina\Model\Entity\Gnominac; // Procesos especiales apra generacion de nomina
use Nomina\Model\Entity\Cesantias; // Cesantias
use Nomina\Model\Entity\Primas; // Primas
use Nomina\Model\Entity\PrimasA; // Prima de antiguedad
use Nomina\Model\Entity\EmbargosN; // Embargos

use Principal\Model\Gnominag; // Procesos generacion de automaticos
use Principal\Model\ExcelFunc; // Funciones de excel 

use Principal\Model\Paranomina; // Parametros especiales de nomina
use Principal\Model\Alertas; // Alertas automaticas

use Principal\Model\LogFunc; // Funciones de logeo y usuarios 

class GnominaretroController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/gnominaretro/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Nominas retro activos"; // Titulo listado
    private $tfor = "Nominas retro activos"; // Titulo formulario
    private $ttab = "Nomina, Periodo, Empleados ,Estado, Pre-nomina, Pre-nomina resumida, Retefuente,Eliminar"; // Titulo de las columnas de la tabla
    
    // LISTAR NOMINAS  ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      $g = new Gnominag($this->dbAdapter);
      $a = new Alertas($this->dbAdapter);
      $form = new Formulario("form");

      $datPer = $d->getPermisos($this->lin); // Permisos de esta opcion
      $con = '';

      if ( $datPer['idGrupNom']>0)
           $con = ' and a.idGrupo='.$datPer['idGrupNom'];
//echo $con;
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "form"      =>  $form,
        "datPer"    =>  $datPer, // Permisos de esta opcion
        "datos"     =>  $g->getListNominas("a.estado in (2) ".$con), // Listado de nominas 
        "datEmp"    =>  $d->getGeneral("select c.CedEmp, c.nombre, c.apellido , d.fechaI, d.fechaF  
                              from n_nomina a 
                                  inner join n_nomina_e b on b.idNom = a.id  
                                  inner join a_empleados c on c.id = b.idEmp 
                                  inner join n_emp_contratos d on d.idEmp = c.id and d.estado = 0 # Traer contrato activo 
                            where a.estado=1 and a.idTnomL > 0 "),
        "datTemp"   => $d->getGeneral("select a.id, lower(d.nombre) as nombre , count( b.idEmp ) as num,
                           ( select count(e.id) from a_empleados e where e.pensionado = 1 and e.id = c.id   ) as pension  
                             from n_nomina a 
                                 inner join n_nomina_e b on b.idNom = a.id
                                 inner join a_empleados c on c.id = b.idEmp  
                                 inner join n_tipemp d on d.id = c.idTemp 
                             where a.estado=1 group by a.id, d.nombre"),
        "datTfon"   => $d->getGeneral("select a.id, case c.idConc
                                           when 11 then 'P'
                                           when 15 then 'S'
                                           when 21 then 'So' end as tipo , count(c.id) as num
                                        from n_nomina a 
                                           inner join n_nomina_e b on b.idNom = a.id
                                           inner join n_nomina_e_d c on c.idInom = b.id
                                        where a.estado = 1 and  c.idConc in (11,15,21) 
                                        group by a.id, c.idConc ;"),        
        "datRet"    => $d->getGeneral1("select count(id) as numRet from n_nomina_retro_i where estado=0"),
        "datAlert"  => $a->getVencimientoContratos(),    
        "datAlertN" => $a->getVencimientoContratosN(),    
        "ttablas"   => $this->ttab,
        'url'       => $this->getRequest()->getBaseUrl(),
        "lin"       => $this->lin,        
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
   // NUEVA NOMINA *********************************************************************************************
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion
      $perGrupo = 0;
      if ( $daPer['idGrupNom']>0)
           $perGrupo = $daPer['idGrupNom'];

      // ------------------------------------------------------------ Grupo de nomina
      $arreglo='';
      if ($perGrupo>0)
         $datos = $d->getGrupoNom(' and id='.$perGrupo); 
      else
         $datos = $d->getGrupo(); 

      foreach ($datos as $dat)
      {  
         $idC=$dat['id'];
         $nom=$dat['nombre'];
         $arreglo[$idC]= $nom;          
      }       
      if ( $arreglo != '' )       
         $form->get("idGrupo")->setValueOptions($arreglo);                         
      // ------------------------------------------------------------ Tipos de calendario
      $arreglo='';
      $datos = $d->getTnom(' and activa=0'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipo'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);                                                 
      // --------------------------------------------------------------- Empleados
      $arreglo='';
      $datos = $d->getEmp(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);                                                 
      //       
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario 
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('tipo'); // ---------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) 
            {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u = new Gnomina($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Consultar fechas del calendario
                $a = new NominaFunc($this->dbAdapter);
                $d = new AlbumTable($this->dbAdapter);
                $c = new Gnominac($this->dbAdapter);
                $g = new Gnominag($this->dbAdapter);

                $datGen = $d->getConfiguraG(''); // CONFIGURACIONES GENERALES
                
                // ------------------------------------------ Ubicar datos del tipo de calendario                
                $datos = $d->getCalendario($data->tipo);                    
                //--
                $dias    = $datos['valor'];
                $idCal   = $datos['idTcal'];
                $tipNom  = $datos['tipo'];
                if ($tipNom == 0)// NOMINAS GENERALES
                {
                   // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);
                
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $g->getCalendarioTipoNomina($data->tipo, $data->idGrupo);           
                   $idIcal = $datos2['id'];
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fechaF'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';                                   
                }
                if ($tipNom==1)// CESANTIAS
                {
                   // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);
                
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $g->getCalendarioTipoNomina($data->tipo, $data->idGrupo);           
                   $idIcal = $datos2['id'];
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fechaF'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                }                
                if ($tipNom==2)// NOMINA DE VACACIONES 
                {
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $d->getGeneral1("select a.id, now() as fechaI , now() as fechaF  
                                      from n_tip_calendario_d a
                                      inner join n_tip_nom b on b.idTcal = a.idCal
                                      where b.id = ".$data->tipo);# Consulta solo para nomina de vacaciones           
                   $idIcal = $datos2['id'];
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fechaF'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                }                                
                if ($tipNom==3)// PRIMAS
                {
                    // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                 // echo $data->tipo.' '.$data->idGrupo.' '.$idCal ;
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);
                
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $g->getCalendarioTipoNomina($data->tipo, $data->idGrupo);           
                   $idIcal = $datos2['id'];
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fechaF'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                }                                
                if ($tipNom == 4)// LIQUIDACION FINAL 
                {
                    $datos2 = $d->getGeneral1("select a.idGrup from a_empleados a where a.id = ".$data->idEmp);# Consulta solo para nomina de vacaciones                               
                    $idGrupo = $datos2['idGrup']; 
                    $fechaF = $data->fechaIni; // fecha de corte de contrato                                   
                    $fechaI = $data->fechaIni; // fecha de corte de contrato                                   
                    $idIcal = 0;
                }
                //print_r($tipNom);
                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
 	                 $connection->beginTransaction();                
                   // Generacion tabla de n_nomina  cabecera
                   $datos2 = $d->getGeneral1("select case when day( '".$fechaI."' ) > 15 then 
                                        concat( year('".$fechaI."') ,'-', lpad(month('".$fechaI."'),2,'0' ) , '-16'  )
                                    else
                                        concat( year('".$fechaI."') ,'-', lpad(month('".$fechaI."'),2,'0' ) , '-01'  ) end as fechaI ") ;
                   $fechaI = $datos2['fechaI'];
                   $id = $u->actRegistro($data,$fechaI,$fechaF,$idCal,$idIcal,$dias,$idGrupo);

                   // Consulta el ultimo periodo liquidado del grupo 
                   $datos2 = $g->getUltimaNomina($data->tipo, $idGrupo); 
                   //$idIcal = 0;
                   $idTnomL = $datos2['idTnom']; // Nomina asociada a la nomina de liquidacion 
                   $idTcalL = $datos2['idTcal']; // Nomina asociada a la nomina de liquidacion                                       

                   $idTnomP = $datos2['idTnomP']; // Prima pendiente
                   $idTnomC = $datos2['idTnomC']; // Cesantias pendiente                   

                   $fechaI = $datos2['fechaI']; // fecha de inicio de nomna para novedades sin liquidar                   
                   // Consulta fecha de inicio de primas del grupo 
                   if ($idTnomP>0)
                   {
                     $datos2 = $g->getCalendarioTipoNomina($idTnomP, $idGrupo);           
                     $fechaIprima = $datos2['fechaI'];
                     // Consulta fecha de inicio de cesantias del grupo 
                     $datos2 = $g->getCalendarioTipoNomina($idTnomC, $idGrupo);                              
                     $fechaIcesantias = $datos2['fechaI'];
                   } 
                   $idEmp = '';                   
                                       
                    if ($tipNom == 4)// LIQUIDACION FINAL 
                    {
                       $d->modGeneral( "update n_nomina 
                           set idTnomL=idTnom, idTnom=".$idTnomL.", idCal = ".$idTcalL.",
                           fechaIp='".$fechaIprima."',
                           fechaIc='".$fechaIcesantias."'    
                                    where id=".$id );
                    }
                    // --------- Inactiva grupo de nomina 
                    $d->modGeneral( 'update n_grupos set activa=1 where id='.$idGrupo );
                    // --------- Buscar id de grupo
                    $datos = $d->getGeneral1("Select idGrupo from n_nomina where id=".$id); 
                    $idg=$datos['idGrupo'];
                    //--------------------------------------*************************-------///
                    //---- GENERAR EMPLEADOS N_NOMINA_E-******-----------------------          
                    //--------------------------------------*************************-------///                       
                    if ( ($tipNom==0) or ($tipNom==1)  or ($tipNom==3) )// Nomina normal y cesantias y primas
                    {
                       $g->getNominaE($id, $idg, $idEmp, $fechaF, $tipNom);  // ***--------------- GENERACION DE EMPLEADOS                       
                    }
                    // ***--------------- FIN GENERACION DE EMPLEADOS----------------------------
                    //echo 'tip'.$tipNom;
                    if ($tipNom==2) // GENERACION EMPLEADOS NOMIA DE VACACIONES 
                    {
                       $datEvac = $d->getGeneral("select idEmp from n_vacaciones where estado=1"); 
                     //  print_r($datEvac);
                          foreach ($datEvac as $datVev)
                          {
                              $idEmp = $datVev['idEmp'];  
                              if ($idEmp>0)
                              {
                                  // Consultar ultimo periodo nomina pagada para comparar 
                                  // ultimo pago del empleado 
                                  $dat = $d->getGeneral1("select DATE_ADD( b.fechaF , interval 1 day) as fechaI      
                                             from n_nomina_e a
                                                inner join n_nomina b on b.id = a.idNom  
                                                 where a.idEmp = ".$idEmp." and b.estado=2
                                                   order by b.fechaF desc limit 1");                              
                                  $g->getNominaE($id, 0, $idEmp, $dat['fechaI'], $tipNom  );  // ***--------------- GENERACION DE EMPLEADOS
                              }
                          }
                    }
                    // ***--------------- FIN GENERACION DE EMPLEADOS----------------------------
                    if ($tipNom==4) // Liquidacion definitiva
                    {
                       $i = 0; 
                       if ($data->idEmp!='') // Recorrido de empleados a liquidar
                       {
                           $idEmp = $data->idEmp;  
                           $i++; 
                           if ($idEmp>0)
                           {
                               // Consultar ultimo periodo nomina pagada para comparar 
                                // ultimo pago del empleado 
                                     $dat = $d->getGeneral1("select DATE_ADD( b.fechaF , interval 1 day) as fechaI , dias=0      
                                             from n_nomina_e a 
                                                inner join n_nomina b on b.id = a.idNom  
                                                 where a.idEmp = ".$idEmp." and b.estado=2
                                                   order by b.fechaF desc limit 1");                              
                                     $g->getNominaE($id, 0, $idEmp, $dat['fechaI'], $tipNom  );  // ***--------------- GENERACION DE EMPLEADOS
                             }
                       }// Fin recorrido de empleados a liquidar                        
                    }
                if ( ($tipNom==2)  ) // SOLO PARA NOMINA DE VACACINOES -------------------------
                {
                    // 1. INCAPACIDADES DE EMPLEADOS
                    $g->getIncapaEmp($id, "n_incapacidades"); // Incapacidades
                    $g->getIncapaEmp($id, "n_incapacidades_pro"); // Prorrogra en incapacidades
                    // 2. VALIDAR INCAPACIDADES -----------------------------------
                    $datInc=$g->getIncapacidades($id); // Extraer datos de la incapacidad del empleado si tuviera
                    foreach($datInc as $dat)
                    {
                        $iddn = $dat['id'];          
                       
                        $dias    = $dat['dias'];
                        $diasEnt = $dat['diasEnt'];           
                        $diasAp  = $dat['diasAp'];
                        $diasDp  = $dat['diasDp'];            
                        //echo ' g '.$datGen['incAtrasada'].'<br />';
                        // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
                        if ( $datGen['incAtrasada'] == 0)
                           $diasAp = 0;
                        if ( $dat['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                           $diasAp = 0;          

                        $diasI = $diasAp + $diasDp ;
                        $dias = $dias - $diasI;                                                    
                        // Empanada para cubrir retornos de vacaciones e incapaciades de mas o son 15 dias exactos de incapacidad
                        if ( ( $diasAp + $diasDp ) > $dat['diasCal'] )
                        {
                            $diasI = $dat['diasCal']; 
                            $dias = 0; 
                        }              
                        //if ($dat['total']==1) // Cubre el periodo todo el dia 
                        //    $dias = 0;                        

                        $d->modGeneral("update n_nomina_e set dias=".$dias.", 
                                     diasI=".$diasI." where id=".$iddn);
                        # Se marca idInc con una 1 para saber que ese empleado tiene incapacidad registrada 
                                                    
                    } // Fin validacion incapacidad                    
                }


                if ( ($tipNom==0)  ) // SOLO PARA NOMINAS GENERALES -------------------------
                {
                    // VALIDAR AUMENTO DE SUELDO EN EL PERIODO 
                    $g->getAumentoEmpleado($id);
                    // VALIDAR FECHA DE INGRESO DEL EMPLEADO  (EN DES USO )                  
                    $datIng = $g->getIngresoEmpleado($id);        
                    foreach($datIng as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = $dat['diasH'] ;                                
                       // $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1 where id=".$iddn);                         
                    } // Fin validacion fecha de ingreso del empleado
                    // VALIDAR FECHA DE CONTRATO EMPLEADOS
                    $datIng = $g->getContratosActivos($id);        
                    foreach($datIng as $dat)
                    {
                        $iddn = $dat['id'];
                        //echo $dat['diasFinC'].'<br />';
                        if ( $dat['diasFinC'] > 0 ) // Tiene un reinicio de contrato
                           $dias = $dat['diasH'] + $dat['diasFinC'];                                                      
                        else    
                           $dias = $dat['diasH'] + $dat['diasA'];                                                      

                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1, contra=".$dat['contra'].", idCon=".$dat['idCon']." where id=".$iddn); 
                    } // Fin validacion fecha de egreo del empleado
                    
                    // 1. INCAPACIDADES DE EMPLEADOS
                    $g->getIncapaEmp($id, "n_incapacidades"); // Incapacidades
                    $g->getIncapaEmp($id, "n_incapacidades_pro"); // Prorrogra en incapacidades

                    // VALIDAR DIAS LABORADOS EN PROYECTO -- PROYECTOS
                    $g->getProyectosEmpleado($id);                    

                    // VALIDAR SI ESTA EN VACACIONES --------------------------------------------
                    $datNome = $d->getNomEmp(" where idVac>0 and idNom=".$id);
                    foreach($datNome as $dat)
                    {
                        $iddn = $dat['id'];
                        $idEmp = $dat['idEmp']; 
                        $dias = $dat['dias']; 
                        $salVac = 0; // 
                        
                        $datVac=$g->getVacaciones($iddn); // Extraer datos de la vacacion del empleado si tuviera
                        $diasVac = $datVac['diasCal'];
                        $idCcos  = $datVac['idCcos'];
                        if ( ($datVac['diasMes']==31) and ( $datVac['diasPeriodoSiguiente']>0) )
                           $diasPerSig = $datVac['diasPeriodoSiguiente']-1;                        
                        else 
                           $diasPerSig = $datVac['diasPeriodoSiguiente'];                                                
                        if(!empty($datVac))
                        {
                           if ( $datVac['estado']==1)// No ha iniciado vacaciones 
                           {
                              if ( $datVac['periI']>0 )   
                                 $dias = $datVac['periI'] ;// Dias a pagar 

                           }else{// Esta en vacaciones se modifican los dias 
                               if ( ($datVac['periI']==0) or ($datVac['periF']==0) ) // Esta en vacaciones 
                               {
                                   $dias = 0;// Dias a pagar 
                               }                             
                               if ( $datVac['periF']>0 ) // Si el periodo indica final de vacaciones se pagan esos dias
                               {  
                                   $dias = $datVac['periF'] ;// Dias a pagar   
                                   $salVac = 1;
							                 }                                                               
                               $diasVac = 0; // Ya no se muestran mas los dias de vacacines    
                           }
                           if ($salVac>0)		
 						               {				   
                              $d->modGeneral("update n_nomina_e set dias = ".$dias." , diasVac=0, actVac=0  where id=".$iddn);
							                $d->modGeneral("update a_empleados set vacAct = 2  where id=".$idEmp); // Regreso de vacaciones
						               }
						               else {
							                $d->modGeneral("update n_nomina_e set dias = (".$dias."+".$diasPerSig.") , diasVac=".$diasVac."  where id=".$iddn); 
						               }  							
                        }       
                      } // FIN DE VACACIONES                    

                    // 2. VALIDAR INCAPACIDADES -----------------------------------
                    $datInc=$g->getIncapacidades($id); // Extraer datos de la incapacidad del empleado si tuviera
                    foreach($datInc as $dat)
                    {
                        $iddn = $dat['id'];					 
						           
                        $dias    = $dat['dias'];
                        $diasEnt = $dat['diasEnt'];						
                        $diasAp  = $dat['diasAp'];
                        $diasDp  = $dat['diasDp'];						
                        //echo ' g '.$datGen['incAtrasada'].'<br />';
                        // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
                        if ( $datGen['incAtrasada'] == 0)
                           $diasAp = 0;
			                  if ( $dat['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                           $diasAp = 0;          

                        $diasI = $diasAp + $diasDp ;
                        $dias = $dias - $diasI;                                                    
                        // Empanada para cubrir retornos de vacaciones e incapaciades de mas o son 15 dias exactos de incapacidad
                        if ( ( $diasAp + $diasDp ) > $dat['diasCal'] )
                        {
                            $diasI = $dat['diasCal']; 
                            $dias = 0; 
                        }  			       
                        //if ($dat['total']==1) // Cubre el periodo todo el dia 
                        //    $dias = 0;                        

                        $d->modGeneral("update n_nomina_e set dias=".$dias.", 
                                     diasI=".$diasI." where id=".$iddn);
						            # Se marca idInc con una 1 para saber que ese empleado tiene incapacidad registrada 
                                                    
                    } // Fin validacion incapacidad                    
                    
                    // VALIDAR AUSENTISMOS -----------------------------------
                    $g->getAusentismosEmp($id); // Generacion de ausentismos
                    // Ausentismos reportados
                    $datAus = $g->getAusentismos($id); // Extraer datos del ausentismos del empleado si tuviera no remunerado
                    foreach($datAus as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = ($dat['dias']+$dat['diasAp']);# Dias de ausentismos no remunerado

                        //else 
                        //   $dias = $dat['dias'];# Dias de ausentismos no remunerado                             
                        if ($dias>0)
                        {
                           $aus = 1;                    
                           if ($dat['tipo']==1)// Licencia remunerada
                           {
                              $d->modGeneral("update n_nomina_e set dias = dias - ".$dias." where id=".$iddn);
                           }
                           if ($dat['tipo']==2)// Licencia no remunerada
                           {                           
                              if ($datGen['asuDesc']==1) // Configuracion general para descontar los dias de lo laborado y dejarlo en el deducido de la nomina
                              {
                                 $d->modGeneral("update n_nomina_e set dias = dias - ".$dias." where id=".$iddn);
                              }
                           }                           
                        }                                    
                    } // Fin validacion ausentismos
                    
                    // Ausentismos reportados tardes
                    $datAus = $g->getAusentismosNoreport($id); // Extraer datos del ausentismos del empleado si tuviera no remunerado
                    foreach($datAus as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = $dat['dias'];# Dias de ausentismos no remunerado reportado tarde
                        $d->modGeneral("update n_nomina_e set dias = dias-".$dias." where id=".$iddn);
                    } // Fin validacion ausentismos reportados tarde

                    // VALIDAR SI TIENE DIAS DIFERENTES EN UNA NOMINA YA LIQUIDAD                
                    $datIng = $d->getGeneral("Select diasLab, idEmp  
                      from n_nomina_nov 
                      where idConc = 0 and idIcal=".$idIcal." and idCal = ".$idCal." and idGrupo=".$idGrupo." and estado=0");        
                    foreach($datIng as $dat)
                    {
                        $idEmp = $dat['idEmp'] ;                   
                        $dias  = $dat['diasLab'] ;                                
                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1 where idEmp=".$idEmp." and idNom=".$id );
                    } // Fin validacion fecha de ingreso del empleado
                } // fin validacion sea solo nomina general

                    $connection->commit();
                    
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);                    
                    
                }// Fin try casth   
                catch (\Exception $e) 
                {
    	            if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
     	                $connection->rollback();
                        echo $e;
 	                }	
 	                 /* Other error handling */
                }// FIN TRANSACCION                                    
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Nomina($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $n = $datos['nombre'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$n"); 
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 

   //----------------------------------------------------------------------------------------------------------
   // GENERACION NOMINA --------------------------------------------------------------------------------------
   //----------------------------------------------------------------------------------------------------------
   
   // GENERACION NOMINA GENERAL-------------------------------------
   public function listpAction()
   {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  
         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d = new AlbumTable($this->dbAdapter);                 
         $n = new NominaFunc($this->dbAdapter);
         $g = new Gnominag($this->dbAdapter);
         $c = new PrimasA($this->dbAdapter);
    
         $datGen = $d->getConfiguraG(''); // CONFIGURACIONES GENERALES             
         // Buscar id de grupo
         $datos  = $d->getPerNomina($id); // Periodo de nomina
         $idg    = $datos['idGrupo'];         
         $idTnomL = $datos['idTnomL']; // Nomina de liquidacion 
         $fechaI = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];      
         $idIcal = $datos['idIcal'];         
         $mesNf = $datos['mesF'];         
         $anoNomina = $datos['ano'];         
         $mesNomina = $datos['mes'];                           
         $periodoNomina = $datos['periodo'];    
         $fechaIprimasCal = $datos['fechaIp'];          
         $sw=1; // Solo para probar mas rapido ojo                                
                  
     // INICIO DE TRANSACCIONES
     $connection = null;
     try {
         $connection = $this->dbAdapter->getDriver()->getConnection();
	      	$connection->beginTransaction();
         
        if ($sw==1) 
        {
         // PAGO DE RETROACTIVOS
         $dat = $d->getConsRetro() ;
         if ( $dat['num']>0)
         {
            $datos = $g->getRetroActivos($id);
         }// FIN PAGO DE RETROACTIVOS

         // PAGO DE RETROACTIVOS POR AUMENTO DE SUELDO INDIVIDUAL 
         $dat = $d->getConsRetroI() ;
         if ( $dat['num']>0)
         {
            $datos = $g->getRetroActivosI($id);
         }// FIN PAGO DE RETROACTIVOS

         // LIQUIDACION FINAL DE CONTRATO ------------------------------- ( 111 )
         if ($idTnomL>0) 
         {
            // LIQUIDACION FINAL PRIMAS
            $p = new Primas($this->dbAdapter);
            $mesIprimasCal = $datos['mesIp'];   
            $fechaIcesantias = $datos['fechaIc'];                                   
            $mesIcesantias = $datos['mesIc'];                                   

            // LIQUIDACION FINAL CESANTIAS
            $c = new Cesantias($this->dbAdapter);          
            
            $datos = $d->getGeneral1("select a.id , a.idCal , a.fechaI, a.fechaF,
                                  month( a.fechaI ) as mesI, month( '".$fechaF."' ) as mesC,
                                  '".$fechaF."' as fechaC, # fecha de corte para anticipo de cesantias  
                                  datediff( '".$fechaF."', a.fechaI  ) +1 as dias, 
                                    '".$fechaF."' as fechaCorte  
                                     from n_tip_calendario_d a 
                                  where a.idCal = 5 and a.estado=0 
                                    order by a.fechaI limit 1") ;
            $diasCesantias = $datos['dias'];
            $idIcal = $datos['id'];
            //$fechaI = $datos['fechaI'];
            $mesI   = $datos['mesI'];   
            $fechaF = $datos['fechaC'];                                   
            $mesF   = $datos['mesC'];                                   
            $fechaCorte = $datos['fechaCorte'];
            $datDcal = $n->getDiasCalen( $mesI, $mesF ); // Funcion apra deolver dias para descontar entr rango de fecha pra dias habiles                
            if ( ($datDcal['diasS']!=0) or ($datDcal['diasR']!=0) )
            {
                $diasCesantias = $diasCesantias - $datDcal['diasR'];   
                $diasCesantias = $diasCesantias + $datDcal['diasS'];                   
            }             
            $datos = $g->getDiasCesa( $idg , $id , $fechaIcesantias ); 

            //print_r($datos);
            foreach ($datos as $datoC)
            {              
                $idEmp = $datoC['idEmp'];
                // Verificar fecha del aumento de sueldo del empleados
                $datFec = $d->getAsalariaF($idEmp, $fechaF); 
                $tipC = 0;
                if ( ( $datFec['meses']>3 ) or ( $datFec['numAum'] == 0 ) ) // Si el ultimo aumento es mayor a 3 meses o no ha habido ningun aumenro no se incluye ne calculo del promedio 
                {
                   // Si el ultimo aumento es mayor a 3 meses o no ha habido ningun aumenro no se incluye ne calculo del promedio 
                   echo 'Dias '.$diasCesantias.'-'.$fechaIcesantias.'- '.$fechaF.'<br />';
                   $datos2 = $g->getCesantias($idEmp, $fechaIcesantias , $fechaF, $diasCesantias);                  
                  // print_r($datos2);
                   $tipC = 1;
                }else{ // Sino se llama la funcion para tenerlo en cuenta en el promedio esto aplica a salarios variables
                   //$datos2 = $g->getCesantiasS($idEmp, $fechaIcesantias, $fechaF, $diasCesantias);  
                   $tipC = 2;
                }   
                // Calcular las cesantias
                foreach ($datos2 as $dato)
                {  
                   $base = round( $dato["baseCesantias"], 2); // Buscar subdisio de transporte
                   //$base = $base + $datoC['subTransporte']; // Base mas subsidio de transporte 
                   echo '----------------------- Cesantias <br />';                                               
                   echo 'base '.$base.'<br /> ';
                   echo 'dias cesantias '.$diasCesantias.'<br /> ';                                                                                  
                   echo '= '.( round(  ($base / 360) * $diasCesantias , 2 ) ).'<br /><hr /> ';                                                                                                     
                   $valor = round(  ($base / 360) * $diasCesantias , 2 );

                   $id      = $datoC['idNom'];  // Id dcumento de novedad 
                   $iddn    = $datoC['id'];  // Id dcumento de novedad
                   $idin    = 0;     // Id novedad
                   $ide     = $idEmp;   // Id empleado
                   //$diasLab = $datoC['diasCes'];    // Dias laborados 
                   $diasLab = $diasCesantias;    // Dias laborados                    
                   $horas   = 0;   // Horas laborados 
                   $diasVac = 0;    // Dias vacaciones
                   $formula = ''; // Formula
                   $tipo    = $datoC["tipo"];    // Devengado o Deducido  
                   $idCcos  = $datoC["idCcos"];  // Centro de costo   
                   $idCon   = 213;   // Concepto
                   //$idCon   = $datoC["idCon"];   // Concepto
                   $dev     = $valor;   // Devengado
                   $ded     = 0;     // Deducido         
                   $idfor   = '';   // Id de la formula    
                   $diasLabC= 0;   // Dias laborados solo para calculados 
                   $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                   $obId    = 1; // 1 para obtener el id insertado
                   $fechaEje  = 0;
                   $idProy  = 0;
                   //echo $dev.'<br />';
                   // Llamado de funion -------------------------------------------------------------------
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId, $fechaEje, $idProy );              
                   $idInom = (int) $idInom;                   
                   // INTERESE DE CENSATIAS 
                   $dev     = ( ( $valor * ( 12/100 ) )/360 ) * $diasCesantias; // Devengado
                   $idCon   = 195; //
                   $obId    = 0; // 1 para obtener el id insertado
                   echo '----------- Ineteres de cesantias <br />';                                               
                   echo 'Valor '.$diasCesantias.'<br /> ';                                                                                  
                   echo '= '.number_format($dev).'<br /><hr /> ';                                                                                                                        
                   if ($valor > 0)
                   {
                       // Llamado de funion -------------------------------------------------------------------
                       $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId, $fechaEje, $idProy);                             
                       // REGISTRO LIBRO DE CESANTIAS                   
                      // $c->actRegistro($ide, 213, 195, $fechaI, $fechaF, $diasLab, 0, $base, $valor, $dev , $idInom , $id);
                   }
                } 
              } // FIN CESANTIAS

            // Buscar dias reales para caculo de vacaicones
            $datVacD = $d->getGeneral1("Select month( c.FechaI ) as mesIvac  
                      , ( DATEDIFF( '".$fechaF."' , d.FechaI ) ) as diasTrabajadosPerVaca   
                                  from n_nomina_e a 
                                  inner join n_nomina aa on aa.id = a.idNom 
                                  inner join a_empleados b on b.id = a.idEmp 
                                  inner join n_emp_contratos c on c.idEmp = b.id  
                                  inner join n_libvacaciones d on d.idEmp = b.id 
                                  where year(d.fechaF)<= year(aa.fechaI) and d.estado=0 # consulta de periodos de vacaiones pendientes 
                        and a.idNom = ".$id." order by d.fechaI limit 1"); 


              $datDcal = $n->getDiasCalen( $datVacD['mesIvac'] , $mesNf ); // Funcion apra deolver dias para descontar entr rango de fecha pra dias habiles

              $diasVaca = $datVacD['diasTrabajadosPerVaca'];

              if ( ($datDcal['diasS']!=0) or ($datDcal['diasR']!=0) )
              {
                    $diasVaca = $diasVaca - $datDcal['diasR'];   
                    $diasVaca = $diasVaca + $datDcal['diasS'];               
              }              
              //$diasVaca = 930; 
              echo 'DIAS VACACIONES: '.$diasVaca.'<br />';
            // Calculo para las vacaciones 
            $datos = $g->getVacasFinal( $fechaF, $id);
           // print_r($datos);
            foreach ($datos as $dato)
            {      
              $iddn    = $dato['id'];  // Id dcumento de novedad
              $idin    = 0;     // Id novedad
              $ide     = $dato['idEmp'];   // Id empleado
              $diasLab = 0;    // Dias laborados 
              $horas   = 0;
              $diasVac = 0;    // Dias vacaciones
              $formula = ''; // Formula
              $tipo    = 1;    // Devengado o Deducido  
              $idCcos  = $dato["idCcos"];  // Centro de costo   
              $idCon   = 133;   // Concepto
              $datVc   = $g->getVacasPromFinal($iddn); // Valor de prima a pagar
              $diasVaca = ( ( $diasVaca * 15 ) / 360);  
              echo '----------------------- Vacaciones <br />';                            
              echo 'Dias vaca '.round( $diasVaca ,2).'<br />Valor base promedio vaca '.round($datVc["vlrBasePromedioVaca"],2).'= '.round($datVc["vlrBasePromedioVaca"],2)*round( $diasVaca ,2).'<br /><hr />';
              $dev     = round($datVc["vlrBasePromedioVaca"],2) *round( $diasVaca ,2) ; // Devengado  Dias trabajados en el semestre
              $ded     = 0;     // Deducido         
              $idfor   = -99;   // Id de la formula    
              $diasLabC= 0;   // Dias laborados solo para calculados 
              $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
              $obId    = 1; // 1 para obtener el id insertado
              $fechaEje  = '';
              $idProy  = 0;
              //echo 'val '.$datVc["vlrBasePromedioVaca"];
              // Llamado de funion -------------------------------------------------------------------
              $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);               
              $idInom = (int) $idInom;                   
              // LIBRO DE VACACIONES
              if ($dev > 0)
              {
                  // REGISTRO LIBRO DE PRIMAS
                  //$c->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id);
              }                                          
            }// Vacaciones 

            // Calculo para las primas por los empleados del grupo
            echo '----------------------- Primas <br />';                                        
            echo 'fecha inicial:'.$fechaIprimasCal;
            echo ' fecha final:'.$fechaF.'<br />';

            $fechaFprimasCal = $fechaF; // Dias finCalendario 

            $mesI = $mesIprimasCal;
            echo 'Mes primas :'.$mesI.'<br />';

            $datos = $g->getPrimasFinal( $fechaIprimasCal, $fechaFprimasCal, $id);
            foreach ($datos as $dato)
            {      
              $iddn    = $dato['id'];  // Id dcumento de novedad
              $idin    = 0;     // Id novedad
              $ide     = $dato['idEmp'];   // Id empleado
              $diasLab = 0;    // Dias laborados 
              $horas   = 0;   // Horas laborados 
              $diasVac = 0;    // Dias vacaciones
              $formula = ''; // Formula
              $tipo    = 1;    // Devengado o Deducido  
              $idCcos  = $dato["idCcos"];  // Centro de costo   
              $idCon   = $datGen['idPrima'];   // Concepto
              $diasPrima = $dato['diasPrima'];
              $datDcal = $n->getDiasCalen( $dato['mesI'] , $mesF ); // Funcion apra deolver dias para descontar entr rango de fecha pra dias habiles
              if ( ($datDcal['diasS']!=0) or ($datDcal['diasR']!=0) )
              {
                 $diasPrima = $diasPrima - $datDcal['diasR'];   
                 $diasPrima = $diasPrima + $datDcal['diasS'];   
              }
              // Buscar ausentismos no remunerado
              $datAus = $d->getAusentismosDias($ide, $fechaIprimasCal, $fechaFprimasCal);
              if ($datAus['dias']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
                  $diasPrima = $diasPrima - $datAus['dias'];  

              $datPr   = $g->getDiasPrima($ide, $fechaIprimasCal ,$fechaF,$diasPrima,$id); // Valor de prima a pagar

                 echo '----------------------- primas <br />';                            
                 echo 'Dias primas '.$diasPrima.'<br />';              
                 echo 'Promedio '.$datPr['promedioMes'].'';
                 echo '= '.$datPr["promedioMes"] * $diasPrima.'<br /><hr />';

              $dev     = $datPr["promedioMes"] * $diasPrima ;     // Devengado  Dias trabajados en el semestre              
              $ded     = 0;     // Deducido         
              $idfor   = -99;   // Id de la formula    
              $diasLabC= 0;   // Dias laborados solo para calculados 
              $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
              $obId    = 1; // 1 para obtener el id insertado
              $fechaEje  = '';
              $idProy  = 0;
              // Llamado de funion -------------------------------------------------------------------
              $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);               
              $idInom = (int) $idInom;                   
              // LIBRO DE PRIMAS
              // LIBRO DE PRIMAS
              if ($dev > 0)
              {
                  // REGISTRO LIBRO DE PRIMAS
                  $p->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id);
                  $subTrans = $datPr["subTransporte"];

                  $d->modGeneral("update n_nomina_e set subTransporte=".$subTrans.",
                                    diasPrimas=".$diasPrima.",dias=0, 
                                    promPrimas=".$datPr["promedioMes"]." where id =".$iddn);
                  // Se modifican los dias del empleado a 0 , porque solo aplica a la nomina de primas 
              }
            }


         }// FIN LIQUIDACION DE CONTRATO ---------------- ( 111 )

         // ( REGISTRO DE NOVEDADES EN PROYECTOS ) ( n_proyectos )  
         $datos2 = $g->getRproyectos($id,$fechaI,$fechaF);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

             $d->modGeneral("update n_nomina_e_d set idProy=".$idProy.", fechaEje ='".$dato['fechaEje']."' where id=".$idInom);
         } // FIN REGISTRO DE NOVEDADES EN PROYECTOS

         // REEMPLAZOS DE EMPLEADOS
         $datos2 = $g->getReemplazos($id,$fechaI,$fechaF);// Reemplazos 
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             if ($dato['reportada']==0)
                $diasLab = $dato['dias']+$dato['diasAnt'];
             else 
                $diasLab = $dato['dias'];

             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["vlrHora"] * $diasLab ;     // Devengado
//             $dev     = $dato["vlrHora"] * 15 ;     // Parametrizar esto 
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = 0;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy = 0;
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy );              
             $idInom = (int) $idInom;                   

             $d->modGeneral("update n_nomina_e_d set detalle ='DIFERENCIA EN SUELDO (".$dato['fechaI']." - ".$dato['fechaF'].")', 
                                       idRem=".$dato['idRem']." where id=".$idInom);             
         } // FIN REGISTRO DE NOVEDADES                     

         // ( REGISTRO DE NOVEDADES ) ( n_novedades ) 
         $datos2 = $g->getRnovedades($id,$idIcal);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             $saldoPact = 0;
             if ( $dato["valCuota"] > 0 )
             {                
                if ($dev>0)
                {
                   $saldoPact = $dev-$dato["pagado"];                  
                   $dev = $dato["valCuota"]; 
                }
                else
                {
                   $saldoPact = $ded-$dato["pagado"];                  
                   $ded = $dato["valCuota"]; 
                }                  
             }
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

             // GUARDAR REGISTRO 
             $d->modGeneral("update n_nomina_e_d 
                set idProy=".$idProy.", saldoPact=".$saldoPact.",
                 fechaEje ='".$dato['fechaEje']."' , idInov=".$dato['idInov']." where id=".$idInom);
             
             // Validar auxilio añoa nterior, empanada para cajamag
             if ($fechaEje>'0000-00-00')
             {
               if ( ( $idCon == 137 ) and ($fechaEje<'2015-01-01') )
               {
                  $formula = ( 1154540 * (25/100) );
                  //echo $formula;
                  $d->modGeneral("update n_nomina_e_d 
                   set devengado=".$formula." where id=".$idInom);                
                }
             }             
         } // FIN REGISTRO DE NOVEDADES                     


         // ( REGISTRO DE RETROACTIVOS NOMINA) 
         $datos2 = $g->getRetroActivosNom($id);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             if ($dato["idConcRetro"]>0)
                $idCon   = $dato["idConcRetro"];   // Concepto de retroactivo 
             else
                $idCon   = $dato["idCon"];   // Concepto

             $dev=0;
             $ded=0;
             if ($tipo==1)
                $dev     = $dato["valor"];     // Devengado
             else  
                $ded     = $dato["valor"];     // Deducido

             $idfor   = -99;   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = 1;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = '';
             $idProy = 0;
             // BUSCAR ALGUN OTRO INCREMENTO ESPECIAL 
             if ( $dato["aumentoConv"]>0 )
             {
                $dev = $dev + ( $dev * ($dato["aumentoConv"]/100) );
                $ded = $ded + ( $ded * ($dato["aumentoConv"]/100) );                
             }

             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

             $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                          
             if ($dato["vlrTrans"]>0) // Descuento de subsidio de transporte
             {
                $idCon   = 114;   // Concepto descuento de subsidio de transporte
                $tipo    = 2;
                $dev     = 0;     // Devengado
                $ded     = $dato["vlrTrans"];     // Deducido              
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                 $idInom = (int) $idInom;                   

                $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                                          
             }
             // Especial Cajamag descuento del 30 %
             if ($dato["sueldoAnt"]>0)
             {
                $idCon   = 59;   // Concepto descuento de subsidio de transporte
                $tipo    = 2;
                $dev     = 0;     // Devengado
                $ded = ($dato["sueldoAct"] - $dato["sueldoAnt"]) * (30/100);
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                 $idInom = (int) $idInom;                   

                $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                                                           
             }             
             //              
         } // FIN REGISTRO DE RETROACTIVOS
         

         // ( REGISTRO DE RETROACTIVOS NOMINA POR AUMENTO DE SUELDO INDIVIDUAL) 
         $datos2 = $g->getRetroActivosNomI($id);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             if ($dato["idConcRetro"]>0)
                $idCon   = $dato["idConcRetro"];   // Concepto de retroactivo 
             else
                $idCon   = $dato["idCon"];   // Concepto

             $dev=0;
             $ded=0;
             if ($tipo==1)
                $dev     = $dato["valor"];     // Devengado
             else  
                $ded     = $dato["valor"];     // Deducido

             $idfor   = -99;   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = 1;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = '';
             $idProy = 0;
             // BUSCAR ALGUN OTRO INCREMENTO ESPECIAL 
             if ( $dato["aumentoConv"]>0 )
             {
                $dev = $dev + ( $dev * ($dato["aumentoConv"]/100) );
                $ded = $ded + ( $ded * ($dato["aumentoConv"]/100) );                
             }

             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

             $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                          
             if ($dato["vlrTrans"]>0) // Descuento de subsidio de transporte
             {
                $idCon   = 114;   // Concepto descuento de subsidio de transporte
                $tipo    = 2;
                $dev     = 0;     // Devengado
                $ded     = $dato["vlrTrans"];     // Deducido              
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                 $idInom = (int) $idInom;                   

                $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                                          
             }
             // Especial Cajamag descuento del 30 %
             if ($dato["sueldoAnt"]>0)
             {
                $idCon   = 59;   // Concepto descuento de subsidio de transporte
                $tipo    = 2;
                $dev     = 0;     // Devengado
                $ded = ($dato["sueldoAct"] - $dato["sueldoAnt"]) * (30/100);
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                 $idInom = (int) $idInom;                   

                $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                                                           
             }             
             //              
         } // FIN REGISTRO DE RETROACTIVOS POR AUMENTO DE SUELDO INDIVIDUAL

         // PRIMA DE ANTIGUEDAD
         $datos = $d->getPrimaAnt();
         $con = '';
         foreach($datos as $dat)
         {
           $ano = $dat['ano']; 
           $mes = $dat['mes']; 
           $anoF = $dat['anoF']; 
           if ( $dat['anual'] == 1 )
              $datos2 = $g->getDiasPantiA($id, $ano, $anoF);// Primas por antiguedad anual 
           else 
              $datos2 = $g->getDiasPanti($id, $ano, $mes);// Primas por antiguedad condicionada
              //    
           //print_r($datos2);
           foreach ($datos2 as $dato)
           {             
            if ( $dato['pg']==0 )
            {
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $horas   = 0;   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dat["formula"]; // Formula
             $tipo    = $dat["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dat["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = -1;   // Id de la formula ,   -1 para ejecutar formula de primas de antiguerad
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $idPant  = 0;
             $obId    = 1; // 1 para obtener el id insertado             
             $fechaEje  = '';
             $idProy  = 0;
             //echo $formula;
             // Llamado de funion -------------------------------------------------------------------
             if ( $dato['diaI'] == 1) // 0 no esta dentro del periodo, 1 esta dentro del periodo
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy );              
                $idInom = (int) $idInom;                   
                // GUARDAR REGISTRO PAGO PRIMA DE ANTIGUEDAD
                $c->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id, $ano, $dat['id']);
                $datAnt = $d->getGeneral1("Select nombre from n_conceptos where id=".$idCon);
                $d->modGeneral("update n_nomina_e_d set detalle='".$datAnt['nombre'].' ('.$dato['fecha'].")' where id=".$idInom);
              }
            }
            } 
         } // FIN PRIMA DE ANTIGUEDAD      
         
        // 3. INCAPACIDADES 
        $datos2 = $g->getIncapNom($id, 0);// ( n_nomina_e_i ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             //$diasLab = $dato['dias'];    // Dias laborados 
             $diasLab = 0;    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Validaciones para incapacidades 
             $diasCal = $dato['diasCal'];
             $diasEnt = $dato['diasEnt'];           
             $diasAp  = $dato['diasAp'];
             $diasDp  = $dato['diasDp'];            
             //echo ' g '.$datGen['incAtrasada'].'<br />';
             // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
             if ( $datGen['incAtrasada'] == 0)
                $diasAp = 0;
             if ( $dato['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                $diasAp = 0;          

             $diasI = $diasAp + $diasDp ; // dias total de incapacidades
             // Validar que no tenga mas dias calendario
             if ($diasI > $dato['diasCal'])
             {
                $diasI = $dato['diasCal'];
             }
             // Convertir a horas
             if ( $dato["tipInc"] == 1 )// Empresa
             {
                 $horas = $dato["diasEmp"] * 8; 
             }
             $horasIncaEnt = 0;
             if ( $dato["tipInc"] == 2 )// Entidad esps u otra
             {
                 $horas = ( $diasI - $dato["diasEmp"] ) * 8;
                 // Validar si esta por debajo del valor dia del minimo
                 $horasIncaEnt = $horas/8;                    
             }             
             // Llamado de funion -------------------------------------------------------------------
             if ($horas>0)
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy); 
			          $idInom = (int) $idInom;
                $rep='';
                if ($dato['reportada']==0)
                    $rep = '*';
                $d->modGeneral("update n_nomina_e_d 
                          set detalle='".$rep."INCAPACIDAD ".$dato['nomTinc']." (".$dato['fechai']." - ".$dato['fechaf'].") -- ".$diasI." dias ' where id=".$idInom);
                if ( $horasIncaEnt > 0 ) // VALIDACION SI EL PAGO ESTA POR DEBAJO DEL MINIMO
                {
                 // if ( $ide == 222 )
                   //  echo 'ENTRO INCAPAC';
                   $datV = $d->getGeneral1("select devengado from n_nomina_e_d where id=".$idInom);                            
                   $horDev = $datV['devengado'] / $horasIncaEnt;
                   //if ( $horDev < ( 644350/30 ) )
                  // {
                  //    $val = (( 644350/30 ) * $horasIncaEnt ); // a lleva al minimo
                   //   $d->modGeneral("update n_nomina_e_d set devengado = ".$val." where id=".$idInom);
                  // }
                }
 	              $d->modGeneral("update n_nomina_e_d set idInc = ".$dato['idInc']." where id=".$idInom);      			                
                $d->modGeneral("update n_nomina_e_i set diasInc= ".$diasI.",diasEmp = ".$dato["diasEmp"]." where idNom=".$id." and idInc=".$dato['idInc']);                           
             }
         } // FIN INCAPACIDADES 

        // INCAPACIDADES PRO
        $datos2 = $g->getIncapNom($id, 1);// ( n_nomina_e_i ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Validaciones para incapacidades 
             $diasCal = $dato['diasCal'];
             $diasEnt = $dato['diasEnt'];           
             $diasAp  = $dato['diasAp'];
             $diasDp  = $dato['diasDp'];            
             //echo ' g '.$datGen['incAtrasada'].'<br />';
             // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
             if ( $datGen['incAtrasada'] == 0)
                $diasAp = 0;
             if ( $dato['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                $diasAp = 0;          

             $diasI = $diasAp + $diasDp ; // dias total de incapacidades
             // Validar que no tenga mas dias calendario
             if ($diasI > $dato['diasCal'])
             {
                $diasI = $dato['diasCal'];
             }
             // Convertir a horas
             if ( $dato["tipInc"] == 2 )// Entidad esps u otra
             {
                 $horas = ( $diasI - $dato["diasEmp"] ) * 8;
             }             
                                       
             // Validar si incapacidad pasa de 90 dias 
             $dat = $d->getGeneral1("select DATEDIFF( a.fechaf, a.fechaI) as dias 
                                        from n_incapacidades a where id=".$dato["idInPadre"]);
             $diasInc = $dat['dias'];
             $dat = $d->getGeneral1("select sum(DATEDIFF( a.fechaf, a.fechaI)) as dias 
                                        from n_incapacidades_pro a where idInc=".$dato["idInPadre"]);             
             $diasInc = $diasInc + $dat['dias'];

             // Convertir a horas
             if ( $dato["tipInc"] == 2 )// Entidad esps u otra
             {
                 $horas   = ( $dato["diasEmp"] + $dato["diasEnt"] ) * 8;
                                  // Validar si esta por debajo del valor dia del minimo
                 $horasIncaEnt = $horas/8;                    
                 
                 if ($diasInc>90) // Ojo esto va por variable 
                 {
                    $idCon = 280 ; 
                    $idfor   = 35;   // Id de la formula  
                    $formula = '($Valhora *(50/100) )'; // Formula
                 }
             }             
             // Llamado de funcion -------------------------------------------------------------------
             if ($horas>0)
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy); 
                $idInom = (int) $idInom;
                $detalle = '';
                if ($diasInc > 180)
                   $detalle = 'Este empleado ha sobrepasado los 180 dias de incapacidad. Total '.$diasInc ;
                 // Validar si es menor que el salario minimo 
                if ($diasInc < 180)
                {
                   $datV = $d->getGeneral1("select devengado from n_nomina_e_d where id=".$idInom);                            
                   $horDev = $datV['devengado'] / $horasIncaEnt;
                   if ( $horDev < ( 644350/30 ) )
                   {
                      $val = (( 644350/30 ) * $horasIncaEnt ); // a lleva al minimo
                      $d->modGeneral("update n_nomina_e_d set devengado = ".$val." where id=".$idInom);
                   }                  
                }
                $d->modGeneral("update n_nomina_e_d set detalle='".$detalle."', idInc = ".$dato['idInc']." where id=".$idInom);                           
                $d->modGeneral("update n_nomina_e_i set diasInc= ".$diasI.",
                                   diasEmp = ".$dato["diasEmp"].", diasProrroga = ".$diasInc."  
                                   where idNom=".$id." and idInc=".$dato['idInc']);                                           
             }
         } // FIN INCAPACIDADES 

         // AUSENTISMOS
         $datos2 = $g->getNominaAus($id);// Por asusentismos ( n_nomina_e_d ) Programado
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $horas   = $dato['horas'];   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             if ($tipo==1)
                $dev = $dato["valor"];   
             else 
                $ded = $dato["valor"];                 
             
             if ($dato["horAus"]>0)// Es por fechas de ausencia 
                $ded = ( $dato["valor"]/8 ) * $dato["horAus"] ;                 

             $idfor   = -99;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;

             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac, $obId, $fechaEje, $idProy);
             $idInom = (int) $idInom;
             if ($dato["horAus"]>0)// Es por fechas de ausencia 
             {
                $detalle = $dato["nomCon"].' ( el '.$dato["fechai"].' por '.$dato["horas"].' horas )';
                $d->modGeneral("update n_nomina_e_d set detalle = '".$detalle."' where id=".$idInom);                
             }
             else    
             {
                $detalle = $dato["nomCon"].' ( del '.$dato["fechai"].' al '.$dato["fechaf"].' )';
                // Se ponen los valroes en 0 para que salga impreso en la nomina               
                if ($dato["tipAus"]==1) // Ausentismo remunerado
                {
                   $d->modGeneral("update n_nomina_e_d set detalle = '".$detalle."' where id=".$idInom);                    
                   if ($dato["horAus"]>0)// Es por fechas de ausencia 
                   {
                      $d->modGeneral("update n_nomina_e_d set devengado=0, deducido=0 where id=".$idInom);                
                   }                   
                }
                if ($dato["tipAus"]==2) // Ausentismo no remunerado
                {
                    if ($datGen['asuDesc']==1) // Descuenta del documento las novedades de ausentismos no remunerados
                      $d->modGeneral("update n_nomina_e_d set devengado=0, deducido=0, detalle = '".$detalle."' where id=".$idInom); 
                    else
                      $d->modGeneral("update n_nomina_e_d set detalle = '".$detalle."' where id=".$idInom);   
                }
                //else                    
                //   $d->modGeneral("update n_nomina_e_d set horas=0, devengado=0, deducido=0, detalle = '".$detalle."' where id=".$idInom); 
             }
			               
         } // FIN AUSENTISMOS    

      // LIQUIDACION FINAL DE CONTRATO ------------------------------- ( 111 )
      if ($idTnomL==0) 
      {                                               
         // VACACIONES EN DISFRUTE GENERACION
         $datos2 = $g->getVacacionesG($id);// Insertar vacaciones 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {        
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $horas   = $dato["horas"];   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $datGen["idVac"]; // Concepto de vacaciones disfrutadas en tabla de configuraciones
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac, $obId, $fechaEje, $idProy);                                                               
         } // FIN VACACIONES EN DISFRUTE       
         // VACACIONES PAGADAS GENERACION
         $datos2 = $g->getVacacionesGc($id);// Insertar vacaciones 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {        
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $horas   = $dato["horas"];   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $datGen["idVacP"]; // Concepto de vacaciones disfrutadas en tabla de configuraciones
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac, $obId, $fechaEje, $idProy);                                                               
         } // FIN VACACIONES PAGADAS
      } // FIN VALIDACION LIQUIDACION NOMINA 

         // CONCEPTOS HIJOS 
         $datos2 = $g->getNominaConH($id);
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {             
           if ($dato['Temp']>0)
           {
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
           }
         } // FIN CONCEPTOS AUTOMATICOS POR PERIODO                           

      // SOLO PARA NOMINAS NORMALES 
      if ($idTnomL==0) 
      {         
         // ( POR TIPO DE AUTOMATICOS )
         $datos2 = $g->getNominaEtau($id,$idg);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funcion -------------------------------------------------------------------
             if ($dato["actVac"]==0)
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                          
              }
              // Si tiene algun aumento de sueldo dentro del periodo
              if ($dato['diasS'] > 0 )
              {              
                 if ( $idCon == 122 ) // Solo para concepto de sueldo 
                 {
                    $horas    = $dato["diasS"];   // Dias laborados
                    $diasLabC = 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas                    
                    $idfor    = -99;   // Id de la formula           
                    $dev      = $dato["vlrDiaAnt"] * $dato["diasS"];   // Dias laborados
                    $formula = ''; // Formula
                    $idCon = 216;                    
                    $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
                    $detalle = 'SUELDO ANTERIOR ( '.number_format( $dato["sueldoAnt"]).')';//fecAum
                    $idInom = (int) $idInom;                   
                    $d->modGeneral("update n_nomina_e_d set nitTer='', detalle='".$detalle."' where id=".$idInom);
                  }
              }

         } // FIN TIPOS DE AUTOMATICOS

         // ( POR TIPO DE AUTOMATICOS 2 opcionales)
         $datos2 = $g->getNominaEtau2($id,$idg);                             
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Valdiacion especial retro por ingreso a grupos cajamag
            // if ( $dato["estado"]==0)
            // {

            // }
             // Llamado de funcion -------------------------------------------------------------------
             if ($dato["actVac"]==0)
             {             
                 $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                 $idInom = (int) $idInom;                   
                 $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                           
             }

         } // FIN TIPOS DE AUTOMATICOS 2 (opcionales)         
         
         // ( POR TIPO DE AUTOMATICOS 3 opcionales)
         $datos2 = $g->getNominaEtau3($id,$idg);                             
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             if ($dato["actVac"]==0)
             {             
                // Llamado de funcion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                       
             }
         } // FIN TIPOS DE AUTOMATICOS 3 (opcionales) 
                 
         // ( POR TIPO DE AUTOMATICOS 4 opcionales)
         $datos2 = $g->getNominaEtau4($id,$idg);                             
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             if ($dato["actVac"]==0)
             {             
                // Llamado de funcion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                                    
             }

         } // FIN TIPOS DE AUTOMATICOS 4 (opcionales)  
                         
         // OTROS AUTOMATICOS POR EMPLEADOS
         $datos2 = $g->getNominaEeua($id);// Insertar nov automaticas ( n_nomina_e_d ) por otros automaticos
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = -99;   // Id de la formula no tiene formula asociada, ya viene la formula 
             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Fomrula para dais mas vacaciones en otros automaticos
             $valor = 0;
             if ( $dev > 0 ) 
             {
                $valor = $dev;$dev=0;
             }else
             {
                $valor = $ded;$ded=0;
             }
             if ( $dato['horasCal'] > 0 ) // Afectado por lso dias laborados
             {
                $formula = ' $diasLab*'.$valor; // Concatenan para armar la formula
                $diasLabC = $dato['dias'] ;   // Dias laborados solo para calculados
             }else{
                if ( $dato['idVac'] > 0 )
                   $formula = ' ($diasLab+$diasVac+$diasInca)*'.$valor; // Concatenan para armar la formula
                else 
                   $formula = ' ($diasLab+$diasVac+$diasInca)*'.$valor; // Concatenan para armar la formula                  
                   //$formula = ' ($diasLab+$diasVac+$diasInca+$diasMod)*'.$valor; // Concatenan para armar la formula
             }    
             if ( $dato['formula']!='' )
                $formula = $dato['formula'];  
             //echo 'ifo  '.$formula;
             // Llamado de funion -------------------------------------------------------------------
             if ( ($dato['fecAct']==0) or ($dato['fecAct']==1) )
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 2,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);             
             }
         } // FIN OTROS AUTOMATICOS POR EMPLEADOS


         // ( REGISTRO DE NOVEDADES MODIFICADAS ) ( n_nomina_nove ) Guardadas en las novedades anteriores
         $datos2 = $g->getRnovedadesN($id);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             $idIcal  = $dato["idIcal"];   // Instruccion para calcular o no calcular
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------             
             if ( $dato["editado"] == 1) // Editar novedad en nomima_e_d
             {
                if ( $dato["idInovN"] > 0 )
                {
                   $d->modGeneral("update n_nomina_e_d 
                                   set devengado = ".$dev.",deducido = ".$ded."  
                                      where idInom =".$dato["id"]." 
                                           and idInov=".$dato["idInovN"]);
                }
                if ( $dato["idInovN"] == 0 ) // Es porque se edito un automatico 
                {
                   $d->modGeneral("update n_nomina_e_d 
                                   set devengado = ".$dev.",deducido = ".$ded."  
                                      where tipo=2 and idInom =".$dato["id"]." 
                                         and idConc = ".$dato["idCon"] );
                }
             }
             else  
             { 
                $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             }
         } // FIN REGISTRO DE NOVEDADES MODIFICADAS POR OTROS AUTOMATICOS
         // ---------------------------------------------------------------------------------
         // CONCEPTOS CALCULADOS AUTOMATICOS (SALUD , PENSION)---------------------------------------------------------------------------------
         // ---------------------------------------------------------------------------------
         $datos2 = $g->getNominaEcau($id);
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             //if ($dato["actVac"]==0)
             //{
             // 
                $sw = 0;
               if ( ($dato["idFpen"]==1) and ( $dato["fondo"]==2 ) ) // Si el concepto de pension no aplica no debe generarlo
                   $sw = 1;             
               if ($sw == 0)
                  $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
              //}
         } // FIN CONCEPTOS AUTOMATICOS         
     }  // FIN LIQUIDACION FINAL 

        // PRESTAMOS 
        $datos = $g->getPrestamos($id);// Prestamos 
        foreach ($datos as $dato2)
        {                      
           $idEmp = $dato2['idEmp'];            
           if ($dato2['dias'] >= 0){
              // Busqueda de cuotas de prestamos y descargue 
              if ($dato2['vacAct']==0)
                 $datos2 = $g->getCprestamosS($id,$idEmp);
              else // Calculo para el regreso de vacaciones
                 $datos2 = $g->getCprestamosR($id,$idEmp);

              foreach ($datos2 as $dato)
              {

                $iddn    = $dato['id'];  // Id dcumento de novedad
                $idin    = 0;     // Id novedad
                $ide     = $dato['idEmp'];   // Id empleado
                $diasLab = $dato['dias'];    // Dias laborados 

                $diasVac = 0;    // Dias vacaciones
                $horas   = $dato["horas"];   // Horas laborados 
                $formula = $dato["formula"]; // Formula
                $tipo    = $dato["tipo"];    // Devengado o Deducido  
                $idCcos  = $dato["idCcos"];  // Centro de costo   
                $idCon   = $dato["idCon"];   // Concepto
                $dev     = 0;     // Devengado
                $ded     = $dato["valor"];     // Deducido         
                $idfor   = $dato["idFor"];   // Id de la formula    
                $diasLabC= 0;   // Dias laborados solo para calculados 
                $idCpres = $dato["idPres"];   // Id de la cuota del prestamo
                $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                $obId    = 1; // 1 para obtener el id insertado
                $nitTer  = $dato['nitTer']; 
                $fechaEje  = '';
                $idProy  = 0;
                // Validar si hay una cuota modificada en la nomina activa
                if ( $dato['valorPresN'] > 0 )
                   $ded  = $dato["valorPresN"];// Deducido         
//if ($idEmp==179)
 //  echo 'Prestamo : '.$idCpres.': $ '.$ded.'<br />';
                // Llamado de funcion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 4,$dev,$ded,$idfor,$diasLabC,$idCpres,1,$conVac,$obId,$fechaEje,$idProy);                                           
                $idInom = (int) $idInom;                   
                // Colocar saldo del prestamo
                $d->modGeneral("update n_nomina_e_d set nitTer='".$nitTer."' where id=".$idInom);                
              }  
           }
        }
         // FONDO DE SOLIDARIDAD PARA SEGUNDO PERIODO
         if ( $periodoNomina == 2)
         {
           $datos2 = $g->getSolidaridad($id);   
		       foreach ($datos2 as $dato)
           {             
              if ($dato['vacAct']==0)
              {
                $ide     = $dato['idEmp'];   // Id empleado
                $ano     = $dato['ano'];   // Año
                $mes     = $dato['mes'];   // Mes                           
                $dat     = $n->getSolidaridad($ano, $mes, $ide); // Extraer los datos de solidaridad de la funcion
                $iddn    = $dato['id'];  // Id dcumento de novedad             
                $idin    = 0;     // Id novedad
                $diasLab = 0;    // Dias laborados 
                $diasVac = 0;    // Dias vacaciones
                $horas   = 0;   // Horas laborados 
                $formula = ''; // Formula
                $tipo    = 2;    // Devengado o Deducido  
                $idCcos  = $dato["idCcos"];  // Centro de costo   
                $idCon   = 21;   // Concepto
                $dev     = 0;     // Devengado
                $ded     = $dat['valor'];     // Deducido         
                $idfor   = -9;   // Id de la formula    
                $diasLabC= 0;   // Dias laborados solo para calculados 
                $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                $obId    = 0; // 1 para obtener el id insertado
                $fechaEje  = '';
                $idProy  = 0;
                // Llamado de funion -------------------------------------------------------------------
                if ($ded>0)
                   $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);
              }
            }// FIN RECORRIDO FONDO DE SOLIDARIDAD               
         } // FIN FONDO DE SOLIDARIDAD
                 
         // VACACIONES FONDO DE SOLIDARIDAD PERIODO DE DESPUES DEL MES ACTUAL
         $datos2 = $g->getVacacionesG($id);// Insertar vacaciones 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {        
             if ( $dato['fondo'] > 15) // Validacion momentaena pero debe tener un analisis mas delicado sobre el periodo
             {
               $iddn    = $dato['id'];  // Id dcumento de novedad
               $idin    = 0;     // Id novedad
               $ide     = $dato['idEmp'];   // Id empleado
               $diasLab = $dato['dias'];    // Dias laborados 
               $horas   = $dato["horas"];   // Horas laborados 
               $diasVac = 0;    // Dias vacaciones
               $formula = $dato["formula"]; // Formula
               $tipo    = $dato["tipo"];    // Devengado o Deducido  
               $idCcos  = $dato["idCcos"];  // Centro de costo   
               $idCon   = $dato["idCon"];   // Concepto
               $dev     = $dato["dev"];     // Devengado
               $ded     = $dato["ded"];     // Deducido
               $idfor   = $dato["idFor"];   // Id de la formula 
               $diasLabC= 0;   // Dias laborados solo para calculados
               $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
               $obId    = 0; // 1 para obtener el id insertado
               // Verificar fondo de solidaridad en vacaciones a partir del segundo periodo 
                $ano     = $dato['ano'];   // Año
                $mes     = $dato['mes'];   // Mes                           
                               
               $dat     = $n->getSolidaridadv($ano, $mes, $id, $ide); 
               //print_r($dat);
               if ( $dat['valor'] > 0 )
               {
                  $idin    = 0;     // Id novedad
                  $diasLab = 0;    // Dias laborados 
                  $diasVac = 0;    // Dias vacaciones
                  $horas   = 0;   // Horas laborados 
                  $formula = ''; // Formula
                  $tipo    = 2;    // Devengado o Deducido  
                  $idCon   = 21;   // Concepto
                  $dev     = 0;     // Devengado
                  $ded     = $dat['valor'];     // Deducido         
                  $idfor   = 0;   // Id de la formula    
                  $diasLabC= 0;   // Dias laborados solo para calculados 
                  $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                  $obId    = 0; // 1 para obtener el id insertado
                  $fechaEje  = '';
                  $idProy  = 0;
                  // Llamado de funion -------------------------------------------------------------------
                  $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);                               
               }
            }// Fin validacion periodo de salida para calcular fondo
             // -------             
         } // FIN SOLIDARIDAD EN VACACIONES QUE TOMAN UN PERIODO FUERA DEL MES ACTUAL
        // EMBARGOS
        $e = new EmbargosN($this->dbAdapter);
        $datos2 = $g->getIembargos($id);// ( n_nomina_e_d ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ""; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $fechaEje  = '';
             $idProy  = 0;
			       // Ejecutar embargos			 
			       if ( $dato["formula"] != '')
			       {
			 	        $con = '"'.$dato["formula"].'"';	
			 	        eval("\$con =$con;");
				 
			 	        $datVal = $d->getGeneral1($con);			
				        $ded   = $datVal['valor'];
                $saldo = $dato["pagado"];

                if ( ( $dato['valor']>0 ) and ( $ded > $dato["pagado"] ) )# si es mayor que el saldo debe tomar el valor de saldo 
                {
                    $ded = $dato["pagado"];
                    $saldo = 0;
                }
				        //echo $dato["formula"].' : '.$ded.'<br />';
                // Llamado de funion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje,$idProy);              
                // Guardar en tabla de embargos para cruzar documentos
                $idInom = (int) $idInom;                   
                // Colocar saldo del embargo
                $d->modGeneral("update n_nomina_e_d set idRef=".$dato['idEmb'].",  nitTer='".$dato['nitTer']."', saldoPact = ".$saldo." where id=".$idInom);
                // GUARDAR REGISTRO DE EMBARGOS
                if ($idInom>0)
                   $e->actRegistro($ide, $ded, $idInom , $id, $dato['idEmb']);                
			        }
             
         } // FIN EMBARGOS                                   
         
        // RETENCION DE LA FUENTE
        if ( ($periodoNomina==$datGen['retePeriodo']) or ($datGen['retePeriodo']==0) )
        { 
          $r = new Retefuente($this->dbAdapter);
          $datos2 = $g->getRetFuente($id, 0);// 
          foreach ($datos2 as $dato)
          {          
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ''; // Formula
             $tipo    = 2;    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = 10;   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $calc    = 0;
             $ano     = $dato['ano'];   // Año
             $mes     = $dato['mes'];   // Mes                           			 
             $ded = 0;
             //if ( $dato['dias']>0)
			       $ded = $r->getReteConc($iddn, $ide); // Procedimiento para guardar la retencion
			       $fechaEje  = '';
             $idProy  = 0;
             //echo 'rete'.$ded ;
             // Llamado de funion -------------------------------------------------------------------
             $dedAnt = 0;
             if ( $ded>0) 
             {
                if ($datGen['retePeriodo']==2) // Funciona en el periodo 2 (verificar caso vacaciones en nomina)
                {
                   // Buscar valor de concepto pagado anterioremente en el mismo año y mes 
                   $datAnt  = $g->getFondSolAnt($ano, $mes,$ide, $id, 10);
                   $dedAnt = 0;                
                   if ( $datAnt['deducido'] > 0 )
                   { 
                      $dedAct = $ded;                                                           
                      $ded = $ded - $datAnt['deducido'];
                      $dedAnt = $datAnt['deducido'];  
                   }                                
                }   
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje, $idProy);              
                $idInom = (int) $idInom; 
                   // Colocar saldo del prestamo
                //echo 'vaa'.$dedAnt;
                   if ( $dedAnt > 0 )
                       $d->modGeneral("update n_nomina_e_d 
                     set detalle='RETENCION EN LA FUENTE (ANT ".number_format($datAnt['deducido'])."- ACT ".number_format($dedAct)." ) ' where id=".$idInom);                                  
             }                      
         } // FIN RETENCION DE LA FUENTE                                   
       }// Fin valdiacion del periodo            

        // Numero de empleados
        $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
        $dato=$d->getGeneral1($con2);                                                  

        // Cambiar estado de nomina
        $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
        $d->modGeneral($con2);                                         
        
        $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado 
        
        
       }// Sw e prueba ojo
        $e = 'Nomina generada de forma correcta';
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
      $valores = array( "e" => $e);
      $view = new ViewModel($valores);        
      $this->layout('layout/blancoC'); // Layout del login
      return $view;              
      
    } // Fin generacion nomina


    // GENERACION DE CESANTIAS -------------------------------------
    public function listcAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  

         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d=new AlbumTable($this->dbAdapter);                 
         $n=new NominaFunc($this->dbAdapter);
         $g=new Gnominag($this->dbAdapter);
         $c=new Cesantias($this->dbAdapter);
         // Buscar id de grupo
         
         $datos = $d->getPerNomina($id); // Periodo de nomina

         $idg    = $datos['idGrupo'];         
         $fechaI = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];    
         $idIcal = $datos['idIcal'];         
         // Calculo para las censantias por los empleados del grupo
         $datos = $g->getDiasCesa($idg,$id); 
         //print_r($datos);
         // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
		        $connection->beginTransaction();
                
            foreach ($datos as $datoC)
            {              
                $idEmp = $datoC['idEmp'];
                // Verificar fecha del aumento de sueldo del empleados
                $datFec = $d->getAsalariaF($idEmp, $fechaF); 
                $tipC = 0;
                if ($datFec['meses']>3) // Si el ultimo aumento es mayor a 3 meses no se incluye ne calculo del promedio 
                {
                   $datos2 = $n->getCesantias($idEmp, $fechaI, $fechaF);                  
                   $tipC = 1;
                }else{ // Sino se llama la funcion para tenerlo en cuenta en el promedio
                   $datos2 = $n->getCesantiasS($idEmp, $fechaI, $fechaF);  
                   $tipC = 2;
                }              
                // Calcular las cesantias
                foreach ($datos2 as $dato)
                {  
                   if ($tipC==1)  
                       $base = round( $dato["valor"] + $dato["sueldo"], 2); // Buscar subdisio de transporte
                   else  // Cesantias mas sueldo      
                       $base = round( $dato["valor"]  , 2 ); 
                   // Valor a pagar 
                   if ($idEmp==51)
                   {
                       	//echo 'base '.$dato["valor"].'<br /> ';
                       	//echo 'base '.$datoC["diasCes"].'<br /> ';                       	                   	
                   }
                   
                   $valor = round(  ($base / 360) * $datoC['diasCes'] , 2 );

                   $id      = $datoC['idNom'];  // Id dcumento de novedad 
                   $iddn    = $datoC['id'];  // Id dcumento de novedad
                   $idin    = 0;     // Id novedad
                   $ide     = $idEmp;   // Id empleado
                   $diasLab = $datoC['diasCes'];    // Dias laborados 
                   $horas   = 0;   // Horas laborados 
                   $diasVac = 0;    // Dias vacaciones
                   $formula = ''; // Formula
                   $tipo    = $datoC["tipo"];    // Devengado o Deducido  
                   $idCcos  = $datoC["idCcos"];  // Centro de costo   
                   $idCon   = 213;   // Concepto
                   //$idCon   = $datoC["idCon"];   // Concepto
                   $dev     = $valor;   // Devengado
                   $ded     = 0;     // Deducido         
                   $idfor   = '';   // Id de la formula    
                   $diasLabC= 0;   // Dias laborados solo para calculados 
                   $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                   $obId    = 1; // 1 para obtener el id insertado
                   $fechaEje  = '';
                   $idProy  = 0;
                   //echo $dev.'<br />';
                   // Llamado de funion -------------------------------------------------------------------
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId,$fechaEje, $idProy);              
                   $idInom = (int) $idInom;                   
                   // INTERESE DE CENSATIAS 
                   $dev     = ( ( $valor * ( 12/100 ) )/360 ) * $datoC['diasCes']; // Devengado
                   $idCon   = 195; //
                   $obId    = 0; // 1 para obtener el id insertado
                   if ($valor > 0)
                   {
                       // Llamado de funion -------------------------------------------------------------------
                       $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId,$fechaEje, $idProy);                             
                       // REGISTRO LIBRO DE CESANTIAS                   
                       $c->actRegistro($ide, 213, 195, $fechaI, $fechaF, $diasLab, $dato["sueldo"], $base, $valor, $dev , $idInom , $id);
                   }
                }                                  
            }
            // Numero de empleados
            $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
            $dato=$d->getGeneral1($con2);                                                  

            // Cambiar estado de nomina
            $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
            $d->modGeneral($con2);                                         
        
            $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado          

           $connection->commit();
        }// Fin try casth   
        catch (\Exception $e) {
	   if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
      	      $connection->rollback();
      	      echo $e;
	   }
	
	 /* Other error handling */
         }// FIN TRANSACCION        
         $view = new ViewModel();        
         $this->layout('layout/blanco'); // Layout del login
         return $view;                    
       }
    }
    
    // GENERACION DE PRIMAS -------------------------------------
    public function listpmAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  

         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d=new AlbumTable($this->dbAdapter);                 
         $n=new NominaFunc($this->dbAdapter);
         $g=new Gnominag($this->dbAdapter);
         $c=new Primas($this->dbAdapter);
         // Buscar id de grupo
         $datGen = $d->getConfiguraG(''); // CONFIGURACIONES GENERALES

         $datos = $d->getPerNomina($id); // Periodo de nomina

         $idg    = $datos['idGrupo'];         
         $fechaI = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];         
         $idIcal = $datos['idIcal'];                  
         // Calculo para las primas por los empleados del grupo
         $datos = $d->getGeneral("Select a.id, a.idEmp, b.idCcos,"
                                . "  case when b.fecIng > '".$fechaI."' then round( ( ( DATEDIFF( '".$fechaF."' , b.fecIng ) + 1 ) * 15 ) / 180,2 )
								     else 15 end  as diasPrima, b.fecIng 
                                  from n_nomina_e a 
                                  inner join a_empleados b on b.id = a.idEmp 
                                  where a.idNom =".$id); 
                  
         // INICIO DE TRANSACCIONES
         $connection = null;
         try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
		        $connection->beginTransaction();
           
         // ( REGISTRO DE NOVEDADES ) ( n_novedades ) 
         $datos2 = $g->getRnovedades($id,$idIcal);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             $saldoPact = 0;
             if ( $dato["valCuota"] > 0 )
             {                
                if ($dev>0)
                {
                   $saldoPact = $dev-$dato["pagado"];                  
                   $dev = $dato["valCuota"]; 
                }
                else
                {
                   $saldoPact = $ded-$dato["pagado"];                  
                   $ded = $dato["valCuota"]; 
                }                  
             }
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   
             // GUARDAR REGISTRO PAGO PRIMA DE ANTIGUEDAD
             $d->modGeneral("update n_nomina_e_d 
                set idProy=".$idProy.", saldoPact=".$saldoPact.",
                 fechaEje ='".$dato['fechaEje']."' , idInov=".$dato['idInov']." where id=".$idInom);
            } // FIN REGISTRO DE NOVEDADES                                     


         // CONCEPTOS EXTRALEGALES
         $datos2 = $g->getConceptosExtra($id);
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
          } // FIN CONCEPTOS EXTRALEGALES

        // PRESTAMOS PARA DESCONTAR EN Primas
        $datos = $g->getPrestamosPrimas($id);// Prestamos 
        //print_r($datos);
        foreach ($datos as $dato)
        {                      
           $iddn    = $dato['id'];  // Id dcumento de novedad
           $idin    = 0;     // Id novedad
           $ide     = $dato['idEmp'];   // Id empleado
           $diasLab = $dato['dias'];    // Dias laborados 

           $diasVac = 0;    // Dias vacaciones
           $horas   = $dato["horas"];   // Horas laborados 
           $formula = $dato["formula"]; // Formula
           $tipo    = $dato["tipo"];    // Devengado o Deducido  
           $idCcos  = $dato["idCcos"];  // Centro de costo   
           $idCon   = $dato["idCon"];   // Concepto
           $dev     = 0;     // Devengado
           $ded     = $dato["valor"];     // Deducido         
           $idfor   = $dato["idFor"];   // Id de la formula    
           $diasLabC= 0;   // Dias laborados solo para calculados 
           $idCpres = $dato["idPres"];   // Id de la cuota del prestamo
           $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
           $obId    = 1; // 1 para obtener el id insertado
           $nitTer  = $dato['nitTer']; 
           $fechaEje  = '';
           $idProy  = 0;
//if ($ide==136)
    //echo $idCpres.' -'.$ded.'prestamos <br />';
           // Llamado de funcion -------------------------------------------------------------------
           $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 4,$dev,$ded,$idfor,$diasLabC,$idCpres,1,$conVac,$obId,$fechaEje,$idProy);                                           
           $idInom = (int) $idInom;                   

           // Colocar saldo del prestamo
           $d->modGeneral("update n_nomina_e_d set nitTer='".$nitTer."' where id=".$idInom);                
         }  
         
          // LIQUIDACION PRIMAS
            $c = new Primas($this->dbAdapter);
            $fechaIprimas = $fechaI; // La fecha real de consulta esta en calendario   
            // Calculo para las primas por los empleados del grupo
            $fechaIprimasCal = '2015-01-01'; // Dias inicial Calendario 
            $fechaFprimasCal = '2015-06-30'; // Dias finCalendario 
            $mesI = '1';
            $mesF = '6';
            $datos = $g->getPrimasFinal( $fechaIprimasCal, $fechaFprimasCal, $id);
            foreach ($datos as $dato)
            {      
              $iddn    = $dato['id'];  // Id dcumento de novedad
              $idin    = 0;     // Id novedad
              $ide     = $dato['idEmp'];   // Id empleado
              $diasLab = 0;    // Dias laborados 
              $horas   = 0;   // Horas laborados 
              $diasVac = 0;    // Dias vacaciones
              $formula = ''; // Formula
              $tipo    = 1;    // Devengado o Deducido  
              $idCcos  = $dato["idCcos"];  // Centro de costo   
              $idCon   = $datGen['idPrima'];   // Concepto
              $diasPrima = $dato['diasPrima'];
              //if ($dato['diasPrimaN']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
              //    $diasPrima = $dato['diasPrimaN'];  
              $datDcal = $n->getDiasCalen( $dato['mesI'] , $mesF ); // Funcion apra deolver dias para descontar entr rango de fecha pra dias habiles
//              if ($diasPrima<180)
              //{  
                 if ( ($datDcal['diasS']!=0) or ($datDcal['diasR']!=0) )
                    $diasPrima = $diasPrima - $datDcal['diasR'];   
                    $diasPrima = $diasPrima + $datDcal['diasS'];   
             // }
              if ($ide==355)
              {
                 //echo $diasPrima.' - '.$datDcal['diasS'].' '.$datDcal['diasR'];                 
              }    
              // Buscar ausentismos no remunerado
              $datAus = $d->getAusentismosDias($ide, $fechaIprimasCal, $fechaFprimasCal);
              if ($datAus['dias']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
                  $diasPrima = $diasPrima - $datAus['dias'];  

              $datPr   = $g->getDiasPrima($ide,$fechaIprimas,$fechaF,$diasPrima,$id); // Valor de prima a pagar
              //print_r($datPr);              
              //if ($ide==50)
              //{
              //   echo 'Dias primas '.$dato['diasPrima'].'<br />';              
              //   echo 'Promedio '.$datPr['promedioMes'].'<br />';
              //}
              $diasLab = 0;    // Dias laborados 
              $dev     = $datPr["promedioMes"]*$diasPrima;     // Devengado  Dias trabajados en el semestre
              $ded     = 0;     // Deducido         
              $idfor   = -99;   // Id de la formula    
              $diasLabC= 0;   // Dias laborados solo para calculados 
              $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
              $obId    = 1; // 1 para obtener el id insertado
              $fechaEje  = '';
              $idProy  = 0;
              // Llamado de funion -------------------------------------------------------------------
              $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);               
              $idInom = (int) $idInom;                   
              // LIBRO DE PRIMAS
              if ($dev > 0)
              {
                  // REGISTRO LIBRO DE PRIMAS
                  $c->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id);
                  $subTrans = $datPr["subTransporte"];

                  $d->modGeneral("update n_nomina_e set subTransporte=".$subTrans.",
                                    diasPrimas=".$diasPrima.",dias=".$diasPrima.", 
                                    promPrimas=".$datPr["promedioMes"]." where id =".$iddn);
              }                 
              // DESCUENTO ESPECIAL CAJAMAG 
              if ( ( $dato["idTau2"]==3 ) or ($dato["idTau3"]==3)  or ($dato["idTau4"]==3) )
              {
                  // Llamado de funion -------------------------------------------------------------------
                  $diasLab = 0;
                  $dev = 0;
                  $idCon   = 62;   // Concepto
                  if ( $dato['sueldo'] > 1006270)
                     $ded = 50000;
                  else
                     $ded = 30000;

                  $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);               
              }
          }
         // CONCEPTOS HIJOS 
         $datos2 = $g->getNominaConH($id);
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {             
           if ($dato['Temp']>0)
           {
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
           }
         } // FIN CONCEPTOS AUTOMATICOS POR PERIODO                           

         // CONCEPTOS AUTOMATICOS 
         $datos2 = $g->getNominaEcau($id);
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             //if ($dato["actVac"]==0)
             //{
                $sw = 0;
               if ( ($dato["idFpen"]==1) and ( $dato["fondo"]==2 ) ) // Si el concepto de pension no aplica no debe generarlo
                   $sw = 1;             
               if ($sw == 0)
                  $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
              //}
         } // FIN CONCEPTOS AUTOMATICOS         

          // Numero de empleados
          $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
          $dato=$d->getGeneral1($con2);                                                  
          // Cambiar estado de nomina
          $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
          $d->modGeneral($con2);                                         
         
          $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado          
          $connection->commit();
       }// Fin try casth   
       catch (\Exception $e) 
       {
   	      if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
          {
   	          $connection->rollback();
   	          echo $e;
 	        }	
 	        /* Other error handling */
       }// FIN TRANSACCION        
       $view = new ViewModel();        
       $this->layout('layout/blanco'); // Layout del login
       return $view;                    
      }
   }
        
    
   // Regenerar nomina ********************************************************************************************
   public function listrgAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Gnomina($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d=new AlbumTable($this->dbAdapter); 
            $c=new Cesantias($this->dbAdapter); 
            // Consultar nomina
            $datos = $d->getGeneral1("Select idTnom, estado, idGrupo from n_nomina where id=".$id); 
            $idTnom = $datos['idTnom'];            
            $idGrupo = $datos['idGrupo'];            
            // INICIO DE TRANSACCIONES
            $connection = null;
            try 
            {
               $connection = $this->dbAdapter->getDriver()->getConnection();
               $connection->beginTransaction();
               // REGISTRO LIBRO DE CESANTIAS
               //$c->delRegistro($id); 
               // Borrar tablas inferiores               
               $d->modGeneral("delete from n_pg_embargos where idNom=".$id);                
               $d->modGeneral("delete from n_pg_primas_ant where idNom=".$id);                
               $d->modGeneral("delete from n_primas where idNom=".$id);                
               $d->modGeneral("delete from n_cesantias where idNom=".$id); 
               $d->modGeneral("delete from n_nomina_e_i where idNom=".$id);
               $d->modGeneral("delete from n_nomina_e_d_integrar where idNom=".$id);
                              
               $datos = $d->getGeneral1("select id from n_nomina_e_d where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d where idNom=".$id); 
               $d->modGeneral("alter table n_nomina_e_d auto_increment = ".$datos['id'] ); 

               $d->modGeneral("update n_nomina set numEmp=0, estado=0 where id=".$id);               
                                            
               $connection->commit();
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);
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

    public function listgAction()
    {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);             

      $dato = $d->getGeneral1("select b.tipo, b.id 
                                from n_nomina a 
                                  inner join n_tip_nom b on b.id=a.idTnom 
                                    where a.id=".$id); // Busco el tipo de nomina para generarla (General, Censatias, Primas, Vacaciones)
            
      $valores=array
      (
        "form"    => $form,
        'url'     => $this->getRequest()->getBaseUrl(),          
        "titulo"  => $this->tlis,
        "datos"   => $d->getGeneral("select b.id, a.CedEmp, a.nombre,a.apellido, a.idVac ,
                       c.nombre as nomCar, d.nombre as nomCcos, e.fechaI, e.fechaF                        
                       from a_empleados a inner join n_nomina_e b on a.id=b.idEmp 
                       left join t_cargos c on c.id=a.idCar
                       inner join n_cencostos d on d.id=a.idCcos
                       left join n_vacaciones e on e.id=b.idVac and e.estado=1 
                       where b.idNom=".$id) ,
        "tipo"    => $dato['tipo'], // Tipo de calendario para esta nomina 
        "lin"     => $this->lin
      );                        
      return new ViewModel($valores);
    }    
    
    public function listg4Action()// Generacion de pruebas 
    {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);             

      $dato = $d->getGeneral1("select b.tipo from n_nomina a 
            inner join n_tip_nom b on b.id=a.idTnom where a.id=".$id); // Busco el tipo de nomina para generarla (General, Censatias, Primas, Vacaciones)
            
      $valores=array
      (
        "form"    => $form,
        'url'     => $this->getRequest()->getBaseUrl(),          
        "titulo"  => $this->tlis,
        "datos"   => $d->getGeneral("select b.id, a.CedEmp, a.nombre,a.apellido, a.idVac ,
                       c.nombre as nomCar, d.nombre as nomCcos, b.incluido, e.fechaI, e.fechaF                        
                       from a_empleados a inner join n_nomina_e b on a.id=b.idEmp 
                       left join t_cargos c on c.id=a.idCar
                       inner join n_cencostos d on d.id=a.idCcos
                       left join n_vacaciones e on e.id=b.idVac and e.estado=1 
                       where b.idNom=".$id) ,
        "tipo"    => $dato['tipo'],
        "lin"     => $this->lin
      );                        
      return new ViewModel($valores);
    }    
    
    // Validar que la nomina no este generada ********************************************************************************************
    public function listvpAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();   
        if ($request->isPost()) {            
           $data = $this->request->getPost();                    
           $id = $data->id; // ID de la nomina                          
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $d=new AlbumTable($this->dbAdapter);         
           $datos = $d->getGeneral1("select estado from n_nomina where id=".$id);
           $valido = '';
           if ($datos['estado']==1)
               $valido = 1;
           $valores=array
           (
            "valido"  =>  $valido,
           );                
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;           
        }
      }
    } // Fin listar registros            

   // Mostrar periodos de acuerdo al tipo de nomina *********************************************************************************************
   public function listtnAction() 
   { 
      $form = new Formulario("form");   
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();   
        if ($request->isPost()) {            
           $data = $this->request->getPost();                    
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $d=new AlbumTable($this->dbAdapter);
           // Grupo de nomina
           $arreglo='';
           $datos = $d->getEmp(' and idGrup = '.$data->id); 
           foreach ($datos as $dat){
               $idc=$dat['id'];$nom=$dat['nombre'];
               $arreglo[$idc]= $nom;
            }              
           $form->get("idEmpM")->setValueOptions($arreglo);                         
        }
      }
      $valores = array("form" => $form );      
      $view = new ViewModel($valores);              
      $this->layout('layout/blancoB'); // Layout del login
      return $view;                 
   }
   
   // ELIMINAR NOMINA ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Gnomina($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d=new AlbumTable($this->dbAdapter); 
            $c=new Cesantias($this->dbAdapter); 
            // Consultar nomina
            $datos = $d->getGeneral1("Select idTnom, estado, idGrupo from n_nomina where id=".$id); 
            $idTnom = $datos['idTnom'];            
            $idGrupo = $datos['idGrupo'];            
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
               $connection = $this->dbAdapter->getDriver()->getConnection();
           $connection->beginTransaction();
               // REGISTRO LIBRO DE CESANTIAS
               //$c->delRegistro($id); 
               // Borrar tablas inferiores      

               $datos = $d->getGeneral1("select id from n_nomina_retro where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_retro where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_retro auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_retro_i where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_retro_i where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_retro_i auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_rete where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_rete where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_e_rete auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_pg_embargos where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_pg_embargos where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_pg_embargos auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_pg_primas_ant where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_pg_primas_ant where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_pg_primas_ant auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_primas where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_primas where idNom=".$id);
               if ( $datos['id'] > 0)  
                  $d->modGeneral("alter table n_primas auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_cesantias where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_cesantias where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_cesantias auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_a where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_a where idNom=".$id);
               if ( $datos['id'] > 0)   
                   $d->modGeneral("alter table n_nomina_e_a auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_i where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_i where idNom=".$id);
               if ( $datos['id'] > 0)   
                   $d->modGeneral("alter table n_nomina_e_i auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_d_integrar where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d_integrar where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_nomina_e_d_integrar auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_d where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d where idNom=".$id); 
               if ( $datos['id'] > 0)  
                  $d->modGeneral("alter table n_nomina_e_d auto_increment = ".$datos['id'] ); 


               $datos = $d->getGeneral1("select id from n_nomina_e where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_nomina_e auto_increment = ".$datos['id'] ); 

               $datos = $d->modGeneral("delete from n_nomina where id=".$id); 
               $d->modGeneral("alter table n_nomina auto_increment = ".$id);
               $datos = $d->modGeneral("update n_grupos set activa=0 where id=".$idGrupo);// Activar grupo de nuevo               
               
               $u->delRegistro($id);
               
               $connection->commit();
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }// Fin try casth   
            catch (\Exception $e) {
              if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                 $connection->rollback();
                   echo $e;
          } 
          /* Other error handling */
            }// FIN TRANSACCION                    
            
          }          
   }// Fin datos eliminar nomina


   // NOMINA A EXCEL 
   public function listexcelAction() 
   { 
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);  
        // CONSULTA DE DEVENGADOS CON CODIGOS
        $datos = $d->getGeneral("select distinct c.id, c.nombre 
                                from n_nomina a
                                  inner join n_nomina_e_d b on b.idNom = a.id 
                                  inner join n_conceptos c on c.id = b.idConc
                                where b.idCpres = 0 and c.codigo != ''
                                   and c.tipo=1 and a.estado = 1 
                                   order by c.codigo");
        $campoDevengados = '';
        foreach ($datos as $dat) 
        {
          $campoDevengados .= 'sum(if( c.idConc = '.$dat['id'].',c.devengado,0 )) as "'.$dat['nombre'].'",';
        }
        // CONSULTA DE DEVENGADOS SIN CODIGOS
        $datos = $d->getGeneral("select distinct c.id, c.nombre 
                                from n_nomina a
                                  inner join n_nomina_e_d b on b.idNom = a.id 
                                  inner join n_conceptos c on c.id = b.idConc
                                where b.idCpres = 0 and c.codigo = ''
                                   and c.tipo=1 and a.estado = 1 
                                   order by c.codigo");
        $campoDevengadosB = '';
        foreach ($datos as $dat) 
        {
          $campoDevengadosB .= 'sum(if( c.idConc = '.$dat['id'].',c.devengado,0 )) as "'.$dat['nombre'].'",';
        }        
        // CONSULTA DE DEDUCIDOS SIN PRESTAMOS
        $datos = $d->getGeneral("select distinct c.id, c.nombre 
                                from n_nomina a
                                  inner join n_nomina_e_d b on b.idNom = a.id 
                                  inner join n_conceptos c on c.id = b.idConc
                                where b.idCpres = 0 
                                   and c.tipo=2 and a.estado = 1 
                                   order by c.codigo");
        $campoDeducidos = '';
        foreach ($datos as $dat) 
        {
          $campoDeducidos .= 'sum(if( c.idConc = '.$dat['id'].',c.deducido,0 )) as "'.$dat['nombre'].'",';
        } // FINCONSULTA DE DEDUCIDOS SIN PRESTAMOS               

        //  CONSULTA DE PRESTAMOS      
        $datos = $d->getGeneral("select distinct f.id, f.nombre  
                                from n_nomina a
                                  inner join n_nomina_e_d c on c.idNom = a.id 
                                  inner join n_prestamos_tn d on d.id = c.idCpres
                                  inner join n_prestamos e on e.id = d.idPres 
                                  inner join n_tip_prestamo f on f.id = e.idTpres 
                              where a.estado = 1 order by f.id ");
        // Armar id para prestamos         
        $campoPresta = '';
        foreach ($datos as $dat) 
        {
          $campoPresta .= 'sum(if( f.id = '.$dat['id'].',c.deducido,0 )) as "'.$dat['nombre'].'",';
        }
        //echo $campoPresta;

        // CONSULTA MAESTRA 
        $datos = $d->getGeneral("select g.CedEmp, g.nombre, g.apellido, b.dias,
                    ".$campoDevengados." 
                    ".$campoDevengadosB." 
                    ".$campoDeducidos." 
                    ".$campoPresta."  
                 a.id as idNom     
                from n_nomina a
                  inner join n_nomina_e b on b.idNom = a.id
                  inner join n_nomina_e_d c on c.idInom = b.id 
                  inner join a_empleados g on g.id = b.idEmp                   
                  left join n_prestamos_tn d on d.id = c.idCpres
                  left join n_prestamos e on e.id = d.idPres 
                  left join n_tip_prestamo f on f.id = e.idTpres 
                  where a.estado=1 
                  group by b.idEmp  
                  order by f.id");
        $c = new ExcelFunc();
        //print_r($datos);
        $c->listexcel($datos, "Nomina");

        $valores = array("datos" => $datos );      
        $view = new ViewModel($valores);              
        return $view;                         
      }
    }// FIN NOMINA A EXCEL

    // LISTAR NOMINAS ARCHIVOS ********************************************************************************************
    public function listarAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      $g = new Gnominag($this->dbAdapter);
      $a = new Alertas($this->dbAdapter);

      $form = new Formulario("form");

      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "form"      =>  $form,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
        "datos"     =>  $g->getListNominas("a.estado in (2)"), // Listado de nominas 
        "datEmp"    =>  $d->getGeneral("select c.CedEmp, c.nombre, c.apellido , d.fechaI, d.fechaF  
                              from n_nomina a 
                                  inner join n_nomina_e b on b.idNom = a.id  
                                  inner join a_empleados c on c.id = b.idEmp 
                                  inner join n_emp_contratos d on d.idEmp = c.id and d.estado = 0 # Traer contrato activo 
                            where a.estado=2"),
        "ttablas"   => "id,Nomina, Periodo, Empleados , Prenomina, Resumida, Retefuente",
        'url'       => $this->getRequest()->getBaseUrl(),
        "lin"       => $this->lin,        
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
    } // Fin listar registros     
}
