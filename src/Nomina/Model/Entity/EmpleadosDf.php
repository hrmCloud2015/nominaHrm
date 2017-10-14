<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class EmpleadosDf extends TableGateway
{
    private $idtnom;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('a_empleados_d', $adapter, $databaseSchema,$selectResultPrototype);
    }   
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    

    public function actRegistroDef($data=array(),$idUsu)
    {
       $id = $data["id"];

       self::getRegistroId($id);
       $datos=array
       (
           "idEmp"      => $data["id"],
           "fecNov"     => $data["fechaNov"], 
           "comentario" => $data["comenN8"],           
           "idFam"      => $data["idDefFami"],
           "idUsu"      => $idUsu,            
       );
      
    
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;    

       
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
