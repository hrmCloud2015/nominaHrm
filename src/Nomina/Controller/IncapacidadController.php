<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Incapacidad;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Form\FormPres;        // Componentes especiales para los prestamos

use Principal\Model\LogFunc; // Funciones de logeo y usuarios 

class IncapacidadController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/incapacidad/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Incapacidades de empleados"; // Titulo listado
    private $tfor = "Documento de incapacidad"; // Titulo formulario
    private $ttab = ",id, Fecha,Empleado,Cargo,Tipo, Desde, Hasta, Días, Estado,Ejecutor, Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.*,b.nombre, b.CedEmp, b.apellido, c.nombre as nomcar, 
d.nombre as nomccos, e.nombre as nomTinc, count(f.id) as num, 
( DATEDIFF(  a.fechaF , a.fechaI ) +1 ) as diasInca, 
   (  select sum( datediff( aa.fechaf, aa.fechai ) +1 )  
                             from n_incapacidades_pro aa 
                             where aa.idInc = a.id  ) as diasIncaPro,
                                
                  case a.cobrada when 0 then ''
                                 when 4 then 'No aplica ( 2 dias )' 
                                 when 3 then 'No transcrita'
                            when 2 then 'Transcrita'
                            when 1 then 'Pagada' end as cobra ,
                   g.usuario 
                          from n_incapacidades a 
                                inner join a_empleados b on a.idEmp=b.id 
                                left join t_cargos c on c.id=b.idCar
                                inner join n_cencostos d on d.id=b.idCcos
                                inner join n_tipinc e on e.id=a.idInc
                                left join n_nomina_e_i f on f.idInc = a.id 
                                left join users g on g.id = a.idUsu
                                group by a.id 
                                order by a.id desc"),            
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
      // Sedes
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      // Empleados
      $d = New AlbumTable($this->dbAdapter);      
      $datos = $d->getEmp('');
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);  
      // 
      $arreglo='';
      $datos = $d->getIncapacidades('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idInc")->setValueOptions($arreglo);              

      // 
      $arreglo='';
      $datos = $d->getCodEnf('');
      foreach ($datos as $dat)
      {
       $idc=$dat['codigo'];$nom=$dat['codigo'].' '.$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $arreglo[0]='NO APLICA';
      $form->get("tipo2")->setValueOptions($arreglo);                    

      $val=array
          (
            "0"  => 'Revisión',
            "1"  => 'Aprobado'
          );       
      $form->get("estado")->setValueOptions($val);      
      $form->get("estado")->setAttribute("value",1); 
      $form->get("tipo2")->setAttribute("value", 1 ); 
      $dat = $d->getGeneral1('select a.*, b.nombre as nomTinc, c.CedEmp,
                              c.nombre, c.apellido, d.nombre as nomEnf    
                                from n_incapacidades a 
                                    inner join n_tipinc b on b.id = a.idInc 
                                    inner join a_empleados c on c.id = a.idEmp 
                                    inner join n_cod_enf d on d.codigo = a.codEnf 
                              where a.id = '.$id);
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           "datosP"  => $d->getGeneral('select * from n_incapacidades_pro where idInc='.$id),         
           "datosTp" => $d->getGeneral1('select count(id) as numP from n_incapacidades_pro where idInc='.$id),                    
           "datInc"  => $d->getGeneral1("select * from ( 
                                    select fechaF  
                                       from n_incapacidades 
                                      where id = ".$id."  
                                    union all 
                                       select a.fechaf   
                                           from n_incapacidades_pro a 
                                            inner join n_incapacidades b on b.id = a.idInc 
                                       where b.id = ".$id."  
                                     ) as a                                
                                    order by fechaF desc limit 1 "),                 
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'estado'  => $dat['estado'],       
           'cobrada'  => $dat['cobrada'],            
           'datos'   => $dat,       
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
            $form->setValidationGroup('id','idEmp','idInc'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                // Actualizar empleado                 
                $d = New AlbumTable($this->dbAdapter);                  
                $u    = new Incapacidad($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  

                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();

                $idUsu =  $dt['idUsu'];
                
                $data = $this->request->getPost();
                // Buscar si hay dias fijos para este tipo de incapacidad
                
                $dat = $d->getGeneral1("select diasFijos, ( DATE_ADD( '".$data->fechaIni."' 
                                         ,INTERVAL (diasFijos-1) DAY) ) as fechaFin  
                                         from n_tipinc where diasFijos>0 and id=".$data->idInc);                 
                $diasFijos = 0;
                $fechaFin = $data->fechaFin;
                if ($dat!='')
                {
                   $diasFijos = $dat['diasFijos'];                    
                   $fechaFin = $dat['fechaFin'];                    
                }
                // Buscar dias de empresa                 
                //$dat = $d->getGeneral1("Select '".$data->fechaIni."' as dias from n_tipinc where id=".$data->idInc); 
                $dat = $d->getGeneral1("Select 
                   case when (DATEDIFF('".$fechaFin."' , '".$data->fechaIni."' ) ) > dias then 
                       ( DATE_ADD( '".$data->fechaIni."' ,INTERVAL (dias-1) DAY) ) # Si es mayor a dias asumidos por empresa se suma 4 dias exactos
                   else
                       ( DATE_ADD( '".$data->fechaIni."' ,INTERVAL  (DATEDIFF( '".$fechaFin."' , '".$data->fechaIni."' )) DAY ))  # Si es mayor a dias asumidos por empresa se suma 4 dias exactos 
                   end as dias 
                from n_tipinc where id=".$data->idInc);                                             
                //
                $id = $data->id;
                
                if ($data->id==0)
                   $id = $u->actRegistro($data, $dat['dias'], $fechaFin,$idUsu);
                else 
                   $u->actRegistro($data, $dat['dias'], $fechaFin,$idUsu);                                  
                
                if ($data->estado==1)
                   $d->modGeneral("update a_empleados set idInc=".$id." where id=".$data->idEmp); 
                
                $this->flashMessenger()->addMessage(''); 
                
                //$datos2 = $g->getIncapNom($id);// ( n_nomina_e_d )                                 
              return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Incapacidad($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("idInc")->setAttribute("value",$datos['idInc']); 
            $form->get("fechaIni")->setAttribute("value",$datos['fechai']); 
            $form->get("fechaFin")->setAttribute("value",$datos['fechaf']); 
            $form->get("tipo2")->setAttribute("value",$datos['codEnf']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            // Para las prorogas
            $form->get("id2")->setAttribute("value",$id); 
            $form->get("id3")->setAttribute("value",$datos['idEmp']); 
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 
   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d=new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $data = $this->request->getPost();       
            $datos = $d->getGeneral1("select idEmp from n_incapacidades where id=".$id);            
            $idEmp = $datos['idEmp'];            
            $u=new Incapacidad($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                     
            $u->delRegistro($id);
            $d->modGeneral("update a_empleados set idInc=0 where id=".$idEmp);            
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }


   // Eliminar dato de prorogra ********************************************************************************************
   public function listpdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d=new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $data = $this->request->getPost();       
            $datos = $d->getGeneral1("select idInc from n_incapacidades_pro where id = ".$id);            
            $idInc = $datos['idInc'];            
            $d->modGeneral( "delete from n_incapacidades_pro where id=".$id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$idInc);
          }          
   }

   // Nueva prorroga *********************************************************************************************
   public function listapAction() 
   {     
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            // Actualizar empleado                 
            $d = New AlbumTable($this->dbAdapter);                  
            $data = $this->request->getPost();
            //print_r($data);
            $d->modGeneral("insert into n_incapacidades_pro (idInc, idEmp, fechai, fechaf, comen ) values (".$data->id2.",".$data->id3.",'".$data->fecIng."','".$data->fecIng2."','".$data->comenN."')" );                 
            $this->flashMessenger()->addMessage('');                 
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$data->id2);
        }
      }
   } // Fin actualizar datos de prorrogas

   // VALIDACION INGRESO DE INCAPACIDAD DOBLE 
   public function listgAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $data = $this->request->getPost();       
            $datos = $u->getGeneral1("select * 
                            from n_incapacidades 
                               where idEmp = ".$data->idEmp." order by id desc limit 1 ");                               
            $valores = array(
               "verPer" => $datos,
               "form"   => $form, 
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }         
   
   // VALIDACION DEL PERIODO PARA GUARDADO DE DATOS
   public function listg2Action() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $data = $this->request->getPost();       
            $datos = $u->getGeneral1("select idGrup from a_empleados where id=".$data->idEmp);            
            $idGrup = $datos['idGrup'];
            $datos = $u->getGeneral1("select a.idTnom, b.idTcal from n_tipinc a 
                        inner join n_tip_nom b on b.id=a.idTnom  
                        where a.id=".$data->idInc);
            // Buscar datos del periodo
            $datos = $u->getCalenIniFin2($idGrup, $datos['idTcal'], $datos['idTnom']); 
            $arreglo = '';
            foreach ($datos as $dat){
                $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
                $arreglo[$idc]= $nom;
                break; 
            }  
            // Comprar el periodo que se intenta guardar
            $fecSis = $data->fechaIni;
            $sw = 0;
            // Fecha del sistema
            $fechaI = $dat['fechaI'];
            $valido = 0;
            if ($fecSis < $fechaI ) // Si es menor que la fecha del sistema no debe guardar el documento
                $valido = 1;
            
            $valores = array(
               "verPer" => $valido,
               "form"   => $form, 
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }            
   // registro de cobro por incapacidades
   public function listcAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);                       
      // Sedes
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      // Empleados
      $d = New AlbumTable($this->dbAdapter);      
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) 
        {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) 
            {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                // Actualizar empleado                 
                $d = New AlbumTable($this->dbAdapter);                  
                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();

                $data = $this->request->getPost();
                // Buscar si hay dias fijos para este tipo de incapacidad
                
                if ($data->estado>0)
                {  
                   $d->modGeneral("update n_incapacidades
                                       set cobrada = ".$data->estado.", 
                                           fecDocR = ".$data->fechaIni.",
                                           comenR = '".$data->comenN."',
                                           idUsuR = ".$dt['idUsu'].",
                                           fechaR = '".$dt['fecSis']."'
                                      where id=".$data->id); 
                } 
                $this->flashMessenger()->addMessage(''); 
                
                //$datos2 = $g->getIncapNom($id);// ( n_nomina_e_d )                                 
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
         }
      }
   } // Fin actualizar datos    
}