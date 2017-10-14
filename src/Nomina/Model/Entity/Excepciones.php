<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Excepciones extends TableGateway
{
    private $id;
    private $idcon;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_emp_conc_exc', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id       = $datos["id"];    
        $this->idcon    = $datos["tipo"];   
    }
    
   
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $datos=array
       (
           'idEmp'  =>$this->id,
           'idCon'  =>$this->idcon,
        );
        $this->insert($datos);
        $inserted_id = $this->lastInsertValue;  
        return $inserted_id;

    }
   
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
        // Se borra primero los tipos de nomina que afecta
        $result=$this->adapter->query("delete from n_emp_conc_tn where idEmCon=".$id,Adapter::QUERY_MODE_EXECUTE);          
        $this->delete(array('id' => $id));               
     }
}
?>
