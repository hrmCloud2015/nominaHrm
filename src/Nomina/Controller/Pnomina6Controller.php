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

class PnominaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/pnomina/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Pagos de nominas"; // Titulo listado
    private $tfor = "Pagos de nomina"; // Titulo formulario
    private $ttab = ",id, Nomina, Periodo, Relación, Planos, Reportar pagos "; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      $g = new Gnominag($this->dbAdapter);
      $form = new Formulario("form");
      $valores=array
      (
        "form"      =>  $form,
        "titulo"    =>  $this->tlis,
        "datPla"    =>  $d->getGeneral("select distinct d.id , e.nombre, e.plano, d.numCuenta     
                                           from n_nomina a 
                               inner join n_nomina_e b on b.idNom = a.id 
                              inner join a_empleados c on c.id = b.idEmp  
                                             inner join n_bancos d on d.id = c.idBancoPlano 
                                             inner join c_bancos e on e.id = d.idBan"),
        "datos"     =>  $g->getListNominas("a.estado in (1,2) and ( a.pagada = 0 or a.archivo = 0) "),
        "datos1"    =>  $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup,
                               c.nombre as nomtcale, d.nombre as nomtnom,
                               a.estado, case when a.idTnomL > 0 
                                           then 'LIQUIDACION FINAL' else'' end as tipNom ,
                                           a.idTnomL, a.numEmp  
                                        from n_nomina a 
                                        inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
                                        inner join n_tip_nom d on d.id=a.idTnom 
                                    where a.estado=2 and a.pagada = 0 order by a.fecha desc"),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros  


//-------------------------------------------------------
//----- ZONA DE ARCHIVOS PLANOS POR LOS DIFERENTES BANCOS 
//-------------------------------------------------------
    // EMPLEADOS LISTADO ARCHIVO PLANO -------------------------------- (2) 
    public function getEmpleadosNomina($idNom, $idBanco)
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // Consulta del tipo de nomina 
      $datos = $d->getGeneral("select 'CC' as cc, d.CedEmp as cedula, 
                     LPAD(ltrim( case when h.codigo!='' then h.codigo else i.codigo end),4,'0') as codBanco4,
( case when h.codigo!='' then h.codigo else i.codigo end) as codBanco,                     
               d.numCuenta as cuenta, 
                    case when d.tipCuenta = 1 then 'CA' else 'CC' end as tipoCuen,
                    case when d.tipCuenta = 1 then '2' else '1' end as tipoCuenN,
                    case when d.tipCuenta = 1 then '21' else '20' end as tipoCuen20,                    
( select  

case when bb.idEmp = 253 then ## Nelley caso emenada 
  137569
else  
  sum( cc.devengado - cc.deducido  ) 
end 

from n_nomina aa
inner join n_nomina_e bb on bb.idNom = aa.id
inner join n_nomina_e_d cc on cc.idInom = bb.id
inner join a_empleados dd on dd.id = bb.idEmp 
inner join n_bancos ee on ee.id = dd.idBanco 
inner join n_conceptos ii on ii.id = cc.idConc 
where ii.info=0 and  aa.id = a.id and bb.idEmp = b.idEmp ) as valorEmp, # Valor por empleado 
substr( concat( RPAD( ltrim(replace(d.apellido2,'Ñ','N')), 18 , ' ' ) , ' ' , RPAD( ltrim(d.nombre) , 18 , ' ' )  ) , 1, 36  ) as nombre36,
substr( concat( LPAD( ltrim(replace(d.apellido2,'Ñ','N')), 11 , ' ' ) , ' ' , LPAD( ltrim(d.nombre) , 11 , ' ' )  ) , 1, 22  ) as nombre22,
substr( concat( LPAD( ltrim(replace(d.apellido2,'Ñ','N')), 11 , ' ' ) , ' ' , LPAD( ltrim(d.nombre) , 10 , ' ' )  ) , 1, 21  ) as nombre21,
substring( concat( substr(ltrim(d.nombre),1,10 ), ' ', substr(ltrim(d.apellido),1,10 ) ), 1,'20'  )as nombre20 ,
concat( year(now()) , LPAD( month(now()) ,2,'0')  , LPAD(  day(now()) ,2,'0')  ) as fechaMv ,
concat( LPAD( hour(now()) ,2,'0') , LPAD( minute(now()) ,2,'0')  , LPAD( second(now()) ,2,'0')   ) as horaMv,
'Vacaciones' as tituloNomina,
lower(d.email) as email,
concat( substring( d.numCuenta, 1, 2 ), '00002000' , substring( d.numCuenta, 5, 100 )  ) as numCuenta5,                           
concat( substring( lpad(d.numCuenta,3,'0') , 1, 3 ), '000200' , ltrim(substring( d.numCuenta, 4, 100 ) )  ) as numCuenta4       
from n_nomina a
  inner join n_nomina_e b on b.idNom = a.id
  inner join a_empleados d on d.id = b.idEmp 
  inner join n_bancos e on e.id = d.idBancoPlano 
  inner join c_bancos g on g.id = e.idBan # Banco de archivo plano
  left join n_bancos h on h.id = d.idBanco 
  left join c_bancos i on i.id = h.idBan # Banco de empleado 
where d.formaPago = 1 and d.idBancoPlano = ".$idBanco."  and a.id = ".$idNom." group by b.id order by d.nombre");
    return $datos;

} // fin empleados en nomina--------------------- (2)    

    // ARCHIVO PLANO POPULAR ----------------------------- ( 1 )   
    public function listpopularAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta del tipo de nomina 
          $datos = $d->getPopular($id); 
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          $numEmp = 0;
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ( $dat['valorEmp']>0 )
            {            
               $numEmp++;
            }
          }        
          $numEmp = str_pad($numEmp, 8, "0", STR_PAD_LEFT);

          fwrite( $archivo , "RC".$dat['nitEmp'].'NOMINOMI0000027369999241CC000051'.$dat['valorNom'].$numEmp.$dat['fechaMv'].$dat['horaMv'].'0000999900000000000000000100000000000000000000000000000000000000000000000000000000'.PHP_EOL );
          foreach ($datos as $dat) 
          {
            $cuenta = $dat['cuenta2'];              
            if ( $dat['valorEmp']>0 )
            {
                   
                 $registro = ltrim($dat['campo1']).ltrim($dat['cedula']).'00000000000000000000'.$cuenta.ltrim($dat['tipoCuen']).$dat['codBanco'].$dat['valorEmp'].$dat['campo3'];                

               fwrite( $archivo , $registro.PHP_EOL );
            }
          }  
          fclose($archivo);          
          $connection->commit();          
          $file = $ruta;

        }// Fin try casth   
        catch (\Exception $e) {
            if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                 $connection->rollback();
          //         echo $e;
         }  
         /* Other error handling */
       }// FIN TRANSACCION                          
       
      $valores=array
      (
        "titulo"  => "Archivo plano de nomina",        
        'url'     => $this->getRequest()->getBaseUrl(),
        "lin"     => $this->lin,
        "ruta"    => $ruta
      );                
      return new ViewModel($valores);

    } // FIN ARCHIVO BANCO POPULAR

    // ARCHIVO PLANO BANCOLOMBIA -------------------------------- (2) 
    public function listbancolombiaAction()
    {
      $id = $this->params()->fromRoute('id', 0);
      $pos = strpos($id,'.');
      $idNom = substr($id, 0, $pos);
      $idBan = substr($id, $pos+1,100);
      $id = $idNom;

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

         // Consulta del tipo de nomina 
         $datos = $d->getGeneral("select  distinct 
( select  LPAD(ltrim(aa.nit),16,'0') as cedula from c_general aa ) as nitEmp , 
LPAD(  CAST(  d.CedEmp AS UNSIGNED),15 ,'0') as cedula, 
'TR' as campo1, 
ltrim( LPAD(ltrim(d.numCuenta),17,'0')) as cuenta, 
ltrim( d.numCuenta ) as cuenta2,
 case when d.tipCuenta = 1 then 'CA' else 'CC' end as tipoCuen,
 '000002' as codBanco,  
 ( select LPAD( round((sum(cc.devengado)-sum(cc.deducido) ),0) ,24,'0') as valor  
from n_nomina aa
inner join n_nomina_e bb on bb.idNom = aa.id
inner join n_nomina_e_d cc on cc.idInom = bb.id
inner join a_empleados dd on dd.id = bb.idEmp 
inner join n_bancos ee on ee.id = dd.idBancoPlano  
where aa.id = a.id and dd.idBancoPlano = ".$idBan." and aa.id = ".$id." and dd.formaPago = 1 )  as valorNom, # total valor de la nomina 

( select LPAD( (sum(cc.devengado)-sum(cc.deducido) ),10,'0') 
from n_nomina aa
inner join n_nomina_e bb on bb.idNom = aa.id
inner join n_nomina_e_d cc on cc.idInom = bb.id
inner join a_empleados dd on dd.id = bb.idEmp 
inner join n_bancos ee on ee.id = dd.idBanco 
where aa.id = a.id and bb.idEmp = b.idEmp ) as valorEmp, # Valor por empleado 
'000000000219999000000000000000000000000000000000000000000000000000000000000000000000000000000000' as campo3,

( select LPAD( count(ee.id) ,6,'0')  
from n_nomina_e ee 
inner join a_empleados pp on pp.id = ee.idEmp 
where ee.idNom = a.id and pp.formaPago = 1  ) as numReg, 

concat( year(now()) , LPAD( month(now()) ,2,'0')  , day(now())  ) as fechaMv ,
concat( LPAD( hour(now()) ,2,'0') , LPAD( minute(now()) ,2,'0')  , LPAD( second(now()) ,2,'0')   ) as horaMv,
substr( concat( RPAD( ltrim(replace(d.apellido2,'Ñ','N')), 9 , ' ' ) , ' ' , RPAD( ltrim(d.nombre) , 9 , ' ' )  ) , 1, 18  ) as nombre, substr(a.id,1,2) as idNom ,
substring( year(now()), 3,2 ) as ano , LPAD( month(now()) ,2,'0') as mes,
substring( year( a.fechaI ), 3,2 ) as anoI, LPAD( month( a.fechaI ) ,2,'0') as mesI, LPAD( day( a.fechaI ) ,2,'0') as diaI,
substring( year( a.fechaI ), 3,2 ) as anoF, LPAD( month( now() ) ,2,'0') as mesF, LPAD( day( now() ) ,2,'0') as diaF 
from n_nomina a
  inner join n_nomina_e b on b.idNom = a.id
  inner join a_empleados d on d.id = b.idEmp 
  inner join n_bancos e on e.id = d.idBanco 
  inner join a_empleados f on f.idBancoPlano = e.id # Amarrar con la tabla de bancos para filtrar solo los de este banco 
where d.formaPago = 1 and d.id not in (4312,4270) and f.idBancoPlano = ".$idBan."  and a.id = ".$id." group by b.id order by d.nombre "); 
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          //print_r($datos);   
          $numeroEmp = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $numeroEmp++;
          }          
//          fwrite( $archivo , "RC".$dat['nitEmp'].'NOMINOMI0000027369999241CC000051'.$dat['valorNom'].$dat['numReg'].$dat['fechaMv'].$dat['horaMv'].'0000999900000000000000000100000000000000000000000000000000000000000000000000000000'.PHP_EOL );

          //$numeroEmp='0000'.$numeroEmp;
          $numeroEmp = str_pad( 210 , 6, "0", STR_PAD_LEFT);

          fwrite( $archivo , '10890204162SEGURIDAD Y VIGI  '.$dat['idNom'].'NOM '.$dat['mes'].'/'.$dat['ano'].''.$dat['anoI'].$dat['mesI'].$dat['diaI'].'A16'.$dat['mesF'].$dat['diaF'].''.$numeroEmp.$dat['valorNom'].'29106172761D'.PHP_EOL );          
          foreach ($datos as $dat) 
          {
            $cuenta = $dat['cuenta2'];              
//            $registro = ltrim($dat['campo1']).ltrim($dat['cedula']).'000000000000000000000'.$cuenta.ltrim($dat['tipoCuen']).$dat['codBanco'].$dat['valorEmp'].$dat['campo3'];
             //echo $cuenta.' - '.$dat['cuenta2'].'<br />';            
            if ($dat['valorEmp']>0)
            {
                $registro = '6'.ltrim($dat['cedula']).$dat['nombre'].'005600078'.$dat['cuenta'].'S37'.$dat['valorEmp'].'PAGO DE N2016/'.$dat['mesF'].'/'.$dat['diaF'].'  0';            
                fwrite( $archivo , $registro.PHP_EOL );
            }            
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
       
      $valores=array
      (
        'url'     => $this->getRequest()->getBaseUrl(),
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
    } // FIN RCHIVO PLANO BANCOLOMBIA--------------------- (2)    

    // ARCHIVO PLANO BANCO DE BOGOTA -------------------------------- (2) 
    public function listbogotaAction()
    {
      $id = $this->params()->fromRoute('id', 0);
      $pos = strpos($id,'.');
      $idNom = substr($id, 0, $pos);
      $idBan = substr($id, $pos+1,100);
      $id = $idNom;

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

         // Consulta del tipo de nomina 
         $datos = $d->getGeneral("select  distinct 
( select  LPAD(ltrim(aa.nit),16,'0') as cedula from c_general aa ) as nitEmp , 
LPAD(  CAST(  d.CedEmp AS UNSIGNED),11 ,'0') as cedula, 
'TR' as campo1, 
ltrim( RPAD(ltrim(d.numCuenta),17,' ')) as cuenta, 
ltrim( d.numCuenta ) as cuenta2,
 case when d.tipCuenta = 1 then 'CA' else 'CC' end as tipoCuen,
 LPAD(ltrim( case when h.codigo!='' then h.codigo else i.codigo end),6,'0') as codBanco,  
 ( select LPAD( round((sum(cc.devengado)-sum(cc.deducido) ),0) ,11,'0') as valor  
from n_nomina aa
inner join n_nomina_e bb on bb.idNom = aa.id
inner join n_nomina_e_d cc on cc.idInom = bb.id
inner join a_empleados dd on dd.id = bb.idEmp 
inner join n_bancos ee on ee.id = dd.idBancoPlano  
where aa.id = a.id and dd.idBancoPlano = ".$idBan." and aa.id = ".$id." and dd.formaPago = 1 )  as valorNom, # total valor de la nomina 

( select LPAD( (sum(cc.devengado)-sum(cc.deducido) ),16,'0') 
from n_nomina aa
inner join n_nomina_e bb on bb.idNom = aa.id
inner join n_nomina_e_d cc on cc.idInom = bb.id
inner join a_empleados dd on dd.id = bb.idEmp 
inner join n_bancos ee on ee.id = dd.idBanco 
where aa.id = a.id and bb.idEmp = b.idEmp ) as valorEmp, # Valor por empleado 
'000000000219999000000000000000000000000000000000000000000000000000000000000000000000000000000000' as campo3,

( select LPAD( count(ee.id) ,6,'0')  
from n_nomina_e ee 
inner join a_empleados pp on pp.id = ee.idEmp 
where ee.idNom = a.id and pp.formaPago = 1  ) as numReg, 

concat( year(now()) , LPAD( month(now()) ,2,'0')  , day(now())  ) as fechaMv ,
concat( LPAD( hour(now()) ,2,'0') , LPAD( minute(now()) ,2,'0')  , LPAD( second(now()) ,2,'0')   ) as horaMv,
substr( concat( RPAD( ltrim(replace(d.apellido2,'Ñ','N')), 20 , ' ' ) , ' ' , RPAD( ltrim(d.nombre) , 20 , ' ' )  ) , 1, 40  ) as nombre, 
substring( concat( substr(ltrim(d.nombre),1,10 ), ' ', substr(ltrim(replace(d.apellido2,'Ñ','N')),1,10 ) ), 1,'20'  )as nombre20 ,
substr(a.id,1,2) as idNom ,
substring( year(now()), 3,2 ) as ano , LPAD( month(now()) ,2,'0') as mes,
substring( year( a.fechaI ), 3,2 ) as anoI, LPAD( month( a.fechaI ) ,2,'0') as mesI, LPAD( day( a.fechaI ) ,2,'0') as diaI,
substring( year( a.fechaI ), 3,2 ) as anoF, LPAD( month( now() ) ,2,'0') as mesF, LPAD( day( now() ) ,2,'0') as diaF,  LPAD( ' ' ,8,' ') as espa,
 year(now()) as anoA, lpad( month(now()),2,'0') as mesA, lpad( day(now()),2,'0')  as diaA   
from n_nomina a
  inner join n_nomina_e b on b.idNom = a.id
  inner join a_empleados d on d.id = b.idEmp 
  inner join n_bancos e on e.id = d.idBancoPlano 
  inner join c_bancos g on g.id = e.idBan # Banco de archivo plano
  inner join n_bancos h on h.id = d.idBanco 
  inner join c_bancos i on i.id = h.idBan # Banco de empleado 
  inner join a_empleados f on f.idBancoPlano = e.id # Amarrar con la tabla de bancos para filtrar solo los de este banco 
where d.formaPago = 1 and f.idBancoPlano = ".$idBan."  and a.id = ".$id." group by b.id order by d.nombre "); 
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          //print_r($datos);   
          $numeroEmp = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $numeroEmp++;
          }          

          $espa = str_pad( '', 129, " ", STR_PAD_RIGHT);
          $espa = $dat['espa'];
         // fwrite( $archivo , '120160405000000000000000000000000100000000600027437SEVICOL LTDA                            00890204162001000520160405600N'.$espa.PHP_EOL );          

fwrite( $archivo , '1'.$dat['anoA'].$dat['mesA'].$dat['diaA'].'000000000000000000000000100000000600027437SEVICOL LTDA                            008902041620010005'.$dat['anoA'].$dat['mesA'].$dat['diaA'].'600N                                                                                                                                 '.PHP_EOL);
$espa = str_pad( '', 8, " ", STR_PAD_RIGHT);
          foreach ($datos as $dat) 
          {
            $cuenta = $dat['cuenta2'];              
            if ($dat['valorEmp']>0)
            {
               // OJO COLOCAR CEROS A LA DERECHA DE LAS CIFRAN 
//            $registro = '6'.ltrim($dat['cedula']).$dat['nombre'].'005600078'.$dat['cuenta'].'S37'.$dat['valorEmp'].'PAGO DE N2016/'.$dat['mesF'].'/'.$dat['diaF'].'  0';
              $nombre20 = str_pad( $dat['nombre20'], 40, " ", STR_PAD_RIGHT);

            $registro = '2C'.ltrim($dat['cedula']).$nombre20.'02'.$dat['cuenta'].$dat['valorEmp'].'00A'.$dat['codBanco'].'0005'.'          Pago de Nomina                                                        000'.$dat['anoA'].$dat['mesA'].$dat['diaA'].'N                                                N'.$espa;            
              fwrite( $archivo , $registro.PHP_EOL );
            }            
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
       
      $valores=array
      (
        'url'     => $this->getRequest()->getBaseUrl(),
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
    } // FIN RCHIVO PLANO BOGOTA--------------------- (2)    


    // ARCHIVO PLANO BANCO BBVA -------------------------------- (2) 
    public function listbbvaAction()
    {
      $id = $this->params()->fromRoute('id', 0);
      $pos = strpos($id,'.');
      $idNom = substr($id, 0, $pos);
      $idBan = substr($id, $pos+1,100);
      $id = $idNom;

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

         // Consulta empleados
         $datos = $this->getEmpleadosNomina($id, $idBan); 
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          $numeroEmp = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $numeroEmp++;
          }          
          $valorNom = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $valorNom = $valorNom + $dat['valorEmp'];
          }          
          // ARMADO ARCHIVO PLANO
          foreach ($datos as $dat) 
          {
            if ($dat['valorEmp']>0)
            {
               $cedula = str_pad( $dat['cedula'], 15, "0", STR_PAD_LEFT);
               $cuenta = str_pad( $dat['numCuenta4'], 16, "0", STR_PAD_LEFT);
               $tipCue = str_pad( $dat['tipoCuenN'], 2, "0", STR_PAD_LEFT);
               $valor = str_pad( $dat['valorEmp'], 13, "0", STR_PAD_LEFT);
               // Campos especiales
               $campo = '078000';
               $campo = '0000000000000000000';
               $campo2 = '00000000000000';
               $campo3 = 'BOGOTA                                                                                                                  ';
               $campo4 = 'PAGOS';

               $registro = '01'.$cedula.'01'.$dat['codBanco4'].$cuenta.$campo.$valor.$campo2.$dat['nombre36'].$campo3.$campo4;            
               fwrite( $archivo , $registro.PHP_EOL );
             }            
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
       
      $valores=array
      (
        'url'     => $this->getRequest()->getBaseUrl(),
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
    } // FIN RCHIVO PLANO BANCO BBVA--------------------- (2)    

    // ARCHIVO PLANO BANCO CAJA SOCIAL -------------------------------- (2) 
    public function listcajasocialAction()
    {
      $id = $this->params()->fromRoute('id', 0);
      $pos = strpos($id,'.');
      $idNom = substr($id, 0, $pos);
      $idBan = substr($id, $pos+1,100);
      $id = $idNom;

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

         // Consulta empleados
         $datos = $this->getEmpleadosNomina($id, $idBan); 
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          $numeroEmp = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $numeroEmp++;
          }          
          $valorNom = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $valorNom = $valorNom + $dat['valorEmp'];
          }          
          // ARMADO ARCHIVO PLANO
          foreach ($datos as $dat) 
          {
            if ($dat['valorEmp']>0)
            {
               $cedula = str_pad( $dat['cedula'], 16, " ", STR_PAD_RIGHT);
               $cuenta = str_pad( $dat['cuenta'], 17, " ", STR_PAD_RIGHT);
               $tipCue = str_pad( $dat['tipoCuenN'], 2, "0", STR_PAD_LEFT);
               $valor = str_pad( $dat['valorEmp'], 10, "0", STR_PAD_LEFT);
               $banco = str_pad( $dat['codBanco'], 9, "0", STR_PAD_LEFT);
               $nombre = str_pad( $dat['nombre20'], 21, " ", STR_PAD_RIGHT);
               // Campos especiales
               $campo = 'V V ';
               $campo2 = str_pad( $dat['tituloNomina'], 78 , " ", STR_PAD_RIGHT);

               $registro = '632'.$valor.'00'.$cuenta.$banco.$cedula.$dat['nombre21'].$campo.$campo2;            
               fwrite( $archivo , $registro.PHP_EOL );
             }            
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
       
      $valores=array
      (
        'url'     => $this->getRequest()->getBaseUrl(),
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
    } // FIN RCHIVO PLANO BANCO CAJA SOCIAL--------------------- (2)    

    // ARCHIVO PLANO BANCO DAVIVIENDA -------------------------------- (2) 
    public function listdaviviendaAction()
    {
      $id = $this->params()->fromRoute('id', 0);
      $pos = strpos($id,'.');
      $idNom = substr($id, 0, $pos);
      $idBan = substr($id, $pos+1,100);
      $id = $idNom;

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta empleados
          $datos = $this->getEmpleadosNomina($id, $idBan); 
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          $numeroEmp = 0;       
          $fechaMv = '';
          $horaMv = '';          
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $numeroEmp++;
            $fechaMv = $dat['fechaMv'];
            $horaMv = $dat['horaMv'];
          }          
          $valorNom = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $valorNom = $valorNom + $dat['valorEmp'];
          }          
          // ARMADO ARCHIVO PLANO
          $numEmp = str_pad($numeroEmp, 8, "0", STR_PAD_LEFT);
          $valorNom = str_pad($valorNom, 16, "0", STR_PAD_LEFT);
          $nitEmpresa = str_pad( $datGen['nit'] , 16, "0", STR_PAD_LEFT);
//          fwrite( $archivo , "RC".$nitEmpresa.'NOMINOMI0000027369999241CC000051'.$valorNom.$numEmp.$fechaMv.$horaMv.'0000999900000000000000000100000000000000000000000000000000000000000000000000000000'.PHP_EOL );
          fwrite( $archivo , 'RC0000008917800933NOMINOMI0000000341003499CC000051'.$valorNom.$numEmp.$fechaMv.$horaMv.'0000999900000000000000000100000000000000000000000000000000000000000000000000000000'.PHP_EOL );
          foreach ($datos as $dat) 
          {
            if ($dat['valorEmp']>0)
            {
               $cedula = str_pad( $dat['cedula'], 16, "0", STR_PAD_LEFT);
               $cuenta = str_pad( $dat['cuenta'], 32, "0", STR_PAD_LEFT);
               $tipCue = str_pad( $dat['tipoCuen'], 2, " ", STR_PAD_LEFT);
               $valor = str_pad( $dat['valorEmp'], 16, "0", STR_PAD_LEFT);
               $banco = str_pad( $dat['codBanco'], 6, "0", STR_PAD_LEFT);
               // Campos especiales
               $registro = 'TR'.$cedula.$cuenta.$tipCue.$banco.$valor.'000000000219999000000000000000000000000000000000000000000000000000000000000000000000000000000000';                
               //$registro = ltrim($dat['campo1']).ltrim($dat['cedula']).'00000000000000000000'.$cuenta.ltrim($dat['tipoCuen']).$dat['codBanco'].$dat['valorEmp'].$dat['campo3'];                

               fwrite( $archivo , $registro.PHP_EOL );
            }
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
       
      $valores=array
      (
        'url'     => $this->getRequest()->getBaseUrl(),
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
    } // FIN RCHIVO PLANO BANCO DAVIVIENDA--------------------- (2)    

    // ARCHIVO PLANO BANCO SUDAMERIC -------------------------------- (2) 
    public function listsuraAction()
    {
      $id = $this->params()->fromRoute('id', 0);
      $pos = strpos($id,'.');
      $idNom = substr($id, 0, $pos);
      $idBan = substr($id, $pos+1,100);
      $id = $idNom;

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
         $connection->beginTransaction();          

          // Consulta empleados
          $datos = $this->getEmpleadosNomina($id, $idBan); 
          // Armar achivo plano del banco
          $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
          $rutaP = $datGen['ruta']; // Ruta padre                    
          $ruta = $rutaP.'/archivo.txt';
          $archivo = fopen($ruta,"w") or die ("Error"); 
          $numeroEmp = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $numeroEmp++;
          }          
          $valorNom = 0;       
          foreach ($datos as $dat)  // Se encesita para armar la cabecera
          {
            if ($dat['valorEmp']>0)
              $valorNom = $valorNom + $dat['valorEmp'];
          }          
          // ARMADO ARCHIVO PLANO
          foreach ($datos as $dat) 
          {
            if ($dat['valorEmp']>0)
            {
               $cedula = str_pad( $dat['cedula'], 12, " ", STR_PAD_RIGHT);
               $cuenta = str_pad( $dat['cuenta'], 16, " ", STR_PAD_RIGHT);
               $tipCue = str_pad( $dat['tipoCuen20'], 2, " ", STR_PAD_LEFT);
               $valor = str_pad( $dat['valorEmp'].'00', 15, " ", STR_PAD_RIGHT);
               $banco = str_pad( $dat['codBanco'], 6, "0", STR_PAD_LEFT);
               $tituloNomina = str_pad( $dat['tituloNomina'], 80, " ", STR_PAD_RIGHT);
               $email = str_pad( $dat['email'], 60, " ", STR_PAD_RIGHT);
               // Campos especiales
               $registro = '000000000000000000000020560012'.$tipCue.$cuenta.$cedula.$dat['nombre22'].$tituloNomina.$valor.$email;
               //$registro = ltrim($dat['campo1']).ltrim($dat['cedula']).'00000000000000000000'.$cuenta.ltrim($dat['tipoCuen']).$dat['codBanco'].$dat['valorEmp'].$dat['campo3'];                

               fwrite( $archivo , $registro.PHP_EOL );
            }
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
       
      $valores=array
      (
        'url'     => $this->getRequest()->getBaseUrl(),
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
    } // FIN RCHIVO PLANO BANCO SUDAMERIC--------------------- (2)    
}
