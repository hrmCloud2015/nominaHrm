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
use Principal\Model\NominaFunc;        // Libreria de funciones nomina
use Nomina\Model\Entity\Novedades;       // (C)


class NovedadesController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/novedades/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Novedades"; // Titulo listado
    private $tfor = "Generación de novedades"; // Titulo formulario
    private $ttab = "Grupo, Tipo de novedad, Novedades"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "datProy"   => $d->getProyectos(),// Lista de proyectos 
        "datos"     =>  $d->getGeneral("select a.id, a.idTnom, upper(a.nombre) as nomTmtz,
                             b.nombre as nomTnom,
                                  c.nombre as nomGrupo, a.tipo   
                                        from n_tip_matriz a 
                           inner join n_tip_nom b on b.id=a.idTnom 
                           inner join n_grupos c on c.id = a.idGrup
                             where a.estado = 0 order by b.nombre"),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 
    
   // Editar y nuevos datos *********************************************************************************************
   // Tipo seleccion
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores inici ales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $dat = $d->getGeneral1("select * from n_tip_matriz where id = ".$id);
      $idGrupo = $dat['idGrup'];
      $nomGrupo = $dat['nombre'];
      // GUARDAR NOVEDADES //
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) 
        {      
          // INICIO DE TRANSACCIONES
          $connection = null;
          try 
          {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();                     

            $u    = new Novedades($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();
            // Buscar datos del cepto                        
            $idCon = '';
            $idProy = 1; // Predeterminado sin proyecto
            if (isset($data->idCcos))
            {
               $idProy = $data->idCcos; // Si existe el proyecto 
            }
            if ($data->id2==0) // Tipo matriz 
            {
                $datos = $d->getGeneral("select a.id, b.nombre as nomMatz, a.idConc, b.alias, b.tipo,b.valor 
                                       from n_tip_matriz_tnv a 
                                       inner join n_conceptos b on b.id=a.idConc 
                                       where a.idTmatz = ".$data->id );
                foreach ($datos as $dato){
                   $idCon  = (int) $dato['idConc'];
                   $idTmat = (int) $dato['id'];
                   $valor  = '$data->val'.$idCon;
                   eval("\$valor =$valor;");        
                   $cuotas = 0;
                   if ($valor>0)
                   {
                      $datCon = $d->getConnom2(" and id=".$idCon);
                      foreach ($datCon as $dat)
                      {                
                        $u->actRegistro($data->id, $idTmat ,$data->idEmp,$idCon, $valor,$data->idCal, $dat['tipo'], $dat['valor'], $data->fechaIni, $idProy, $cuotas );
                      }             
                   }
                }
            }else{ // Tipo lineal de seleccion
               $valor = 0; 
               if (isset($data->valor))
               {
                   $valor = $data->valor;
                   if ($valor > 0)
                   {
                       $datCon = $d->getConnom2(" and id=".$data->tipo);
                       foreach ($datCon as $dat)
                       {                                
                          $idInov = $u->actRegistro($data->id, 0,$data->idEmp ,$data->tipo, $valor, $data->idCal, $dat['tipo'], $dat['valor'], $data->fechaIni, $idProy, $data->numero);
                       }
                      // CUOTAS 
                      if ($data->numero>0)
                      {
                         $d->modGeneral("insert into n_novedades_cuotas
                                     (idInov, idEmp, idCal, idConc, valor, cuotas) 
                          values(".$idInov.",".$data->idEmp.",".$data->idCal.",".$data->tipo.",".$valor.",".$data->numero.")");
                      }                       
                   }
                }else{ // es porque sera ejecutado por formula
                    $cuotas = 0; 
                    $datCon = $d->getConnom2(" and id=".$data->tipo);
                    foreach ($datCon as $dat){                                
                        $u->actRegistro($data->id, 0,$data->idEmp ,$data->tipo, $valor, $data->idCal, $dat['tipo'], $dat['valor'], $data->fechaIni, $idProy, $cuotas);                    
                }
              }     
                    
            }   
            // Buscar de en novedades cuales tienen formulas 
            $datos = $d->getGeneral("select a.id, c.id as idFor,c.formula,a.horas, b.tipo, a.idEmp, a.idProy  
                           from n_novedades a 
                              inner join n_conceptos b on b.id=a.idConc 
                              inner join n_formulas c on c.id=b.idFor 
                          where a.calc=0 and a.estado = 0");
            $f = new NominaFunc($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            //print_r($data);
            foreach ($datos as $dat)
            {
                if ($dat['formula']!='') // LLamar funcion para hallar formulas                    
                 {                     
                    //echo $dat['formula'].'<br />';
                    $fechaEje = '';
                    $idProy   = $dat['idProy'];
                    $datFor = $f->getFormula($dat['formula'], $dat['idFor'], $dat['tipo'], $dat['horas'], $dat['idEmp'],0,0,0,0,$fechaEje, $idProy) ;
                    //print_r($datFor).'<br />';
                    ///$d->modGeneral('Update n_novedades Set calc=1 , devengado='.$datFor['dev'].', deducido='.$datFor['ded'].'  Where id='.$dat['id']);                                                                                   
                  }                      
            }         

            $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              /* Other error handling */
            }// FIN TRANSACCION                                                    
        } // Fin Post 
      }
      // Datos 
      $datTnom = $d->getGeneral1("select a.id, a.idTnom, b.idTcal, c.idGrupo from n_tip_matriz a 
                                 inner join n_tip_nom b on b.id=a.idTnom 
                                 inner join n_tip_calendario_d c 
                                 on c.idTnom=a.idTnom and c.idGrupo=a.idGrup                                   
                                 where a.id=".$id." limit 1" );           
      // Empleados
      $arreglo='';
      $datos = $d->getEmp(' and idGrup='.$idGrupo); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);                         
      //
      // Calendario
      $arreglo='';
      //echo $datTnom['idGrupo'].' '.$datTnom['idTcal'].' '.$datTnom['idTnom'];
      $datos = $d->getCalenIniFin2($datTnom['idGrupo'], $datTnom['idTcal'], $datTnom['idTnom']); 
      foreach ($datos as $dat){
         if ($dat['idNom']==0) // Solo muestra calendario de nominas no generadas
         {
             $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
             $arreglo[$idc]= $nom;
         }
      }              
      if ($arreglo!='')
         $form->get("idCal")->setValueOptions($arreglo);                         
      //      
      $datos = $d->getGeneral1("select tipo from n_tip_matriz where id = ".$id);
      $tipo = $datos['tipo']; 
      $form->get("id2")->setAttribute("value",$tipo); // Tipo de matriz
      $arreglo='';  
      $con=' ';
      if ($tipo==1) 
         $con=' and tipo=1';  
      if ($tipo==2) 
         $con=' and tipo=2';        
      $datos = $d->getConPres($con); 
      if ($con!=' ')
      {
        foreach ($datos as $dat){
           $idc=$dat['id'];$nom=$dat['codigo'].' - '.$dat['nombre'];
           $arreglo[$idc]= $nom;
        }
        $form->get("tipo")->setValueOptions($arreglo);                                   
      }

      $valores=array
      (
           "titulo"  => $this->tfor.' '.$nomGrupo,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'tipo'    => $tipo,          
           'id'      => $id,          
           'datos'   => $d->getGeneral("select a.id, b.nombre as nomMatz, a.idConc, b.alias, b.tipo,b.valor  
                                        from n_tip_matriz_tnv a 
                                        inner join n_conceptos b on b.id=a.idConc where a.idTmatz=".$id." order by b.codigo "),
           'datNov'  => $d->getDnovedades(" and a.idMatz=".$id),
           'datNovA' => $d->getGeneral1("select count(b.id) as num 
                                           from n_tip_matriz a 
                                              inner join n_nomina b on b.idGrupo = a.idGrup 
                                        where b.estado in (0,1) and a.id =".$id),
           "ttablas" =>  "Cedula, Empleado, Concepto, Sueldo, Horas, Devengado, Deducido, Periodo, Ejecutado , Eliminar",                   
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin actualizar datos 
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Novedades($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            // Buscar id del tipo del tipo de novedad
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select c.id from n_novedades a 
                                        inner join n_tip_matriz c on c.id = a.idMatz 
                                        where a.id = ".$id); 
              // INICIO DE TRANSACCIONES
            $connection = null;
            try 
            {
               $connection = $this->dbAdapter->getDriver()->getConnection();
               $connection->beginTransaction();                     

               $d->modGeneral("delete from n_novedades_cuotas where idInov=".$id);
               $u->delRegistro($id);
               
               $connection->commit();
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$datos['id']);
            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              /* Other error handling */
            }// FIN TRANSACCION                                                                   
            
          }          
   }   
   // Mostrar valor cuando la novedad lo pida
   public function listavAction() 
   {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();
         if ($request->isPost()) {             
             $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
             $data = $this->request->getPost();   
             $d = new AlbumTable($this->dbAdapter);         
             $form = new Formulario("form"); 
             $datos = $d->getGeneral1("select count(idFor) as num from n_conceptos where id = ".$data->id);
             $conFor = $datos['num']; 
      
             $valores=array
             (
                "form"      => $form,
                "conFor"    => $conFor,
                'url'       => $this->getRequest()->getBaseUrl(),
             );                
             $view = new ViewModel($valores);        
             $this->layout('layout/blancoC'); // Layout del login
             return $view;                                   
         }
      }        
   }
   //// Editar y nuevos datos *********************************************************************************************
   // Tipo matriz 
   public function listmAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Empleados
      $arreglo='';
      $datos = $d->getEmp(''); 
      foreach ($datos as $dat)
      {
         $idc=$dat['id'];$nom=$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);                         
      //
      $datos = $d->getGeneral1("select tipo from n_tip_matriz where id = ".$id);
      $tipo = $datos['tipo']; 
      $arreglo='';  
      $con=' ';
      if ($tipo==1) 
         $con=' and tipo=1';  
      if ($tipo==2) 
         $con=' and tipo=2';        
      $datos = $d->getConnom2($con); 
      if ($con!=' ')
      {
        foreach ($datos as $dat){
           $idc=$dat['id'];$nom=$dat['nombre'];
           $arreglo[$idc]= $nom;
        }
        $form->get("tipo")->setValueOptions($arreglo);                                   
      }
      $datos=0;
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'tipo'    => $tipo,    
           "ttablas" =>  "Empleado, Cargo, Centro de costos, Eliminar",
           'datos'   => $d->getGeneral("select a.id, concat(a.nombre ,' ' ,a.apellido) as nomEmp, 
                                        b.nombre as nomCar, c.nombre as nomConc from a_empleados a 
                                        left join t_cargos b on a.idCar=b.id
                                        inner join n_cencostos c on a.idCcos=c.id 
                                        order by a.nombre,a.apellido"),
           'datosM'  => $d->getGeneral("select a.id, b.nombre as nomMatz, a.idConc, b.alias, b.tipo,b.valor 
                                        from n_tip_matriz_tnv a 
                                        inner join n_conceptos b on b.id=a.idConc where a.idTmatz=".$id ),          
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin actualizar datos 
   public function listagAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
      
            $u    = new Novedades($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();
            if ($data->opcion==1)
            {
               // Buscar datos del cepto
               $datos = $d->getConnom2(" and id=".$data->idConc);
               foreach ($datos as $dat){
                 $u->actRegistro($data, $dat['tipo'], $dat['valor']);
               }            
               // Buscar de en novedades cuales tienen formulas 
               $datos = $d->getGeneral("select a.id, c.id as idCon,c.formula,a.horas, b.tipo, a.idEmp, a.idProy  
                           from n_novedades a 
                              inner join n_conceptos b on b.id=a.idConc 
                              inner join n_formulas c on c.id=b.idFor where a.calc=0");
               $f = new NominaFunc($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
               foreach ($datos as $dat)
               {
                  if ($dat['formula']!='') // LLamar funcion para hallar formulas                    
                  {                     
                    $fechaEje = '';
                    $idProy = $dat['idProy'];
                    $datFor = $f->getFormula($dat['formula'], $dat['idCon'], $dat['tipo'], $dat['horas'], $dat['idEmp'],0,0,0,0,0,$fechaEje, $idProy) ;
                    $d->modGeneral('Update n_novedades Set calc=0 , devengado='.$datFor['dev'].', deducido='.$datFor['ded'].'  Where id='.$dat['id']);                                                                                   
                  }                      
               }              
            }// Fin validar opcion 
        }
      }      
      
      // Buscar constantes de funciones
      $valores=array
      (
           "form"    => $form,
           "titulo"  => $this->tfor,
           'url'     => $this->getRequest()->getBaseUrl(),
           'datos'   => $d->getDnovedades(),
           "ttablas" =>  "Cedula, Empleado, Centro de costos, Devengado, Deducido, Horas, Periodo , Eliminar",          
           "lin"     => $this->lin
      );
      $view = new ViewModel($valores);        
      $this->layout('layout/blanco'); // Layout del login
      return $view;                          
   }

   // Datos del cargo
   public function listeAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         

            $data = $this->request->getPost();       
            $valores = array(
               "datos"  => $d->getGeneral1("select a.sueldo, b.nombre as nomCar  
                                              from a_empleados a
                                              inner join t_cargos b on b.id = a.idCar
                                                where a.id=".$data->idEmp),
               "form"   => $form, 
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }               
   // Datos del proyecto
   public function listpAction() 
   {
      $form = new Formulario("form");  
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
          
      $valores = array
      (
         "titulo"  => "Novedades en proyecto ",
         'url'     => $this->getRequest()->getBaseUrl(),            
         "form"    => $form, 
         "datos"   => $d->getProyectoEmpleados($id), 
         "ttablas" =>  "Cedula, Empleado, Cargo, Dias / Horas, Domingo, ",          
         "lin"     => $this->lin         
      );                    
      $view = new ViewModel($valores);        
      return $view;
   }               

   // Combo con datos del proyecto
   public function listcpAction() 
   {
      $form = new Formulario("form");  
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
      $data = $this->request->getPost();        
      $valores = array
      (
         "titulo"  => "Novedades en proyecto ",
         'url'     => $this->getRequest()->getBaseUrl(),            
         "form"    => $form, 
         "datos"   => $d->getProyectosEmp($data->idEmp), 
         "ttablas" =>  "Cedula, Empleado, Cargo, Dias laborados, ",          
         "lin"     => $this->lin         
      );                    
      $view = new ViewModel($valores);        
      $this->layout("layout/blancoC");
      return $view;            
   }               

   // DATOS DE LIQUIDACION FINAL  ********************************************************************************************
   public function listnovAction()
   {     
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // --      
      if($this->getRequest()->isPost()) // Si es por busqueda
      {
          $request = $this->getRequest();
          if ($request->isPost()) 
          {
             $data = $this->request->getPost();        
             $dat = $d->getGeneral1("select count(id) as id  
                                       from n_novedades_pr 
                                         where estado=0 and idProy=".$data->idProy." 
                                         and idEmp=".$data->idEmp." "); 
             if ($dat['id']==0)
             {
                $d->modGeneral("insert into n_novedades_pr (idProy, idEmp, dias, domingo)
                               values(".$data->idProy.",".$data->idEmp.",".$data->horas.",".$data->domingo.")");  
             }else{
                $d->modGeneral("update n_novedades_pr set dias=".$data->horas." ,
                                  domingo = ".$data->domingo." 
                               where idProy=".$data->idProy." and idEmp=".$data->idEmp);  
             }
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }
}
