<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Principal\Model\IntegrarFunc; // Funciones para integrar nomina

use Nomina\Model\Entity\Gnominac; // Procesos especiales apra generacion de nomina
use Principal\Model\EmailFunc;
use Principal\Model\Gnominag; // Procesos generacion de automaticos

class CnominaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/cnomina/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Cierre de nominas activas"; // Titulo listado
    private $tfor = "Cierre de nomina"; // Titulo formulario
    private $ttab = ",id,Nomina, Periodo, Nomina, Nomina resumida, Retefuente,Bancos,Cheques,Cerrar nomina,Notificación"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $g = new Gnominag($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "datos"     =>  $g->getListNominas("a.estado in (1, 2)"), // Listado de nominas 
        "datos1"     =>  $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup,
                            c.nombre as nomtcale, d.nombre as nomtnom,a.estado, case when a.idTnomL > 0 then 'LIQUIDACION FINAL' else'' end as tipNom, a.idTnomL   
                                        from n_nomina a 
                                        inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
                                        inner join n_tip_nom d on d.id=a.idTnom 
                                    where a.estado in (1,2) and
                                        a.idTnom=1 order by a.id desc"),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros  

   //----------------------------------------------------------------------------------------------------------
   // CIERRE DE NOMINA --------------------------------------------------------------------------------------
   //----------------------------------------------------------------------------------------------------------
    public function listpAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          
          // Cambiar estado de nomina
          $con2 = 'update n_nomina set estado=2 where id='.$id ;     
          $d->modGeneral($con2);           
          // Consulta del tipo de nomina 
          $datos = $d->getGeneral1("Select * from n_nomina where id=".$id); 
          $fechaI = $datos['fechaI'];
          $fechaF = $datos['fechaF'];
          $idTnom = $datos['idTnom'];
          // Se activa el tipo nomina      
          $con2 = 'update n_tip_nom set activa=0 where id='.$datos['idTnom'];           
          $d->modGeneral($con2);                                     
          // Cerrar novedades 
          $con2 = "update n_novedades a 
               inner join n_nomina c on c.idIcal = a.idCal 
               set a.estado=1 where c.id=".$id;     
          $d->modGeneral($con2);                                           
          //---------------------------------------------------------
          $c=new Gnominac($this->dbAdapter);
          // Verificar en movimiento del calendario
          $datos2 = $c->getRegistroId( $datos['idTnom'] ,$datos['idGrupo'] , $datos['idCal']);                                               
          // Mover calendario 
          $c->actRegistro( $datos['idTnom'] , $datos['idGrupo'] , $datos['idCal'] ,$datos['fechaI'] , $datos['fechaF'] ,1,1 );                               
          // Registrar descuentos de pagos
          $d->modGeneral("update n_prestamos_tn a 
                          inner join n_nomina_e_d b on b.idCpres=a.id
                          set a.pagado = a.pagado + b.deducido    
                          where b.idNom=".$id);   

           if ( ($idTnom==1) or ($idTnom==5) ) // Solo nominas normales o de vacaciones 
           {                                                                             
              // Registrar pago de vacaciones
              $d->modGeneral("update n_vacaciones a 
                          inner join n_nomina_e b on b.idVac=a.id 
                          set a.estado = 2 
                          where b.idNom=".$id);                                                            
              // Registrar periodo de vacaciones pagado
              $d->modGeneral("update n_libvacaciones b
                             inner join n_vacaciones_p a on b.id = a.idPvac
                             inner join n_nomina_e c on c.idVac = a.idVac
                             set b.diasP = a.dias, b.diasD = a.diasDin  
                             where c.idNom = ".$id);                                                            

              // Activar salida a vacaciones del empleado
              $d->modGeneral("update a_empleados a 
                          inner join n_nomina_e b on b.idEmp=a.id and b.idVac=a.idVac
                          set a.vacAct = 1
                          where a.vacAct = 0 and b.idVac>0 and b.idNom=".$id);                                                            
              // Activar regreso a vacaciones del empleado
              $d->modGeneral("update a_empleados a 
                         inner join n_nomina_e b on b.idEmp=a.id and b.idVac=a.idVac
                         set a.vacAct = 0
                         where a.vacAct = 2 and b.idVac>0 and b.idNom=".$id);                                                                  
          }
          // Activar regreso de incapacidad empleado pagos por nomina
          $d->modGeneral("update a_empleados c 
                         inner join n_nomina_e_i a on a.idEmp = c.id 
                         inner join n_nomina b on b.id=a.idNom 
                         inner join n_incapacidades d on d.id=a.idInc 
                         inner join n_tipinc e on e.id = d.idInc  
                         set c.idInc=0   
                         where e.completa=0 and a.idNom = ".$id." and a.idInc>0 and d.fechaf <= b.fechaF  ");// Si la fecha fin de incapacidad es menor que 
                         //la fecha fin de nomina sale de la incapacidad          
                         
          // Activar regreso de incapacidad empleado pago completo (Eje Maternidad)
          $d->modGeneral("update a_empleados c 
                         inner join n_nomina_e_i a on a.idEmp = c.id 
                         inner join n_nomina b on b.id=a.idNom 
                         inner join n_incapacidades d on d.id=a.idInc 
                         inner join n_tipinc e on e.id = d.idInc  
                         set c.idInc=0   
                         where e.completa=1 and a.idNom = ".$id." and a.idInc>0 ");// Si la fecha fin de incapacidad es menor que 
                         //la fecha fin de nomina sale de la incapacidad          

          // Reportar incapacidades
          $d->modGeneral("update n_incapacidades a
                     inner join n_nomina_e_i b on b.idInc = a.id 
                     set a.reportada=1, a.diasEmp = b.diasEmp  
                     where b.tipo = 0 and b.idNom=".$id);                                                                      

          // Reportar incapacidades prorroga
          $d->modGeneral("update n_incapacidades_pro a
                     inner join n_nomina_e_i b on b.idInc = a.id 
                     set a.reportada=1 
                     where b.tipo = 1 and b.idNom=".$id);                                                                      

          // Reportar ausentismos
          $d->modGeneral("update n_ausentismos a
                     inner join n_nomina_e_a b on b.idAus = a.id 
                     set a.reportada=1 
                     where  b.idNom = ".$id);                                                                      

          // Reportar reemplazos
          $d->modGeneral("update n_reemplazos a
                     inner join n_nomina_e_d b on b.idRem = a.id 
                     set a.reportada=1 
                     where  b.idNom = ".$id);                                                                      

          // Registrar descuentos en embargos 
          $d->modGeneral("update n_embargos a 
                          inner join n_nomina_e_d b on b.idRef = a.id
                          set a.pagado = a.pagado + b.deducido    
                          where b.idNom=".$id);
          // Cuotas en novedades por cuotas
          $d->modGeneral("update n_nomina_e_d a
                        inner join n_novedades b on b.id = a.idInov 
                        inner join n_novedades_cuotas c on c.idInov = b.id
                  set c.pagado = if(a.devengado >0, a.devengado, a.deducido ) 
                  where a.idNom = ".$id." and a.idInom > 0 and b.porCuotas > 0 ");

          // Activar grupo 
          $d->modGeneral("update n_grupos a
             inner join n_nomina b on b.idGrupo = a.id
             set a.activa=0 where  b.id =".$id);                                                                      

           if ($idTnom==1) 
           {
              // Inactivar empleado por terminacion de contrato
              $d->modGeneral("update a_empleados a  
                         inner join n_nomina_e b on b.idEmp = a.id 
                         inner join n_nomina c on c.id = b.idNom 
                         set a.estado=1 , a.activo=1 
                         where b.contra = 2 and b.idNom = ".$id);                                                                      
           }
          // Cerrar cesantias
          $d->modGeneral("update n_cesantias set estado = 2 where idNom=".$id);                                                                      
          // Cerrar primas
          $d->modGeneral("update n_primas set estado = 2 where idNom=".$id);                                                                                
          
          // Integrar nomina
          $c = new IntegrarFunc($this->dbAdapter);
          $pagoCes = 0; 
          $c->getIntegrarNomina($id, $id, $pagoCes ); //------------------ Integra nomina
          $d->modGeneral("update n_nomina_e_d_integrar set codCta = '' where error like '%Sin%' and idNom=".$id);// Borrar cuentas sin configurar

          // Paso nomina a vistas de nomina para enviar a ERP
          $datosP = $c->getIntegraNominaPaso($id, $pagoCes);

          foreach ($datosP as $dat) 
          {
             $formaPago = $dat['formaPago'];
             if ( $dat['tercero'] != '0') 
                $formaPago = $dat['tercero'];

             $nitTer = $dat['nitTer'];
             if ( $dat['nitEmb'] > 0 )
                $nitTer = $dat['nitEmb'];

             $registro = ltrim($dat['fechaI']).','.ltrim($dat['codCta']).','.($dat['nit']).','.ltrim($dat['debito']).','.$dat['credito'].','.$dat['idCcos'].','.$dat['detalle'].','.$dat['origen'].','.$formaPago.','.$dat['nitTer'].','.$dat['idNom'];
             $d->modGeneral("insert into n_integracion_paso 
                      (idNom, fechaI, fechaF, codigoCta, debito, credito, nit, nitTer, codCc, detalle, formaPago, tercero, embargo, origen )
                values(".$dat['idNom'].",'".$dat['fechaI']."','".$dat['fechaF']."','".$dat['codCta']."',".$dat['debito'].",".$dat['credito'].",
                '".$dat['nit']."', '".$nitTer."' ,'".$dat['idCcos']."','".$dat['detalle']."','".$dat['formaPago']."','".$dat['tercero']."','".$dat['embargo']."','".$dat['origen']."')");
           }            
           //-----------------------------------------------------------
           //-----Integracion de las provisiones cuando la nomina es la quincenal 
           //------------------------------------------------------------------
           if ( ($idTnom==1) or ($idTnom==5 ) )
           {
             // Integrar proviciones (2)
             $d->modGeneral("delete from n_provisiones_integrar_p where idNom=".$id);
      
             $datos = $d->getGeneral("select b.idEmp, a.id 
                   from n_nomina a
                     inner join n_nomina_e b on b.idNom = a.id
                      where a.id =".$id);  
             $f = new IntegrarFunc($this->dbAdapter);
             foreach($datos as $dat)
             {              
               $idEmp =$dat['idEmp'];     
               $idNom =$dat['id'];           
               // Cesantias
               $idProc = 5;
               $idProv = 1;
               $f->getIntProv($idProc, $idEmp, 'Cesantias', $idProv, $idNom );
               // Interes de cesantias
               $idProc = 5;
               $idProv = 2;
               $f->getIntProv($idProc, $idEmp, 'Intereses de cesantias', $idProv, $idNom );
               // Interes de cesantias
               $idProc = 4;
               $idProv = 3;
               $f->getIntProv($idProc, $idEmp, 'Primas', $idProv, $idNom );         
               // Interes de cesantias
               $idProc = 7;
               $idProv = 4;
               $f->getIntProv($idProc, $idEmp, 'Vacaciones', $idProv, $idNom );                  

               // Compensacion 
               $idProc = 16;
               $idProv = 11;
               $f->getIntProv($idProc, $idEmp, 'Compensacion', $idProv, $idNom );                                 
            }            
            $d->modGeneral("delete from n_provisiones_integrar_p where idEmp=0"); // Temmporal porque no deja insertar consltas sin resultados

          // Integracion proviciones 
          $datos = $d->getGeneral("select * from ( 
Select d.id as idNom, d.fechaI, d.fechaF, a.nombre as nomCon, a.codCtaD as codCta, round(a.valor,0) as debito, 0 as credito, 
i.nombre as nomCta, a.nitTerD as nit, c.codigo as idCcos, substring( ltrim(c.nombre), 1, 30) as nomCcos
, b.CedEmp, b.nombre as nomEmp, b.apellido, '  ' as error    
                        from n_provisiones_integrar_p a
                        left join n_plan_cuentas i on i.codigo = a.codCtaD # Cuenta debito                         
                        left join a_empleados b on b.id = a.idEmp 
                        left join n_cencostos c on c.id = b.idCcos
                        left join n_nomina d on d.id = a.idNom 
   where d.estado = 2 and d.idTnom = 1 and d.id = ".$id."   
union all
Select d.id as idNom, d.fechaI, d.fechaF, a.nombre as nomCon, a.codCtaC as codCta, 0 as debito, round(a.valor,0) as credito, 
i.nombre as nomCta, a.nitTerD as nit, ## Se coloco porque debe ser el nit del empleado siempre 
 c.codigo as idCcos, substring( ltrim(c.nombre), 1, 30) as nomCcos 
, b.CedEmp, b.nombre as nomEmp, b.apellido, '  ' as error    
                        from n_provisiones_integrar_p a
                        left join n_plan_cuentas i on i.codigo = a.codCtaD # Cuenta debito                         
                        left join a_empleados b on b.id = a.idEmp 
                        left join n_cencostos c on c.id = b.idCcos 
                        left join n_nomina d on d.id = a.idNom 
where d.estado = 2 and d.idTnom = 1 and d.id = ".$id."  
) as a 
order by idNom, CedEmp, nomCon, credito, debito"); 
          $fechaArchivo = '' ;
          $dat = $d->getGeneral1("select id from n_integracion_paso where  trans = 0 order by id desc limit 1");
          //$d->modGeneral("delete from n_integracion_paso where trans = 0 and tipoNom=1");
          //$d->modGeneral("alter table n_integracion_paso auto_increment=".$dat['id']);
          foreach ($datos as $dat) 
          {            
             $d->modGeneral("insert into n_integracion_paso 
                     (idNom, fechaI, fechaF, codigoCta, debito, credito, nit, nitTer, codCc, detalle, formaPago, tercero, embargo, origen, tipoNom )
               values(".$dat['idNom'].", '".$dat['fechaI']."','".$dat['fechaF']."','".$dat['codCta']."',".$dat['debito'].",".$dat['credito'].",
                '".$dat['nit']."', '".$dat['nit']."' ,'050011','".$dat['nomCta']."','','','','PROVISIONES', 1)");
          }              
           }// Fin validacion nomina quincenal integracion provisiones 

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
       
       // return new ViewModel();        
    } // Fin generar novedades automaticas


    // Envio de correos masivos pago de nomina
    public function listeAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      $form = new Formulario("form");
      $form->get("id")->setAttribute("value",$id);                       

      $valores=array
      (
        "form"    => $form,
        "titulo"    =>  "Envio de correos soporte de pago",
        "datos"     =>  $d->getGeneral("select a.idNom, b.CedEmp, b.nombre, b.apellido   
                              from n_nomina_e a
                                  inner join a_empleados b on b.id = a.idEmp 
                              where a.idNom = ".$id),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros      

   public function listemAction() 
   { 
      // $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      //$f = New EmailFunc($this->dbAdapter);    
      //$f->sendMail('$htmlBody', '$textBody', '$subject', '$from', '$to');   
      //$form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();              
        }        
      }  
      $id = $data->id; // Id de la nomina          
      
      $f = New EmailFunc($this->dbAdapter);      
      // Enviar correo
      // INICIO DE TRANSACCIONES
      //$connection = null; 
      try {      
        // $connection = $this->dbAdapter->getDriver()->getConnection();
         //$connection->beginTransaction();     
         $dia = date_create();
         $dia=date_format($dia, 'Y-m-d') ;
         
         if(isset($data['reintento_emp_id'])){
             $idEmpsVarios='';
             foreach ($data['reintento_emp_id'] as $idEmp) {
                 $idEmpsVarios.=(int)$idEmp.',';
             }
             $idEmpsVarios = substr($idEmpsVarios, 0, -1);
             
             $datosEmps=$d->getGeneral("select e.CedEmp,e.id, e.nombre, e.apellido, e.foto ,e.email  
                                         from a_empleados e
                                         where e.id in($idEmpsVarios)
                                         "
                                         );
         }else{
         $datosEmps=$d->getGeneral("select a.id , b.email , b.nombre , apellido   
                                      from n_nomina_e a 
                                     inner join a_empleados b on b.id = a.idEmp 
                                where idNom = ".$id." 
                                   and not ( a.idVac > 0 and a.diasVac = 0) 
                                     and fechaEmail = '0000-00-00 00:00:00'  " ); 
                                         //# where id = '.$id
         }                //#inner join n_nomina_e n on e.id=n.idEmp 
         $htmlBody='Comprobante de pago';
         $textBody='Comprobante de pago';
         $subject='Comprobante de pago - Nomina';
         $from='CAJACOPI';
         
         $n=new \Nomina\Model\Entity\NominaE2($this->dbAdapter);
         
         $fecha = date_create();
         $fecha = date_format($fecha, 'Y-m-d H:i:s') ;
         $correoEnviadoError=array();
         $correoEnviadoBn=array();
         
         foreach ($datosEmps as $datosEmp) 
         {
             $f->creadAdjunto(2,$datosEmp['id'] );
             
             if($f->sendMail($htmlBody, $textBody, $subject, $from, $datosEmp['email']) ==true  )
             {
                  $idEmp_act=$datosEmp['id'] ;
                  array_push($correoEnviadoBn, $idEmp_act);
                  
                  $d->modGeneral("update n_nomina_e set fechaEmail = now() where id = ".$datosEmp['id']);
             }else{
                 array_push($correoEnviadoError, array('id'=>$datosEmp['id'],'nom'=>$datosEmp['nombre'],'ape'=>$datosEmp['apellido'] ) );
             }
             
         }
        //if(!empty($correoEnviadoBn)){
                //$correoEnviadoBn = substr($correoEnviadoBn, 0, -1);
          //echo  $correoEnviadoBn;
                //print_r($correoEnviadoBn);
         //     $n->actFechaEmail($fecha,$correoEnviadoBn);
          //d->getGeneral(" UPDATE `n_nomina_e` SET fechaEmail = NOW( ) WHERE idEmp IN ( $correoEnviadoBn ) " );
       // }
        //print_r($correo_enviado_error);

         
               
          //$connection->commit();
          //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          
        }// Fin try casth   
        catch (\Exception $e) {
            echo ('error de conexión') ;
            echo $e;
        }
            //if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
               //  $connection->rollback();
                   
         //}  
          //Other error handling 
       //}// FIN TRANSACCION                          
     
      $view = new ViewModel( array( 'correos'=>$correoEnviadoError) );        
      $this->layout('layout/blancoC'); // Layout del login
      return $view;        
    } 

}
