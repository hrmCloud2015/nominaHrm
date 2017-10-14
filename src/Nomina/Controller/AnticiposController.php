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
use Nomina\Model\Entity\Anticipos;       // (C)
use Nomina\Model\Entity\AnticiposD;       // (C)

class AnticiposController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/anticipos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Anticipos de nomina"; // Titulo listado
    private $tfor = "Anticipos de nomina"; // Titulo formulario
    private $ttab = "Documento, Fecha , Estado, Items ,Editar ,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
        "datos"     =>  $d->getGeneral("select id, fecDoc, estado from n_anticipos order by fecDoc desc"),            
        "ttablas"   =>  $this->ttab,
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado          
        "lin"       =>  $this->lin
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

      $u    = new Anticipos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)        
      $d=new AlbumTable($this->dbAdapter);
      if ($id==0) // Esta recien aprobado 
         $form->get("estado")->setValueOptions(array("0"=>"Revisión"));                                     
      else
         $form->get("estado")->setValueOptions(array("0"=>"Revisión","1"=>"Aprobado"));                                            

      $datConf = $d->getConfiguraG(""); // Averiguar en configuraciones generales , si maneja escala salarial o no  
      $escala = $datConf['escala'];
      $form->get("id2")->setAttribute("value",$escala); 

      // Grupo de nomina
      $arreglo='';
      $datos = $d->getGrupo(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }       
      if ( $arreglo != '' )       
         $form->get("idGrupo")->setValueOptions($arreglo);                         

      // Guardar datos 
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {                        
            $data = $this->request->getPost();      

            if ( $escala == 0 )
                $datos = $d->getEmpM(""); // NO maneja escala 
            else
                $datos = $d->getDocEscala($data->id); // Maneja de escala
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                

                if ($data->id==0)
                   $id = $u->actRegistro($data, $escala ); // Trae el ultimo id de insercion en nuevo registro              
                else 
                {
                   $d->modGeneral("update n_anticipos 
                                    set estado=".$data->estado." where id=".$data->id);
                   $id = $data->id;
                }            

                $connection->commit();
                $this->flashMessenger()->addMessage('');
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$id);// El 1 es para mostrar mensaje de guardado
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
      // Consulta 
      if ($id > 0) 
      {
         $datosR = $u->getRegistroId($id);
         // Inicio de periodo                                                      

          $form->get("idGrupo")->setAttribute("value",$datosR['idGrup']); 
          $form->get("estado")->setAttribute("value",$datosR['estado']);                     
      } 

      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),           
           "escala"  => $datConf['escala'],
           "lin"     => $this->lin, 
           "id"      => $id, 
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

     } // Fin actualizar datos  
   

   // Editar y nuevos datos *********************************************************************************************
   public function listiAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $form->get("estado")->setValueOptions(array("0"=>"Revisión","1"=>"Aprobado"));                                     

      $datConf = $d->getConfiguraG(""); // Averiguar en configuraciones generales , si maneja escala salarial o no  
      $escala = $datConf['escala'];
      $form->get("id2")->setAttribute("value",$escala); 
      // Guardar datos 
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {            
            $u    = new Anticipos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();      

            if ( $escala == 0 )
                $datos = $d->getEmpMSalario(""); // NO maneja escala 
            else
                $datos = $d->getDocEscala($data->id); // Maneja de escala
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                

                if ($data->id>0)
                {
                   $d->modGeneral("update n_anticipos set estado=".$data->estado." 
                                                       where id=".$data->id);
                   $id = $data->id;
                }            
                $d->modGeneral("delete from n_anticipos_e where idAsal=".$id);// Tabal sin escala
                //echo $data->sa1.' - ';
                //print_r($datos);
                $i = 1;
                foreach ($datos as $datSal)
                {    
                  $idP  = $datSal['id'];

                  $sal  = '$data->sa'.$idP;
                  eval("\$sal =$sal;"); 
                
                  $por = '$data->por'.$idP;                
                  eval("\$por =$por;"); 
                  echo $por.'<br />';
                   $d->modGeneral("insert into n_anticipos_e (idAsal, idEmp, salarioAct, porInc, salarioNue) 
                                 values(".$id.",".$idP.",".$datSal['sueldo'].",".$por.",".$val.")");            


                }// Fin recorrido de sueldos
                $connection->commit();
                $this->flashMessenger()->addMessage('');
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);// El 1 es para mostrar mensaje de guardado
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
      $datEst = $d->getGeneral1("select a.*, b.nombre as nomGrup,
                                     d.nombre as nomTant, d.idCon     
                                   from n_anticipos a 
                                      inner join n_grupos b on b.id = a.idGrup
                                      inner join n_tip_anticipo d on d.id = a.idTant 
                                      inner join n_conceptos c on c.id = d.idCon 
                                 where a.id=".$id);

      $form->get("id")->setAttribute("value",$datEst['idCon']); 

      $ttablas = "Cedula, Nombres, Apellidos, Cargo, Valor de anticipo"; 
 
      // Consulta 
      if ($id > 0) 
      {
         $u    = new Anticipos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
         $datosR = $u->getRegistroId($id);

          $form->get("estado")->setAttribute("value",$datosR['estado']);                      
      } 

      $valores=array
      (
           "titulo"  => $this->tfor.' ('.$datEst['nomGrup'].') - '.$datEst['nomTant'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),           
           'datos'   => $d->getDocEscala($id),// Datos de manejo con escala
           "datosE"  => $d->getGeneral( 'select a.id , a.CedEmp, a.nombre, a.apellido, c.                            nombre as nomCar, 
                                           case when b.id is null then 0 else b.valor end  as valor  
                                         from a_empleados a 
                                           inner join t_cargos c on c.id = a.idCar 
                                           left join n_anticipos_e b on b.idEmp = a.id and b.idAsal = '.$id.' 
                                           order by a.nombre ' ), // Listado de empleados           
           "estado"  => $datEst['estado'],           
           "datEmpG" => $d->getGeneral("Select * from n_anticipos_e where idAsal=".$id), 
           "lin"     => $this->lin, 
           "id"      => $id, 
           "ttablas" => $ttablas, 
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

     } // Fin actualizar datos     
   
   // Eliminar dato ********************************************************************************************
   public function listedAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Novedades($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            // Buscar id del tipo del tipo de novedad
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select c.id from n_novedades a 
                                      inner join n_tip_matriz_tnv b on b.id=a.idTmatz 
                                      inner join n_tip_matriz c on c.id=b.idTmatz
                                      where a.id=".$id);             
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['id']);
          }          
   }   



   
}
