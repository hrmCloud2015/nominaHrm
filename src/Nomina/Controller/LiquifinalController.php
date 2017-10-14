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


class LiquifinalController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/liquifinal/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Liquidación final"; // Titulo listado
    private $tfor = "Generación de la nomina"; // Titulo formulario
    private $ttab = "Tipo de nomina, Periodo, Tipo de calendario, Grupo, Empleados ,Estado, Personal, Prenomina, Retefuente,Regenerar,Eliminar"; // Titulo de las columnas de la tabla
    
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
                                        d.nombre as nomtnom,a.estado,a.numEmp, d.tipo 
                                        from n_nomina a
                                        inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
                                        inner join n_tip_nom d on d.id=a.idTnom 
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
      $datos = $d->getGrupo(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }       
      if ( $arreglo != '' )       
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
                
                //if ($data->tipo==1)// Nominas normales, quincenas, mes
                //{
                   // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);                                       
                
                   // Verificar en movimiento del calendario
                   $datos2 = $d->getGeneral1("select fechaI, fechaF 
                                            from n_tip_calendario_d 
                                            where idTnom = ".$data->tipo." and idGrupo=".$data->idGrupo." 
                                            and estado=0 order by fechaI limit 1");           
                   $fechaI = $datos2['fechaI'];// se toma la fecha de movimiento de calendario reemplazando la fecha de inicio                     
                   $fechaF = $datos2['fechaF'];// se toma la fecha de movimiento de calendario reemplazando la fecha de inicio                                        
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                //}
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
                                order by a.fechaF desc limit 1"); // Se buscar ultimo periodo pagado de nomina           
                   $fechaI = $datos2['fechaF'];
                   $fechaF = '2014-07-10';// Fecha finde contrato segun digitacion en text
                   $idGrupo = 1;
                   $idEmp = $data->idEmp;
                }
                // INICIO DE TRANSACCIONES
                $connection = null;
                try {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
   	                $connection->beginTransaction();                
                    // Generacion tabla de n_nomina  cabecera
                    $id = $u->actRegistro($data,$fechaI,$fechaF,$idCal,$dias,$idGrupo);
                    // Inactiva tipo nomina 
                    $con2 = 'update n_grupos set activa=1 where id='.$data->tipo ;     
                    $d->modGeneral($con2);                 
                    // Buscar id de grupo
                    $datos = $d->getGeneral1("Select idGrupo from n_nomina where id=".$id); 
                    $idg=$datos['idGrupo'];
                    // Generar empleados 
                    
                    $g->getNominaE($id,$idg, $idEmp);  // ***------------------------------------- GENERACION DE EMPLEADOS
                    
                    // Insertar incapacidades empleados 
                    $g->getIncapaEmp($id, "n_incapacidades"); // Incapacidades
                    $g->getIncapaEmp($id, "n_incapacidades_pro"); // Prorrogra en incapacidades
                    
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
                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1 where id=".$iddn);                         
                    } // Fin validacion fecha de ingreso del empleado
                    
                    // VALIDAR FECHA DE CONTRATO EMPLEADOS
                    $datIng = $g->getContratosActivos($id);        
                    foreach($datIng as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = $dat['diasH'];                                
                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1, contra=".$dat['contra']." where id=".$iddn);                                                 
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
                    } // Fin validacion vacaciones

                    $datGen = $d->getConfiguraG(''); // Configuraciones de incapaciades 
                    // VALIDAR INCAPACIDADES -----------------------------------
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
                        if ( $datGen['incAtrasada']==0)
                           $diasAp  = 0;

			                  if ( $dat['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                           $diasAp = 0;
                        
                        if ( ( $diasAp + $diasDp ) > ( $dias ) )
                            $diasI = 0;
			                  else 
  			                    $diasI = ( $diasAp + $diasDp ) ;// Dias de incapacidad
  						  				
                        if ( $diasI > 15)
                             $diasI = 0; 

                        $dias = $dias - $diasI;                                                    
                        // Empanada para cubrir retornos de vacaciones e incapaciades de mas o son 15 dias exactos de incapacidad
                        if (  ( $diasAp + $diasDp )==15 )
                        {
                            $diasI = 15; 
                            $dias = 0; 
                        }  			                
                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diasI=".$diasI." where id=".$iddn);
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
                    

                    // VALIDAR SI TIENE DIAS DIFERENTES EN UNA NOMINA YA LIQUIDAD                
                    $datIng = $d->getGeneral("Select diasLab, idEmp  
                      from n_nomina_nov 
                      where idConc = 0 and fechaI='".$fechaI."' and fechaF='".$fechaF."' and idCal = ".$idCal." and idGrupo=".$idGrupo." and estado=0");        
                    foreach($datIng as $dat)
                    {
                        $idEmp = $dat['idEmp'] ;                   
                        $dias  = $dat['diasLab'] ;                                
                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1 where idEmp=".$idEmp." and idNom=".$id );                         
                    } // Fin validacion fecha de ingreso del empleado


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

      $dato = $d->getGeneral1("select b.tipo, b.id from n_nomina a 
            inner join n_tip_nom b on b.id=a.idTnom where a.id=".$id); // Busco el tipo de nomina para generarla (General, Censatias, Primas, Vacaciones)
            
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
        "tipo"    => $dato['tipo'], // Tipo de calendari para esta nomina 
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
         $e = new EmbargosN($this->dbAdapter);                 
                 
         // Buscar id de grupo
         $datos  = $d->getPerNomina($id); // Periodo de nomina
         $idg    = $datos['idGrupo'];         
         $fechaI = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];         
         $sw=1; // Solo para probar mas rapido ojo                                
                  
     // INICIO DE TRANSACCIONES
     $connection = null;
     try {
         $connection = $this->dbAdapter->getDriver()->getConnection();
	      	$connection->beginTransaction();

        if ($sw==1) 
        {

         // REEMPLAZOS DE EMPLEADOS
         $datos2 = $g->getReemplazos($id,$fechaI,$fechaF);// Reemplazos 
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
             $calc    = 0;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId);              
         } // FIN REGISTRO DE NOVEDADES                     

         // ( REGISTRO DE NOVEDADES ) ( n_novedades ) 
         $datos2 = $g->getRnovedades($id,$fechaI,$fechaF);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
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
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId);              
         } // FIN REGISTRO DE NOVEDADES                     

         // ( REGISTRO DE NOVEDADES ) ( n_nomina_nove ) Guardadas en las novedades anteriores
         $datos2 = $g->getRnovedades($id,$fechaI,$fechaF);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              

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
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             ///$n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId);              
         } // FIN REGISTRO DE NOVEDADES                              
         
         // PRIMA DE ANTIGUEDAD
         $datos = $d->getPrimaAnt();
         $con = '';
         foreach($datos as $dat)
         {
           $ano = $dat['ano']; 
           $mes = $dat['mes']; 
           if ( $dat['anual'] == 1 )
              $datos2 = $g->getDiasPantiA($id, $ano);// Primas por antiguedad anual 
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
             //echo $formula;
             // Llamado de funion -------------------------------------------------------------------
             if ( $dato['diaI'] == 1) // 0 no esta dentro del periodo, 1 esta dentro del periodo
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
                $idInom = (int) $idInom;                   
                // GUARDAR REGISTRO PAGO PRIMA DE ANTIGUEDAD
                $c->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id, $ano, $dat['id']);
              }
            }
            } 
         } // FIN PRIMA DE ANTIGUEDAD      
         
        // INCAPACIDADES
        $datos2 = $g->getIncapNom($id);// ( n_nomina_e_i ) 
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
             // Convertir a horas
             if ( $dato["tipInc"] == 1 )// Empresa
             {
                 $horas   = $dato["diasEmp"] * 8; 
             }
             if ( $dato["tipInc"] == 2 )// Entidad esps u otra
             {
                 $horas   = $dato["diasEnt"] * 8;
             }             
             // Llamado de funion -------------------------------------------------------------------
             if ($horas>0)
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId); 
			          $idInom = (int) $idInom;
			          $d->modGeneral("update n_nomina_e_d set idInc = ".$dato['idInc']." where id=".$idInom);      			                

                $d->modGeneral("update n_nomina_e_i set diasEmp = ".$dato["diasEmp"]." where idNom=".$id." and idInc=".$dato['idInc']);                           
             }
         } // FIN INCAPACIDADES 

        // INCAPACIDADES PRO
        $datos2 = $g->getIncaPpNom($id);// ( n_nomina_e_i ) 
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
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId); 
                $idInom = (int) $idInom;
                $detalle = '';
                if ($diasInc > 180)
                   $detalle = 'Este empleado ha sobrepasado los 180 dias de incapacidad. Total '.$diasInc ;

                $d->modGeneral("update n_nomina_e_d set detalle='".$detalle."', idInc = ".$dato['idInc']." where id=".$idInom);                           
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
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $detalle = $dato["nomCon"].' ( del '.$dato["fechai"].' al '.$dato["fechaf"].' )';
             // Llamado de funion -------------------------------------------------------------------
             $d->modGeneral("insert into n_nomina_e_d (idNom, idINom, idConc, idCcos, detalle ) 
             values (".$id.",".$iddn.",".$idCon.",".$idCcos.",'".$detalle."' )");
			               
         } // FIN AUSENTISMOS                                          
         // VACACIONES GENERACION
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
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac, $obId);                                                  
             
         } // FIN VACACIONES      

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
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
           }
         } // FIN CONCEPTOS AUTOMATICOS POR PERIODO                           
         
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
             // Llamado de funcion -------------------------------------------------------------------
             if ($dato["actVac"]==0)
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                          
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
             // Llamado de funcion -------------------------------------------------------------------
                 $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
                 $idInom = (int) $idInom;                   
                 $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                           

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
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
             $idInom = (int) $idInom;                   
             $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                       
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
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
             $idInom = (int) $idInom;                   
             $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                                    

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
                   $formula = ' ($diasLab+$diasVac+$diasInca+$diasMod)*'.$valor; // Concatenan para armar la formula
             }    
             //echo 'ifo  '.$formula;
             // Llamado de funion -------------------------------------------------------------------
             if ( ($dato['fecAct']==0) or ($dato['fecAct']==1) )
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 2,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);             
             }
         } // FIN OTROS AUTOMATICOS POR EMPLEADOS
         
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
             // Llamado de funion -------------------------------------------------------------------
             if ($dato["actVac"]==0)
             {
                $sw = 0;
               if ( ($dato["idFpen"]==1) and ( $dato["fondo"]==2 ) ) // Si el concepto de pension no aplica no debe generarlo
                   $sw = 1;             
               if ($sw == 0)
                  $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);              
              }
         } // FIN CONCEPTOS AUTOMATICOS         

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
                // Validar si hay una cuota modificada en la nomina activa
                if ( $dato['valorPresN'] > 0 )
                   $ded     = $dato["valorPresN"];// Deducido         

                // Llamado de funcion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 4,$dev,$ded,$idfor,$diasLabC,$idCpres,1,$conVac,$obId);                                           
                $idInom = (int) $idInom;                   
                // Colocar saldo del prestamo
                $d->modGeneral("update n_nomina_e_d set nitTer='".$nitTer."' where id=".$idInom);                
              }  
           }
        }
         // FONDO DE SOLIDARIDAD
         $datos2 = $g->getSolidaridad($id);   
		 //print_r($datos2);      
         foreach ($datos2 as $dato)
         {             
             $ide     = $dato['idEmp'];   // Id empleado
             $ano     = $dato['ano'];   // Año
             $mes     = $dato['mes'];   // Mes                           
             //echo $ide.' <br />';
             $dat     = $n->getSolidaridad($ano, $mes, $ide); // Extraer los datos de solidaridad de la funcion
             //if ($ide==23) 
                //print_r($dat);
             //echo $dat['id'].'<br />';
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
             // Llamado de funion -------------------------------------------------------------------
             if ($ded>0)
                $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);
                           
         } // FIN FONDO DE SOLIDARIDAD
                 
         // VACACIONES FONDO DE SOLIDARIDAD PERIODO DE DESPUES DEL MES ACTUAL
         $datos2 = $g->getVacacionesG($id);// Insertar vacaciones 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {        
             if ( $dato['diaI'] > 15) // Validacion momentaena pero debe tener un analisis mas delicado sobre el periodo
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
               $dat     = $n->getSolidaridadv($id, $ide); 
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
                  // Llamado de funion -------------------------------------------------------------------
                  $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);                               
               }
            }// Fin validacion periodo de salida para calcular fondo
             // -------             
         } // FIN SOLIDARIDAD EN VACACIONES QUE TOMAN UN PERIODO FUERA DEL MES ACTUAL
        // EMBARGOS
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
			       // Ejecutar embargos			 
			       if ( $dato["formula"] != '')
			       {
			 	        $con = '"'.$dato["formula"].'"';	
			 	        eval("\$con =$con;");
				 
			 	        $datVal = $d->getGeneral1($con);			
				        $ded   = $datVal['valor'];
				        //echo $dato["formula"].' : '.$ded.'<br />';
                // Llamado de funion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId);              
                // Guardar en tabla de embargos para cruzar documentos
                $idInom = (int) $idInom;                   
                // Colocar saldo del embargo
                $d->modGeneral("update n_nomina_e_d set idRef=".$dato['idEmb'].",  nitTer='".$dato['nitTer']."', saldoPact = ".$dato["pagado"]." where id=".$idInom);
                // GUARDAR REGISTRO DE EMBARGOS
                if ($idInom>0)
                   $e->actRegistro($ide, $ded, $idInom , $id, $dato['idEmb']);                
			        }
             
         } // FIN EMBARGOS                                   
         
        // RETENCION DE LA FUENTE
        $r = new Retefuente($this->dbAdapter);
        $datos2 = $g->getRetFuente($id);// 
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
			       $ded = $r->getReteConc($iddn, $ide); // Procedimiento para guardar la retencion
			 
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId);              
                      
         } // FIN RETENCION DE LA FUENTE                                   
                  

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
         
         $datos = $d->getPerNomina($id); // Periodo de nomina

         $idg    = $datos['idGrupo'];         
         $fechaI = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];         
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
              $idCon   = 214;   // Concepto
              $datPr   = $g->getDiasPrima($ide,$fechaI,$fechaF); // Valor de prima a pagar
              $dev     = $datPr["basePromedio"] / $dato['diasPrima'] ;     // Devengado  Dias trabajados en el semestre
              $ded     = 0;     // Deducido         
              $idfor   = 99;   // Id de la formula    
              $diasLabC= 0;   // Dias laborados solo para calculados 
              $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
              $obId    = 1; // 1 para obtener el id insertado
              // Llamado de funion -------------------------------------------------------------------
              $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId);               
              $idInom = (int) $idInom;                   
              // LIBRO DE PRIMAS
              if ($dev > 0)
              {
                  // REGISTRO LIBRO DE PRIMAS
                  $c->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id);
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
            try {
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
    
    
}
