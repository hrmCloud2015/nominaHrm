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
use Principal\Model\IntegrarFunc;      // Integracion de nomina

use Principal\Model\Gnominag; // Procesos generacion de automaticos
use Principal\Model\ExcelFunc; // Funciones de excel 


class IntegrarController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/integrar/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Integración de nominas"; // Titulo listado
    private $tfor = "Integracion de nominas"; // Titulo formulario
    private $ttab = "id, Tipo de nomina, Periodo, Grupo, Integrar, Archivo plano, Pdf empleados, Pdf cuenta, Pdf conceptos, Pdf conceptos resumidos,Provisiones,Excel,Archivo plano provisiones "; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $form = new Formulario("form");

      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "form"      =>  $form,
        'url'       => $this->getRequest()->getBaseUrl(),          
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
        "datos"     =>  $d->getGeneral("select distinct a.idTnom, a.id, a.fechaI, a.fechaF, b.nombre as nomGrupo,
                             case when c.id is null then 0 else c.id end as integrada,
                             ( case when a.idTnomL > 0 then 'LIQUIDACION FINAL' else d.nombre end ) as nomTnom, 
            ( 0 ) as error    
                                from n_nomina a
                                  inner join n_grupos b on b.id = a.idGrupo
                                  left join n_nomina_e_d_integrar c on c.idNom = a.id 
                                  left join n_tip_nom d on d.id = a.idTnom 
                                  where a.estado = 2 group by a.id  order by a.id desc"),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin,
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
    // Listado de registros ********************************************************************************************
    public function listiAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d=new AlbumTable($this->dbAdapter);
        $f=new IntegrarFunc($this->dbAdapter);
        // Borrar integracion actual y generar una nueva        
        // INICIO DE TRANSACCIONES
        $connection = null;
        try 
        {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();                      

            $d->modGeneral("delete from n_nomina_e_d_integrar where idNom=".$id);
            $d->modGeneral("delete from n_nomina_e_d_integrar_pagar where idNom=".$id);

            $datos = $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup, c.nombre as nomtcale, 
                                        d.nombre as nomtnom,a.estado,a.numEmp,
                                        case when e.id is null then 0 else e.id end as integra 
                                        from n_nomina a 
                                        inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
                                        inner join n_tip_nom d on d.id=a.idTnom 
                                        left join n_nomina_e_d_integrar e on e.idNom = a.id  
                                        where a.id = ".$id." group by a.id ");      
            foreach($datos as $dat)
            {
               $idInom =$dat['id']; 
          $pagoCes = 0; 
               $f->getIntegrarNomina($idInom, $id, $pagoCes); // Funcion para integracion de nomina (1)
            }      
            $d->modGeneral("update n_nomina_e_d_integrar set codCta = '' where error like '%Sin%' and idNom=".$id);// Borrar cuentas sin configurar
      


          // Paso nomina a vistas de nomina para enviar a ERP
          $datosP = $f->getIntegraNominaPaso($id, $pagoCes);

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


            // ---
            $connection->commit();
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);                    
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
        $valores=array
        (
           "titulo"    =>  $this->tlis,
           "datos"     =>  $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup, c.nombre as nomtcale, 
                                        d.nombre as nomtnom,a.estado,a.numEmp,
                                        case when e.id is null then 0 else e.id end as integra 
                                        from n_nomina a 
                                        inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
                                        inner join n_tip_nom d on d.id=a.idTnom 
                                        left join n_nomina_e_d_integrar e on e.idNom = a.id  
                                        where a.estado in (0,1)
                                        group by a.id "),            
           "ttablas"   =>  $this->ttab,
           "lin"       =>  $this->lin,
           "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado          
        );                
        return new ViewModel($valores);        
    } // Fin listar registros 



   // NOMINA A EXCEL 
   public function listexcelAction() 
   { 
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        $data = $this->request->getPost();         
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);  

        // CONSULTA MAESTRA 
        $datos = $d->getGeneral("Select * from ( 
select a.idNom as NOMINA, concat( bb.fechaI, ' - ' ,bb.fechaF ) as 'PERIODO',  # DATOS DE LA NOMINA 
ltrim(f.CedEmp) as CEDULA, f.nombre as NOMBRES, f.apellido as APELLIDOS,  case when d.idVac > 0 then 'X' else '' end as VAC,
  d.sueldo as SUELDO, # DATOS DEL EMPLEADO   
a.codCon as 'COD', g.nombre as 'CONCEPTO', 
sum( case when a.natCta = 'Debito' then a.valor else 0 end ) as DEBITO,
sum( case when a.natCta = 'Credito' then a.valor else 0 end ) as CREDITO,# INFORMACION DE LA CUENTA 
a.codCta as 'CODIGO DE CUENTA',e.nombre as CUENTA , 
# INFORMACION DEL TERCERO 
a.nit as 'NIT TERCERO',
# INFORMACION DEL TERCERO 
case when g.id = 21 then # VALIDAR FONDO DE SOLIDARIDAD 
     h.nombre 
else # VALIDACIONES RESTANTES 
  case when ( a.nitTer = f.CedEmp ) then # Si el empleado es el mismo tercero
       concat( f.nombre, ' -' , f.apellido   ) 
  else 
     case when ( a.idCon = 15 ) then # Si es salud 
          ( select i.nombre from t_fondos i where i.nit = a.nitFonS limit 1 ) 
     else 
         case when ( a.idCon = 11 ) then # Si es salud 
            ( select j.nombre from t_fondos j where j.nit = a.nitFonP limit 1 ) 
         else
         h.nombre # Tercero asingado    
         end    
     end 
  end 
end as 'NOMBRE TERCERO', 
# CENTRO DE COSTO   
 c.nombre as 'CENTRO DE COSTO', a.error as ERROR   
from n_nomina_e_d_integrar a 
inner join n_nomina bb on bb.id = a.idNom 
left join n_cencostos c on c.id = a.idCcos 
left join n_nomina_e d on d.id = a.idInom  
left join n_plan_cuentas e on e.codigo = a.codCta 
left join a_empleados f on f.id = d.idEmp 
left join n_conceptos g on g.id = a.idCon  
left join n_terceros h on h.codigo = a.nitTer 
where a.idNom = ".$data->id."  
group by a.codCta , CAST(  f.CedEmp AS UNSIGNED) , g.tipo, CAST(  g.codigo AS UNSIGNED)  

union all 

# CUENTAS SALARIOS POR PAGAR COMPAÑIA 
select a.idNom as NOMINA, concat( bb.fechaI, ' - ' ,bb.fechaF ) as 'PERIODO',  # DATOS DE LA NOMINA 
ltrim(f.CedEmp) as CEDULA, f.nombre as NOMBRES, f.apellido as APELLIDOS,  '' as VAC,
  d.sueldo as SUELDO, # DATOS DEL EMPLEADO   
'' as 'COD', a.nomCon as 'CONCEPTO', 
0 as DEBITO,
a.valor as CREDITO,# INFORMACION DE LA CUENTA 
a.codCta as 'CODIGO DE CUENTA',e.nombre as CUENTA , 
# INFORMACION DEL TERCERO 
a.nit as 'NIT TERCERO',
# INFORMACION DEL TERCERO 
i.nombre as 'NOMBRE TERCERO', 
# CENTRO DE COSTO   
'' as 'CENTRO DE COSTO', '' as ERROR   
from n_nomina_e_d_integrar_pagar a 
inner join n_nomina bb on bb.id = a.idNom 
left join n_nomina_e d on d.id = a.idInom  
left join n_plan_cuentas e on e.codigo = a.codCta 
left join a_empleados f on f.id = d.idEmp 
left join c_general i on i.id = 1
where a.idNom = ".$data->id." ) as a 
order by CAST(  CEDULA AS UNSIGNED) , CUENTA , DEBITO, CREDITO");
        $c = new ExcelFunc();
        //print_r($datos);
        $c->listexcel($datos, "Integración nomina");

        $valores = array("datos" => $datos );      
        $view = new ViewModel($valores);              
        return $view;                         
      }
    }// FIN NOMINA A EXCEL

    // ARCHIVO PLANO INTEGRACION NOMINA CRM 
    public function listarchivoAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta del archivo de integracion 
          $datos = $d->getConsIntegrarNomina($id); 
          $fechaArchivo = '' ;
          foreach ($datos as $dat) 
          {
             $fechaArchivo = $dat['fechaI'] ;
          }
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          //$ruta = $rutaP.'/IntegraContable'.$fechaArchivo.'.txt';
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 

          foreach ($datos as $dat) 
          {
            $formaPago = $dat['formaPago'];
            if ( $dat['tercero'] != '0') 
                $formaPago = $dat['tercero'];

            $registro = ltrim($dat['fechaI']).','.ltrim($dat['codCta']).','.($dat['nit']).','.ltrim($dat['debito']).','.$dat['credito'].','.$dat['idCcos'].','.$dat['detalle'].','.$dat['origen'].','.$formaPago.','.$dat['nitTer'].','.$dat['idNom'];
//echo $registro.'<br />';            
            fwrite( $archivo , $registro.PHP_EOL );
          //              echo $registro.'<br />';
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
//       $file = $ruta;
 //      header("Content-disposition: attachment; filename=$file");
 //      header("Content-type: application/octet-stream");
  //     readfile($file);              
      $valores=array
      (
        'url'       =>  $this->getRequest()->getBaseUrl(),          
        "lin"       =>  $this->lin,
      );                
      return new ViewModel($valores);
    } // Fin integracion tabla vista 

    // ARCHIVO PLANO INTEGRACION CRM PROVISIONES  
    public function listarchivoprovAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta del archivo de integracion 
          $datos = $d->getGeneral("select * from ( 
Select d.fechaI, a.nombre as nomCon, a.codCtaD as codCta, round(a.valor,0) as debito, 0 as credito, 
i.nombre as nomCta, a.nitTerD as nit, c.codigo as idCcos, substring( ltrim(c.nombre), 1, 30) as nomCcos
, b.CedEmp, b.nombre as nomEmp, b.apellido, '  ' as error    
                        from n_provisiones_integrar_p a
                        left join n_plan_cuentas i on i.codigo = a.codCtaD # Cuenta debito                         
                        left join a_empleados b on b.id = a.idEmp 
                        left join n_cencostos c on c.id = b.idCcos
                        left join n_nomina d on d.id = a.idNom 
   where a.idNom = ".$id."  
union all
Select d.fechaI, a.nombre as nomCon, a.codCtaC as codCta, 0 as debito, round(a.valor,0) as credito, 
i.nombre as nomCta, a.nitTerD as nit, ## Se coloco porque debe ser el nit del empleado siempre 
 c.codigo as idCcos, substring( ltrim(c.nombre), 1, 30) as nomCcos 
, b.CedEmp, b.nombre as nomEmp, b.apellido, '  ' as error    
                        from n_provisiones_integrar_p a
                        left join n_plan_cuentas i on i.codigo = a.codCtaD # Cuenta debito                         
                        left join a_empleados b on b.id = a.idEmp 
                        left join n_cencostos c on c.id = b.idCcos 
                        left join n_nomina d on d.id = a.idNom 
where a.idNom = ".$id."  
) as a 
order by CedEmp, nomCon, credito, debito"); 
          $fechaArchivo = '' ;
          foreach ($datos as $dat) 
          {
             $fechaArchivo = $dat['fechaI'] ;
          }
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          //$ruta = $rutaP.'/IntegraContable'.$fechaArchivo.'.txt';
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 

          foreach ($datos as $dat) 
          {
            $registro = ltrim($dat['fechaI']).','.ltrim($dat['codCta']).','.ltrim($dat['nit']).','.ltrim($dat['debito']).','.$dat['credito'].','.$dat['idCcos'];
//echo $registro.'<br />';            
            fwrite( $archivo , $registro.PHP_EOL );
          //              echo $registro.'<br />';
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
       $file = $ruta;
       header("Content-disposition: attachment; filename=$file");
       header("Content-type: application/octet-stream");
       readfile($file);       
      //$valores=array
     // (
      //  'url'       =>  $this->getRequest()->getBaseUrl(),          
      //  "lin"       =>  $this->lin,
      //);                
      //return new ViewModel($valores);

    } // Fin archivo plano Popular        

    // ARCHIVO PLANO INTEGRACION CRM PLANILLA UNICA
    public function listplanillaAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $f=new IntegrarFunc($this->dbAdapter);                  
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          // Salud
          $datos = $f->getIntegrarPlanilla(5); 
          foreach ($datos as $dat) 
          {
            $debito = $dat['valor'] ;
            $registro = ltrim($dat['ano']).'-'.$dat['mes'].'-01,'.ltrim($dat['cuentaDeb']).','.ltrim($dat['nitDeb']).','.$debito.',0,'.$dat['codCcosD'].',PU-'.$dat['ano'].'-'.$dat['mes'].'- ('.$dat['fondo'].') -'.$dat['nombre'].','.$dat['id'];
            fwrite( $archivo , $registro.PHP_EOL );                        
            $credito = $dat['valor'] ;
            $registro = ltrim($dat['ano']).'-'.$dat['mes'].'-01,'.ltrim($dat['cuentaCred']).','.ltrim($dat['nitCred']).',0,'.$credito.','.$dat['codCcosC'].',PU-'.$dat['ano'].'-'.$dat['mes'].'-('.$dat['fondo'].')-'.$dat['nombre'].','.$dat['id'];            
            fwrite( $archivo , $registro.PHP_EOL );            
          //              echo $registro.'<br />';
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
//       $file = $ruta;
 //      header("Content-disposition: attachment; filename=$file");
 //      header("Content-type: application/octet-stream");
  //     readfile($file);              
      $valores=array
      (
        'url'       =>  $this->getRequest()->getBaseUrl(),          
        "lin"       =>  $this->lin,
      );                
      return new ViewModel($valores);
    } // Fin archivo plano Popular        

    // ---------------------------------------------------------------------------------
    // ------------------- Envio de datos a tabla vista para integracion contable ------
    // ---------------------------------------------------------------------------------

    // INTEGRACION NOMINA 
    public function listintegrarAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta del archivo de integracion 
          $datos = $d->getGeneral("select * from ( 
           select 0 as cedula ,a.idInom, concat( year(now()) , '-', month(now())  , '-', day(now())   ) as fecha,
            b.fechaI, b.fechaF, a.codCta, 
               case when a.natCta = 'Debito' then round(a.valor,0) else 0 end as debito,   
               case when a.natCta = 'Credito' then round(a.valor,0) else 0 end as credito,
               case when a.dv = ' ' then a.nit  
                    else concat( a.nit,'-' ,a.dv ) end as nit, a.codCcos as idCcos,
          concat( g.nombre , '- (', b.fechaI, '-' ,b.fechaF , ')' )  as detalle,  
                 c.nombre as origen, '0' as formaPago,
                        case when a.embargo>1 then 'EMBARGO' else  
                 case when f.tercero=1 then 'TERCERO' else '0' end end as tercero, 
              case when a.embargo>1 then a.nitEmb else  
                      case when f.tercero=1 then concat( a.nitTer, '-', h.dv )
                else  '0'  end end as nitTer, 
                case when a.embargo>1 then 'EMBARGO' else '' end as embargo, b.id as idNom                            
               from n_nomina_e_d_integrar a 
                  inner join n_nomina b on b.id = a.idNom 
                  inner join n_tip_nom c on c.id = b.idTnom 
                  inner join n_cencostos d on d.id = a.idCcos 
                  inner join n_plan_cuentas e on e.codigo = a.codCta 
                  inner join n_conceptos f on f.id = a.idCon 
                  inner join n_grupos g on g.id = b.idGrupo 
                  left join n_terceros h on h.codigo = a.nitTer 
              where b.id = ".$id."  
   union all  
         select d.CedEmp as cedula, a.idInom,concat( year(now()) , '-', month(now())  , '-', day(now())   ) as fecha, b.fechaI, b.fechaF, a.codCta, 
               0 as debito, round(a.valor,0) as credito,
               a.nit, 0 as idCcos, concat( g.nombre , '- (', b.fechaI, '-' ,b.fechaF , ')' )  as detalle,  
                 c.nombre as origen, case when d.formaPago = 1 
                                    then 'TRANNSFERENCIA' else 
                                       case when d.formaPago = 2 then  
                                          'CHEQUE'
                                       else
                                          'EFECTIVO'
                                       end 
                                    end as formaPago, '0'  as tercero , 
                              case when d.formaPago = 2 then 
                        d.cedEmp 
                     else '0' end as nitTer , 

                                    ''  as embargo, b.id as idNom   
               from n_nomina_e_d_integrar_pagar a 
                  inner join n_nomina b on b.id = a.idNom
                  inner join n_nomina_e e on e.id = a.idInom  
                  inner join n_tip_nom c on c.id = b.idTnom 
                  inner join a_empleados d on d.id = e.idEmp 
                  inner join n_grupos g on g.id = b.idGrupo  
               where b.id = ".$id."  
               ) as a order by idInom, debito, credito   "); 
          $fechaArchivo = '' ;
          foreach ($datos as $dat) 
          {
             $fechaArchivo = $dat['fechaI'] ;
          }
          $d->modGeneral("delete from n_integracion_paso");
          $d->modGeneral("alter table n_integracion_paso auto_increment=1");
          foreach ($datos as $dat) 
          {
            $formaPago = $dat['formaPago'];
            if ( $dat['tercero'] != '0') 
                $formaPago = $dat['tercero'];

            $registro = ltrim($dat['fechaI']).','.ltrim($dat['codCta']).','.($dat['nit']).','.ltrim($dat['debito']).','.$dat['credito'].','.$dat['idCcos'].','.$dat['detalle'].','.$dat['origen'].','.$formaPago.','.$dat['nitTer'].','.$dat['idNom'];
            $d->modGeneral("insert into n_integracion_paso 
                     (fechaI, fechaF, codCta, debito, credito, nit, codCcos, detalle, formaPago, tercero, embargo, origen )
               values('".$dat['fechaI']."','".$dat['fechaF']."','".$dat['codCta']."',".$dat['debito'].",".$dat['credito'].",
                '".$dat['nit']."','".$dat['idCcos']."','".$dat['detalle']."','".$dat['formaPago']."','".$dat['tercero']."','".$dat['embargo']."','NOMINA')");
//echo $registro.'<br />';            

          //              echo $registro.'<br />';
          }  

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
      $valores=array
      (
        'url'       =>  $this->getRequest()->getBaseUrl(),          
        "lin"       =>  $this->lin,
      );                
      return new ViewModel($valores);
    } // Fin integracion tabla vista 

    // INTEGRACION PLANILLA UNICA  
    public function listintegrarplaAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta del archivo de integracion 
          $datos = $d->getGeneral("select * from ( 
           select 0 as cedula ,a.idInom, concat( year(now()) , '-', month(now())  , '-', day(now())   ) as fecha,
            b.fechaI, b.fechaF, a.codCta, 
               case when a.natCta = 'Debito' then round(a.valor,0) else 0 end as debito,   
               case when a.natCta = 'Credito' then round(a.valor,0) else 0 end as credito,
               case when a.dv = ' ' then a.nit  
                    else concat( a.nit,'-' ,a.dv ) end as nit, a.codCcos as idCcos,
          concat( g.nombre , '- (', b.fechaI, '-' ,b.fechaF , ')' )  as detalle,  
                 c.nombre as origen, '0' as formaPago,
                        case when a.embargo>1 then 'EMBARGO' else  
                 case when f.tercero=1 then 'TERCERO' else '0' end end as tercero, 
              case when a.embargo>1 then a.nitEmb else  
                      case when f.tercero=1 then concat( a.nitTer, '-', h.dv )
                else  '0'  end end as nitTer, 
                case when a.embargo>1 then 'EMBARGO' else '' end as embargo, b.id as idNom                            
               from n_nomina_e_d_integrar a 
                  inner join n_nomina b on b.id = a.idNom 
                  inner join n_tip_nom c on c.id = b.idTnom 
                  inner join n_cencostos d on d.id = a.idCcos 
                  inner join n_plan_cuentas e on e.codigo = a.codCta 
                  inner join n_conceptos f on f.id = a.idCon 
                  inner join n_grupos g on g.id = b.idGrupo 
                  left join n_terceros h on h.codigo = a.nitTer 
              where b.id = ".$id."  
   union all  
         select d.CedEmp as cedula, a.idInom,concat( year(now()) , '-', month(now())  , '-', day(now())   ) as fecha, b.fechaI, b.fechaF, a.codCta, 
               0 as debito, round(a.valor,0) as credito,
               a.nit, 0 as idCcos, concat( g.nombre , '- (', b.fechaI, '-' ,b.fechaF , ')' )  as detalle,  
                 c.nombre as origen, case when d.formaPago = 1 
                                    then 'TRANNSFERENCIA' else 
                                       case when d.formaPago = 2 then  
                                          'CHEQUE'
                                       else
                                          'EFECTIVO'
                                       end 
                                    end as formaPago, '0'  as tercero , 
                              case when d.formaPago = 2 then 
                        d.cedEmp 
                     else '0' end as nitTer , 

                                    ''  as embargo, b.id as idNom   
               from n_nomina_e_d_integrar_pagar a 
                  inner join n_nomina b on b.id = a.idNom
                  inner join n_nomina_e e on e.id = a.idInom  
                  inner join n_tip_nom c on c.id = b.idTnom 
                  inner join a_empleados d on d.id = e.idEmp 
                  inner join n_grupos g on g.id = b.idGrupo  
               where b.id = ".$id."  
               ) as a order by idInom, debito, credito   "); 
          $fechaArchivo = '' ;
          foreach ($datos as $dat) 
          {
             $fechaArchivo = $dat['fechaI'] ;
          }
          $d->modGeneral("delete from n_integracion_paso");
          $d->modGeneral("alter table n_integracion_paso auto_increment=1");
          foreach ($datos as $dat) 
          {
            $formaPago = $dat['formaPago'];
            if ( $dat['tercero'] != '0') 
                $formaPago = $dat['tercero'];

            $registro = ltrim($dat['fechaI']).','.ltrim($dat['codCta']).','.($dat['nit']).','.ltrim($dat['debito']).','.$dat['credito'].','.$dat['idCcos'].','.$dat['detalle'].','.$dat['origen'].','.$formaPago.','.$dat['nitTer'].','.$dat['idNom'];
            $d->modGeneral("insert into n_integracion_paso 
                     (fechaI, fechaF, codCta, debito, credito, nit, codCcos, detalle, formaPago, tercero, embargo, origen )
               values('".$dat['fechaI']."','".$dat['fechaF']."','".$dat['codCta']."',".$dat['debito'].",".$dat['credito'].",
                '".$dat['nit']."','".$dat['idCcos']."','".$dat['detalle']."','".$dat['formaPago']."','".$dat['tercero']."','".$dat['embargo']."','NOMINA')");
//echo $registro.'<br />';            

          //              echo $registro.'<br />';
          }  

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
      $valores=array
      (
        'url'       =>  $this->getRequest()->getBaseUrl(),          
        "lin"       =>  $this->lin,
      );                
      return new ViewModel($valores);
    } // Fin integracion tabla vista planilla unica 


    // ARCHIVO PLANO BONIFICACION
    public function listarchivopBonirovAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta del archivo de integracion 
          $datos = $d->getGeneral("select h.CedEmp , j.nombre , h.nombre , h.apellido , sum(i.devengado), b.fechaI, b.fechaF    
    from n_nomina_e a 
                inner join n_nomina b on b.id=a.idNom
                inner join a_empleados h on h.id=a.idEmp 
                inner join n_nomina_e_d i on i.idInom = a.id 
                inner join n_cencostos l on l.id = h.idCcos 
                inner join t_cargos m on m.id = h.idCar 
                inner join n_conceptos j on j.id = i.idConc 
                inner join n_tipemp k on k.id = h.idTemp 
                inner join n_grupos n on n.id = h.idGrup 
                inner join n_tarifas p on p.id = h.idRies
           inner join a_tipcon q on q.id = h.IdTcon # Tipo de contratos  
                where b.fechaI >='2015-10-01' and b.fechaF <= '2015-10-31' and i.devengado > 0 
                group by h.id "); 
          $fechaArchivo = '' ;
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          //$ruta = $rutaP.'/IntegraContable'.$fechaArchivo.'.txt';
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 

          foreach ($datos as $dat) 
          {
            $registro = ltrim($dat['fechaI']).','.ltrim($dat['codCta']).','.ltrim($dat['nit']).','.ltrim($dat['debito']).','.$dat['credito'].','.$dat['idCcos'];
//echo $registro.'<br />';            
            fwrite( $archivo , $registro.PHP_EOL );
          //              echo $registro.'<br />';
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
       $file = $ruta;
       header("Content-disposition: attachment; filename=$file");
       header("Content-type: application/octet-stream");
       readfile($file);       
      //$valores=array
     // (
      //  'url'       =>  $this->getRequest()->getBaseUrl(),          
      //  "lin"       =>  $this->lin,
      //);                
      //return new ViewModel($valores);

    } // Fin archivo plano Popular        
}
