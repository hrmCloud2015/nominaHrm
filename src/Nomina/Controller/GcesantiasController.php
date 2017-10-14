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


class GcesantiasController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/gcesantias/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Cesantias activas"; // Titulo listado
    private $tfor = "Generación de cesantias"; // Titulo formulario
    private $ttab = " Periodo,  Grupo, Empleados ,Estado, Personal,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
        "datos"     =>  $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup, c.nombre as nomtcale, 
                                        d.nombre as nomtnom,a.estado,a.numEmp
                                        from n_nomina a inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id inner join n_tip_nom d on d.id=a.idTnom 
                                        where a.estado in (0,1)"),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin,
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
   // Editar y nuevos datos *********************************************************************************************
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Grupo de nomina
      $arreglo='';
      $datos = $d->getGrupo(' and activa=0'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idGrupo")->setValueOptions($arreglo);                         
      // Tipos de calendario
      $arreglo='';
      $datos = $d->getTnom(' and activa=0'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipo'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);                                                 
      
      // Empleados
      $arreglo='';
      $datos = $d->getEmp(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);                                                 
      //       
      $datos=0;
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'datos'   => $datos,  
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
            $form->setValidationGroup('tipo'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u = new Gnomina($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Consultar fechas del calendario
                $a = new NominaFunc($this->dbAdapter);
                $d = new AlbumTable($this->dbAdapter);
                $c = new Gnominac($this->dbAdapter);
                $g = new Gnominag($this->dbAdapter);
                // Ubicar datos del tipo de calendario                
                $datos = $d->getCalendario($data->tipo);                    
                //--
                $dias   = $datos['valor'];
                $idCal  = $datos['idTcal'];
                
                if ($data->tipo==1)// Nominas normales, quincenas, mes
                {
                   // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);                                       
                
                   // Verificar en movimiento del calendario
                   $datos2 = $d->getGeneral1("select fechaI, fechaF from n_tip_calendario_d 
                                            where idTnom = ".$data->tipo." and idGrupo=".$data->idGrupo." 
                                            and estado=0 order by fechaI limit 1");           
                   $fechaI = $datos2['fechaI'];// se toma la fecha de movimiento de calendario reemplazando la fecha de inicio                     
                   $fechaF = $datos2['fechaF'];// se toma la fecha de movimiento de calendario reemplazando la fecha de inicio                                        
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                }
                if ($data->tipo==3)// Cesantias
                {
                	// Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                	$g->getGenerarP($data->tipo, $data->idGrupo, $idCal);
                
                	// Verificar en movimiento del calendario
                	$datos2 = $d->getGeneral1("select fechaI, fechaF from n_tip_calendario_d
                                            where idTnom = ".$data->tipo." and idGrupo=".$data->idGrupo."
                                            and estado=0 order by fechaI limit 1");
                	$fechaI = $datos2['fechaI'];// se toma la fecha de movimiento de calendario reemplazando la fecha de inicio
                	$fechaF = $datos2['fechaF'];// se toma la fecha de movimiento de calendario reemplazando la fecha de inicio
                	$idGrupo = $data->idGrupo;
                	$idEmp = '';
                }                
                if ($data->tipo==4)// Liquidacion fin de contrato
                {         
                   $datos2 = $d->getGeneral1("select a.fechaF 
                                from n_nomina a
                                inner join n_nomina_e b on b.idNom = a.id
                                where a.estado = 2 and b.idEmp = ".$data->idEmp."
                                order by a.fechaF desc limit 1");           
                   $fechaI = $datos2['fechaF'];
                   $fechaF = '2014-07-10';// se toma la fecha de movimiento de calendario reemplazando la fecha de inicio                                        
                   $idGrupo = 1;
                   $idEmp = $data->idEmp;
                }
                // INICIO DE TRANSACCIONES
                $connection = null;
                try {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
   	            $connection->beginTransaction();                
                    //
                    $id = $u->actRegistro($data,$fechaI,$fechaF,$idCal,$dias,$idGrupo);
                    // Inactiva tipo nomina 
                    $con2 = 'update n_grupos set activa=1 where id='.$data->tipo ;     
                    $d->modGeneral($con2);                 
                    // Buscar id de grupo
                    $datos = $d->getGeneral1("Select idGrupo from n_nomina where id=".$id); 
                    $idg=$datos['idGrupo'];
                    // Generar empleados 
                    
                    $g->getNominaE($id,$idg, $idEmp);  // Generacion de empleados  
                    
                    // Insertar incapacidades empleados 
                    $g->getIncapaEmp($id);
                    
                    // VALIDAR FECHA DE INGRESO DEL EMPLEADO                    
                    $datIng = $d->getGeneral("Select a.id, ( DATEDIFF( b.fechaF, c.fecIng ) +1 ) as diasH 
                                  from n_nomina_e a
                                       inner join n_nomina b on b.id = a.idNom 
                                       inner join a_empleados c on c.id = a.idEmp 
                                       where b.id = ".$id." and c.fecIng > b.fechaI");        
                    foreach($datIng as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = $dat['diasH'] ;                                
                        $d->modGeneral("update n_nomina_e set dias=".$dias." where id=".$iddn);                         
                    } // Fin validacion fecha de ingreso del empleado
                    
                    // VALIDAR FECHA DE CONTRATO EMPLEADOS
                    $datIng = $d->getGeneral("select a.id, ( DATEDIFF(  d.fechaF , b.fechaI ) +1 ) as diasH 
                                  from n_nomina_e a
                                       inner join n_nomina b on b.id = a.idNom 
                                       inner join a_empleados c on c.id = a.idEmp 
                                       inner join n_emp_contratos d on d.idEmp = c.id 
                                       where b.id = ".$id." and ( ( d.fechaF >= b.fechaI ) and ( d.fechaF <= b.fechaF ) )
                                       and d.estado=0");        
                    foreach($datIng as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = $dat['diasH'] ;                                
                        $d->modGeneral("update n_nomina_e set dias=".$dias." where id=".$iddn);                         
                    } // Fin validacion fecha de egreo del empleado
					
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
                              $d->modGeneral("update n_nomina_e set dias = ".$dias.", diasVac=0, actVac=0  where id=".$iddn);
							  $d->modGeneral("update a_empleados set vacAct = 2  where id=".$idEmp); // Regreso de vacaciones
						   }
						   else {
							  $d->modGeneral("update n_nomina_e set dias = ".$dias.", diasVac=".$diasVac."  where id=".$iddn); 
						   }  							
                        }       
                    } // Fin validacion vacaciones
                    // VALIDAR INCAPACIDADES -----------------------------------
                    $datInc=$g->getIncapacidades($id); // Extraer datos de la incapacidad del empleado si tuviera
                    foreach($datInc as $dat)
                    {
                        $iddn = $dat['id'];					 
						
                        $dias    = $dat['dias'];
                        $diasEnt = $dat['diasEnt'];						
                        $diasAp  = $dat['diasAp'];
                        $diasDp  = $dat['diasDp'];						
                        
			if ( $dat['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                           $diasAp = 0;
                        
                        if ( ( $diasAp + $diasDp ) > ( $dias ) )
                            $diasI = 0;
			else 
  			    $diasI = ( $diasAp + $diasDp ) ;// Dias de incapacidad
  						  						                                
  			$dias = $dias - $diasI;		  						                                
                        $d->modGeneral("update n_nomina_e set dias=".$dias.", idInc = 1, diasI=".$diasI." , pgIncEmp = 0, pgIncEnt = 0 where id=".$iddn);
						# Se marca idInc con una 1 para saber que ese empleado tiene incapacidad registrada 
                                                    
                    } // Fin validacion incapacidad
                    
                    // VALIDAR AUSENTISMOS -----------------------------------
                    $datAus = $g->getAusentismos($id); // Extraer datos del ausentismos del empleado si tuviera no remunerado
                    foreach($datAus as $dat)
                    {
                        $iddn = $dat['id'];
						$idEmp = $dat['idEmp'];
                        $dias = $dat['diasH'] - $dat['diasAus'];# Dias de ausentismos no remunerado
                        $aus = 1;                    
						if ($dias > 0) // Si regreso en el priodo se activa a empleado de neuvo 
						{
						   $aus = 0;// Se sca el estado de ausentismo;	
						}                    
                        $d->modGeneral("update n_nomina_e set idAus=".$dat['idAus'].", aus=".$aus.", dias=".$dias." where id=".$iddn);						
						
						if ($dias > 0) // Si regreso en el priodo se activa a empleado de nuevo 
						{
						   $d->modGeneral("update a_empleados set idAus = 0 where id=".$idEmp );   
						}
						                         
                    } // Fin validacion ausentismos
                    
                    $connection->commit();
                    
                    $this->flashMessenger()->addMessage('');
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
   
   // Eliminar dato ********************************************************************************************
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
               $datos = $d->modGeneral("delete from n_pg_embargos where idNom=".$id);                
               $datos = $d->modGeneral("delete from n_pg_primas_ant where idNom=".$id);                
               $datos = $d->modGeneral("delete from n_primas where idNom=".$id);                
               $datos = $d->modGeneral("delete from n_cesantias where idNom=".$id); 
               $datos = $d->modGeneral("delete from n_nomina_e_i where idNom=".$id);
               $datos = $d->modGeneral("delete from n_nomina_e_d_integrar where idNom=".$id);
               $datos = $d->modGeneral("delete from n_nomina_e_d where idNom=".$id); 
               $datos = $d->modGeneral("delete from n_nomina_e where idNom=".$id); 
               $datos = $d->modGeneral("delete from n_nomina where id=".$id); 
               $datos = $d->modGeneral("update n_grupos set activa=0 where id=".$idGrupo);// Activar grupo de nuevo
               $d->modGeneral("alter table n_nomina auto_increment = ".$id);// Activar tipo de nomina
               
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
   }
   //----------------------------------------------------------------------------------------------------------
   // GENERACION NOMINA --------------------------------------------------------------------------------------
   //----------------------------------------------------------------------------------------------------------
   
    public function listgAction()
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
                       	echo 'base '.$dato["valor"].'<br /> ';
                       	echo 'base '.$datoC["diasCes"].'<br /> ';                       	                   	
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
                   //echo $dev.'<br />';
                   // Llamado de funion -------------------------------------------------------------------
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId);              
                   $idInom = (int) $idInom;                   
                   // INTERESE DE CENSATIAS 
                   $dev     = ( ( $valor * ( 12/100 ) )/360 ) * $datoC['diasCes']; // Devengado
                   $idCon   = 195; //
                   $obId    = 0; // 1 para obtener el id insertado
                   if ($valor > 0)
                   {
                       // Llamado de funion -------------------------------------------------------------------
                       $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId);                             
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
    

    
    
    
    
    
}
