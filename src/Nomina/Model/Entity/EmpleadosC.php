<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class EmpleadosC extends TableGateway
{
    private $idtnom;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('a_empleados_conv', $adapter, $databaseSchema,$selectResultPrototype);
    }   
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    

       public function actRegistroConv($data=array(),$idUsu)
    {
     

       $datos=array
       (
           "idEmp"      => $data["id"],
           "idTipConv"  => $data["convenios"], 
           "entidad"    => $data["entidad"],           
           "valor"      => $data["valCon"],           
           "comentario" => $data["comConv"],  
           "idUsu" => $idUsu,          
           
           
        );
        $this->insert($datos);
   }

    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('idEmp' => $id));
       $row = $rowset->current();
      
      
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }
}
?>
