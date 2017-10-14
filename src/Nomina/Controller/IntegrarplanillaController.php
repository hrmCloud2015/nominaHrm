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


class IntegrarplanillaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/integrarplanilla/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Integración de planilla unica"; // Titulo listado
    private $tfor = "Integracion de planilla unica"; // Titulo formulario
    private $ttab = "id,Periodo, Calcular, pdf, Archivo plano, Excel "; // Titulo de las columnas de la tabla
    
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
        "datos"     =>  $d->getGeneral("select * 
                                      from n_planilla_unica a 
                                          where a.estado = 2 "),            
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
               $f->getIntegrarNomina($idInom, $id); // Funcion para integracion de nomina (1)
            }      
            $d->modGeneral("update n_nomina_e_d_integrar set codCta = '' where error like '%Sin%' and idNom=".$id);// Borrar cuentas sin configurar
      

            // Integrar proviciones (2)
            $d->modGeneral("delete from n_provisiones_integrar_p where idNom=".$id);
      
            $datos = $d->getGeneral("select b.idEmp, a.id 
                   from n_nomina a
                     inner join n_nomina_e b on b.idNom = a.id
                      where a.id =".$id);  
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
            }            
            $d->modGeneral("delete from n_provisiones_integrar_p where idEmp=0"); // Temmporal porque no deja insertar consltas sin resultados
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

    // ARCHIVO PLANO INTEGRACION CRM 
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
          $datos = $d->getGeneral("select * from ( 
           select 0 as cedula ,a.idInom, concat( year(now()) , '-', month(now())  , '-', day(now())   ) as fecha, b.fechaI, a.codCta, 
               case when a.natCta = 'Debito' then round(a.valor,0) else 0 end as debito,   
               case when a.natCta = 'Credito' then round(a.valor,0) else 0 end as credito,
               case when a.dv = ' ' then a.nit  
                    else concat( a.nit,'-' ,a.dv ) end as nit, a.codCcos as idCcos,
          concat( ( case when b.idTnomL > 0 then 'LIQUIDACION FINAL' else c.nombre end ) , '- (', b.fechaI, '-' ,b.fechaF , ')' )  as detalle,  
                 'NOMINA' as origen, '' as formaPago,
            case when e.ter=1 then 'TERCERO' else '' end  as tercero, 
                case when a.embargo=1 then 'EMBARGO' else '' end as embargo                           
               from n_nomina_e_d_integrar a 
                  inner join n_nomina b on b.id = a.idNom 
                  inner join n_tip_nom c on c.id = b.idTnom 
                  inner join n_cencostos d on d.id = a.idCcos 
                  inner join n_plan_cuentas e on e.codigo = a.codCta 
              where b.id = ".$id."  
   union all  
         select d.CedEmp as cedula, a.idInom,concat( year(now()) , '-', month(now())  , '-', day(now())   ) as fecha, b.fechaI, a.codCta, 
               0 as debito, round(a.valor,0) as credito,
               a.nit, 0 as idCcos, concat( a.nomCon , '- (', b.fechaI, '-' ,b.fechaF , ')' )  as detalle,  
                 'NOMINA' as origen, case when d.formaPago = 1 
                                    then 'TRANNSFERENCIA' else 
                                       case when d.formaPago = 2 then  
                                          'CHEQUE'
                                       else
                                          'EFECTIVO'
                                       end 
                                    end as formaPago, ''  as tercero , ''  as embargo  
               from n_nomina_e_d_integrar_pagar a 
                  inner join n_nomina b on b.id = a.idNom
            inner join n_nomina_e e on e.id = a.idInom  
                  inner join a_empleados d on d.id = e.idEmp 
               where b.id = ".$id."  
               ) as a order by idInom, debito, credito   "); 
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
            $registro = ltrim($dat['fechaI']).','.ltrim($dat['codCta']).','.ltrim($dat['nit']).','.ltrim($dat['debito']).','.$dat['credito'].','.$dat['idCcos'].','.$dat['detalle'].','.$dat['origen'].','.$dat['formaPago'];
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
    } // Fin archivo plano Popular        

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
Select d.fechaI, a.nombre as nomCon, a.codCtaD as codCta, a.valor as debito, 0 as credito, 
i.nombre as nomCta, a.nitTerD as nit, b.idCcos, substring( ltrim(c.nombre), 1, 30) as nomCcos
, b.CedEmp, b.nombre as nomEmp, b.apellido, '  ' as error    
                        from n_provisiones_integrar_p a
                        left join n_plan_cuentas i on i.codigo = a.codCtaD # Cuenta debito                         
                        left join a_empleados b on b.id = a.idEmp 
                        left join n_cencostos c on c.id = b.idCcos
                        left join n_nomina d on d.id = a.idNom 
   where a.idNom = ".$id."  
union all
Select d.fechaI, a.nombre as nomCon, a.codCtaC as codCta, 0 as debito, a.valor as credito, 
i.nombre as nomCta, a.nitTerC as nit, b.idCcos, substring( ltrim(c.nombre), 1, 30) as nomCcos 
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

}
