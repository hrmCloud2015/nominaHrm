<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class EmpleadosG extends TableGateway
{
    private $id;
    private $lentes;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('a_empleados_g', $adapter, $databaseSchema,$selectResultPrototype);
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       $id = $data["id"];
       $datos=array
       (
           "idEmp"     => $data["id"],
           "lentes"    => $data["lentes"],
           "alergia"   => $data["alergia"],
        );
       if ($id==0) // Nuevo registro
          $this->insert($datos);
       else // Mdificar registro
          $this->update($datos, array('id' => $id));
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
       $this->delete(array('id' => $id));               
     }
}
?>
