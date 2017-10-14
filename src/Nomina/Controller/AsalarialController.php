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
use Nomina\Model\Entity\Asalarial;       // (C)
use Nomina\Model\Entity\AsalarialG;       // (C)
use Nomina\Model\Entity\AsalarialD;       // (C)

class AsalarialController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/asalarial/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Incremento salarial"; // Titulo listado
    private $tfor = "Incremento salarial"; // Titulo formulario
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
        "datos"     =>  $d->getGeneral("select id, fecDoc, estado from n_asalarial order by fecDoc desc"),            
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

      $u    = new Asalarial($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)        
      $d=new AlbumTable($this->dbAdapter);
      if ($id==0) // Esta recien aprobado 
         $form->get("estado")->setValueOptions(array("0"=>"Revisión"));                                     
      else
         $form->get("estado")->setValueOptions(array("0"=>"Revisión","1"=>"Aprobado"));                                            

      $datConf = $d->getConfiguraG(""); // Averiguar en configuraciones generales , si maneja escala salarial o no  
      $escala = $datConf['escala'];
      $form->get("id2")->setAttribute("value",$escala); 

      $arreglo[0]='Sin Aplicación';
      $form->get("tipo1")->setValueOptions($arreglo);
      $form->get("tipo2")->setValueOptions($arreglo);
      $form->get("tipo3")->setValueOptions($arreglo);
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
                   $d->modGeneral("update n_asalarial set estado=".$data->estado.", 
                                                          idPerI=".$data->tipo1.", 
                                                          idPerF=".$data->tipo2.",
                                                          idPerA=".$data->tipo3." where id=".$data->id);
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
         $arreglo = ''; 
         $arreglo[0]='Sin Aplicación';
         $datos = $d->getGeneral("select * 
                           from n_tip_calendario_d 
                             where idCal=2 and idGrupo=".$datosR['idGrup']." and estado=1 order by fechaI  desc"); 
         foreach ($datos as $dat){
            $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
            $arreglo[$idc]= $nom;
         }     
         if (!empty($datos))
            $form->get("tipo1")->setValueOptions($arreglo);                                                 

         // Fin de periodo
         $arreglo = ''; 
         $arreglo[0]='Sin Aplicación';
         $datos = $d->getGeneral("select * 
                           from n_tip_calendario_d 
                             where idCal=2 and idGrupo=".$datosR['idGrup']." and estado=1 order by fechaI desc"); 
         foreach ($datos as $dat){
            $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
            $arreglo[$idc]= $nom;
         }     
         if (!empty($datos))
            $form->get("tipo2")->setValueOptions($arreglo);                                                       

         // Aplicacion periodo
         $arreglo = ''; 
         $arreglo[0]='Sin Aplicación';
         $datos = $d->getGeneral("select * 
                           from n_tip_calendario_d 
                             where idCal=2 and idGrupo=".$datosR['idGrup']." and estado=0 order by fechaI"); 
         foreach ($datos as $dat){
            $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
            $arreglo[$idc]= $nom;
         }     
         if (!empty($datos))
            $form->get("tipo3")->setValueOptions($arreglo);                                                       

          $form->get("idGrupo")->setAttribute("value",$datosR['idGrup']); 
          $form->get("estado")->setAttribute("value",$datosR['estado']); 
          $form->get("tipo1")->setAttribute("value",$datosR['idPerI']);           
          $form->get("tipo2")->setAttribute("value",$datosR['idPerF']);           
          $form->get("tipo3")->setAttribute("value",$datosR['idPerA']);                     
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
      //$form->get("id2")->setAttribute("value",$escala); 
      // Guardar datos 
      $filtroSueldos = '';
      $sueldo = 0;
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {            
            $u    = new Asalarial($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();      

            // Filtro de sueldo  
            if ($data->tipo4 > 0)
                {
                   $filtroSueldos = ' and round(a.sueldo) = '.$data->tipo4;
                   $sueldo = $data->tipo4;
                }                            
            $datEst = $d->getGeneral1("select * from n_asalarial where id=".$data->id);
            $idGrupo = $datEst['idGrup'];   

            if ( $escala == 0 )
                $datos = $d->getEmpMSalario( ' and a.idGrup='.$idGrupo.$filtroSueldos ); // NO maneja escala 
            else
                $datos = $d->getDocEscala($data->id); // Maneja de escala
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                


                $id = $data->id;
                $form->get("tipo4")->setAttribute("value",$data->tipo4);
                $form->get("id2")->setAttribute("value",$data->tipo4);
                $connection->commit();
                $this->flashMessenger()->addMessage('');
                //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);// El 1 es para mostrar mensaje de guardado
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
      $datEst = $d->getGeneral1("select * from n_asalarial where id=".$id);
      if ( $datConf['escala'] == 1 ) 
           $ttablas = "id, Codigo, Salario actual, % Incremento, Nuevo salario ";
      else   
           $ttablas = "Numero, id,Cedula, Nombres, Apellidos, Cargo, Centro de costos, Sueldo actual, % Incremento,Nuevo sueldo"; 
      $idGrupo = $datEst['idGrup'];   
      $form->get("id3")->setAttribute("value",$idGrupo); 
      // 
      // Consulta 
      if ($id > 0) 
      {
         $u    = new Asalarial($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
         $datosR = $u->getRegistroId($id);
         // Inicio de periodo
         $arreglo = ''; 
         $arreglo[0]='Sin Aplicación';
         $idCal=2; $estado=1; $orden=2;
         $datos = $d->getCalendariosAnoAct( $datosR['idGrup'],$idCal, $estado, $orden); 
         foreach ($datos as $dat)
         {
            $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
            $arreglo[$idc]= $nom;
         }     
         if (!empty($arreglo))
            $form->get("tipo1")->setValueOptions($arreglo);                                                 

         // Fin de periodo
         $arreglo = ''; 
         $arreglo[0]='Sin Aplicación';
         $idCal=2; $estado=1; $orden=1;
         $datos = $d->getCalendariosAnoAct( $datosR['idGrup'],$idCal, $estado, $orden); 
         foreach ($datos as $dat)
         {
            $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
            $arreglo[$idc]= $nom;
         }     
         if (!empty($arreglo))
            $form->get("tipo2")->setValueOptions($arreglo);                                                       

         // Aplciar en el periodo
         $arreglo = ''; 
         $arreglo[0]='Sin Aplicación';
         $idCal=2; $estado=0; $orden=2;
         $datos = $d->getCalendariosAnoAct( $datosR['idGrup'],$idCal, $estado, $orden); 
         foreach ($datos as $dat){
            $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
            $arreglo[$idc]= $nom;
         }     
         if (!empty($datos))
            $form->get("tipo3")->setValueOptions($arreglo);                                                       

      // Grupo de nomina
      $arreglo='';
      $datos = $d->getGrupo(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }       


      if ( $arreglo != '' )       
         $form->get("idGrupo")->setValueOptions($arreglo);                        

          $form->get("idGrupo")->setAttribute("value",$datosR['idGrup']);

          $form->get("estado")->setAttribute("value",$datosR['estado']); 
          $form->get("tipo1")->setAttribute("value",$datosR['idPerI']);           
          $form->get("tipo2")->setAttribute("value",$datosR['idPerF']);           
          $form->get("tipo3")->setAttribute("value",$datosR['idPerA']);                     
      } 
      // Sueldos 
      $arreglo = '';
      $arreglo[0] = 'Ver todos';
      $datos = $d->getEmpMSalarioR( ' and a.idGrup='.$idGrupo );
      foreach ($datos as $dat)
         {
            $idc = $dat['sueldo'];$nom = number_format( $dat['sueldo'],0)  ;
            $arreglo[$idc]= $nom;
         }     
         if (!empty($datos))
            $form->get("tipo4")->setValueOptions($arreglo);      
      $form->get("id2")->setAttribute("value",$sueldo); 
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),           
           'datos'   => $d->getDocEscala($id),// Datos de manejo con escala
           "datosE"  => $d->getEmpMSalario( ' and a.idGrup='.$idGrupo.$filtroSueldos ), // Listado de empleados           
           'datosEi' => $d->getDocEscalaS($id),// Datos sin escala
           "escala"  => $datConf['escala'],
           "estado"  => $datEst['estado'],           
           "datEmpG" => $d->getGeneral("Select * from n_asalarial_emp where idAsal=".$id), 
           "lin"     => $this->lin, 
           "id"      => $id, 
           "sueldo"  => $sueldo, 
           "ttablas" => $ttablas, 
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

     } // Fin actualizar datos     

   public function listgAction() 
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
      $filtroSueldos = '';
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {            
            $u    = new AsalarialG($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();      

            // Filtro de sueldo  
            if ($data->id2 > 0)
                {
                   $filtroSueldos = ' and a.sueldo = '.$data->id2;
                }

            $datEst = $d->getGeneral1("select * from n_asalarial where id=".$data->id);
            $idGrupo = $datEst['idGrup'];   

            if ( $escala == 0 )
                $datos = $d->getEmpMSalario( ' and a.idGrup='.$idGrupo.$filtroSueldos ); // NO maneja escala 
            else
                $datos = $d->getDocEscala($data->id); // Maneja de escala
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                

                if ($data->id>0)
                {
                   $d->modGeneral("update n_asalarial set estado=".$data->estado.",
                                                          idPerI=".$data->tipo1.",
                                                          idPerF=".$data->tipo2.",
                                                          idPerA=".$data->tipo3."
                                                       where id=".$data->id);
                   $id = $data->id;
                }

                $u  = new AsalarialD($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $d->modGeneral("delete from n_asalarial_d where idAsal=".$id);            
                //echo $data->sa1.' - ';
                $i = 1;
                foreach ($datos as $datSal)
                {    
                  $idP  = $datSal['id'];

                  $sal  = '$data->sa'.$idP;
                  eval("\$sal =$sal;"); 
                
                  $por = '$data->por'.$idP;                
                  eval("\$por =$por;"); 

                  $por2 = '$data->por2'.$idP; // Por centaje oculto cuando se guarda por segunda vez 
                  eval("\$por2 =$por2;"); 

                  $salA = '$data->nsa'.$idP;                
                  eval("\$salA =$salA;"); 

                  $salEditado = '$data->salario2'.$idP;                
                  eval("\$salEditado =$salEditado;");                   

                  if ( ($por2>0) and ($por==0) ) // Hay un porcentaje guardad0
                     $por = $por2;

                  if ( $escala == 1 ) // Con escala salarial
                  {
                     $u->actRegistro($data,$id,$idP,$sal,$por,$salA);
                     //$u->actRegistro($data,$id,1,$sal,$por,$salA); // se manda el codio 1 de la escala para n romper la integridad            
                  }
                  else{  // Sin escala 
                      $val = 0;
                      if ($salA > 0)
                          $val = $salA;

                     //echo $id.",".$idP.",".$datSal['sueldo'].",".$por.",".$val."<br /> ";
                         if ($data->numero >0)
                             $por = $data->numero;

                         if ($data->numero1 >0)
                         {
                            $val =  $data->numero1 ;
                         }else{    
                            if ( $por>0 ) 
                            {  
                               $val = $datSal['sueldo'] + 
                                 round($datSal['sueldo'] * ($por/100),0 );
                            } 
                         }    
                         // Validar sueldo editado
                         if ($salEditado != $salA)
                         { 
                             $val = $salA;  
                             $por = 0;
                         }   


                          $datEmp = $d->getGeneral1("select count(id) as num  
                                          from n_asalarial_emp 
                                            where idAsal=".$id." and idEmp = ".$idP);                         
                          $por = (int) $por;
                          $val = (int) $val;           
                          //echo $val.' emp '.$idP.' <br /> ';                         
                          if ($datEmp['num']==0)
                          {  
                              $d->modGeneral("insert into n_asalarial_emp (idAsal, idEmp, salarioAct, porInc, salarioNue) 
                                       values(".$id.",".$idP.",".$datSal['sueldo'].",".$por.",".$val.")");  
                          }else{
                  //          echo ' valor '.$val.'<br />';
                              $d->modGeneral("update n_asalarial_emp 
                                    set salarioNue = ".$val." ,
                                        porInc = ".$por." 
                                  where idAsal=".$id." and idEmp = ".$idP);                              
                          }    
                                                 
                   }
                   if ( ($salA > 0 ) and ( $data->estado == 1 ) )
                   {
                      if ( $escala == 0 ) // Sin escala salarial
                      {
                         // Actualizar sueldo 
                         $d->modGeneral("update a_empleados set sueldo = ".$salA."
                                      where id = ".$idP); 
                      }else{ // Con escala salarial
                         $d->modGeneral("update a_empleados set sueldo = ".$salA."
                                      where idSal = ".$idP);                          
                         $d->modGeneral("update n_salarios set salario = ".$salA."
                                      where id = ".$idP);                                                   
                      }
                   }

                }// Fin recorrido de sueldos
                $id = $data->id;
                $form->get("tipo4")->setAttribute("value",$data->tipo4);
                $connection->commit();
                $this->flashMessenger()->addMessage('');
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);// El 1 es para mostrar mensaje de guardado
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
            



     } // Fin actualizar datos     

   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');

            // Buscar id del tipo del tipo de novedad
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->modGeneral("delete from n_asalarial_emp where idAsal =".$id);             
            $datos = $d->modGeneral("delete from n_asalarial_d where idAsal =".$id);             
            $datos = $d->modGeneral("delete from n_asalarial where id =".$id);             
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }   



   
}
